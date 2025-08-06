<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');

header('Content-Type: application/json');

try {
    // Use centralized security verification for GET requests
    $moderator_id = sud_verify_ajax([
        'methods' => ['GET', 'POST'],
        'require_auth' => true,
        'require_nonce' => false, // No nonce required for moderator actions
        'rate_limit' => ['requests' => 100, 'window' => 60, 'action' => 'load_moderator_conversation']
    ]);
    
    // Check if user has moderator permissions
    if (!current_user_can('sud_manage_messages') && !current_user_can('manage_options')) {
        throw new Exception('Access denied: You do not have permission to access messaging dashboard.');
    }
    
    $user_id = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0;
    
    if ($user_id <= 0) {
        throw new Exception('Invalid user ID provided.');
    }
    
    
    global $wpdb;
    
    // Get full conversation involving this SUD user (both TO and FROM them)
    // This shows the complete conversation between the SUD account and real users
    $messages = $wpdb->get_results($wpdb->prepare("
        SELECT m.*, 
               s.display_name as sender_name,
               r.display_name as receiver_name
        FROM {$wpdb->prefix}sud_messages m
        LEFT JOIN {$wpdb->users} s ON m.sender_id = s.ID
        LEFT JOIN {$wpdb->users} r ON m.receiver_id = r.ID
        WHERE (m.sender_id = %d OR m.receiver_id = %d)
        ORDER BY m.timestamp ASC
        LIMIT 100
    ", $user_id, $user_id));
    
    
    if ($wpdb->last_error) {
        throw new Exception('Database error while loading conversation.');
    }
    
    // Format messages for frontend
    $formatted_messages = [];
    foreach ($messages as $msg) {
        $formatted_messages[] = [
            'id' => (int)$msg->id,
            'sender_id' => (int)$msg->sender_id,
            'receiver_id' => (int)$msg->receiver_id,
            'message' => stripslashes($msg->message),
            'timestamp' => $msg->timestamp,
            'sender_name' => $msg->sender_name ?: 'Unknown',
            'receiver_name' => $msg->receiver_name ?: 'Unknown',
            'is_read' => (bool)$msg->is_read
        ];
    }
    
    wp_send_json_success([
        'messages' => $formatted_messages,
        'impersonate_user_id' => $user_id,
        'total_messages' => count($formatted_messages)
    ]);
    
} catch (Exception $e) {
    sud_handle_ajax_error($e);
}
?>