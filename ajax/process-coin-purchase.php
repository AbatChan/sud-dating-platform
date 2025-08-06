<?php

// Set JSON header first thing to ensure consistent responses
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output, just log them

try {
    require_once(dirname(__FILE__, 2) . '/includes/config.php');
    require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
    require_once(dirname(__FILE__, 2) . '/includes/payment-functions.php');
    require_once(dirname(__FILE__, 2) . '/includes/mailer.php');
    require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Critical Error: Failed to load required files.']);
    exit;
}

$stripe_sdk_path = dirname(__FILE__, 2) . '/vendor/stripe/stripe-php/init.php';
if (file_exists($stripe_sdk_path)) {
    require_once($stripe_sdk_path);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Critical Error: Payment library is missing.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Use centralized security verification with custom nonce for payments
    $user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_one_off_payment_nonce', // Keep existing payment nonce
        'nonce_field' => 'nonce', // Custom nonce field name for JSON input
        'input_data' => $input, // Pass JSON input for nonce verification
        'rate_limit' => ['requests' => 10, 'window' => 300, 'action' => 'coin_purchase'] // 10 purchases per 5 minutes
    ]);

    // Get data from unified payment format
    $amount = isset($input['coin_amount']) ? intval($input['coin_amount']) : 0;
    $price = isset($input['amount']) ? floatval($input['amount']) : 0;
    $payment_method = $input['payment_method'] ?? 'stripe';
    $payment_method_id = $input['payment_method_id'] ?? '';
    $transaction_id = $input['transaction_id'] ?? '';
    $order_uid = sanitize_text_field($input['order_uid'] ?? '');

    if (empty($order_uid)) {
        throw new Exception('Invalid order ID - please refresh and try again');
    }

    if ($amount <= 0) {
        throw new Exception('Invalid coin amount specified');
    }

    if ($price <= 0) {
        throw new Exception('Invalid price specified');
    }

    $payment_success = false;
    $final_transaction_id = '';

    if ($payment_method === 'paypal') {
        // PayPal payment - transaction already captured
        if (empty($transaction_id)) {
            throw new Exception('PayPal transaction ID is missing');
        }
        $payment_success = true;
        $final_transaction_id = $transaction_id;
    } else {
        // Stripe payment processing
        $secret_key = sud_get_stripe_secret_key();
        if (empty($secret_key)) {
            throw new Exception('Payment gateway is not configured on the server');
        }
    \Stripe\Stripe::setApiKey($secret_key);

    try {
        $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);

        $payment_params = [
            'amount' => $price * 100, 
            'currency' => 'usd',
            'payment_method' => $payment_method_id,
            'description' => "Purchase of {$amount} SUD Coins",
            'confirm' => true,
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never'
            ],
            'metadata' => [
                'order_uid' => $order_uid,
                'wp_user_id' => $user_id,
                'coin_amount' => $amount,
                'type' => 'coin_purchase'
            ],
        ];

        if (!empty($stripe_customer_id)) {
            $payment_params['customer'] = $stripe_customer_id;
        }

        // Generate deterministic idempotency key to prevent duplicate charges
        // Use order_uid for uniqueness instead of time buckets to avoid package collision
        $idempotency_key = 'coin_' . $user_id . '_' . $amount . '_' . $order_uid;

        $paymentIntent = \Stripe\PaymentIntent::create(
            $payment_params,
            ['idempotency_key' => $idempotency_key]
        );

            if ($paymentIntent->status !== 'succeeded') {
                throw new Exception('Payment could not be completed immediately. Please try another card');
            }

        $payment_success = true;
        $final_transaction_id = $paymentIntent->id;

    } catch (\Exception $e) {
        error_log("Stripe coin purchase error for user $user_id: " . $e->getMessage());

        // Use comprehensive error mapping for better user experience
        $user_friendly_message = sud_get_user_friendly_stripe_error($e);
        
        throw new Exception($user_friendly_message);
    }
    }

    // Process successful payment with idempotency protection
    if ($payment_success) {
        // Check for duplicate order processing (primary defense)
        global $wpdb;
        $table_name = $wpdb->prefix . 'sud_coin_transactions';
        
        $existing_order = $wpdb->get_row($wpdb->prepare(
            "SELECT id, transaction_id FROM $table_name WHERE order_uid = %s",
            $order_uid
        ));
        
        if ($existing_order) {
            // Order already processed, return success without double-processing
            $current_balance = get_user_meta($user_id, 'coin_balance', true);
            wp_send_json_success([
                'message' => 'Coins purchased successfully!',
                'new_coin_balance' => $current_balance,
                'formatted_balance' => number_format($current_balance),
                'amount_added' => $amount,
                'transaction_id' => $existing_order->transaction_id,
                'duplicate_prevented' => true
            ]);
            exit;
        }
        
        // Secondary check: also check transaction_id for backwards compatibility
        $existing_transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE transaction_id = %s AND user_id = %d",
            $final_transaction_id,
            $user_id
        ));
        
        if ($existing_transaction) {
            // Transaction already processed, return success without double-processing
            $current_balance = get_user_meta($user_id, 'coin_balance', true);
            wp_send_json_success([
                'message' => 'Coins purchased successfully!',
                'new_coin_balance' => $current_balance,
                'formatted_balance' => number_format($current_balance),
                'amount_added' => $amount,
                'transaction_id' => $final_transaction_id,
                'duplicate_prevented' => true
            ]);
            exit;
        }
        
        $current_balance = get_user_meta($user_id, 'coin_balance', true);
        if (!is_numeric($current_balance)) {
            $current_balance = 0;
        }

    $new_balance = $current_balance + $amount;
    $update_result = update_user_meta($user_id, 'coin_balance', $new_balance);

    if ($update_result) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sud_coin_transactions';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            throw new Exception('Coin transactions table not found. Please contact support.');
        }

        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'coin_amount' => $amount,
                'price' => $price,
                'payment_method' => $payment_method,
                'transaction_id' => $final_transaction_id,
                'order_uid' => $order_uid,
                'status' => 'completed',
                'created_at' => current_time('mysql')
            ]
        );
        
        // Send email notifications
        $transaction_data = [
            'type' => 'coins',
            'user_id' => $user_id,
            'amount' => number_format($price, 2),
            'item_name' => $amount . ' SUD Coins',
            'payment_method' => ($payment_method === 'paypal') ? 'PayPal' : 'Credit Card',
            'transaction_id' => $final_transaction_id
        ];
        
        send_payment_confirmation_email($user_id, $transaction_data);
        send_admin_payment_notification($transaction_data);

        // Add in-app notification for user
        add_notification($user_id, 'coins_purchased', "You purchased {$amount} SUD Coins! Your balance has been updated.", null);

            wp_send_json_success([
                'message' => 'Coins purchased successfully!',
                'new_coin_balance' => $new_balance,
                'formatted_balance' => number_format($new_balance),
                'amount_added' => $amount,
                'transaction_id' => $final_transaction_id
            ]);
        } else {
            throw new Exception('Failed to update coin balance. Please contact support');
        }
    } else {
        throw new Exception('Payment processing failed');
    }
    
} catch (Exception $e) {
    sud_handle_ajax_error($e);
}