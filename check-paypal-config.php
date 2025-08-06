<?php
// Quick PayPal configuration check
require_once('../wp-config.php');

echo "PayPal Configuration Check\n";
echo "========================\n\n";

$payment_settings = get_option('sud_payment_settings', []);

echo "Current settings found:\n";
if (empty($payment_settings)) {
    echo "❌ No sud_payment_settings found in database\n";
} else {
    echo "✅ sud_payment_settings exists\n";
    
    $test_mode = isset($payment_settings['test_mode']) ? (bool)$payment_settings['test_mode'] : false;
    echo "Test Mode: " . ($test_mode ? 'ON' : 'OFF') . "\n\n";
    
    // Check PayPal settings
    $paypal_fields = [
        'paypal_test_client_id' => 'PayPal Test Client ID',
        'paypal_test_secret' => 'PayPal Test Secret',
        'paypal_live_client_id' => 'PayPal Live Client ID', 
        'paypal_live_secret' => 'PayPal Live Secret'
    ];
    
    foreach ($paypal_fields as $field => $label) {
        $value = isset($payment_settings[$field]) ? $payment_settings[$field] : '';
        $status = !empty($value) ? '✅' : '❌';
        $display_value = !empty($value) ? (strlen($value) > 10 ? substr($value, 0, 10) . '...' : $value) : 'NOT SET';
        echo "$status $label: $display_value\n";
    }
    
    // Check current active client ID
    echo "\nActive Configuration:\n";
    $active_client_id = $test_mode 
        ? ($payment_settings['paypal_test_client_id'] ?? '') 
        : ($payment_settings['paypal_live_client_id'] ?? '');
    
    echo "Current PayPal Client ID: " . (!empty($active_client_id) ? substr($active_client_id, 0, 15) . '...' : 'NOT SET') . "\n";
    
    // Check if it's the default 'sb' value
    if ($active_client_id === 'sb' || empty($active_client_id)) {
        echo "⚠️  WARNING: Using default/empty client ID - this will cause PayPal errors\n";
    }
}

echo "\n";
?>