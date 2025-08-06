<?php

require_once(dirname(__FILE__, 3) . '/wp-load.php');
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php'); 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wp_send_json_error(['message' => 'Invalid request method.'], 405);
}

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Authentication required.'], 401);
}

if (empty($_POST['sud_settings_nonce']) || !wp_verify_nonce($_POST['sud_settings_nonce'], 'sud_update_settings_action')) {
    wp_send_json_error(['message' => 'Security check failed. Please refresh and try again.'], 403);
}

try {
    $user_id = get_current_user_id();

    $new_settings = [];
    $allowed_settings = ['email_notifications', 'message_notifications', 'favorite_notifications', 'view_notifications', 'match_notifications', 'hide_online_status'];

    foreach ($allowed_settings as $key) {
        $new_settings[$key] = isset($_POST[$key]);
    }

    if (function_exists('update_user_settings')) {
        $updated = update_user_settings($user_id, $new_settings);

        if ($updated) {
            wp_send_json_success(['message' => 'Settings saved successfully!']);
        }

        global $wpdb;
        if (!empty($wpdb->last_error)) {
            error_log('SUD Settings AJAX Save Error: ' . $wpdb->last_error);
            throw new Exception('Database error saving settings. Please contact support.');
        }

        wp_send_json_success(['message' => 'Settings saved (no changes detected).']);
    }

    throw new Exception('Server configuration error [UUSNF]');
    
} catch (Exception $e) {
    sud_handle_ajax_error($e);
} 
?>