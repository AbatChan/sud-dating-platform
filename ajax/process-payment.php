<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
require_once(dirname(__FILE__, 2) . '/includes/payment-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/mailer.php');
require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');

$stripe_sdk_path = dirname(__FILE__, 2) . '/vendor/stripe/stripe-php/init.php';
if (file_exists($stripe_sdk_path)) {
    require_once($stripe_sdk_path);
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Critical Error: Payment library is missing.']);
    exit;
}

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Use centralized security verification
    $user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_one_off_payment_nonce',
        'nonce_field' => 'nonce',
        'input_data' => $input,
        'rate_limit' => ['requests' => 10, 'window' => 300, 'action' => 'process_payment']
    ]);

    $user_id = get_current_user_id();

    $item_type = $input['item_type'] ?? '';
    $item_key = $input['item_key'] ?? '';
    $client_price = $input['amount'] ?? 0;
    $payment_method = $input['payment_method'] ?? 'stripe';
    $payment_method_id = $input['payment_method_id'] ?? '';
    $transaction_id = $input['transaction_id'] ?? '';
    $order_uid = sanitize_text_field($input['order_uid'] ?? '');

    $item_details = null;
    if ($item_type === 'boost') {
        $packages = sud_get_boost_packages();
        $item_details = $packages[$item_key] ?? null;
    } elseif ($item_type === 'swipe-up') {
        $packages = sud_get_swipe_up_packages();
        $item_details = $packages[$item_key] ?? null;
    }

    if (!$item_details) {
        wp_send_json_error(['message' => 'Invalid item specified for purchase.']);
        exit;
    }

    $server_price = floatval($item_details['price']);
    if (abs($server_price - $client_price) > 0.01) { 
        wp_send_json_error(['message' => 'Price mismatch. Please refresh the page and try again.']);
        exit;
    }

    $payment_success = false;
    $final_transaction_id = '';

    if ($payment_method === 'paypal') {
        if (empty($transaction_id)) {
            wp_send_json_error(['message' => 'PayPal transaction ID is missing.']);
            exit;
        }
        $payment_success = true;
        $final_transaction_id = $transaction_id;
    } else {
        // Stripe payment processing
        $secret_key = sud_get_stripe_secret_key();
        if (empty($secret_key)) {
            wp_send_json_error(['message' => 'Payment gateway is not configured on the server.']);
            exit;
        }
        \Stripe\Stripe::setApiKey($secret_key);
        sud_init_stripe_ssl_fix();

        try {
            $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);

            $payment_params = [
                'amount' => $server_price * 100, 
                'currency' => 'usd',
                'payment_method' => $payment_method_id,
                'description' => $item_details['name'] ?? 'One-time purchase',
                'confirm' => true,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never'
                ],
                'metadata' => [
                    'wp_user_id' => $user_id,
                    'item_type' => $item_type,
                    'item_key' => $item_key,
                ],
            ];

            if (!empty($stripe_customer_id)) {
                $payment_params['customer'] = $stripe_customer_id;
            }

            $paymentIntent = \Stripe\PaymentIntent::create($payment_params);

            if ($paymentIntent->status !== 'succeeded') {
                wp_send_json_error(['message' => 'Payment could not be completed immediately. Please try another card.']);
                exit;
            }

            $payment_success = true;
            $final_transaction_id = $paymentIntent->id;

        } catch (\Exception $e) {
            error_log("Stripe one-off payment error for user $user_id: " . $e->getMessage());
            
            // Use the new comprehensive error mapping function
            $user_friendly_message = sud_get_user_friendly_stripe_error($e);

            wp_send_json_error(['message' => $user_friendly_message]);
            exit;
        }
    }

    // Process successful payment
    if ($payment_success) {
        if ($item_type === 'boost') {
            $boost_duration_hours = 24;
            $boost_end_time = time() + ($boost_duration_hours * 3600);
            update_user_meta($user_id, 'active_boost_type', $item_key);
            update_user_meta($user_id, 'active_boost_name', $item_details['name']);
            update_user_meta($user_id, 'active_boost_end_time', $boost_end_time);
            update_user_meta($user_id, 'active_boost_started', time());
            
        } elseif ($item_type === 'swipe-up') {
            $current_balance = (int) get_user_meta($user_id, 'sud_purchased_swipe_ups_balance', true);
            $amount_to_add = (int) ($item_details['quantity'] ?? 0);
            $new_balance = $current_balance + $amount_to_add;
            update_user_meta($user_id, 'sud_purchased_swipe_ups_balance', $new_balance);
        }

        global $wpdb;
        $transactions_table = $wpdb->prefix . 'sud_transactions';

        // Check if the table exists, just in case
        if($wpdb->get_var("SHOW TABLES LIKE '$transactions_table'") == $transactions_table) {
            $wpdb->insert(
                $transactions_table,
                [
                    'user_id'        => $user_id,
                    'description'    => $item_details['name'], // Store item name, format dynamically in display
                    'amount'         => $server_price,
                    'payment_method' => $payment_method,
                    'transaction_id' => $final_transaction_id,
                    'status'         => 'completed',
                    'created_at'     => current_time('mysql')
                ],
                [ '%d', '%s', '%f', '%s', '%s', '%s', '%s' ]
            );
        } else {
            // Log an error if the table is missing, so you know to run the database setup
            error_log("SUD CRITICAL ERROR in process-payment.php: The '{$transactions_table}' table does not exist. Transaction was not recorded.");
        }
        
        $response_data = [
            'message' => 'Purchase successful!',
            'item_purchased' => $item_details['name'],
            'transaction_id' => $final_transaction_id
        ];

        // Add swipe-up balance if it's a swipe-up purchase
        if ($item_type === 'swipe-up') {
            $new_balance = function_exists('sud_get_user_swipe_up_balance') ? sud_get_user_swipe_up_balance($user_id) : 0;
            $response_data['new_swipe_up_balance'] = $new_balance;
        }

        // Add boost info to response
        if ($item_type === 'boost') {
            $response_data['boost_active'] = true;
            $response_data['boost_type'] = $item_key;
            $response_data['boost_name'] = $item_details['name'];
            $response_data['boost_end_time'] = get_user_meta($user_id, 'active_boost_end_time', true);
        }

        // Send email notifications
        $transaction_data = [
            'type' => $item_type,
            'user_id' => $user_id,
            'amount' => number_format($server_price, 2),
            'item_name' => $item_details['name'],
            'payment_method' => ($payment_method === 'paypal') ? 'PayPal' : 'Credit Card',
            'transaction_id' => $final_transaction_id
        ];
        
        send_payment_confirmation_email($user_id, $transaction_data);
        send_admin_payment_notification($transaction_data);

        // Add in-app notification for user
        if ($item_type === 'boost') {
            add_notification($user_id, 'boost_purchased', "You purchased {$item_details['name']}! Your profile is now boosted for 24 hours.", null);
        } elseif ($item_type === 'swipe-up') {
            $quantity = $item_details['quantity'] ?? 1;
            add_notification($user_id, 'super_swipe_purchased', "You purchased {$quantity} Super Swipe credits! Start making super swipes now.", null);
        }

        wp_send_json_success($response_data);
    } else {
        wp_send_json_error(['message' => 'Payment processing failed.']);
    }

} catch (Exception $e) {
    error_log("SUD Payment Error: " . $e->getMessage());
    
    // Check if it's a Stripe error and format accordingly
    if (strpos(get_class($e), 'Stripe') !== false) {
        $user_message = sud_get_user_friendly_stripe_error($e);
    } else {
        $user_message = 'Payment processing failed. Please try again or contact support.';
    }
    
    wp_send_json_error(['message' => $user_message]);
}