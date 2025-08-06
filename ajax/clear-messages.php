<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');

header('Content-Type: application/json');

try {
    // Use centralized security verification
    $current_user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_clear_messages_nonce', // Keep existing nonce for compatibility
        'rate_limit' => ['requests' => 10, 'window' => 60, 'action' => 'clear_messages']
    ]);
    $other_user_id = SUD_AJAX_Security::validate_user_id($_POST['user_id'] ?? 0, false);

    if ($current_user_id == $other_user_id) {
        throw new Exception('Cannot clear messages with yourself');
    }

    global $wpdb;
    $messages_table = $wpdb->prefix . 'sud_messages';
    $deleted_messages_table = $wpdb->prefix . 'sud_deleted_messages'; 

    if (function_exists('create_deleted_messages_table')) {
        create_deleted_messages_table();
    }

    $message_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT m.id
         FROM $messages_table m
         LEFT JOIN $deleted_messages_table d ON m.id = d.message_id AND d.user_id = %d
         WHERE ((m.sender_id = %d AND m.receiver_id = %d) OR (m.sender_id = %d AND m.receiver_id = %d))
           AND d.id IS NULL",
        $current_user_id, $current_user_id, $other_user_id, $other_user_id, $current_user_id
    ));

    if (empty($message_ids)) {
        wp_send_json_success(['message' => 'No messages to clear for your view.']);
        exit;
    }

    $values = [];
    $placeholders = [];
    foreach ($message_ids as $message_id) {
        $values[] = $message_id;
        $values[] = $current_user_id;
        $placeholders[] = '(%d, %d)';
    }

    $sql = "INSERT IGNORE INTO $deleted_messages_table (message_id, user_id) VALUES " . implode(', ', $placeholders);
    $wpdb->query('START TRANSACTION');
    $result = $wpdb->query($wpdb->prepare($sql, $values));

    if ($result !== false) {
        $wpdb->query('COMMIT');
        wp_send_json_success(['message' => 'Your view of the conversation has been cleared.']);
    } else {
        $wpdb->query('ROLLBACK');
        throw new Exception('Database error while clearing messages.');
    }

} catch (Exception $e) {
    if (isset($wpdb) && $wpdb->dbh) $wpdb->query('ROLLBACK');
    sud_handle_ajax_error($e);
}
exit;