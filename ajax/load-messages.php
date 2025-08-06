<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');

header('Content-Type: application/json');

try {
    // Use centralized security verification for GET requests
    $user_id = sud_verify_ajax([
        'methods' => ['GET'],
        'require_auth' => true,
        'require_nonce' => false, // No nonce required for GET requests
        'rate_limit' => ['requests' => 100, 'window' => 60, 'action' => 'load_messages'] // Allow frequent message polling
    ]);
    $other_user_id = SUD_AJAX_Security::validate_user_id($_GET['user_id'] ?? 0, false);
    $before_message_id = isset($_GET['before_message_id']) ? intval($_GET['before_message_id']) : 0;
    $last_message_id = isset($_GET['last_message_id']) ? intval($_GET['last_message_id']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 30;

    if ($limit <= 0 || $limit > 100) $limit = 30;

    if ($user_id == $other_user_id) {
        throw new Exception('Cannot load messages with yourself');
    }

    global $wpdb;
    $msg_table = $wpdb->prefix . 'sud_messages';
    $del_table = $wpdb->prefix . 'sud_deleted_messages';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $msg_table)) != $msg_table) create_messages_table();
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $del_table)) != $del_table) create_deleted_messages_table();

    $params = array($user_id, $user_id, $other_user_id, $other_user_id, $user_id);

    $query = "
        SELECT m.*
        FROM $msg_table m
        LEFT JOIN $del_table d ON m.id = d.message_id AND d.user_id = %d
        WHERE
            ((m.sender_id = %d AND m.receiver_id = %d) OR (m.sender_id = %d AND m.receiver_id = %d))
          AND d.id IS NULL
    ";

    if ($before_message_id > 0) {
        $query .= " AND m.id < %d";
        $params[] = $before_message_id;
        $query .= " ORDER BY m.timestamp DESC, m.id DESC LIMIT %d";
        $params[] = $limit;
    } elseif ($last_message_id > 0) {
        $query .= " AND m.id > %d";
        $params[] = $last_message_id;
        $query .= " ORDER BY m.timestamp ASC, m.id ASC";
    } else { 
        $query .= " ORDER BY m.timestamp DESC, m.id DESC LIMIT %d"; 
        $params[] = $limit;
    }

    $prepared_query = $wpdb->prepare($query, $params);
    $messages = $wpdb->get_results($prepared_query);

    if ($before_message_id > 0 || $last_message_id == 0) {
        $messages = array_reverse($messages ?: []);
    }

    if (($last_message_id >= 0 || $last_message_id == 0) && $before_message_id == 0) {
        mark_messages_as_read($other_user_id, $user_id);
    }

    $has_more_older = false;
    if ($before_message_id > 0) {
        $oldest_fetched_id = !empty($messages) ? $messages[0]->id : $before_message_id;
        $more_params = array($user_id, $user_id, $other_user_id, $other_user_id, $user_id, $oldest_fetched_id);
        $more_check_query = "
            SELECT 1 FROM $msg_table m
            LEFT JOIN $del_table d ON m.id = d.message_id AND d.user_id = %d
            WHERE ((m.sender_id = %d AND m.receiver_id = %d) OR (m.sender_id = %d AND m.receiver_id = %d))
              AND d.id IS NULL AND m.id < %d
            LIMIT 1
        ";
        $more_check = $wpdb->get_var($wpdb->prepare($more_check_query, $more_params));
        $has_more_older = !empty($more_check);
    } elseif ($last_message_id == 0 && count($messages) === $limit) {
        $has_more_older = true;
    }

    $other_user_info = null;
    $other_user_profile = get_user_profile_data($other_user_id);
    if ($other_user_profile) {
        $other_user_info = [
           'id' => $other_user_profile['id'],
           'name' => $other_user_profile['name'] ?? 'User',
           'profile_pic' => $other_user_profile['profile_pic'] ?? (SUD_IMG_URL . '/default-profile.jpg'),
           'is_online' => $other_user_profile['is_online'] ?? false,
           'is_verified' => $other_user_profile['is_verified'] ?? false,
       ];
    }

    $formatted_messages = [];
    $seen_message_ids = []; 

    foreach ($messages as $msg) {
        if (in_array($msg->id, $seen_message_ids)) {
            continue;
        }
        $seen_message_ids[] = $msg->id;
        $message_text = stripslashes($msg->message);
        $time = strtotime($msg->timestamp);
        if ($time === false || $time <= 0) {
            $time = current_time('timestamp', true);
        }
        $formatted_messages[] = [
            'id' => (int)$msg->id,
            'sender_id' => (int)$msg->sender_id,
            'receiver_id' => (int)$msg->receiver_id,
            'message' => $message_text, 
            'timestamp_unix' => $time,
            'timestamp_formatted' => date('h:i A', $time), 
            'date_raw' => date('Y-m-d', $time), 
            'is_read' => (bool)$msg->is_read
        ];
    }

    $message_counts = get_unread_message_counts($user_id);
    
    // Include current user's balance for real-time updates
    $current_balance = (int)get_user_meta($user_id, 'coin_balance', true);

    wp_send_json_success([
        'messages' => $formatted_messages,
        'user' => $other_user_info,
        'has_more_older' => $has_more_older,
        'total_unread_message_count' => $message_counts['total_messages'], 
        'unread_conversation_count' => $message_counts['total_conversations'],
        'current_balance' => $current_balance,
        'current_balance_formatted' => number_format($current_balance)
    ]);

} catch (Exception $e) {
    sud_handle_ajax_error($e);
}

?>