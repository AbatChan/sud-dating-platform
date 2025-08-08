<?php
/**
 * Trial expiry and management cron functions
 */

// Hook into WordPress cron system
add_action('sud_check_trial_expiry', 'sud_process_expired_trials');
add_action('sud_process_billing_retries', 'sud_process_billing_retries');
add_action('sud_cleanup_stale_payment_intents', 'sud_cleanup_stale_payment_intents');

/**
 * Initialize cron jobs - called from main app initialization
 * This replaces plugin activation hooks since this is a standalone app
 */
function sud_init_trial_cron_jobs() {
    // Wait until WP core is fully booted before scheduling
    if (!did_action('wp')) {
        return;
    }
    
    // Schedule cron jobs if they don't exist
    if (!wp_next_scheduled('sud_check_trial_expiry')) {
        wp_schedule_event(time(), 'hourly', 'sud_check_trial_expiry');
    }
    if (!wp_next_scheduled('sud_process_billing_retries')) {
        wp_schedule_event(time(), 'hourly', 'sud_process_billing_retries');
    }
    if (!wp_next_scheduled('sud_cleanup_stale_payment_intents')) {
        wp_schedule_event(time(), 'hourly', 'sud_cleanup_stale_payment_intents');
    }
}

/**
 * Clean up cron jobs - call this if needed for maintenance
 */
function sud_cleanup_trial_cron_jobs() {
    wp_clear_scheduled_hook('sud_check_trial_expiry');
    wp_clear_scheduled_hook('sud_process_billing_retries');
}

// Initialize cron jobs early to prevent multiple calls on heavy sites
add_action('after_setup_theme', 'sud_init_trial_cron_jobs');

/**
 * Process expired trials and handle automatic billing/downgrade
 */
function sud_process_expired_trials() {
    // Prevent concurrent execution with mutex lock
    if (get_transient('sud_trial_expiry_lock')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            sud_safe_log("SUD TRIAL EXPIRY: Another process is already running, skipping");
        }
        return;
    }
    set_transient('sud_trial_expiry_lock', 1, 3 * MINUTE_IN_SECONDS);
    
    try {
        require_once(dirname(__FILE__) . '/pricing-config.php');
        
        // Use WordPress meta query with DATETIME type for better performance
        $users_with_expired_trials = get_users([
            'meta_key' => 'sud_trial_end',
            'meta_compare' => '<',
            'meta_value' => current_time('mysql'),
            'meta_type' => 'DATETIME',
            'fields' => ['ID'],
            'number' => 500, // Process in batches to avoid memory issues
        ]);
        
        if (empty($users_with_expired_trials)) {
            return;
        }
        
        
        foreach ($users_with_expired_trials as $user) {
        $user_id = $user->ID;
        
        // Get trial data directly (don't use sud_get_active_trial as it returns false for expired trials)
        $trial_plan = get_user_meta($user_id, 'sud_trial_plan', true);
        $trial_start = get_user_meta($user_id, 'sud_trial_start', true);
        $trial_end = get_user_meta($user_id, 'sud_trial_end', true);
        
        // Skip if no trial data exists (already processed)
        if (!$trial_plan || !$trial_end) {
            continue;
        }
        
        // CRITICAL: Check if trial was cancelled by admin - DO NOT charge cancelled trials
        $downgrade_reason = get_user_meta($user_id, 'sud_trial_downgrade_reason', true);
        if (!empty($downgrade_reason) && strpos($downgrade_reason, 'Cancelled by admin') !== false) {
            // Trial was cancelled by admin, just clean up trial data without charging
            delete_user_meta($user_id, 'sud_trial_plan');
            delete_user_meta($user_id, 'sud_trial_start');
            delete_user_meta($user_id, 'sud_trial_end');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("TRIAL EXPIRY: Skipping billing for user {$user_id} - trial was cancelled by admin");
            }
            continue;
        }
        
        $trial_data = [
            'plan' => $trial_plan,
            'start' => $trial_start,
            'end' => $trial_end
        ];
        
        
        // Get secure payment details for automatic billing (replaces old insecure method)
        $secure_payment = get_user_meta($user_id, 'sud_trial_secure_payment', true);
        $legacy_payment = get_user_meta($user_id, 'sud_trial_payment_details', true); // For backwards compatibility
        
        if (!empty($secure_payment) && is_array($secure_payment)) {
            // Use new secure payment method
            $billing_cycle = $secure_payment['billing_cycle'] ?? 'monthly';
            // Attempt automatic billing with secure payment data
            $billing_result = sud_attempt_trial_billing($user_id, $trial_data['plan'], $secure_payment);
            
            // CRITICAL: Extra validation to prevent false conversions
            if ($billing_result['success'] && 
                isset($billing_result['subscription_id']) && !empty($billing_result['subscription_id']) &&
                isset($billing_result['status']) && in_array($billing_result['status'], ['active', 'trialing'])) {
                // Double-check: Only convert if we have a real Stripe subscription ID
                
                // Successfully billed, convert to paid subscription
                sud_convert_trial_to_subscription($user_id, $trial_data['plan'], $billing_result);
                
                // Send conversion confirmation email
                sud_send_trial_conversion_email($user_id, $trial_data['plan']);
                
            } else {
                // Billing failed - check if we should retry or downgrade
                $should_retry = sud_should_retry_billing($user_id, $billing_result['error']);
                
                if ($should_retry) {
                    // Queue for retry instead of immediate downgrade
                    sud_queue_billing_retry($user_id, $trial_data, $billing_result['error']);
                } else {
                    // Non-retryable error or max retries reached - downgrade immediately
                    sud_downgrade_expired_trial($user_id, $billing_result['error']);
                    sud_send_trial_billing_failure_email($user_id, $trial_data['plan'], $billing_result['error']);
                }
            }
        } elseif (!empty($legacy_payment) && is_array($legacy_payment)) {
            // Fallback for old insecure trials (to be phased out)
            sud_downgrade_expired_trial($user_id, 'Legacy payment method - automatic billing disabled for security');
            sud_send_trial_expiry_email($user_id, $trial_data['plan']);
        } else {
            // No payment details, just downgrade
            sud_downgrade_expired_trial($user_id, 'No payment method on file');
            
            // Send trial expiry email
            sud_send_trial_expiry_email($user_id, $trial_data['plan']);
            
        }
        } // Close foreach loop
        
        // If we processed a full batch (500), there might be more expired trials
        // Schedule another run to handle remaining users (with recursion protection)
        if (count($users_with_expired_trials) === 500) {
            $batch_depth = get_transient('sud_trial_batch_depth') ?: 0;
            
            if ($batch_depth < 10) { // Max 10 recursive batches to prevent infinite loops
                    set_transient('sud_trial_batch_depth', $batch_depth + 1, 30 * MINUTE_IN_SECONDS);
                wp_schedule_single_event(time() + 300, 'sud_check_trial_expiry'); // 5 min buffer
            } else {
                sud_safe_log("SUD TRIAL EXPIRY: WARNING - Max batch depth reached ({$batch_depth}), stopping recursion to prevent infinite loop");
                delete_transient('sud_trial_batch_depth'); // Reset for next hour
            }
        } else {
            // Reset batch depth when we process less than full batch
            delete_transient('sud_trial_batch_depth');
        }
        
    } finally {
        // Clean up mutex lock - always runs even if PHP fatals
        delete_transient('sud_trial_expiry_lock');
    }
}

/**
 * Attempt to bill user for trial conversion
 * 
 * @param int $user_id
 * @param string $plan_id
 * @param array $payment_details
 * @return array
 */
function sud_attempt_trial_billing($user_id, $plan_id, $payment_details) {
    try {
        $billing_cycle = $payment_details['billing_cycle'] ?? 'monthly';
        
        // Only Stripe is supported for now - PayPal will be added later
        return sud_process_stripe_trial_billing($user_id, $plan_id, $billing_cycle, $payment_details);
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Process Stripe billing for trial conversion
 * 
 * @param int $user_id
 * @param string $plan_id
 * @param string $billing_cycle
 * @param array $payment_details
 * @return array
 */
function sud_process_stripe_trial_billing($user_id, $plan_id, $billing_cycle, $payment_details) {
    try {
        // Get secure payment data instead of raw card data
        $secure_payment = get_user_meta($user_id, 'sud_trial_secure_payment', true);
        
        if (empty($secure_payment) || empty($secure_payment['stripe_customer_id']) || empty($secure_payment['payment_method_id'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("STRIPE BILLING DEBUG: User {$user_id} secure payment data: " . print_r($secure_payment, true));
            }
            return ['success' => false, 'error' => 'No valid payment method found for automatic billing'];
        }
        
        
        // Initialize Stripe SDK using relative path that won't break if folder is renamed
        $stripe_sdk_path = plugin_dir_path(__DIR__) . 'vendor/stripe/stripe-php/init.php';
        if (!file_exists($stripe_sdk_path)) {
            return ['success' => false, 'error' => 'Stripe SDK not found'];
        }
        require_once($stripe_sdk_path);
        
        // Load payment functions for API key and test mode checking
        require_once(dirname(__FILE__) . '/payment-functions.php');
        $secret_key = sud_get_stripe_secret_key();
        if (empty($secret_key)) {
            return ['success' => false, 'error' => 'Stripe API key not configured'];
        }
        \Stripe\Stripe::setApiKey($secret_key);
        
        // Get plan configuration
        require_once(dirname(__FILE__) . '/premium-functions.php');
        $plan_config = sud_get_plan_details($plan_id);
        
        if (!$plan_config || $plan_id === 'free') {
            return ['success' => false, 'error' => 'Invalid plan configuration'];
        }
        
        // Get the correct Stripe price ID for the billing cycle (with test/live mode support)
        $is_test_mode = sud_is_test_mode();
        
        if ($billing_cycle === 'annual') {
            $stripe_price_id = $is_test_mode ? 
                ($plan_config['stripe_price_id_annually_test'] ?? $plan_config['stripe_price_id_annually']) :
                ($plan_config['stripe_price_id_annually'] ?? '');
        } else {
            $stripe_price_id = $is_test_mode ? 
                ($plan_config['stripe_price_id_monthly_test'] ?? $plan_config['stripe_price_id_monthly']) :
                ($plan_config['stripe_price_id_monthly'] ?? '');
        }
            
        if (empty($stripe_price_id)) {
            $mode = $is_test_mode ? 'test' : 'live';
            // Always log configuration errors
            sud_safe_log("STRIPE BILLING ERROR: No {$mode} price configured for {$plan_id} {$billing_cycle}");
            if (sud_is_verbose_logging_enabled()) {
                $config_dump = print_r($plan_config, true);
                // Memory protection: limit large array dumps
                if (strlen($config_dump) > 5000) {
                    $config_dump = substr($config_dump, 0, 5000) . '... [TRUNCATED]';
                }
                sud_safe_log("STRIPE BILLING DEBUG: Plan config for {$plan_id}: " . $config_dump);
            }
            return ['success' => false, 'error' => "No Stripe {$mode} price configured for {$plan_id} {$billing_cycle} billing. Check your pricing configuration."];
        }
        
        // Log critical billing attempts, verbose for details
        $mode = $is_test_mode ? 'test' : 'live';
        $customer_id = $secure_payment['stripe_customer_id'];
        sud_safe_log("STRIPE BILLING: Using {$mode} price ID {$stripe_price_id} for {$plan_id} {$billing_cycle}");
        if (sud_is_verbose_logging_enabled()) {
            sud_safe_log("STRIPE BILLING: User {$user_id} - Attempting {$mode} mode billing with customer {$customer_id}");
        }
        
        // Validate that the customer and payment method exist in Stripe
        try {
            $customer = \Stripe\Customer::retrieve($secure_payment['stripe_customer_id']);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $status = (isset($customer->deleted) && $customer->deleted) ? 'deleted' : 'active';
                error_log("STRIPE BILLING: Customer {$customer->id} found, status: {$status}");
            }
            if (isset($customer->deleted) && $customer->deleted) {
                return ['success' => false, 'error' => 'Customer account was deleted from Stripe'];
            }
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Check for test/live mode mismatch
            $error_message = $e->getMessage();
            if (strpos($error_message, 'No such customer') !== false) {
                $current_mode = sud_is_test_mode() ? 'test' : 'live';
                $opposite_mode = sud_is_test_mode() ? 'live' : 'test';
                
                return ['success' => false, 'error' => "Customer was created in {$opposite_mode} mode but site is currently in {$current_mode} mode. Please contact support or retry your trial setup."];
            }
            
            return ['success' => false, 'error' => 'Customer not found in Stripe: ' . $error_message];
        }
        
        try {
            $payment_method = \Stripe\PaymentMethod::retrieve($secure_payment['payment_method_id']);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("STRIPE BILLING: Payment method {$payment_method->id} found, type: {$payment_method->type}");
            }
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Check for test/live mode mismatch
            $error_message = $e->getMessage();
            if (strpos($error_message, 'No such payment_method') !== false) {
                $current_mode = sud_is_test_mode() ? 'test' : 'live';
                $opposite_mode = sud_is_test_mode() ? 'live' : 'test';
                
                return ['success' => false, 'error' => "Payment method was created in {$opposite_mode} mode but site is currently in {$current_mode} mode. Please retry your trial setup."];
            }
            
            return ['success' => false, 'error' => 'Payment method not found in Stripe: ' . $error_message];
        }
        
        // Create subscription with the stored payment method
        $subscription = \Stripe\Subscription::create([
            'customer' => $secure_payment['stripe_customer_id'],
            'items' => [
                ['price' => $stripe_price_id]
            ],
            'default_payment_method' => $secure_payment['payment_method_id'],
            'payment_behavior' => 'error_if_incomplete', // CRITICAL: Fail fast if payment fails
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => [
                'wp_user_id' => $user_id,
                'converted_from_trial' => 'true',
                'plan_id' => $plan_id,
                'billing_cycle' => $billing_cycle
            ]
        ]);
        
        // CRITICAL: Double-check subscription status and payment intent
        $status = $subscription->status;
        $payment_intent = $subscription->latest_invoice->payment_intent ?? null;
        $payment_succeeded = $payment_intent && $payment_intent->status === 'succeeded';
        
        // Only return success if subscription is active/trialing AND payment succeeded
        if (!in_array($status, ['active', 'trialing']) || !$payment_succeeded) {
            // Clean up the incomplete subscription
            try {
                $subscription->cancel(['invoice_now' => false, 'prorate' => false]);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("STRIPE BILLING: Cancelled incomplete subscription {$subscription->id} for user {$user_id}");
                }
            } catch (Exception $cleanup_error) {
                error_log("STRIPE BILLING: Failed to cancel incomplete subscription {$subscription->id}: " . $cleanup_error->getMessage());
            }
            
            // Guard against missing payment intent
            if (!$payment_intent) {
                return ['success' => false, 'error' => 'payment_intent_missing'];
            }
            
            // Get decline code for better retry logic
            $decline_code = $payment_intent->last_payment_error->decline_code ?? '';
            $error_msg = $payment_intent->last_payment_error->message ?? 'Initial payment failed';
            
            // Use decline code for retry decisions, fallback to message
            $error_for_retry = $decline_code ?: $error_msg;
            
            // Always log decline code for production debugging
            sud_safe_log("STRIPE BILLING: Payment failed for user {$user_id} - Decline Code: {$decline_code}, Error: {$error_msg}");
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("STRIPE BILLING: Payment failed for user {$user_id} - Status: {$status}, PI Status: " . ($payment_intent->status ?? 'none') . ", Decline Code: {$decline_code}, Error: {$error_msg}");
            }
            
            return ['success' => false, 'error' => $error_for_retry];
        }
        
        // Store subscription data (use canonical keys)
        update_user_meta($user_id, 'stripe_subscription_id', $subscription->id);
        update_user_meta($user_id, 'subscription_billing_cycle', $billing_cycle);
        update_user_meta($user_id, 'subscription_start_date', current_time('mysql'));
        
        // Log successful conversion with detailed info
        // Always log successful billing for production monitoring
        
        return [
            'success' => true, 
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ];
        
    } catch (Exception $e) {
        
        // Check for specific test/live mode mismatch error
        if (strpos($e->getMessage(), 'similar object exists in live mode, but a test mode key was used') !== false) {
            $mode = sud_is_test_mode() ? 'test' : 'live';
            return ['success' => false, 'error' => "Price ID exists in live mode but you're using test mode. Check your Stripe mode settings and price configuration."];
        }
        
        if (strpos($e->getMessage(), 'similar object exists in test mode, but a live mode key was used') !== false) {
            return ['success' => false, 'error' => "Price ID exists in test mode but you're using live mode. Check your Stripe mode settings and price configuration."];
        }
        
        // Extract decline code for retry logic, fallback to user-friendly message
        require_once(dirname(__FILE__) . '/payment-functions.php');
        
        $decline_code = '';
        if ($e instanceof \Stripe\Exception\CardException) {
            $decline_code = $e->getDeclineCode();
        }
        
        // Use decline code for retry decisions, but log user-friendly message for debugging
        $user_friendly_error = sud_get_user_friendly_stripe_error($e);
        $error_for_retry = $decline_code ?: $user_friendly_error;
        
        // Log both for debugging
        sud_safe_log("STRIPE BILLING: CardException - Decline Code: {$decline_code}, User Message: {$user_friendly_error}");
        
        return ['success' => false, 'error' => $error_for_retry];
    }
}

// TODO: PayPal integration will be added later when needed

/**
 * Convert trial to paid subscription
 * 
 * @param int $user_id
 * @param string $plan_id
 * @param array $billing_result
 */
function sud_convert_trial_to_subscription($user_id, $plan_id, $billing_result) {
    $plans = SUD_PREMIUM_PLANS;
    $plan = $plans[$plan_id] ?? null;
    
    if (!$plan) {
        return;
    }
    
    // Calculate next billing date using secure payment data
    $secure_payment = get_user_meta($user_id, 'sud_trial_secure_payment', true);
    $billing_cycle = $secure_payment['billing_cycle'] ?? 'monthly';
    $next_billing = $billing_cycle === 'annual' 
        ? date('Y-m-d H:i:s', strtotime('+1 year'))
        : date('Y-m-d H:i:s', strtotime('+1 month'));
    
    // Update user subscription
    update_user_meta($user_id, 'premium_plan', $plan_id);
    update_user_meta($user_id, 'subscription_expires', $next_billing);
    update_user_meta($user_id, 'subscription_auto_renew', '1');
    update_user_meta($user_id, 'payment_method', 'stripe'); // Only Stripe for now
    
    // Clean up trial data
    delete_user_meta($user_id, 'sud_trial_plan');
    delete_user_meta($user_id, 'sud_trial_start');
    delete_user_meta($user_id, 'sud_trial_end');
    delete_user_meta($user_id, 'sud_trial_payment_details'); // Legacy insecure data
    
    // Keep secure payment data for renewals, just mark trial as converted
    update_user_meta($user_id, 'sud_trial_converted', 1);
    
    // Store subscription details from billing result
    if (isset($billing_result['subscription_id'])) {
        update_user_meta($user_id, 'subscription_id', $billing_result['subscription_id']);
    }
    
    // Create transaction record for Premium Payments tab
    global $wpdb;
    $transactions_table = $wpdb->prefix . 'sud_transactions';
    
    // Check if transactions table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$transactions_table'") == $transactions_table) {
        // Get plan pricing
        $amount = $billing_cycle === 'annual' ? 
            ($plan['price_annually'] ?? $plan['annual_price'] ?? 0) : 
            ($plan['price_monthly'] ?? $plan['monthly_price'] ?? 0);
            
        // Create transaction record
        $description = "Premium " . ucfirst($plan_id) . " Subscription (converted from trial)";
        
        $wpdb->insert(
            $transactions_table,
            [
                'user_id' => $user_id,
                'description' => $description,
                'amount' => $amount,
                'payment_method' => 'stripe',
                'transaction_id' => $billing_result['subscription_id'] ?? '',
                'status' => 'completed',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%f', '%s', '%s', '%s', '%s']
        );
        
        if ($wpdb->last_error) {
            error_log("TRIAL CONVERSION: Failed to create transaction record for user {$user_id}: " . $wpdb->last_error);
        } else {
            error_log("TRIAL CONVERSION: Created transaction record for user {$user_id} - {$plan_id} subscription");
        }
    }
}

/**
 * Downgrade expired trial to free plan
 * 
 * @param int $user_id
 * @param string $reason
 */
function sud_downgrade_expired_trial($user_id, $reason = '') {
    // Get current trial plan to check for auto-verification removal
    $trial_plan = get_user_meta($user_id, 'sud_trial_plan', true);
    
    // Revert to free plan
    update_user_meta($user_id, 'premium_plan', 'free');
    delete_user_meta($user_id, 'subscription_expires');
    delete_user_meta($user_id, 'subscription_auto_renew');
    
    // Handle auto-verification removal for Diamond trial users
    if ($trial_plan === 'diamond' && function_exists('sud_handle_plan_verification_update')) {
        sud_handle_plan_verification_update($user_id, 'free', $trial_plan);
    }
    
    // Clean up trial data
    delete_user_meta($user_id, 'sud_trial_plan');
    delete_user_meta($user_id, 'sud_trial_start');
    delete_user_meta($user_id, 'sud_trial_end');
    delete_user_meta($user_id, 'sud_trial_payment_details'); // Legacy insecure data
    
    // Keep secure payment data for potential future resubscription
    // Only clear on GDPR delete or explicit "remove card" action
    update_user_meta($user_id, 'sud_trial_downgraded', 1);
    
    // Log the downgrade reason
    update_user_meta($user_id, 'sud_trial_downgrade_reason', $reason);
    update_user_meta($user_id, 'sud_trial_downgrade_date', current_time('mysql'));
}

/**
 * Send trial conversion confirmation email
 * 
 * @param int $user_id
 * @param string $plan_id
 */
function sud_send_trial_conversion_email($user_id, $plan_id) {
    $user = get_userdata($user_id);
    if (!$user) return;
    
    // Get plan config for correct trial duration
    require_once(dirname(__FILE__) . '/pricing-config.php');
    $plan_config = sud_get_plan_details($plan_id);
    
    $plan_name = ucfirst($plan_id);
    $trial_days = $plan_config['free_trial_days'] ?? 3;
    $subject = "Your {$plan_name} subscription is now active";
    
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <p>Hi {$user->display_name},</p>
    
    <p>Your {$trial_days}-day free trial has ended and your {$plan_name} subscription is now active.</p>
    
    <p>You'll continue to enjoy all premium features without interruption.</p>
    
    <p>Thank you for choosing " . SUD_SITE_NAME . "!</p>
    
    <p>Best regards,<br>
    The " . SUD_SITE_NAME . " Team</p>
    </body>
    </html>
    ";
    
    wp_mail($user->user_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
}


/**
 * Send trial billing failure email
 * 
 * @param int $user_id
 * @param string $plan_id
 * @param string $error
 */
function sud_send_trial_billing_failure_email($user_id, $plan_id, $error) {
    $user = get_userdata($user_id);
    if (!$user) {
        error_log("SUD TRIAL EMAIL: Cannot send billing failure email - user not found: $user_id");
        return false;
    }
    
    // Get plan config for correct trial duration
    require_once(dirname(__FILE__) . '/pricing-config.php');
    $plan_config = sud_get_plan_details($plan_id);
    
    $plan_name = ucfirst($plan_id);
    $trial_days = $plan_config['free_trial_days'] ?? 3;
    $subject = "Action required: Update your payment method";
    
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <p>Hi {$user->display_name},</p>
    
    <p>Your {$trial_days}-day free trial for {$plan_name} has ended, but we were unable to process your payment.</p>
    
    <p>Your account has been temporarily downgraded to our free plan. To reactivate your {$plan_name} subscription, please:</p>
    
    <ol>
        <li>Visit your subscription settings: <a href='" . SUD_URL . "/pages/subscription'>" . SUD_URL . "/pages/subscription</a></li>
        <li>Update your payment method</li>
        <li>Resubscribe to {$plan_name}</li>
    </ol>
    
    <p>If you have any questions, please contact our support team.</p>
    
    <p>Best regards,<br>
    The " . SUD_SITE_NAME . " Team</p>
    </body>
    </html>
    ";
    
    $email_sent = wp_mail($user->user_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    
    if ($email_sent) {
        error_log("SUD TRIAL EMAIL: ✅ Billing failure email sent to {$user->user_email}");
    } else {
        error_log("SUD TRIAL EMAIL: ❌ Failed to send billing failure email to {$user->user_email}");
    }
    
    return $email_sent;
}

/**
 * Send trial expiry email
 * 
 * @param int $user_id
 * @param string $plan_id
 */
function sud_send_trial_expiry_email($user_id, $plan_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        error_log("SUD TRIAL EMAIL: Cannot send trial expiry email - user not found: $user_id");
        return false;
    }
    
    // Get plan config for correct trial duration
    require_once(dirname(__FILE__) . '/pricing-config.php');
    $plan_config = sud_get_plan_details($plan_id);
    
    $plan_name = ucfirst($plan_id);
    $trial_days = $plan_config['free_trial_days'] ?? 3;
    $subject = "Your {$plan_name} trial has ended";
    
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <p>Hi {$user->display_name},</p>
    
    <p>Your {$trial_days}-day free trial for {$plan_name} has ended.</p>
    
    <p>To continue enjoying premium features, you can subscribe to {$plan_name} at any time:</p>
    
    <p><a href='" . SUD_URL . "/pages/premium' style='color: #007cba; text-decoration: none;'>Visit: " . SUD_URL . "/pages/premium</a></p>
    
    <p>Thank you for trying " . SUD_SITE_NAME . "!</p>
    
    <p>Best regards,<br>
    The " . SUD_SITE_NAME . " Team</p>
    </body>
    </html>
    ";
    
    $email_sent = wp_mail($user->user_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    
    if ($email_sent) {
        error_log("SUD TRIAL EMAIL: ✅ Trial expiry email sent to {$user->user_email}");
    } else {
        error_log("SUD TRIAL EMAIL: ❌ Failed to send trial expiry email to {$user->user_email}");
    }
    
    return $email_sent;
}

/**
 * Check if billing should be retried based on error type
 * 
 * @param int $user_id
 * @param string $error
 * @return bool
 */
function sud_should_retry_billing($user_id, $error) {
    // Check retry count
    $retry_count = get_user_meta($user_id, 'sud_billing_retry_count', true) ?: 0;
    if ($retry_count >= 3) {
        return false; // Max retries reached
    }
    
    // Retryable error patterns (use regex for more accurate matching)
    $retryable_patterns = [
        '/insufficient[\s_]*funds/i', // Matches "insufficient funds" or "insufficient_funds"
        '/rate_limit/i', 
        '/connection_error/i',
        '/processing_error/i',
        '/authentication_required/i',
        '/try[\s_]*again[\s_]*later/i', // Matches "try_again_later" or "try again later"
        '/issuer_declined/i', // Temporary bank issues
        '/temporarily_unavailable/i',
        '/do_not_honor/i', // Common decline code
        '/payment_intent_missing/i' // Edge case: PI creation failed
    ];
    
    foreach ($retryable_patterns as $pattern) {
        if (preg_match($pattern, $error)) {
            return true;
        }
    }
    
    return false; // Non-retryable error
}

/**
 * Queue billing retry for later processing
 * 
 * @param int $user_id
 * @param array $trial_data
 * @param string $error
 */
function sud_queue_billing_retry($user_id, $trial_data, $error) {
    $retry_count = get_user_meta($user_id, 'sud_billing_retry_count', true) ?: 0;
    $retry_count++;
    
    // Exponential backoff: 2h, 6h (reduced to 2 retries)
    $retry_delays = [2, 6, 24]; // hours - 2h, 6h, 24h
    $delay_hours = $retry_delays[$retry_count - 1] ?? 24;
    $next_retry = date('Y-m-d H:i:s', strtotime("+{$delay_hours} hours"));
    
    // Store retry metadata
    update_user_meta($user_id, 'sud_billing_retry_count', $retry_count);
    update_user_meta($user_id, 'sud_billing_next_retry', $next_retry);
    update_user_meta($user_id, 'sud_billing_last_error', $error);
    update_user_meta($user_id, 'sud_billing_retry_data', $trial_data);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("SUD BILLING RETRY: Scheduled retry #{$retry_count} for user {$user_id} at {$next_retry}");
    }
}

/**
 * Process billing retries (call this from a separate cron job)
 */
function sud_process_billing_retries() {
    // Use separate mutex for retries to avoid starving retry processing
    if (get_transient('sud_billing_retry_lock')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SUD BILLING RETRY: Another retry process is running, skipping");
        }
        return;
    }
    set_transient('sud_billing_retry_lock', 1, 5 * MINUTE_IN_SECONDS);
    
    try {
        // Get users with pending retries
        $users_to_retry = get_users([
            'meta_key' => 'sud_billing_next_retry',
            'meta_compare' => '<=',
            'meta_value' => current_time('mysql'),
            'meta_type' => 'DATETIME',
            'fields' => ['ID'],
            'number' => 100, // Process in batches
        ]);
        
        if (empty($users_to_retry)) {
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SUD BILLING RETRY: Processing " . count($users_to_retry) . " billing retries");
        }
    
    foreach ($users_to_retry as $user) {
        $user_id = $user->ID;
        $trial_data = get_user_meta($user_id, 'sud_billing_retry_data', true);
        $secure_payment = get_user_meta($user_id, 'sud_trial_secure_payment', true);
        
        if (!$trial_data || !$secure_payment) {
            // Missing data, clean up and skip
            sud_clean_retry_metadata($user_id);
            continue;
        }
        
        // Attempt billing again
        $billing_result = sud_attempt_trial_billing($user_id, $trial_data['plan'], $secure_payment);
        
        // CRITICAL: Extra validation to prevent false conversions in retries
        if ($billing_result['success'] && 
            isset($billing_result['subscription_id']) && !empty($billing_result['subscription_id']) &&
            isset($billing_result['status']) && in_array($billing_result['status'], ['active', 'trialing'])) {
            // Success! Convert to subscription
            sud_convert_trial_to_subscription($user_id, $trial_data['plan'], $billing_result);
            sud_send_trial_conversion_email($user_id, $trial_data['plan']);
            sud_clean_retry_metadata($user_id);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("SUD BILLING RETRY: ✅ Retry successful for user {$user_id} - Subscription ID: {$billing_result['subscription_id']}");
            }
        } else {
            // Still failed - check if we should retry again or give up
            if (sud_should_retry_billing($user_id, $billing_result['error'])) {
                // Queue another retry
                sud_queue_billing_retry($user_id, $trial_data, $billing_result['error']);
            } else {
                // Max retries reached or non-retryable error - downgrade
                sud_downgrade_expired_trial($user_id, $billing_result['error']);
                sud_send_trial_billing_failure_email($user_id, $trial_data['plan'], $billing_result['error']);
                sud_clean_retry_metadata($user_id);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("SUD BILLING RETRY: ❌ Final retry failed for user {$user_id}: " . $billing_result['error']);
                }
            }
        }
        }
    } finally {
        // Clean up retry mutex lock - always runs even if PHP fatals
        delete_transient('sud_billing_retry_lock');
    }
}

/**
 * Clean up retry metadata
 * 
 * @param int $user_id
 */
function sud_clean_retry_metadata($user_id) {
    delete_user_meta($user_id, 'sud_billing_retry_count');
    delete_user_meta($user_id, 'sud_billing_next_retry');
    delete_user_meta($user_id, 'sud_billing_last_error');
    delete_user_meta($user_id, 'sud_billing_retry_data');
}

/**
 * Check if verbose logging is enabled
 */
function sud_is_verbose_logging_enabled() {
    return defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && 
           (defined('SUD_VERBOSE_LOGGING') && SUD_VERBOSE_LOGGING || 
            get_option('sud_verbose_logging', false));
}

/**
 * Safe error logging with PII sanitization
 */
function sud_safe_log($message) {
    // Sanitize potential PII (emails, card data patterns)
    $sanitized = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/i', '[EMAIL]', $message);
    $sanitized = preg_replace('/\b[0-9]{4}[-\s]?[0-9]{4}[-\s]?[0-9]{4}[-\s]?[0-9]{4,7}\b/', '[CARD]', $sanitized); // 13-19 digits
    $sanitized = preg_replace('/\b3[47][0-9]{13}\b/', '[AMEX]', $sanitized); // AmEx 15 digits
    
    // Use sanitize_text_field() if available, fallback for CLI context
    if (function_exists('sanitize_text_field')) {
        $sanitized = sanitize_text_field($sanitized);
    } else {
        // Fallback for CLI/early WordPress context
        $sanitized = htmlspecialchars(strip_tags($sanitized), ENT_QUOTES, 'UTF-8');
    }
    
    error_log($sanitized);
}

/**
 * Enhanced logging wrapper for production monitoring
 */
function sud_log_trial_system_health() {
    global $wpdb;
    
    // Count active trials (using UTC for timezone safety)
    $active_trials = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->usermeta} 
        WHERE meta_key = 'sud_trial_end' 
        AND meta_value > UTC_TIMESTAMP()
        AND meta_value != ''
    ");
    
    // Count expired trials (not yet processed)
    $expired_trials = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->usermeta} 
        WHERE meta_key = 'sud_trial_end' 
        AND meta_value < UTC_TIMESTAMP()
        AND meta_value != ''
    ");
    
    // Count retry queue
    $retry_queue = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->usermeta} 
        WHERE meta_key = 'sud_billing_next_retry'
        AND meta_value <= UTC_TIMESTAMP()
        AND meta_value != ''
    ");
    
    // Only log health stats in verbose mode, always log warnings
    if (sud_is_verbose_logging_enabled()) {
        sud_safe_log("SUD TRIAL SYSTEM HEALTH: Active: {$active_trials}, Expired: {$expired_trials}, Retry Queue: {$retry_queue}");
    }
    
    if ($expired_trials > 0) {
        sud_safe_log("SUD TRIAL SYSTEM: WARNING - {$expired_trials} expired trials need processing");
    }
    
    if ($retry_queue > 0) {
        sud_safe_log("SUD TRIAL SYSTEM: INFO - {$retry_queue} billing retries pending");
    }
}

/**
 * Cleanup stale PaymentIntents that are stuck in requires_capture status
 * Prevents users from hitting authorization limits due to abandoned flows
 */
function sud_cleanup_stale_payment_intents() {
    // Prevent concurrent execution with mutex lock
    if (get_transient('sud_stale_cleanup_lock')) {
        error_log('SUD STALE CLEANUP: Another process is already running, skipping');
        return;
    }
    set_transient('sud_stale_cleanup_lock', 1, 10 * MINUTE_IN_SECONDS);
    
    // Get users with pending trial payment intents older than 15 minutes (MySQL does the filtering)
    $fifteen_minutes_ago = time() - (15 * 60);
    $stale_users = get_users([
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'pending_trial_payment_intent',
                'compare' => 'EXISTS'
            ],
            [
                'key' => 'pending_trial_created',
                'value' => $fifteen_minutes_ago,
                'compare' => '<',
                'type' => 'NUMERIC'
            ]
        ],
        'fields' => ['ID'],
        'number' => 100 // Process in batches
    ]);
    
    if (empty($stale_users)) {
        return;
    }
    
    try {
        // Initialize Stripe
        require_once(dirname(__FILE__, 2) . '/vendor/stripe/stripe-php/init.php');
        require_once(dirname(__FILE__) . '/payment-functions.php');
        
        $secret_key = sud_get_stripe_secret_key();
        if (empty($secret_key)) {
            error_log('STALE PI CLEANUP: Stripe API key not configured');
            return;
        }
        \Stripe\Stripe::setApiKey($secret_key);
        
        $cleanup_count = 0;
        
        foreach ($stale_users as $user) {
            $user_id = $user->ID;
            $payment_intent_id = get_user_meta($user_id, 'pending_trial_payment_intent', true);
            
            if (empty($payment_intent_id)) {
                continue;
            }
            
            try {
                // PaymentIntent is already confirmed stale by MySQL query
                $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
                
                // Refund stale charge if it succeeded but wasn't completed
                if ($payment_intent->status === 'succeeded') {
                    // Check if already refunded
                    $refunds = \Stripe\Refund::all(['payment_intent' => $payment_intent_id, 'limit' => 1]);
                    if (empty($refunds->data)) {
                        // No refund exists, create one
                        $plan_id = get_user_meta($user_id, 'pending_trial_plan', true);
                        \Stripe\Refund::create([
                            'payment_intent' => $payment_intent_id,
                            'reason' => 'requested_by_customer',
                            'metadata' => [
                                'type' => 'stale_cleanup_refund',
                                'user_id' => $user_id,
                                'plan_id' => $plan_id ?: 'unknown'
                            ]
                        ]);
                        $cleanup_count++;
                        error_log("STALE PI CLEANUP: Refunded stale $5.00 PaymentIntent {$payment_intent_id} for user {$user_id}");
                    }
                }
                
                // Clean up stale meta regardless of PI status
                $plan_id = get_user_meta($user_id, 'pending_trial_plan', true);
                $created_timestamp = get_user_meta($user_id, 'pending_trial_created', true);
                
                delete_user_meta($user_id, 'pending_trial_payment_intent');
                delete_user_meta($user_id, 'pending_trial_plan');
                delete_user_meta($user_id, 'pending_trial_created');
                delete_user_meta($user_id, 'trial_completion_status');
                
                // Clean up idempotency key for stale PIs (20+ min TTL for auth voiding)
                if ($plan_id && $created_timestamp) {
                    $twenty_minutes_ago = time() - (20 * 60);
                    if ($created_timestamp < $twenty_minutes_ago) {
                        delete_user_meta($user_id, 'trial_idempotency_key_' . $plan_id);
                        error_log("STALE PI CLEANUP: Cleared idempotency key for user {$user_id}, plan {$plan_id} (20+ min TTL)");
                    }
                }
                
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // PaymentIntent doesn't exist, clean up meta
                delete_user_meta($user_id, 'pending_trial_payment_intent');
                delete_user_meta($user_id, 'pending_trial_plan');
                delete_user_meta($user_id, 'pending_trial_created');
                delete_user_meta($user_id, 'trial_completion_status');
            } catch (Exception $e) {
                error_log("STALE PI CLEANUP: Error processing user {$user_id}: " . $e->getMessage());
            }
        }
        
        if ($cleanup_count > 0) {
            error_log("STALE PI CLEANUP: Cancelled {$cleanup_count} stale PaymentIntents");
        }
        
    } catch (Exception $e) {
        error_log('STALE PI CLEANUP: General error: ' . $e->getMessage());
    } finally {
        // Clean up mutex lock - always runs even if PHP fatals
        delete_transient('sud_stale_cleanup_lock');
    }
}

// Hook the functions to WordPress cron events  
add_action('sud_check_trial_expiry', 'sud_log_trial_system_health', 5); // Priority 5 = runs before main function
add_action('sud_check_trial_expiry', 'sud_process_expired_trials');
add_action('sud_process_billing_retries', 'sud_process_billing_retries');