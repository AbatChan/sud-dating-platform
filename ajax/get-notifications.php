<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');

header('Content-Type: application/json');

try {
    if (!is_user_logged_in()) {
        throw new Exception('User not logged in');
    }
    $user_id = get_current_user_id();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? sanitize_key($_POST['action']) : ''; 

        switch ($action) {
            case 'mark_read':
                $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
                if ($notification_id) {
                    $result = mark_notification_read($notification_id);
                    echo json_encode([
                        'success' => $result,
                        'message' => $result ? 'Notification marked as read' : 'Failed to mark notification as read',
                        'unread_count' => get_unread_notification_count($user_id)
                    ]);
                    exit;
                }

                echo json_encode(['success' => false, 'message' => 'Invalid notification ID.', 'unread_count' => get_unread_notification_count($user_id)]);
                exit;
                break; 

            case 'mark_all_read':
                $result = mark_all_notifications_read($user_id);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'All notifications marked as read' : 'Failed to mark notifications as read',
                    'unread_count' => 0 
                ]);
                exit;
                break; 

            case 'delete_all':
                $result = delete_all_user_notifications($user_id);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'All notifications cleared successfully.' : 'Failed to clear notifications.',
                    'unread_count' => 0 
                ]);
                exit;
                break; 

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action requested.', 'unread_count' => get_unread_notification_count($user_id)]);
                exit;
        }
    }

    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : null;
    $mark_read_on_load = isset($_GET['mark_read']) ? filter_var($_GET['mark_read'], FILTER_VALIDATE_BOOLEAN) : false; 

    $notifications = get_user_notifications($user_id, $limit, $offset, $type);

    if ($mark_read_on_load && !empty($notifications)) {
        mark_all_notifications_read($user_id);
        $unread_count = 0;
    } else {
        $unread_count = get_unread_notification_count($user_id);
    }

    $can_view_profiles = sud_user_can_access_feature($user_id, 'viewed_profile');

    $formatted_notifications = [];
    foreach ($notifications as $notification) {
        // Use centralized notification display function
        $notification_data = get_notification_display_data($notification, $user_id);
        
        $formatted_notifications[] = [
            'id' => $notification_data['id'],
            'type' => $notification_data['type'],
            'content' => esc_html($notification_data['content']), 
            'related_id' => $notification_data['related_id'],
            'is_read' => $notification_data['is_read'],
            'timestamp' => $notification_data['timestamp'],
            'time_ago' => $notification_data['time_ago'],
            'profile_pic' => esc_url($notification_data['profile_pic']),
            'avatar_html' => $notification_data['avatar_html'], // Add centralized avatar HTML
            'is_premium_teaser' => $notification_data['is_premium_teaser'],
            'is_system_notification' => $notification_data['is_system_notification'],
            'upgrade_url' => $notification_data['upgrade_url']
        ];
    }

    global $wpdb;
    $total_notification_count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sud_notifications WHERE user_id = %d",
        $user_id
    ));

    echo json_encode([
        'success' => true,
        'notifications' => $formatted_notifications,
        'unread_count' => $unread_count,
        'total_count' => $total_notification_count, 
        'has_more' => count($notifications) == $limit,
        'can_view_profiles' => $can_view_profiles
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . esc_html($e->getMessage()), 
        'unread_count' => (isset($user_id) && $user_id) ? get_unread_notification_count($user_id) : 0 
    ]);
}
?>