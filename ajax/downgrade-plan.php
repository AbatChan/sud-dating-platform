<?php
/**
 * AJAX endpoint for downgrading to free plan
 */

try {
    require_once(dirname(__FILE__, 3) . '/wp-load.php');
    require_once(dirname(__FILE__, 2) . '/includes/pricing-config.php');
} catch (Exception $e) {
    http_response_code(500);
    die('Failed to load required files: ' . $e->getMessage());
}

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_die('You must be logged in to downgrade your plan.');
}

// Verify nonce
if (!wp_verify_nonce($_GET['nonce'] ?? '', 'sud_downgrade')) {
    wp_die('Security check failed.');
}

$user_id = get_current_user_id();

// Cancel any active trial but preserve trial usage history
$active_trial = sud_get_active_trial($user_id);
if ($active_trial) {
    // Send trial cancellation email before removing trial data
    if (function_exists('send_trial_cancellation_email')) {
        $trial_data = [
            'plan' => $active_trial['plan'],
            'cancelled_date' => current_time('mysql'),
            'end' => $active_trial['end']
        ];
        send_trial_cancellation_email($user_id, $trial_data);
    }
    
    // Don't use sud_cancel_trial() as it deletes trial usage history
    // Just remove active trial metadata but keep 'sud_used_trials'
    delete_user_meta($user_id, 'sud_trial_plan');
    delete_user_meta($user_id, 'sud_trial_start');
    delete_user_meta($user_id, 'sud_trial_end');
    delete_user_meta($user_id, 'sud_trial_payment_details'); // Legacy insecure data
    delete_user_meta($user_id, 'sud_trial_secure_payment'); // New secure data
}

// Set user to free plan
update_user_meta($user_id, 'premium_plan', 'free');
delete_user_meta($user_id, 'subscription_expires');

// Redirect back to premium page 
wp_redirect(SUD_URL . '/pages/premium');
exit;