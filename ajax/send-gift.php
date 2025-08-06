<?php

$wp_load_path = dirname(__FILE__, 3) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    $wp_load_path = dirname(__FILE__, 4) . '/wp-load.php';
    if (!file_exists($wp_load_path)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not load WordPress environment.']);
        exit;
    }
}

require_once($wp_load_path);
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/database-setup.php'); 

global $wpdb; 
$gifts_table = $wpdb->prefix . 'sud_gifts';
$user_gifts_table = $wpdb->prefix . 'sud_user_gifts';
$messages_table = $wpdb->prefix . 'sud_messages'; 
$gifts_img_url = SUD_IMG_URL . '/gifts/'; 

try {
    // Use centralized security verification
    $sender_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_ajax_action',
        'rate_limit' => ['requests' => 5, 'window' => 60, 'action' => 'send_gift']
    ]);

    $receiver_id = SUD_AJAX_Security::validate_user_id($_POST['receiver_id'] ?? 0, false);
    $gift_id = isset($_POST['gift_id']) ? intval($_POST['gift_id']) : 0;

    if ($gift_id <= 0) {
        throw new InvalidArgumentException('Invalid gift ID');
    }

    // Check if users are blocked
    SUD_AJAX_Security::check_user_blocked($sender_id, $receiver_id);

    // Check message limits for free users (including gifts in the count)
    if (!sud_user_can_access_feature($sender_id, 'premium_messaging')) {
        $messages_table = $wpdb->prefix . 'sud_messages';
        $sent_message_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $messages_table 
             WHERE sender_id = %d AND receiver_id = %d",
            $sender_id, $receiver_id
        ));
        
        if ($sent_message_count >= 10) {
            throw new Exception('upgrade required: You\'ve reached your 10 free messages! Upgrade to Premium for unlimited gifts and messaging.');
        }
    }

    $selected_gift_db = $wpdb->get_row($wpdb->prepare(
        "SELECT name, cost, icon, image_filename FROM $gifts_table WHERE id = %d AND is_active = 1",
        $gift_id
    ));

    if (!$selected_gift_db || !isset($selected_gift_db->cost)) {
        error_log("SUD Send Gift Error: Gift ID {$gift_id} not found/inactive/invalid. Sender: {$sender_id}.");
        wp_send_json_error(['message' => 'This gift is currently unavailable.'], 404);
        exit;
    }
    $gift_cost = (int)$selected_gift_db->cost;
    $gift_name = $selected_gift_db->name;

    $gift_display_element = '🎁'; 
    if (!empty($selected_gift_db->image_filename)) {
        $safe_filename = sanitize_file_name($selected_gift_db->image_filename);
        if ($safe_filename === $selected_gift_db->image_filename) {
            $gift_display_element = $gifts_img_url . $safe_filename;
        } else {
            if (!empty($selected_gift_db->icon)) { $gift_display_element = $selected_gift_db->icon; }
        }
    } elseif (!empty($selected_gift_db->icon)) {
        $gift_display_element = $selected_gift_db->icon;
    }

    // Check for duplicate gifts (centralized rate limiting handles general limits)
    $duplicate_check = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $user_gifts_table 
         WHERE sender_id = %d AND receiver_id = %d AND gift_id = %d 
         AND timestamp > DATE_SUB(NOW(), INTERVAL 10 SECOND)",
        $sender_id, $receiver_id, $gift_id
    ));
    if ($duplicate_check > 0) {
        throw new Exception('You just sent this gift! Please wait a moment before sending the same gift again.');
    }

    $wpdb->query('START TRANSACTION');
    $success = true; 
    $message_object_for_response = null; 

    // ATOMIC BALANCE UPDATE - Fix for race condition vulnerability
    // This prevents multiple simultaneous requests from bypassing balance checks
    $usermeta_table = $wpdb->prefix . 'usermeta';
    $balance_update_result = $wpdb->query($wpdb->prepare(
        "UPDATE $usermeta_table 
         SET meta_value = CAST(meta_value AS SIGNED) - %d 
         WHERE user_id = %d 
         AND meta_key = 'coin_balance' 
         AND CAST(meta_value AS SIGNED) >= %d",
        $gift_cost, $sender_id, $gift_cost
    ));
    
    if ($balance_update_result === 0) {
        $wpdb->query('ROLLBACK');
        $current_balance = (int)get_user_meta($sender_id, 'coin_balance', true);
        wp_send_json_error([
            'message' => "Insufficient balance. You have {$current_balance} coins but need {$gift_cost} coins to send this gift.",
            'current_balance' => $current_balance,
            'required_balance' => $gift_cost,
            'action_needed' => 'purchase_coins'
        ], 400);
        exit;
    }

    // Get updated sender balance
    $new_sender_balance = (int)get_user_meta($sender_id, 'coin_balance', true);
    
    // Update receiver balance atomically
    if ($success) {
        $receiver_balance_update = $wpdb->query($wpdb->prepare(
            "UPDATE $usermeta_table 
             SET meta_value = CAST(meta_value AS SIGNED) + %d 
             WHERE user_id = %d 
             AND meta_key = 'coin_balance'",
            $gift_cost, $receiver_id
        ));
        
        if ($receiver_balance_update === 0) {
            // Create receiver balance if it doesn't exist
            add_user_meta($receiver_id, 'coin_balance', $gift_cost);
        }
    }

    if ($success) {
        $insert_gift_result = $wpdb->insert(
            $user_gifts_table,
            [
                'sender_id'   => $sender_id,
                'receiver_id' => $receiver_id,
                'gift_id'     => $gift_id,
                'cost_paid'   => $gift_cost,
                'timestamp'   => current_time('mysql', 1)
            ],
            ['%d', '%d', '%d', '%d', '%s']
        );
        if (!$insert_gift_result) {
            $success = false;
            error_log("SUD Send Gift DB Error: Failed gift insert. Sender: {$sender_id}. Err: " . $wpdb->last_error);
        }
    }

    if ($success) {
        if (function_exists('add_notification')) {
            $sender_name = get_user_full_name($sender_id);
            $notification_content = sprintf(
                "%s sent you a %s!",
                esc_html($sender_name),
                esc_html($gift_name)
            );
            add_notification($receiver_id, 'gift', $notification_content, $sender_id);
        }
    }

    if ($success) {
        if (function_exists('send_message')) {
            $coin_rate = defined('SUD_COIN_WITHDRAWAL_RATE_USD') ? SUD_COIN_WITHDRAWAL_RATE_USD : 0.10;
            $usd_value = round($gift_cost * $coin_rate, 2);

            $system_message_payload = sprintf(
                "SUD_GIFT::%d::%d::%d::%s::%s::%d::%.2f",
                $gift_id, $sender_id, $receiver_id, $gift_display_element, $gift_name, $gift_cost, $usd_value
            );

            $inserted_message_id = send_message($sender_id, $receiver_id, $system_message_payload);

            if ($inserted_message_id === false) {
                $success = false; 
                error_log("SUD Send Gift DB Error: send_message function returned false for gift {$gift_id}. Rolling back.");
            } elseif (is_int($inserted_message_id) && $inserted_message_id > 0) {
                $created_message_db = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM $messages_table WHERE id = %d", 
                    $inserted_message_id
                ));
                if ($created_message_db) {
                    $time = strtotime($created_message_db->timestamp);
                    if ($time === false) $time = time();
                    $message_object_for_response = [
                        'id' => (int)$created_message_db->id,
                        'sender_id' => (int)$created_message_db->sender_id,
                        'receiver_id' => (int)$created_message_db->receiver_id,
                        'message' => stripslashes($created_message_db->message), 
                        'timestamp_unix' => $time,
                        'timestamp_formatted' => date('h:i A', $time),
                        'date_raw' => date('Y-m-d', $time),
                        'is_read' => false
                    ];
                } else {
                }
            } else {
                $success = false;
                error_log("SUD Send Gift DB Error: send_message returned unexpected value for gift {$gift_id}. Value: " . print_r($inserted_message_id, true) . ". Rolling back.");
            }
        } else {
            $success = false;
            error_log("SUD Send Gift DB Error: send_message function not found. Rolling back.");
        }
    }

    if ($success) {
        $wpdb->query('COMMIT');
        
        
        // Get receiver's new balance
        $new_receiver_balance = (int)get_user_meta($receiver_id, 'coin_balance', true);
        
        $response_payload = [
            'message' => 'Gift sent successfully!',
            'new_balance' => $new_sender_balance,
            'new_balance_formatted' => number_format($new_sender_balance),
            'receiver_new_balance' => $new_receiver_balance,
            'receiver_new_balance_formatted' => number_format($new_receiver_balance),
            'receiver_id' => $receiver_id
        ];

        if ($message_object_for_response !== null) {
            $response_payload['data']['message_object'] = $message_object_for_response;
        }
        wp_send_json_success($response_payload);
    } else { 
        $wpdb->query('ROLLBACK'); 
        wp_send_json_error(['message' => 'Could not complete gift transaction due to a server issue.'], 500);
    }
} catch (Exception $e) {
    if (isset($wpdb) && $wpdb->ready) { $wpdb->query('ROLLBACK'); }
    sud_handle_ajax_error($e);
}

?>