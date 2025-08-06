<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');

header('Content-Type: application/json');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'User not logged in.']);
    exit;
}

if (!isset($_POST['target_user_id']) || !is_numeric($_POST['target_user_id'])) {
    wp_send_json_error(['message' => 'Invalid user ID.']);
    exit;
}

$current_user_id = get_current_user_id();
$target_user_id = intval($_POST['target_user_id']);

if ($current_user_id === $target_user_id) {
    wp_send_json_error(['message' => 'Cannot check match status with yourself.']);
    exit;
}

if (!function_exists('sud_are_users_matched')) {
    wp_send_json_error(['message' => 'Match checking function not available.']);
    exit;
}

$are_matched = sud_are_users_matched($current_user_id, $target_user_id);

wp_send_json_success([
    'are_matched' => $are_matched,
    'can_message' => $are_matched, // Same logic for now
    'target_user_id' => $target_user_id
]);
?>