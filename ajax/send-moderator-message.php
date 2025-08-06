<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');

header('Content-Type: application/json');

try {
    // Use centralized security verification for POST requests
    $moderator_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => false, // Skip nonce for moderator actions
        'rate_limit' => ['requests' => 50, 'window' => 60, 'action' => 'send_moderator_message']
    ]);
    
    // Check if user has moderator permissions
    if (!current_user_can('sud_manage_messages') && !current_user_can('manage_options')) {
        throw new Exception('Access denied: You do not have permission to send messages.');
    }
    
    $conversation_user_id = intval($_POST['user_id'] ?? 0);
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    
    
    if (!$conversation_user_id || !$message) {
        throw new Exception('Invalid data - missing user ID or message');
    }
    
    global $wpdb;
    
    // Find who sent messages TO this SUD user (so we can reply back to them)
    $recent_sender = $wpdb->get_row($wpdb->prepare("
        SELECT sender_id as target_user_id
        FROM {$wpdb->prefix}sud_messages 
        WHERE receiver_id = %d
        ORDER BY timestamp DESC 
        LIMIT 1
    ", $conversation_user_id));
    
    
    if (!$recent_sender) {
        throw new Exception('No messages found sent to this SUD user. Cannot reply.');
    }
    
    // Reply AS the SUD user TO the person who messaged them
    $impersonate_as_user_id = $conversation_user_id; // Reply AS the SUD user
    $send_to_user_id = $recent_sender->target_user_id; // Reply TO the person who messaged them
    
    
    // Send message AS the impersonated user TO their conversation partner
    $result = $wpdb->insert(
        $wpdb->prefix . 'sud_messages',
        [
            'sender_id' => $impersonate_as_user_id, // Send AS the user we're impersonating
            'receiver_id' => $send_to_user_id,      // Send TO their conversation partner
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'is_read' => 0
        ],
        ['%d', '%d', '%s', '%s', '%d']
    );
    
    if ($result) {
        $message_id = $wpdb->insert_id;
        
        // Mark all unread messages from the recipient as read (since moderator is responding)
        $mark_read_result = $wpdb->update(
            $wpdb->prefix . 'sud_messages',
            ['is_read' => 1],
            [
                'sender_id' => $send_to_user_id,
                'receiver_id' => $impersonate_as_user_id,
                'is_read' => 0
            ],
            ['%d'],
            ['%d', '%d', '%d']
        );
        
        
        wp_send_json_success([
            'message_id' => $message_id,
            'impersonated_as' => $impersonate_as_user_id,
            'sent_to' => $send_to_user_id,
            'messages_marked_read' => $mark_read_result,
            'message' => 'Message sent successfully'
        ]);
    } else {
        $error = $wpdb->last_error ?: 'Unknown database error';
        error_log("Chat Moderator DB Error: Failed to insert message - " . $error);
        throw new Exception('Failed to send message: ' . $error);
    }
    
} catch (Exception $e) {
    error_log("Chat Moderator Error: " . $e->getMessage());
    sud_handle_ajax_error($e);
}
?>