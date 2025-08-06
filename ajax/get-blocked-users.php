<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }
    if (!is_user_logged_in()) {
        throw new Exception('User not logged in');
    }

    $current_user_id = get_current_user_id();

    if (!function_exists('get_blocked_users')) {
        error_log("SUD AJAX Error: get_blocked_users function is missing.");
        throw new Exception('Server error: Cannot retrieve blocked users.');
    }

    $blocked_users_list = get_blocked_users($current_user_id);
    
    wp_send_json_success([
        'blocked_users' => $blocked_users_list 
    ]);

} catch (Exception $e) {
    wp_send_json_error(['message' => $e->getMessage()], 500); 
}

exit;