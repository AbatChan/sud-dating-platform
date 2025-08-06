<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/payment-functions.php');

header('Content-Type: application/json');

$options = get_option('sud_payment_settings');
$test_mode = sud_is_test_mode();

$settings_to_send = [
    'test_mode' => $test_mode,
    'stripe_test_publishable_key' => isset($options['stripe_test_publishable_key']) ? trim($options['stripe_test_publishable_key']) : '',
    'stripe_live_publishable_key' => isset($options['stripe_live_publishable_key']) ? trim($options['stripe_live_publishable_key']) : '',
    'paypal_test_client_id' => isset($options['paypal_test_client_id']) ? trim($options['paypal_test_client_id']) : '',
    'paypal_live_client_id' => isset($options['paypal_live_client_id']) ? trim($options['paypal_live_client_id']) : '',
];

echo json_encode([
    'success' => true,
    'settings' => $settings_to_send
]);