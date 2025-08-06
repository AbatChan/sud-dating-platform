<?php

require_once( dirname(__FILE__, 3) . '/wp-load.php' );
require_once( dirname(__FILE__, 2) . '/includes/messaging-functions.php' );
require_once( dirname(__FILE__, 2) . '/includes/notification-functions.php' );
require_once( dirname(__FILE__, 2) . '/includes/user-functions.php');

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in.']); 
        exit;
    }

    $user_id = get_current_user_id();
    $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'all'; 
    $client_requests_message_toast = isset($_GET['check_toast']) && $_GET['check_toast'] == '1';

    $response_data = [
        'success' => true,
        'unread_conversation_count' => 0,
        'unread_notification_count' => 0,
        'toast_data' => null 
    ];

    global $wpdb;
    $msg_table = $wpdb->prefix . 'sud_messages';
    $del_table = $wpdb->prefix . 'sud_deleted_messages';
    $notif_table = $wpdb->prefix . 'sud_notifications';

    $latest_message_processed_for_toast = false;

    if (($type === 'messages' || $type === 'all') && $client_requests_message_toast) {
        $message_counts = get_unread_message_counts($user_id);
        $response_data['unread_conversation_count'] = $message_counts['total_conversations'];

        if ($message_counts['total_conversations'] > 0) {
            $latest_unread_message = $wpdb->get_row($wpdb->prepare(
                "SELECT m.id, m.sender_id, m.message, m.timestamp
                FROM $msg_table m
                LEFT JOIN $del_table d ON m.id = d.message_id AND d.user_id = %d
                WHERE m.receiver_id = %d AND m.is_read = 0 AND d.id IS NULL
                ORDER BY m.timestamp DESC, m.id DESC
                LIMIT 1",
                $user_id, $user_id
            ));

            if ($latest_unread_message) {
                $last_toast_msg_id = isset($_SESSION['sud_last_toast_msg_id']) ? (int)$_SESSION['sud_last_toast_msg_id'] : 0;
                if ($latest_unread_message->id > $last_toast_msg_id) {
                    $sender_profile = get_user_profile_data($latest_unread_message->sender_id);
                    $message_content_for_toast = stripslashes($latest_unread_message->message);

                    $response_data['toast_data'] = [
                        'id' => 'msg_' . $latest_unread_message->id,
                        'sender_id' => $latest_unread_message->sender_id,
                        'sender_name' => $sender_profile['name'] ?? 'Someone',
                        'profile_pic' => $sender_profile['profile_pic'] ?? (defined('SUD_IMG_URL') ? SUD_IMG_URL . '/default-profile.jpg' : ''),
                        'message' => $message_content_for_toast,
                        'type' => 'message',
                        'timestamp' => strtotime($latest_unread_message->timestamp)
                    ];
                    $_SESSION['sud_last_toast_msg_id'] = $latest_unread_message->id;
                    $latest_message_processed_for_toast = true; 
                }
            }
        }
    }

    if (($type === 'notifications' || $type === 'all')) {
        if (function_exists('get_unread_notification_count')) {
            $response_data['unread_notification_count'] = get_unread_notification_count($user_id);
        }

        if ($response_data['unread_notification_count'] > 0) {
            $latest_unread_general_notif = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $notif_table
                 WHERE user_id = %d AND is_read = 0 AND type != 'message' -- Still exclude 'message' type notifications
                 ORDER BY timestamp DESC, id DESC
                 LIMIT 1",
                $user_id
            ));

            if ($latest_unread_general_notif) {
                $last_toast_general_notif_id = isset($_SESSION['sud_last_toast_general_notif_id']) ? (int)$_SESSION['sud_last_toast_general_notif_id'] : 0;
                $can_show_general_toast = ($latest_unread_general_notif->id > $last_toast_general_notif_id);

                if ($can_show_general_toast && $latest_message_processed_for_toast && $latest_unread_general_notif->type === 'gift') {
                    $general_notif_time = strtotime($latest_unread_general_notif->timestamp);
                    $message_toast_time = $response_data['toast_data']['timestamp'] ?? 0; 

                    if (strpos($response_data['toast_data']['message'], "SUD_GIFT::") === 0 && abs($general_notif_time - $message_toast_time) < 5) {
                        $can_show_general_toast = false;
                        $_SESSION['sud_last_toast_general_not_id'] = $latest_unread_general_notif->id; 
                    }
                }

                if ($response_data['toast_data'] !== null && $can_show_general_toast) {
                    $current_toast_time = $response_data['toast_data']['timestamp'] ?? 0;
                    $general_notif_time = strtotime($latest_unread_general_notif->timestamp);
                    if ($general_notif_time < $current_toast_time) { 
                        $can_show_general_toast = false;
                    }
                }

                if ($can_show_general_toast) {
                    // Use centralized notification display function for consistency
                    $notification_data = get_notification_display_data($latest_unread_general_notif, $user_id);
                    
                    $related_user_id = $notification_data['related_id'];
                    $related_user_name = 'Someone';
                    $related_user_pic = $notification_data['profile_pic'];

                    if ($related_user_id) {
                        $related_profile = get_user_profile_data($related_user_id);
                        if ($related_profile) {
                            $related_user_name = $related_profile['name'] ?? 'Someone';
                        }
                    }

                    $response_data['toast_data'] = [
                        'id' => 'notif_' . $latest_unread_general_notif->id,
                        'sender_id' => $related_user_id,
                        'sender_name' => $related_user_name,
                        'profile_pic' => $related_user_pic,
                        'avatar_html' => $notification_data['avatar_html'], // Add centralized avatar HTML
                        'message' => stripslashes($latest_unread_general_notif->content),
                        'type' => $latest_unread_general_notif->type,
                        'timestamp' => strtotime($latest_unread_general_notif->timestamp)
                    ];
                    $_SESSION['sud_last_toast_general_notif_id'] = $latest_unread_general_notif->id;
                }
            }
        }
    }
    wp_send_json_success($response_data);

} catch (Exception $e) {
    error_log("SUD check-updates Error: " . $e->getMessage());
    wp_send_json_error(['message' => 'Could not check for updates.'], 500);
}
?>