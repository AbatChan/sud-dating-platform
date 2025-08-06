<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
require_once(dirname(__FILE__, 2) . '/includes/payment-functions.php');

header('Content-Type: application/json');

try {
    // Use centralized security verification
    $user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_ajax_action',
        'rate_limit' => ['requests' => 3, 'window' => 300, 'action' => 'cancel_subscription'] // 3 cancellations per 5 minutes
    ]);
    $subscription_id = get_user_meta($user_id, 'subscription_id', true);
    $payment_method = get_user_meta($user_id, 'payment_method', true);

    if (empty($subscription_id)) {
        throw new Exception('No active subscription found to cancel');
    }

    if ($payment_method === 'stripe') {
        $stripe_sdk_path = dirname(__FILE__, 2) . '/vendor/stripe/stripe-php/init.php';
        if (!file_exists($stripe_sdk_path)) throw new Exception("Stripe SDK not found.");
        require_once($stripe_sdk_path);

        $secret_key = sud_get_stripe_secret_key();
        \Stripe\Stripe::setApiKey($secret_key);

        \Stripe\Subscription::update($subscription_id, ['cancel_at_period_end' => true]);

    } elseif ($payment_method === 'paypal') {

        if (function_exists('sud_cancel_paypal_subscription')) {
            sud_cancel_paypal_subscription($subscription_id);
        } else {
            error_log("User $user_id initiated PayPal cancellation for sub ID: $subscription_id. Manual cancellation in PayPal dashboard is required for now.");
        }

    } else {
        throw new Exception('Unknown payment method for cancellation');
    }

    update_user_meta($user_id, 'subscription_auto_renew', '0');
    wp_send_json_success(['message' => 'Your subscription has been set to cancel at the end of your current billing period.']);

} catch (Exception $e) {
    error_log("Subscription Cancellation Error for user $user_id: " . $e->getMessage());
    sud_handle_ajax_error($e);
}