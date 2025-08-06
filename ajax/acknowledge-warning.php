<?php
/**
 * AJAX handler for acknowledging warnings
 */

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ban-warning-system.php');

header('Content-Type: application/json');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = intval($input['user_id'] ?? 0);
$nonce = $input['nonce'] ?? '';

if (!wp_verify_nonce($nonce, 'acknowledge_warning')) {
    wp_send_json_error(['message' => 'Security check failed']);
    exit;
}

$current_user_id = get_current_user_id();
if ($user_id !== $current_user_id) {
    wp_send_json_error(['message' => 'Unauthorized']);
    exit;
}

$result = sud_acknowledge_warning($user_id);

if ($result) {
    wp_send_json_success(['message' => 'Warning acknowledged']);
} else {
    wp_send_json_error(['message' => 'Failed to acknowledge warning']);
}
?>