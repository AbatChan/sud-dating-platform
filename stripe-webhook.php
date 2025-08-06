<?php

require_once(dirname(__FILE__, 2) . '/wp-load.php');

$stripe_sdk_path = dirname(__FILE__) . '/vendor/stripe/stripe-php/init.php';
if (file_exists($stripe_sdk_path)) {
    require_once($stripe_sdk_path);
} else {
    http_response_code(500);
    error_log("Stripe Webhook Error: The Stripe SDK is missing at " . $stripe_sdk_path);
    exit('Webhook Error: Stripe SDK not found.');
}

// Include payment functions for SSL fix
require_once(dirname(__FILE__) . '/includes/payment-functions.php');
require_once(dirname(__FILE__) . '/includes/mailer.php');
sud_init_stripe_ssl_fix();

$payment_settings = get_option('sud_payment_settings');
$webhook_secret = sud_is_test_mode()
    ? ($payment_settings['stripe_test_webhook_secret'] ?? '')
    : ($payment_settings['stripe_live_webhook_secret'] ?? '');

if (empty($webhook_secret)) {
    $mode = sud_is_test_mode() ? 'TEST' : 'LIVE';
    http_response_code(500);
    error_log("Stripe Webhook Error: Webhook signing secret is not configured in WordPress settings (MODE: {$mode}).");
    exit('Webhook Error: Secret not configured.');
}

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Check if this is a valid Stripe webhook request
if (empty($sig_header)) {
    http_response_code(400);
    exit('Webhook Error: Missing Stripe signature header.');
}

$event = null;

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400); 
    exit('Webhook Error: Invalid payload.');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400); 
    exit('Webhook Error: Invalid signature.');
}

switch ($event->type) {
    case 'invoice.payment_succeeded':

        $invoice = $event->data->object;
        $subscription_id = $invoice->subscription ?? null;

        if ($subscription_id) {
            $subscription = \Stripe\Subscription::retrieve($subscription_id);
            $user_id = $subscription->metadata->wp_user_id ?? 0;

            // Fallback: find user by customer ID if metadata fails
            if (!$user_id && $invoice->customer) {
                $users = get_users([
                    'meta_key' => 'stripe_customer_id',
                    'meta_value' => $invoice->customer,
                    'number' => 1
                ]);
                $user_id = !empty($users) ? $users[0]->ID : 0;
            }

            if ($user_id > 0) {
                $new_expiry_date = gmdate('Y-m-d H:i:s', $subscription->current_period_end);
                update_user_meta($user_id, 'subscription_expires', $new_expiry_date);
                update_user_meta($user_id, 'premium_plan', $subscription->metadata->plan_id ?? 'unknown');
                update_user_meta($user_id, 'subscription_auto_renew', '1');
                
                // Handle auto-verification for Diamond tier renewals
                if (function_exists('sud_update_verification_based_on_plan')) {
                    sud_update_verification_based_on_plan($user_id);
                }
                
                // Send admin notification for subscription renewal
                $user = get_userdata($user_id);
                if ($user) {
                    $plan_id = $subscription->metadata->plan_id ?? 'unknown';
                    $amount = ($invoice->amount_paid ?? 0) / 100; // Convert from cents
                    
                    $transaction_data = [
                        'type' => 'subscription_renewal',
                        'user_id' => $user_id,
                        'amount' => number_format($amount, 2),
                        'item_name' => ucfirst($plan_id) . ' Plan (Renewal)',
                        'payment_method' => 'Credit Card',
                        'transaction_id' => $subscription_id
                    ];
                    
                    send_admin_payment_notification($transaction_data);
                    
                    // Track plan renewal conversion with TrafficJunky
                    if (function_exists('track_plan_purchase_conversion')) {
                        track_plan_purchase_conversion($user_id, $plan_id, $subscription_id, [
                            'plan_name' => ucfirst($plan_id) . ' Plan',
                            'billing_cycle' => 'renewal',
                            'amount' => $amount,
                            'payment_method' => 'stripe_webhook'
                        ]);
                    }
                }
            }
        }
        break;

    case 'customer.subscription.deleted':

        $subscription = $event->data->object;
        $user_id = $subscription->metadata->wp_user_id ?? 0;

        // Fallback: find user by customer ID if metadata fails
        if (!$user_id && $subscription->customer) {
            $users = get_users([
                'meta_key' => 'stripe_customer_id',
                'meta_value' => $subscription->customer,
                'number' => 1
            ]);
            $user_id = !empty($users) ? $users[0]->ID : 0;
        }

        if ($user_id > 0) {
            $current_sub_id = get_user_meta($user_id, 'subscription_id', true);
            if ($current_sub_id === $subscription->id) {
                // Get the old plan before changing it for verification handling
                $old_plan_id = get_user_meta($user_id, 'premium_plan', true);
                
                update_user_meta($user_id, 'premium_plan', 'free');
                delete_user_meta($user_id, 'subscription_id');
                delete_user_meta($user_id, 'subscription_expires');
                update_user_meta($user_id, 'subscription_auto_renew', '0');
                
                // Handle verification removal when downgrading from Diamond
                if (function_exists('sud_handle_plan_verification_update')) {
                    sud_handle_plan_verification_update($user_id, 'free', $old_plan_id);
                }
                
                // Send admin notification for subscription cancellation
                $user = get_userdata($user_id);
                if ($user && function_exists('send_admin_subscription_cancellation_notification')) {
                    $plan_id = $subscription->metadata->plan_id ?? 'unknown';
                    
                    $cancellation_data = [
                        'user_id' => $user_id,
                        'user_name' => $user->display_name,
                        'user_email' => $user->user_email,
                        'plan_name' => ucfirst($plan_id) . ' Plan',
                        'subscription_id' => $subscription->id,
                        'cancellation_date' => current_time('mysql'),
                        'payment_method' => 'Stripe'
                    ];
                    
                    send_admin_subscription_cancellation_notification($cancellation_data);
                }
            }
        }
        break;

    case 'refund.created':
        $refund = $event->data->object;
        handleTrialRefundCreated($refund);
        break;
        
    case 'refund.updated':
        $refund = $event->data->object;
        handleTrialRefundUpdate($refund);
        break;
        
    case 'charge.refund.updated':
        $refund = $event->data->object;
        handleTrialRefundUpdate($refund);
        break;
}

/**
 * Handle trial validation refund creation
 */
function handleTrialRefundCreated($refund) {
    if (isset($refund->metadata->type) && $refund->metadata->type === 'trial_validation_refund') {
        $user_id = $refund->metadata->user_id ?? null;
        
        if ($user_id) {
            error_log("REFUND WEBHOOK: Trial validation refund created - Refund: {$refund->id}, User: {$user_id}, Amount: {$refund->amount}");
            update_user_meta($user_id, 'trial_refund_confirmed_at', current_time('mysql'));
            update_user_meta($user_id, 'trial_refund_id', $refund->id);
        }
    }
}

/**
 * Handle trial validation refund updates (including failures)
 */
function handleTrialRefundUpdate($refund) {
    if (isset($refund->metadata->type) && $refund->metadata->type === 'trial_validation_refund') {
        $user_id = $refund->metadata->user_id ?? null;
        
        if ($user_id) {
            if ($refund->status === 'failed') {
                error_log("REFUND WEBHOOK ALERT: Trial validation refund FAILED - Refund: {$refund->id}, User: {$user_id}, Reason: {$refund->failure_reason}");
                
                // Flag user record for manual review
                update_user_meta($user_id, 'trial_refund_failed', true);
                update_user_meta($user_id, 'trial_refund_failure_reason', $refund->failure_reason ?? 'unknown');
                update_user_meta($user_id, 'trial_refund_failed_at', current_time('mysql'));
                
                // Clear idempotency key so support can manually restart the flow
                $plan_id = $refund->metadata->plan_id ?? null;
                if ($plan_id) {
                    delete_user_meta($user_id, 'trial_idempotency_key_' . $plan_id);
                    error_log("REFUND WEBHOOK: Cleared idempotency key for user {$user_id}, plan {$plan_id} due to refund failure");
                }
                
                // Send alert email to ops (if configured)
                $ops_email = get_option('sud_ops_alert_email', '');
                if (!empty($ops_email)) {
                    $subject = '[TRIAL] Refund Failed - Manual Review Required';
                    $message = "A trial validation refund has failed:\n\n";
                    $message .= "User ID: {$user_id}\n";
                    $message .= "Refund ID: {$refund->id}\n";
                    $message .= "Payment Intent: {$refund->payment_intent}\n";
                    $message .= "Failure Reason: {$refund->failure_reason}\n";
                    $message .= "Amount: $" . number_format($refund->amount / 100, 2) . "\n";
                    if (isset($refund->failure_balance_transaction)) {
                        $message .= "Balance Transaction: {$refund->failure_balance_transaction}\n";
                    }
                    $message .= "Time: " . current_time('mysql') . "\n\n";
                    $message .= "Please check Stripe dashboard and manually process if needed.";
                    
                    wp_mail($ops_email, $subject, $message);
                }
                
            } elseif ($refund->status === 'succeeded') {
                // Clear failure flags on success
                delete_user_meta($user_id, 'trial_refund_failed');
                delete_user_meta($user_id, 'trial_refund_failure_reason');
                delete_user_meta($user_id, 'trial_refund_failed_at');
                update_user_meta($user_id, 'trial_refund_succeeded_at', current_time('mysql'));
                
                error_log("REFUND WEBHOOK: Trial validation refund succeeded - Refund: {$refund->id}, User: {$user_id}");
            }
        }
    }
}

http_response_code(200); 
echo 'Event handled successfully.';