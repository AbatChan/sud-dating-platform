<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/mailer.php');

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'You must be logged in.']);
    exit;
}

$user_id = get_current_user_id();
$paypal_sub_id = $input['subscription_id'] ?? null;
$plan_id = $input['plan_id'] ?? null;
$billing_cycle = $input['billing_cycle'] ?? 'monthly'; 

if (empty($paypal_sub_id) || empty($plan_id)) {
    wp_send_json_error(['message' => 'Invalid subscription data from PayPal.']);
    exit;
}

$end_date_obj = new DateTime(current_time('mysql', true));
if ($billing_cycle === 'annual') {
    $end_date_obj->modify('+1 year');
} else {
    $end_date_obj->modify('+1 month');
}
$end_date = $end_date_obj->format('Y-m-d H:i:s');

update_user_meta($user_id, 'premium_plan', $plan_id);
update_user_meta($user_id, 'subscription_id', $paypal_sub_id); 
update_user_meta($user_id, 'payment_method', 'paypal');
update_user_meta($user_id, 'subscription_start', current_time('mysql'));
update_user_meta($user_id, 'subscription_expires', $end_date);
update_user_meta($user_id, 'subscription_billing_type', $billing_cycle);
update_user_meta($user_id, 'subscription_auto_renew', '1');

// Get plan details for email
$all_plans = sud_get_all_plans_with_pricing();
$plan_details = $all_plans[$plan_id] ?? ['name' => 'Premium Plan'];
$plan_amount = ($billing_cycle === 'annual') ? 
    ($plan_details['price_annually'] ?? $plan_details['annual_price'] ?? 0) : 
    ($plan_details['price_monthly'] ?? $plan_details['monthly_price'] ?? 0);

// Send email notifications
$transaction_data = [
    'type' => 'subscription',
    'user_id' => $user_id,
    'amount' => number_format($plan_amount, 2),
    'item_name' => $plan_details['name'] . ' Plan (' . ucfirst($billing_cycle) . ')',
    'payment_method' => 'PayPal',
    'transaction_id' => $paypal_sub_id
];

send_payment_confirmation_email($user_id, $transaction_data);
send_admin_payment_notification($transaction_data);

wp_send_json_success(['message' => 'PayPal subscription activated!']);