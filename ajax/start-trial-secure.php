<?php
/**
 * Secure AJAX endpoint for starting free trials with Stripe SetupIntents
 */

// Basic error reporting
error_reporting(E_ERROR | E_WARNING);
ini_set('display_errors', 0); // Hide errors in JSON response

try {
    $wp_load_path = dirname(__FILE__, 3) . '/wp-load.php';
    if (!file_exists($wp_load_path)) {
        throw new Exception('WordPress wp-load.php not found at: ' . $wp_load_path);
    }
    require_once($wp_load_path);
    
    $pricing_config_path = dirname(__FILE__, 2) . '/includes/pricing-config.php';
    if (!file_exists($pricing_config_path)) {
        throw new Exception('pricing-config.php not found at: ' . $pricing_config_path);
    }
    require_once($pricing_config_path);
    
    $payment_functions_path = dirname(__FILE__, 2) . '/includes/payment-functions.php';
    if (!file_exists($payment_functions_path)) {
        throw new Exception('payment-functions.php not found at: ' . $payment_functions_path);
    }
    require_once($payment_functions_path);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to load required files: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// Debug: Check if WordPress functions are available
if (!function_exists('is_user_logged_in')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'WordPress authentication functions not available']);
    exit;
}

// Check if user is logged in
if (!is_user_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to start a trial.']);
    exit;
}

// Verify nonce
if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sud_start_trial_secure')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security check failed.']);
    exit;
}

$user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Get and validate plan
$plan_id = sanitize_text_field($_POST['plan'] ?? '');

// Debug: Check if sud_get_plan_details function exists
if (!function_exists('sud_get_plan_details')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Plan configuration functions not available']);
    exit;
}

$plan_config = sud_get_plan_details($plan_id);

if (!$plan_config || $plan_id === 'free') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid plan selected: ' . $plan_id]);
    exit;
}

// Check if user already has an active trial for this plan
$active_trial = sud_get_active_trial($user_id);
if ($active_trial && $active_trial['plan'] === $plan_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You are already on this trial plan.']);
    exit;
}

// Handle trial plan switching - allow switching to different trial plan
if ($active_trial && $active_trial['plan'] !== $plan_id) {
    // User is switching from one trial to another - this is allowed
    // The trial activation will handle updating the plan and preserving trial end date
}

// Collect secure billing details following industry standards (minimal data like Netflix/Shopify)
$billing_details = [
    'email' => sanitize_email($_POST['email'] ?? $current_user->user_email),
    'name' => sanitize_text_field($_POST['cardholder_name'] ?? $current_user->display_name),
];

// Validate required fields
if (empty($billing_details['email']) || !is_email($billing_details['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email is required.']);
    exit;
}

if (empty($billing_details['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Billing name is required.']);
    exit;
}

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
    
    // Create or retrieve Stripe customer
    $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
    
    if (empty($stripe_customer_id)) {
        // Create new Stripe customer
        $customer = \Stripe\Customer::create([
            'email' => $billing_details['email'],
            'name' => $billing_details['name'],
            'metadata' => [
                'wordpress_user_id' => $user_id,
                'source' => 'sud_trial_signup'
            ]
        ]);
        
        $stripe_customer_id = $customer->id;
        update_user_meta($user_id, 'stripe_customer_id', $stripe_customer_id);
    } else {
        // Update existing customer
        \Stripe\Customer::update($stripe_customer_id, [
            'email' => $billing_details['email'],
            'name' => $billing_details['name']
        ]);
    }
    
    // Check for existing idempotency key to prevent duplicate charges
    $existing_idempotency_key = get_user_meta($user_id, 'trial_idempotency_key_' . $plan_id, true);
    $existing_payment_intent_id = get_user_meta($user_id, 'pending_trial_payment_intent', true);
    
    if ($existing_idempotency_key && $existing_payment_intent_id) {
        // Check if existing PaymentIntent is still valid
        try {
            $existing_pi = \Stripe\PaymentIntent::retrieve($existing_payment_intent_id);
            if ($existing_pi->status === 'requires_payment_method' || 
                $existing_pi->status === 'requires_confirmation' ||
                $existing_pi->status === 'requires_action') {
                // Re-use existing PaymentIntent
                $payment_intent = $existing_pi;
                $idempotency_key = $existing_idempotency_key;
            } else {
                // Generate new idempotency key for new attempt
                $attempt_count = get_user_meta($user_id, 'trial_attempt_count_' . $plan_id, true) ?: 0;
                $attempt_count++;
                update_user_meta($user_id, 'trial_attempt_count_' . $plan_id, $attempt_count);
                $idempotency_key = 'trial_' . $user_id . '_' . $plan_id . '_' . $attempt_count;
                update_user_meta($user_id, 'trial_idempotency_key_' . $plan_id, $idempotency_key);
                $payment_intent = null; // Will create new one below
            }
        } catch (Exception $e) {
            // Existing PI not found, create new one
            $attempt_count = get_user_meta($user_id, 'trial_attempt_count_' . $plan_id, true) ?: 0;
            $attempt_count++;
            update_user_meta($user_id, 'trial_attempt_count_' . $plan_id, $attempt_count);
            $idempotency_key = 'trial_' . $user_id . '_' . $plan_id . '_' . $attempt_count;
            update_user_meta($user_id, 'trial_idempotency_key_' . $plan_id, $idempotency_key);
            $payment_intent = null; // Will create new one below
        }
    } else {
        // Generate new idempotency key for first attempt
        $attempt_count = get_user_meta($user_id, 'trial_attempt_count_' . $plan_id, true) ?: 0;
        $attempt_count++;
        update_user_meta($user_id, 'trial_attempt_count_' . $plan_id, $attempt_count);
        $idempotency_key = 'trial_' . $user_id . '_' . $plan_id . '_' . $attempt_count;
        update_user_meta($user_id, 'trial_idempotency_key_' . $plan_id, $idempotency_key);
        $payment_intent = null; // Will create new one below
    }
    
    // Create PaymentIntent only if we don't have a reusable one
    if (!$payment_intent) {
        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => 500, // $5.00 in cents
            'currency' => 'usd',
            'customer' => $stripe_customer_id,
            'payment_method_types' => ['card'],
            'description' => "Trial verification charge for WP user #{$user_id} ({$plan_id})",
            // Note: Using automatic capture (default) - charge will be captured and then immediately refunded
            'metadata' => [
                'type' => 'trial_validation_charge',
                'plan_id' => $plan_id,
                'user_id' => $user_id,
                'trial_duration_days' => $plan_config['free_trial_days'] ?? 3,
                'will_refund' => 'true'
            ]
        ], [
            'idempotency_key' => $idempotency_key
        ]);
    }
    
    // Store payment intent for completion tracking
    update_user_meta($user_id, 'pending_trial_payment_intent', $payment_intent->id);
    update_user_meta($user_id, 'pending_trial_plan', $plan_id);
    update_user_meta($user_id, 'pending_trial_created', time()); // For efficient cleanup queries
    
    // Return client secret for frontend processing
    $response = [
        'success' => true,
        'payment_intent_client_secret' => $payment_intent->client_secret,
        'customer_id' => $stripe_customer_id,
        'debug_payment_intent_id' => $payment_intent->id,
        'trial_duration_days' => $plan_config['free_trial_days'] ?? 3
    ];

    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Trial Setup Error: ' . $e->getMessage() . ' in file: ' . $e->getFile() . ' line: ' . $e->getLine());
    
    // Use comprehensive error mapping for better user experience
    $user_friendly_message = 'Payment verification setup failed. Please try again.';
    
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