<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/location-functions.php'); 
require_once(dirname(__FILE__, 2) . '/includes/premium-functions.php'); 

try {
    // Use centralized security verification
    $sender_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_ajax_action',
        'rate_limit' => ['requests' => 10, 'window' => 60, 'action' => 'send_message']
    ]);

    $receiver_id = SUD_AJAX_Security::validate_user_id($_POST['receiver_id'] ?? 0, false);
    $message_text_raw = $_POST['message_text'] ?? '';

    // Check for duplicate messages within 5 seconds
    global $wpdb;
    $messages_table = $wpdb->prefix . 'sud_messages';
    $duplicate_check = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $messages_table 
         WHERE sender_id = %d AND receiver_id = %d AND message = %s 
         AND timestamp > DATE_SUB(NOW(), INTERVAL 5 SECOND)",
        $sender_id, $receiver_id, $message_text_raw
    ));
    if ($duplicate_check > 0) {
        throw new Exception('Duplicate message detected. Please wait before sending the same message again.');
    }
    
    // Check if users are matched and not blocked
    SUD_AJAX_Security::check_users_matched($sender_id, $receiver_id);
    SUD_AJAX_Security::check_user_blocked($sender_id, $receiver_id);

    // Check message limits for free users (10 messages before premium required)
    if (!sud_user_can_access_feature($sender_id, 'premium_messaging')) {
        $sent_message_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $messages_table 
             WHERE sender_id = %d AND receiver_id = %d",
            $sender_id, $receiver_id
        ));
        
        if ($sent_message_count >= 10) {
            throw new Exception('upgrade required: You\'ve reached your 10 free messages! Upgrade to Premium for unlimited messaging.');
        }
    }

    // Use centralized message validation and sanitization
    $message_text_sanitized = SUD_AJAX_Security::validate_message($message_text_raw);
    $message_id = send_message($sender_id, $receiver_id, $message_text_sanitized);

    if (!$message_id) {
        error_log("SUD Send Message Error: send_message function failed for sender {$sender_id} to receiver {$receiver_id}");
        throw new Exception('Failed to send message due to a server issue.');
    }

    global $wpdb; 
    $table_name = $wpdb->prefix . 'sud_messages'; 
    $inserted_message = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $message_id));

    if (!$inserted_message) {
        error_log("SUD Send Message Error: Failed to retrieve message ID {$message_id} after sending.");
        wp_send_json_success(['message_status' => 'Sent but could not retrieve full details.']);
    } else {
        $time = strtotime($inserted_message->timestamp);
        if ($time === false) $time = time();
        wp_send_json_success([
            'message' => [
                'id' => (int)$inserted_message->id,
                'sender_id' => (int)$inserted_message->sender_id,
                'receiver_id' => (int)$inserted_message->receiver_id,
                'message' => stripslashes($inserted_message->message), 
                'timestamp_unix' => $time,
                'timestamp_formatted' => date('h:i A', $time),
                'date_raw' => date('Y-m-d', $time),
                'is_read' => false 
            ]
        ]);
    }
} catch (Exception $e) {
    sud_handle_ajax_error($e);
}

?>