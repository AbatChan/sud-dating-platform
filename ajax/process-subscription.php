<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');

require_once(dirname(__FILE__, 2) . '/includes/payment-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/mailer.php');

$stripe_sdk_path = dirname(__FILE__, 2) . '/vendor/stripe/stripe-php/init.php';
if (file_exists($stripe_sdk_path)) {
    require_once($stripe_sdk_path);
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Critical Error: Payment processing library is missing.']);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'You must be logged in to subscribe.']);
    exit;
}

if (!isset($input['nonce']) || !wp_verify_nonce($input['nonce'], 'sud_subscription_nonce')) {
    wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
    exit;
}

$user_id = get_current_user_id();
$user_info = get_userdata($user_id);

$payment_method_id = $input['payment_method_id'] ?? null;
$plan_id = $input['plan_id'] ?? null;
$billing_cycle = $input['billing_cycle'] ?? 'monthly';

if (empty($payment_method_id) || empty($plan_id)) {
    wp_send_json_error(['message' => 'Invalid payment data provided. Please try again.']);
    exit;
}

$all_plans = sud_get_all_plans_with_pricing();
$plan_details = $all_plans[$plan_id] ?? null;

if (!$plan_details) {
    wp_send_json_error(['message' => 'The selected plan is invalid.']);
    exit;
}

$price_id = sud_get_stripe_price_id($plan_id, $billing_cycle);

$plan_amount = ($billing_cycle === 'annual')
    ? ($plan_details['price_annually'] ?? $plan_details['annual_price'] ?? 0)
    : ($plan_details['price_monthly'] ?? $plan_details['monthly_price'] ?? 0);

if (empty($price_id) || !preg_match('/^price_/', $price_id)) {
    error_log("Missing or invalid Stripe price ID for plan $plan_id, billing cycle: $billing_cycle. Price ID: " . ($price_id ?? 'null') . ". Plan details: " . json_encode($plan_details));
    wp_send_json_error(['message' => 'This plan is not configured for purchase. Please contact support.']);
    exit;
}

$secret_key = sud_get_stripe_secret_key();
if (empty($secret_key)) {
    wp_send_json_error(['message' => 'The payment gateway is not configured correctly on the server.']);
    exit;
}
\Stripe\Stripe::setApiKey($secret_key);
sud_init_stripe_ssl_fix();

try {

    $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
    $customer = null;

    if ($stripe_customer_id) {
        try {
            $customer = \Stripe\Customer::retrieve($stripe_customer_id);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $stripe_customer_id = null; 
        }
    }

    if (!$customer) {
        $customer = \Stripe\Customer::create([
            'payment_method' => $payment_method_id,
            'email' => $user_info->user_email,
            'name' => $user_info->display_name,
            'invoice_settings' => ['default_payment_method' => $payment_method_id],
            'metadata' => ['wp_user_id' => $user_id],
        ]);
        $stripe_customer_id = $customer->id;
        update_user_meta($user_id, 'stripe_customer_id', $stripe_customer_id);
    } else {
        $payment_method = \Stripe\PaymentMethod::retrieve($payment_method_id);
        $payment_method->attach(['customer' => $stripe_customer_id]);
        \Stripe\Customer::update($stripe_customer_id, [
            'invoice_settings' => ['default_payment_method' => $payment_method_id],
        ]);
    }

    $subscription = \Stripe\Subscription::create([
        'customer' => $stripe_customer_id,
        'items' => [['price' => $price_id]],
        'default_payment_method' => $payment_method_id,
        'payment_behavior' => 'error_if_incomplete',
        'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
        'expand' => ['latest_invoice.payment_intent'],
        'metadata' => ['wp_user_id' => $user_id, 'plan_id' => $plan_id],
    ]);

    // Refresh subscription to get all properties
    $subscription = \Stripe\Subscription::retrieve($subscription->id);

    $status = $subscription->status;
    if ($status === 'active' || $status === 'trialing') {
        // Clear any existing trial data when real subscription starts
        delete_user_meta($user_id, 'sud_trial_plan');
        delete_user_meta($user_id, 'sud_trial_start');
        delete_user_meta($user_id, 'sud_trial_end');
        delete_user_meta($user_id, 'sud_trial_payment_details'); // Legacy insecure data
        delete_user_meta($user_id, 'sud_trial_secure_payment'); // New secure data
        
        // Set subscription metadata
        update_user_meta($user_id, 'premium_plan', $plan_id);
        update_user_meta($user_id, 'subscription_id', $subscription->id);
        update_user_meta($user_id, 'payment_method', 'stripe');
        update_user_meta($user_id, 'subscription_start', gmdate('Y-m-d H:i:s', $subscription->created));
        // Fix: Use proper fallback based on billing cycle
        if (isset($subscription->current_period_end)) {
            $period_end = $subscription->current_period_end;
        } else {
            // Fallback: Calculate proper period based on billing cycle
            $fallback_days = ($billing_cycle === 'annual') ? 365 : 30;
            $period_end = time() + ($fallback_days * 24 * 60 * 60);
        }
        $expires_date = gmdate('Y-m-d H:i:s', $period_end);
        update_user_meta($user_id, 'subscription_expires', $expires_date);
        update_user_meta($user_id, 'subscription_billing_type', $billing_cycle);
        update_user_meta($user_id, 'subscription_auto_renew', '1');
        
        
        // Verify premium status after setting
        $is_premium_check = sud_is_user_premium($user_id);

        // Grant initial subscription bonuses
        sud_grant_initial_subscription_bonuses($user_id, $plan_id);

        // Handle auto-verification for Diamond tier
        if (function_exists('sud_handle_plan_verification_update')) {
            sud_handle_plan_verification_update($user_id, $plan_id);
        }

        // Send email notifications
        $transaction_data = [
            'type' => 'subscription',
            'user_id' => $user_id,
            'amount' => number_format($plan_amount, 2),
            'item_name' => $plan_details['name'] . ' Plan (' . ucfirst($billing_cycle) . ')',
            'payment_method' => 'Credit Card',
            'transaction_id' => $subscription->id
        ];
        
        send_payment_confirmation_email($user_id, $transaction_data);
        send_admin_payment_notification($transaction_data);
        
        // Add in-app notification for user
        require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');
        add_notification($user_id, 'subscription', "Welcome to " . $plan_details['name'] . "! Your subscription is now active.", null);
        
        // Store transaction in database for history
        global $wpdb;
        $transaction_table = $wpdb->prefix . 'sud_transactions';
        $wpdb->insert(
            $transaction_table,
            [
                'user_id' => $user_id,
                'description' => $plan_details['name'] . ' Plan (' . ucfirst($billing_cycle) . ')',
                'amount' => $plan_amount,
                'payment_method' => 'stripe',
                'transaction_id' => $subscription->id,
                'status' => 'completed',
                'created_at' => gmdate('Y-m-d H:i:s')
            ],
            ['%d', '%s', '%f', '%s', '%s', '%s', '%s']
        );
        
        // Send subscription welcome email with manage subscription link
        if (function_exists('send_subscription_success_email')) {
            send_subscription_success_email($user_id, $plan_details, ['billing_cycle' => $billing_cycle]);
        }

        // Track plan purchase conversion with TrafficJunky
        if (function_exists('track_plan_purchase_conversion')) {
            track_plan_purchase_conversion($user_id, $plan_id, $subscription->id, [
                'plan_name' => $plan_details['name'],
                'billing_cycle' => $billing_cycle,
                'amount' => $plan_amount,
                'payment_method' => 'stripe'
            ]);
        }

        wp_send_json_success(['message' => 'Subscription activated successfully!', 'subscriptionId' => $subscription->id]);
    } elseif ($status === 'incomplete' && isset($subscription->latest_invoice) && isset($subscription->latest_invoice->payment_intent) && $subscription->latest_invoice->payment_intent->status === 'requires_action') {
        error_log("Subscription requires 3D Secure authentication for user $user_id");
        wp_send_json(['requires_action' => true, 'payment_intent_client_secret' => $subscription->latest_invoice->payment_intent->client_secret]);
    } else {
        error_log("Subscription failed for user $user_id. Status: $status");
        
        $error_details = "Status: $status";
        if (isset($subscription->latest_invoice)) {
            $invoice = $subscription->latest_invoice;
            if (isset($invoice->payment_intent)) {
                $pi = $invoice->payment_intent;
                $error_details .= ", Payment Intent Status: " . ($pi->status ?? 'unknown');
                if (isset($pi->last_payment_error)) {
                    $error_details .= ", Error: " . ($pi->last_payment_error->message ?? 'unknown error');
                }
            }
        }
        
        wp_send_json_error(['message' => 'Could not activate subscription. ' . $error_details]);
    }
} catch (\Exception $e) {
    error_log('Stripe Subscription Error for WP User ID ' . $user_id . ': ' . $e->getMessage());
    
    // Use comprehensive error mapping for better user experience
    $user_friendly_message = sud_get_user_friendly_stripe_error($e);
    wp_send_json_error(['message' => $user_friendly_message]);
}