<?php
/**
 * Complete trial setup after successful SetupIntent confirmation
 * This activates the trial with the verified payment method
 */

try {
    require_once(dirname(__FILE__, 3) . '/wp-load.php');
    require_once(dirname(__FILE__, 2) . '/includes/pricing-config.php');
    require_once(dirname(__FILE__, 2) . '/includes/payment-functions.php');
    require_once(dirname(__FILE__, 2) . '/includes/premium-functions.php');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load required files: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// Check if user is logged in
if (!is_user_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

// Verify nonce
if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sud_complete_trial_setup')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security check failed.']);
    exit;
}

$user_id = get_current_user_id();
$payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');


if (empty($payment_intent_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment intent ID is required.']);
    exit;
}

// Race condition protection with timeout - check if trial is already being processed
$completion_status = get_user_meta($user_id, 'trial_completion_status', true);
$completion_timestamp = get_user_meta($user_id, 'trial_completion_timestamp', true);

// Check if processing status is stale (older than 5 minutes)
$five_minutes_ago = time() - (5 * 60);
if ($completion_status === 'processing' && $completion_timestamp && $completion_timestamp < $five_minutes_ago) {
    // Clear stale processing status
    delete_user_meta($user_id, 'trial_completion_status');
    delete_user_meta($user_id, 'trial_completion_timestamp');
    error_log("SUD TRIAL: Cleared stale processing status for user {$user_id}");
    $completion_status = '';
}

if ($completion_status === 'processing') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Trial is already being processed. Please wait.']);
    exit;
} elseif ($completion_status === 'completed') {
    // Check if this is a legitimate completion or stale/mismatched plan
    $active_trial = sud_get_active_trial($user_id);
    $pending_plan_for_switch = get_user_meta($user_id, 'pending_trial_plan', true);

    if ($active_trial) {
        // If active trial exists but it's for a DIFFERENT plan than pending switch,
        // treat this as a plan switch and clear stale 'completed' status to proceed.
        if (!empty($pending_plan_for_switch) && $active_trial['plan'] !== $pending_plan_for_switch) {
            delete_user_meta($user_id, 'trial_completion_status');
            delete_user_meta($user_id, 'trial_completion_timestamp');
            $completion_status = '';
        } else {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Trial already activated.']);
            exit;
        }
    } else {
        // Stale completion status without active trial - clear it
        delete_user_meta($user_id, 'trial_completion_status');
        delete_user_meta($user_id, 'trial_completion_timestamp');
        $completion_status = '';
    }
}

// Mark as processing to prevent race conditions
update_user_meta($user_id, 'trial_completion_status', 'processing');
update_user_meta($user_id, 'trial_completion_timestamp', time());

try {
    // Initialize Stripe SDK
    $stripe_sdk_path = dirname(__FILE__, 2) . '/vendor/stripe/stripe-php/init.php';
    if (!file_exists($stripe_sdk_path)) {
        throw new Exception('Stripe SDK not found');
    }
    require_once($stripe_sdk_path);
    
    // Set Stripe API key
    $secret_key = sud_get_stripe_secret_key();
    if (empty($secret_key)) {
        throw new Exception('Stripe API key not configured');
    }
    \Stripe\Stripe::setApiKey($secret_key);
    
    // Retrieve and verify the PaymentIntent
    $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
    
    if ($payment_intent->status !== 'succeeded') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment verification failed.']);
        exit;
    }
    
    // Verify this PaymentIntent belongs to the current user
    $pending_payment_intent = get_user_meta($user_id, 'pending_trial_payment_intent', true);
    if ($pending_payment_intent !== $payment_intent_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid payment intent.']);
        exit;
    }
    
    // Get plan_id before creating refund metadata
    $plan_id = get_user_meta($user_id, 'pending_trial_plan', true);
    if (empty($plan_id)) {
        $plan_id = 'unknown';
    }
    
    // Check if refund already exists to prevent duplicates (idempotent refund creation)
    $existing_refunds = \Stripe\Refund::all([
        'payment_intent' => $payment_intent_id,
        'limit' => 1
    ]);
    
    if (empty($existing_refunds->data)) {
        // Create refund only if none exists
        $refund = \Stripe\Refund::create([
            'payment_intent' => $payment_intent_id,
            'reason' => 'requested_by_customer',
            'metadata' => [
                'type' => 'trial_validation_refund',
                'user_id' => $user_id,
                'plan_id' => $plan_id
            ]
        ]);
    } else {
        // Use existing refund
        $refund = $existing_refunds->data[0];
        error_log("TRIAL VALIDATION: Using existing refund {$refund->id} for PaymentIntent {$payment_intent_id} (user {$user_id})");
    }
    
    // Log the refund for tracking
    
    // Get the plan config (plan_id already retrieved above)
    $plan_config = sud_get_plan_details($plan_id);
    
    if (!$plan_config || $plan_id === 'free') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid plan configuration.']);
        exit;
    }
    
    // Check trial eligibility again
    $used_trials = get_user_meta($user_id, 'sud_used_trials', true) ?: [];
    if (in_array($plan_id, $used_trials)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You have already used a trial for this plan.']);
        exit;
    }
    
    // Store secure payment information (NO RAW CARD DATA)
    $secure_payment_data = [
        'stripe_customer_id' => $payment_intent->customer,
        'payment_method_id' => $payment_intent->payment_method,
        'payment_intent_id' => $payment_intent_id,
        'refund_id' => $refund->id,
        'charge_refunded' => true,
        'billing_cycle' => sanitize_text_field($_POST['billing_cycle'] ?? 'monthly'),
        'created_at' => current_time('mysql'),
        'verification_method' => 'stripe_payment_intent_charge_refund'
    ];
    
    // Activate the trial
    // Preserve existing trial end date if switching between trial plans
    $existing_trial = function_exists('sud_get_active_trial') ? sud_get_active_trial($user_id) : false;
    $is_switching_trial_plan = $existing_trial && isset($existing_trial['plan']) && $existing_trial['plan'] !== $plan_id;

    if ($is_switching_trial_plan) {
        $trial_start = get_user_meta($user_id, 'sud_trial_start', true) ?: current_time('mysql');
        $trial_end = $existing_trial['end'];
        
    } else {
        $trial_start = current_time('mysql');
        $trial_end = date('Y-m-d H:i:s', strtotime('+' . ($plan_config['free_trial_days'] ?? 3) . ' days'));
        
    }

    // Set trial-specific metadata
    update_user_meta($user_id, 'sud_trial_plan', $plan_id);
    update_user_meta($user_id, 'sud_trial_start', $trial_start);
    update_user_meta($user_id, 'sud_trial_end', $trial_end);
    update_user_meta($user_id, 'sud_trial_secure_payment', $secure_payment_data);

    // IMPORTANT: Set premium_plan so user gets actual plan privileges during trial
    update_user_meta($user_id, 'premium_plan', $plan_id);
    update_user_meta($user_id, 'subscription_expires', $trial_end);
    
    // Handle auto-verification for Diamond trials (if premium-functions.php is loaded)
    if (function_exists('sud_handle_plan_verification_update')) {
        sud_handle_plan_verification_update($user_id, $plan_id, 'free');
    }
    
    // Mark this plan as used for trials
    $used_trials[] = $plan_id;
    update_user_meta($user_id, 'sud_used_trials', $used_trials);
    
    // Clean up pending data and idempotency keys
    delete_user_meta($user_id, 'pending_trial_payment_intent');
    delete_user_meta($user_id, 'pending_trial_plan');
    delete_user_meta($user_id, 'pending_trial_created');
    delete_user_meta($user_id, 'trial_idempotency_key_' . $plan_id);
    
    // Mark trial completion as done
    update_user_meta($user_id, 'trial_completion_status', 'completed');
    delete_user_meta($user_id, 'trial_completion_timestamp');
    
    // Remove any old insecure payment data if it exists
    delete_user_meta($user_id, 'sud_trial_payment_details');
    
    // Add in-app notification for trial activation
    require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');
    add_notification($user_id, 'subscription', "Your " . ($plan_config['name'] ?? $plan_id) . " trial is now active! Enjoy " . ($plan_config['free_trial_days'] ?? 3) . " days of premium features.", null);
    
    // Send trial success emails
    if (function_exists('send_trial_welcome_email') && function_exists('send_admin_trial_notification')) {
        $trial_data = [
            'plan' => $plan_id,
            'days' => $plan_config['free_trial_days'] ?? 3,
            'end' => $trial_end,
            'payment_method' => $payment_intent->payment_method
        ];
        
        // Send welcome email to user
        $user_email_sent = send_trial_welcome_email($user_id, $trial_data);
        if (!$user_email_sent) {
            error_log("SUD TRIAL: Failed to send welcome email to user $user_id");
        }
        
        // Send notification to admin
        $admin_email_sent = send_admin_trial_notification($user_id, $trial_data);
        if (!$admin_email_sent) {
            error_log("SUD TRIAL: Failed to send admin notification for user $user_id");
        }
    } else {
        error_log("SUD TRIAL: Email functions not available - emails not sent for user $user_id");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Trial activated successfully!',
        'trial_end' => $trial_end,
        'plan' => $plan_config
    ]);
    
} catch (Exception $e) {
    // Reset completion status on error
    delete_user_meta($user_id, 'trial_completion_status');
    delete_user_meta($user_id, 'trial_completion_timestamp');
    
    
    
    // Use comprehensive error mapping for better user experience
    $user_friendly_message = 'Failed to activate trial. Please contact support.';
    
    // Check if this is a Stripe error that we can make user-friendly
    if (function_exists('sud_get_user_friendly_stripe_error') && 
        ($e instanceof \Stripe\Exception\StripeException)) {
        $user_friendly_message = sud_get_user_friendly_stripe_error($e);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $user_friendly_message
    ]);
}
?>