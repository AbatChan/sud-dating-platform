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
        'rate_limit' => ['requests' => 10, 'window' => 60, 'action' => 'send_moderator_gift']
    ]);
    
    // Check if user has moderator permissions
    if (!current_user_can('sud_send_gifts') && !current_user_can('manage_options')) {
        throw new Exception('Access denied: You do not have permission to send gifts.');
    }
    
    $conversation_user_id = intval($_POST['conversation_user_id'] ?? 0);
    $gift_id = intval($_POST['gift_id'] ?? 0);
    $gift_cost = intval($_POST['gift_cost'] ?? 0);
    
    
    if (!$conversation_user_id || !$gift_id || !$gift_cost) {
        throw new Exception('Invalid data - missing required gift parameters');
    }
    
    global $wpdb;
    
    // Find who sent messages TO this SUD user (so we can send gift to them)
    $recent_sender = $wpdb->get_row($wpdb->prepare("
        SELECT sender_id as target_user_id
        FROM {$wpdb->prefix}sud_messages 
        WHERE receiver_id = %d
        ORDER BY timestamp DESC 
        LIMIT 1
    ", $conversation_user_id));
    
    if (!$recent_sender) {
        throw new Exception('No messages found sent to this SUD user. Cannot send gift.');
    }
    
    // Gift is sent FROM the SUD user TO the person who messaged them
    $sender_id = $conversation_user_id; // SUD user sending the gift
    $receiver_id = $recent_sender->target_user_id; // Person who messaged the SUD user
    
    
    // Get gift details
    $gifts_table = $wpdb->prefix . 'sud_gifts';
    $gift = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$gifts_table} WHERE id = %d AND is_active = 1", $gift_id));
    
    if (!$gift) {
        throw new Exception('Gift not found or inactive');
    }
    
    // Verify cost matches database
    if ($gift->cost != $gift_cost) {
        throw new Exception('Gift cost mismatch - please refresh and try again');
    }
    
    // Check sender's balance
    $sender_balance = (int)get_user_meta($sender_id, 'coin_balance', true);
    
    
    if ($sender_balance < $gift_cost) {
        $sender_user = get_user_by('ID', $sender_id);
        $sender_name = $sender_user ? $sender_user->display_name : 'Unknown';
        $error_message = "Insufficient coins! {$sender_name} has {$sender_balance} coins but the gift costs {$gift_cost} coins.";
        throw new Exception($error_message);
    }
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    // Deduct from sender
    $new_sender_balance = $sender_balance - $gift_cost;
    $update_sender_balance = update_user_meta($sender_id, 'coin_balance', $new_sender_balance);
    
    // Add to receiver
    $receiver_balance = (int)get_user_meta($receiver_id, 'coin_balance', true);
    $new_receiver_balance = $receiver_balance + $gift_cost;
    $update_receiver_balance = update_user_meta($receiver_id, 'coin_balance', $new_receiver_balance);
    
    // Record gift transaction
    $user_gifts_table = $wpdb->prefix . 'sud_user_gifts';
    $insert_gift = $wpdb->insert(
        $user_gifts_table,
        [
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'gift_id' => $gift_id,
            'cost_paid' => $gift_cost,
            'timestamp' => current_time('mysql')
        ],
        ['%d', '%d', '%d', '%d', '%s']
    );
    
    if ($update_sender_balance !== false && $update_receiver_balance !== false && $insert_gift) {
        // Create gift message in SUD_GIFT format
        $coin_rate = defined('SUD_COIN_WITHDRAWAL_RATE_USD') ? SUD_COIN_WITHDRAWAL_RATE_USD : 0.10;
        $usd_value = round($gift_cost * $coin_rate, 2);
        
        $gift_image_url = '';
        if (!empty($gift->image_filename)) {
            $gift_image_url = (defined('SUD_IMG_URL') ? SUD_IMG_URL : '/wordpress/sud/assets/img') . '/gifts/' . sanitize_file_name($gift->image_filename);
        }
        
        $gift_message = sprintf(
            "SUD_GIFT::%d::%d::%d::%s::%s::%d::%.2f",
            $gift_id,
            $sender_id,
            $receiver_id,
            $gift_image_url,
            $gift->name,
            $gift_cost,
            $usd_value
        );
        
        // Insert gift message into messages table
        $messages_result = $wpdb->insert(
            $wpdb->prefix . 'sud_messages',
            [
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'message' => $gift_message,
                'timestamp' => current_time('mysql'),
                'is_read' => 0
            ],
            ['%d', '%d', '%s', '%s', '%d']
        );
        
        if ($messages_result) {
            // Mark all unread messages from the recipient as read (since moderator is responding)
            $mark_read_result = $wpdb->update(
                $wpdb->prefix . 'sud_messages',
                ['is_read' => 1],
                [
                    'sender_id' => $receiver_id,
                    'receiver_id' => $sender_id,
                    'is_read' => 0
                ],
                ['%d'],
                ['%d', '%d', '%d']
            );
            
            $wpdb->query('COMMIT');
            
            
            wp_send_json_success([
                'gift_id' => $gift_id,
                'gift_name' => $gift->name,
                'gift_cost' => $gift_cost,
                'sender_new_balance' => $new_sender_balance,
                'receiver_new_balance' => $new_receiver_balance,
                'messages_marked_read' => $mark_read_result,
                'message' => 'Gift sent successfully'
            ]);
        } else {
            $wpdb->query('ROLLBACK');
            throw new Exception('Failed to create gift message');
        }
    } else {
        $wpdb->query('ROLLBACK');
        throw new Exception('Failed to process gift transaction');
    }
    
} catch (Exception $e) {
    if (isset($wpdb)) {
        $wpdb->query('ROLLBACK');
    }
    
    // Return user-friendly error message
    wp_send_json_error([
        'message' => $e->getMessage(),
        'type' => 'gift_error'
    ]);
}
?>