<?php

require_once(dirname(__FILE__, 3) . '/wp-load.php');
require_once(dirname(__FILE__, 2) . '/includes/config.php');

header('Content-Type: application/json');

if (!is_user_logged_in()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = get_current_user_id();

if (!function_exists('get_profile_completion_percentage')) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log("SUD Error: get_profile_completion_percentage function not found in get-completion.php");
    echo json_encode(['success' => false, 'message' => 'Server configuration error [GPC].']);
    exit;
}

$percentage = get_profile_completion_percentage($user_id);

echo json_encode(['success' => true, 'completion_percentage' => $percentage]);
exit;
?>