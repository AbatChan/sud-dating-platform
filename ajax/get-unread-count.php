<?php
require_once(dirname(__FILE__, 2) . '/includes/ajax-bootstrap.php');

try {
    // Use centralized bootstrap for consistent setup
    $moderator_id = sud_ajax_bootstrap([
        'methods' => ['GET'],
        'require_auth' => true,
        'require_nonce' => false,
        'rate_limit' => ['requests' => 100, 'window' => 60, 'action' => 'get_unread_count']
    ]);
    
    // Check if user has moderator permissions
    if (!current_user_can('sud_manage_messages') && !current_user_can('manage_options')) {
        throw new Exception('Access denied: You do not have permission to check unread messages.');
    }
    
    global $wpdb;
    
    // Get count of unread messages sent TO SUD users
    $unread_count = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}sud_messages m
        INNER JOIN {$wpdb->users} recipient ON m.receiver_id = recipient.ID
        WHERE m.is_read = 0 
          AND recipient.user_status = 0
          AND (recipient.user_email LIKE '%@sud.com' OR recipient.user_email LIKE '%@swipeupdaddy.com')
    ");
    
    $unread_count = (int)$unread_count;
    
    // Use standardized success response
    sud_ajax_success([
        'unread_count' => $unread_count
    ]);
    
} catch (Exception $e) {
    sud_handle_ajax_error($e);
}
?>