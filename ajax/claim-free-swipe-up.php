<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
require_once(dirname(__FILE__, 2) . '/includes/swipe-functions.php');

header('Content-Type: application/json');

try {
    // Use centralized security verification
    $user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_swipe_action_nonce',
        'rate_limit' => ['requests' => 10, 'window' => 60, 'action' => 'claim_swipe_up'] // 10 claims per minute
    ]);

    if (function_exists('sud_is_user_premium') && sud_is_user_premium($user_id)) {
        throw new Exception('This offer is for free members only');
    }

    $today_date = current_time('Y-m-d');
    $last_claim_date = get_user_meta($user_id, 'sud_free_swipe_up_claimed_date', true);

    // If this is just a check request, handle it first
    if (isset($_POST['check_only']) && $_POST['check_only']) {
        if ($last_claim_date === $today_date) {
            wp_send_json_error(['message' => 'You have already claimed your free Swipe Up for today']);
            exit;
        } else {
            wp_send_json_success(['message' => 'User can claim free swipe up today.']);
            exit;
        }
    }

    // For actual claim requests, throw exception if already claimed
    if ($last_claim_date === $today_date) {
        throw new Exception('You have already claimed your free Swipe Up for today');
    }

    $current_balance = (int) get_user_meta($user_id, 'sud_purchased_swipe_ups_balance', true);
    $new_balance = $current_balance + 1;

    update_user_meta($user_id, 'sud_purchased_swipe_ups_balance', $new_balance);
    update_user_meta($user_id, 'sud_free_swipe_up_claimed_date', $today_date);

    // Get balance using safe function call
    try {
        $final_balance = sud_get_user_swipe_up_balance($user_id);
    } catch (Exception $e) {
        $final_balance = $new_balance; // Fallback to the balance we just set
    }

    wp_send_json_success([
        'message' => 'Free Swipe Up claimed! You now have 1 Swipe Up to use.',
        'new_swipe_up_balance' => $final_balance
    ]);
    
} catch (Exception $e) {
    sud_handle_ajax_error($e);
}