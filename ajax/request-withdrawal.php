<?php

$wp_load_path = dirname(__FILE__, 3) . '/wp-load.php'; 
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {

    header('Content-Type: application/json');
    http_response_code(500); 
    echo json_encode(['success' => false, 'data' => ['message' => 'Critical Error: Cannot load site environment.']]);
    exit;
}

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');

header('Content-Type: application/json');

try {
    // Use centralized security verification
    $current_user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_request_withdrawal_action',
        'nonce_field' => 'sud_withdrawal_nonce',
        'rate_limit' => ['requests' => 5, 'window' => 3600, 'action' => 'withdrawal'] // 5 withdrawals per hour
    ]);

    // Get user plan details
    $premium_details = sud_get_user_current_plan_details($current_user_id);
    $user_plan_id = $premium_details['id'] ?? 'free';
    $minimum_withdrawal_usd = 50.00; // Set minimum to $50 for all withdrawals
    
    // Check withdrawal permission based on tier level
    $can_withdraw = false;
    if ($user_plan_id === 'gold' || $user_plan_id === 'diamond') {
        $can_withdraw = true;
    }

    if (!$can_withdraw) {
        if ($user_plan_id === 'free') {
            throw new Exception('Coin withdrawal is a premium feature. Please upgrade to premium.');
        } elseif ($user_plan_id === 'trial') {
            throw new Exception('Coin withdrawal is not available during trial. Please upgrade to Gold.');
        } else {
            throw new Exception('Your plan does not allow withdrawals. Please upgrade to Gold or higher.');
        }
    }

    $amount_coins = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
    $paypal_email = isset($_POST['paypal_email']) ? sanitize_email($_POST['paypal_email']) : '';

    $coin_to_usd_rate = defined('SUD_COIN_WITHDRAWAL_RATE_USD') ? SUD_COIN_WITHDRAWAL_RATE_USD : 0.10;
    $net_payout_usd = round($amount_coins * $coin_to_usd_rate, 2);

    if ($minimum_withdrawal_usd > 0 && $net_payout_usd < $minimum_withdrawal_usd) {
        throw new Exception(sprintf('Withdrawal amount of $%.2f is below the minimum of $%.2f', $net_payout_usd, $minimum_withdrawal_usd));
    }

    if ($amount_coins <= 0) {
        throw new Exception('Withdrawal amount must be positive');
    }
    
    $current_balance = (float) get_user_meta($current_user_id, 'coin_balance', true);
    if ($amount_coins > $current_balance) {
        throw new Exception('Insufficient balance (' . number_format($current_balance) . ')');
    }

    $destination = '';
    if ($method === 'paypal') {
        if (empty($paypal_email) || !is_email($paypal_email)) {
            throw new Exception('Invalid PayPal email');
        }
        $destination = $paypal_email;
    }
    elseif ($method === 'bank_transfer') {
        throw new Exception('Bank transfer unavailable');
    }
    else {
        throw new Exception('Invalid withdrawal method');
    }

    global $wpdb;
    $withdrawal_table = $wpdb->prefix . 'sud_withdrawals';

    $wpdb->query('START TRANSACTION');

    $new_balance = $current_balance - $amount_coins;
    $balance_updated = update_user_meta($current_user_id, 'coin_balance', $new_balance);

    if ($balance_updated === false) {
        $wpdb->query('ROLLBACK');
        error_log("Withdrawal Error: Failed to update balance for user $current_user_id.");
        throw new Exception('Failed to update balance');
    }

    $inserted = $wpdb->insert(
        $withdrawal_table,
        [
            'user_id' => $current_user_id,
            'amount_coins' => $amount_coins,
            'net_payout_usd' => $net_payout_usd,
            'currency' => 'USD',
            'method' => $method,
            'destination' => $destination,
            'status' => 'pending',
            'requested_at' => current_time('mysql', 1)
        ],
        [ '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ]
    );

    if ($inserted === false) {
        $wpdb->query('ROLLBACK');
        error_log("Withdrawal Error: Failed insert for user $current_user_id. DB Error: " . $wpdb->last_error);
        throw new Exception('Failed to record request');
    }

    $wpdb->query('COMMIT');

    wp_send_json_success([ 
        'message' => sprintf('Withdrawal request for %.0f Coins ($%.2f USD) submitted successfully!', $amount_coins, $net_payout_usd),
        'new_balance_raw' => $new_balance,
        'new_balance_formatted' => number_format($new_balance)
    ]);
    
} catch (Exception $e) {
    if (isset($wpdb) && $wpdb->ready) {
        $wpdb->query('ROLLBACK');
    }
    sud_handle_ajax_error($e);
}

?>