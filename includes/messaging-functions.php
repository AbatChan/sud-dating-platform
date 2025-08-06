<?php

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/user-functions.php');

function get_user_messages($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_messages';

    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        create_messages_table();
    }

    $received_messages = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE receiver_id = %d ORDER BY timestamp DESC",
            $user_id
        ),
        ARRAY_A
    );

    $sent_messages = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE sender_id = %d ORDER BY timestamp DESC",
            $user_id
        ),
        ARRAY_A
    );

    $unread_count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE receiver_id = %d AND is_read = 0",
            $user_id
        )
    );

    return array(
        'received' => $received_messages ?: array(),
        'sent' => $sent_messages ?: array(),
        'unread_count' => $unread_count ?: 0
    );
}

function get_user_conversations($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_messages';

    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        create_messages_table();
    }

    $conversations_query = "
        SELECT 
            CASE 
                WHEN sender_id = %d THEN receiver_id
                ELSE sender_id
            END as other_user_id,
            MAX(timestamp) as latest_message_time
        FROM 
            $table_name
        WHERE 
            sender_id = %d OR receiver_id = %d
        GROUP BY 
            other_user_id
        ORDER BY 
            latest_message_time DESC
    ";

    $conversations = $wpdb->get_results(
        $wpdb->prepare(
            $conversations_query,
            $user_id, $user_id, $user_id
        )
    );

    $message_threads = array();

    if (!empty($conversations)) {
        foreach ($conversations as $convo) {
            $other_user_id = $convo->other_user_id;
            $other_user = get_userdata($other_user_id);

            if (!$other_user) {
                continue; 
            }

            $messages_query = "
                SELECT * FROM $table_name
                WHERE 
                    (sender_id = %d AND receiver_id = %d)
                    OR
                    (sender_id = %d AND receiver_id = %d)
                ORDER BY 
                    timestamp ASC
            ";

            $messages = $wpdb->get_results(
                $wpdb->prepare(
                    $messages_query,
                    $user_id, $other_user_id, $other_user_id, $user_id
                )
            );

            $unread_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name 
                     WHERE sender_id = %d AND receiver_id = %d AND is_read = 0",
                    $other_user_id, $user_id
                )
            );

            $premium_badge_html_sidebar = '';
            if (function_exists('sud_get_premium_badge_html')) {
                $premium_badge_html_sidebar = sud_get_premium_badge_html($other_user_id, 'xs');
            }

            $profile_picture_id = get_user_meta($other_user_id, 'profile_picture', true);
            $profile_pic = !empty($profile_picture_id) ? 
                wp_get_attachment_image_url($profile_picture_id, 'thumbnail') : 
                SUD_IMG_URL . '/default-profile.jpg';

            $last_message = end($messages);
            $last_message_preview = wp_trim_words($last_message->message, 10, '...');
            $last_message_time = human_time_diff(strtotime($last_message->timestamp), current_time('timestamp')) . ' ago';

            $message_threads[] = array(
                'user_id' => $other_user_id,
                'name' => $other_user->display_name,
                'profile_pic' => $profile_pic,
                'unread_count' => $unread_count,
                'last_message' => $last_message_preview,
                'last_message_time' => $last_message_time,
                'messages' => $messages,
                'premium_badge_html_sidebar' => $premium_badge_html_sidebar,
                'is_online' => get_user_meta($other_user_id, 'last_active', true) > (time() - 300), 
            );
        }
    }

    $unread_count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE receiver_id = %d AND is_read = 0",
            $user_id
        )
    );

    return array(
        'threads' => $message_threads,
        'unread_count' => $unread_count ?: 0
    );
}

function get_filtered_user_conversations($user_id) {
    $conversations = get_user_conversations($user_id);

    if (empty($conversations['threads']) || !function_exists('is_user_blocked')) {
        return $conversations;
    }

    $filtered_threads = [];
    foreach ($conversations['threads'] as $thread) {
        $other_user_id = $thread['user_id'];

        if (is_user_blocked($user_id, $other_user_id) || is_user_blocked($other_user_id, $user_id)) {
            continue;
        }
        $filtered_threads[] = $thread;
    }
    $conversations['threads'] = $filtered_threads;
    return $conversations;
}

function mark_messages_as_read($sender_id, $receiver_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_messages';

    return $wpdb->update(
        $table_name,
        array('is_read' => 1),
        array(
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'is_read' => 0
        ),
        array('%d'),
        array('%d', '%d', '%d')
    );
}

function send_message($sender_id, $receiver_id, $message_text) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_messages';
    $gift_prefix = "SUD_GIFT::";

    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        create_messages_table();
    }

    $result = $wpdb->insert(
        $table_name,
        array(
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'message' => $message_text,
            'timestamp' => current_time('mysql', 1),
            'is_read' => 0
        ),
        array('%d', '%d', '%s', '%s', '%d')
    );

    if ($result) {
        $message_id = $wpdb->insert_id;
        if (strpos($message_text, $gift_prefix) !== 0) {
            if (function_exists('add_message_notification')) {
                $message_preview = wp_trim_words(stripslashes($message_text), 10, '...');
                add_message_notification($receiver_id, $sender_id, $message_id, $message_preview);
            }
        }

        $receiver_user_data = get_userdata($receiver_id);
        $sender_user_data = get_userdata($sender_id);

        if ($receiver_user_data && $sender_user_data && function_exists('sud_send_event_notification_email')) {
            if ($sender_id != $receiver_id) {
                $message_preview_for_email = '';
                $gift_prefix = "SUD_GIFT::";

                if (strpos($message_text, $gift_prefix) === 0) {
                    $parts = explode('::', substr($message_text, strlen($gift_prefix)));
                    if (count($parts) >= 5) {
                        $giftName = esc_html($parts[4]);
                        $message_preview_for_email = "<p>" . esc_html($sender_user_data->display_name) . " sent you a gift: <strong>" . $giftName . "</strong>!</p>";
                    } else {
                        $message_preview_for_email = "<p>" . esc_html($sender_user_data->display_name) . " sent you a special gift!</p>";
                    }
                } else {
                    $message_preview_for_email = "<p>" . esc_html($sender_user_data->display_name) . " sent you a message:</p>" .
                                                "<blockquote style='border-left: 3px solid var(--sud-primary); padding-left: 15px; margin: 10px 0; font-style: italic; color: var(--sud-text-primary); background-color:var(--sud-surface); padding:10px 15px; border-radius:4px;'>" . 
                                                nl2br(esc_html(wp_trim_words(stripslashes($message_text), 30, '...'))) . "</blockquote>";
                }

                $conversation_link = SUD_URL . '/pages/messages?user=' . $sender_id;

                sud_send_event_notification_email(
                    $receiver_id,
                    $receiver_user_data->display_name,
                    'new_message',
                    $sender_user_data->display_name,
                    SUD_URL . '/pages/profile?id=' . $sender_id,
                    $message_preview_for_email,
                    'View Message',
                    SUD_URL . '/pages/messages?user=' . $sender_id
                );
            }
        }
        do_action('sud_new_message_sent', $message_id, $sender_id, $receiver_id, $message_text);
        return $message_id;
    }
    return false;
}

function create_messages_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        sender_id bigint(20) UNSIGNED NOT NULL,
        receiver_id bigint(20) UNSIGNED NOT NULL,
        message longtext NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        is_read tinyint(1) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id),
        KEY sender_id (sender_id),
        KEY receiver_id (receiver_id),
        KEY timestamp (timestamp),
        KEY receiver_read_ts (receiver_id, is_read, timestamp)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($table_exists) {
        maybe_add_column($table_name, 'id', "bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT"); 

        $index_exists = $wpdb->get_row("SHOW INDEX FROM $table_name WHERE Key_name = 'receiver_read_ts'");
        if (!$index_exists) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX receiver_read_ts (receiver_id, is_read, timestamp)");
        }
        $index_ts_exists = $wpdb->get_row("SHOW INDEX FROM $table_name WHERE Key_name = 'timestamp'");
        if (!$index_ts_exists) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX timestamp (timestamp)");
        }

        $index_sender_exists = $wpdb->get_row("SHOW INDEX FROM $table_name WHERE Key_name = 'sender_id'");
        if (!$index_sender_exists) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX sender_id (sender_id)");
        }
        $index_receiver_exists = $wpdb->get_row("SHOW INDEX FROM $table_name WHERE Key_name = 'receiver_id'");
        if (!$index_receiver_exists) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX receiver_id (receiver_id)");
        }
    }
}

function get_active_members($current_user_id, $limit = 6, $offset = 0) {
    $args = [
        'orderby' => 'last_active', 
        'order'   => 'DESC',
    ];

    $fetch_limit = $limit * 2;
    $users_data = custom_get_users($current_user_id, $fetch_limit, $offset, $args);

    if (function_exists('prioritize_nearby_users')) {

        if(!function_exists('calculate_distance')) {
            @include_once(__DIR__.'/location-functions.php');
        }
        if(function_exists('prioritize_nearby_users')) {
            $users_data = prioritize_nearby_users($users_data, $current_user_id);
        }
    }

    if (function_exists('filter_quality_profiles')) {
        $users_data = filter_quality_profiles($users_data);
    }

    return array_slice($users_data, 0, $limit);
}

function get_user_conversations_preview($user_id) {
    global $wpdb;
    $msg_table = $wpdb->prefix . 'sud_messages';
    $del_table = $wpdb->prefix . 'sud_deleted_messages';
    $users_table = $wpdb->users; 

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $msg_table)) != $msg_table) create_messages_table();
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $del_table)) != $del_table) create_deleted_messages_table();

    $user_id = intval($user_id); 

    $latest_message_id_subquery = $wpdb->prepare("
        SELECT MAX(m_inner.id)
        FROM $msg_table m_inner
        LEFT JOIN $del_table d_inner ON m_inner.id = d_inner.message_id AND d_inner.user_id = %d
        WHERE
            ((m_inner.sender_id = %d AND m_inner.receiver_id = partners.partner_id) OR (m_inner.sender_id = partners.partner_id AND m_inner.receiver_id = %d))
          AND d_inner.id IS NULL
    ", $user_id, $user_id, $user_id);

    $conversations_query = $wpdb->prepare("
        SELECT
            other_user.id as other_user_id,
            other_user.display_name,
            latest_msg.message as last_message_raw, -- Get the raw message text
            latest_msg.timestamp as last_message_timestamp,
            latest_msg.sender_id as last_sender_id,
            (SELECT COUNT(*)
             FROM $msg_table m2
             LEFT JOIN $del_table d2 ON m2.id = d2.message_id AND d2.user_id = %d -- Exclude deleted for count
             WHERE m2.sender_id = other_user.id AND m2.receiver_id = %d AND m2.is_read = 0 AND d2.id IS NULL
            ) as unread_thread_count
        FROM (
            -- Find unique conversation partners involved in non-deleted messages
            SELECT DISTINCT
                CASE WHEN m3.sender_id = %d THEN m3.receiver_id ELSE m3.sender_id END as partner_id
            FROM $msg_table m3
            LEFT JOIN $del_table d3 ON m3.id = d3.message_id AND d3.user_id = %d -- Exclude deleted conversations
            WHERE (m3.sender_id = %d OR m3.receiver_id = %d)
              AND d3.id IS NULL
        ) as partners
        JOIN $users_table other_user ON partners.partner_id = other_user.id
        JOIN $msg_table latest_msg ON latest_msg.id = ($latest_message_id_subquery) -- Join based on the subquery result for the latest message ID
        ORDER BY latest_msg.timestamp DESC
    ", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);

    $conversations = $wpdb->get_results($conversations_query);

    $message_threads = array();
    $total_unread_messages = 0;
    $unread_conversation_count = 0; 
    $gift_prefix = "SUD_GIFT::";

    if (!empty($conversations)) {
        foreach ($conversations as $convo) {
            $other_user_id = (int)$convo->other_user_id;
            $last_sender_id = (int)$convo->last_sender_id;
            $unslashed_message_text = stripslashes($convo->last_message_raw);
            $is_last_message_gift = (strpos($unslashed_message_text, $gift_prefix) === 0);

            $preview_text = '';

            if ($is_last_message_gift) {
                $parts = explode('::', substr($unslashed_message_text, strlen($gift_prefix)));
                // Format: {gift_id}::{sender_id}::{receiver_id}::{icon_or_url}::{name}::{cost}::{usd_value}
                if (count($parts) >= 5) {
                    $gift_name = $parts[4];
                    $gift_name_escaped = esc_html($gift_name);
                    if ($last_sender_id === $user_id) {
                        $preview_text = "You sent " . $gift_name_escaped;
                    } else {
                        $preview_text = '<i class="fas fa-gift" style="margin-right: 4px; font-size: 0.9em;"></i> Gift Received';
                    }
                } else {
                    $preview_text = '[Gift]';
                }
            }
            else {
                $preview_text = wp_trim_words($unslashed_message_text, 10, '...');
                if ($last_sender_id === $user_id) {
                    $preview_text = 'You: ' . esc_html($preview_text);
                } else {
                    $preview_text = esc_html($preview_text);
                }
            }
            
            $profile_pic_url = get_user_profile_data($other_user_id)['profile_pic'] ?? SUD_IMG_URL . '/default-profile.jpg';
            $last_active_time = get_user_meta($other_user_id, 'last_active', true);
            $is_online = $last_active_time && ($last_active_time > (time() - 300));
            $last_message_time_raw = strtotime($convo->last_message_timestamp);
            $last_message_time_formatted = $last_message_time_raw ? human_time_diff($last_message_time_raw, current_time('timestamp')) . ' ago' : 'N/A';
            $unread_count_for_thread = intval($convo->unread_thread_count);

            $total_unread_messages += $unread_count_for_thread;
            if ($unread_count_for_thread > 0) {
                $unread_conversation_count++;
            }

            $message_threads[] = array(
                'user_id' => $other_user_id,
                'name' => $convo->display_name,
                'profile_pic' => $profile_pic_url,
                'unread_count' => $unread_count_for_thread,
                'last_message' => $preview_text,
                'last_message_raw' => $unslashed_message_text,
                'last_message_time' => $last_message_time_formatted,
                'is_online' => $is_online,
                'is_verified' => get_user_meta($other_user_id, 'is_verified', true),
            );
        }
    }

    return array(
        'threads' => $message_threads,
        'total_unread_message_count' => $total_unread_messages,
        'unread_conversation_count' => $unread_conversation_count
    );
}

function toggle_user_block($current_user_id, $target_user_id, $block_action = true) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_blocked_users';

    $current_user_id = intval($current_user_id);
    $target_user_id = intval($target_user_id);

    if ($current_user_id <= 0 || $target_user_id <= 0 || $current_user_id === $target_user_id) {
        return false;
    }

    if ($block_action) {
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id'           => $current_user_id,
                'blocked_user_id'   => $target_user_id,
                'blocked_at'        => current_time('mysql', 1)
            ],
            ['%d', '%d', '%s']
        );
        if ($result !== false && function_exists('toggle_user_favorite')) {
            toggle_user_favorite($target_user_id, false); 
        }
        return ($result !== false);
    } else {
        $result = $wpdb->delete(
            $table_name,
            [
                'user_id'           => $current_user_id,
                'blocked_user_id'   => $target_user_id
            ],
            ['%d', '%d']
        );
        return ($result !== false);
    }
}

function get_conversation_thread($user1_id, $user2_id, $requesting_user_id, $limit = 50, $offset = 0) {
    global $wpdb;
    $msg_table = $wpdb->prefix . 'sud_messages';
    $del_table = $wpdb->prefix . 'sud_deleted_messages';

    if ($wpdb->get_var("SHOW TABLES LIKE '$msg_table'") != $msg_table) create_messages_table();
    if ($wpdb->get_var("SHOW TABLES LIKE '$del_table'") != $del_table) create_deleted_messages_table();

    $messages_query = $wpdb->prepare("
        SELECT m.*
        FROM $msg_table m
        LEFT JOIN $del_table d ON m.id = d.message_id AND d.user_id = %d
        WHERE
            ((m.sender_id = %d AND m.receiver_id = %d) OR (m.sender_id = %d AND m.receiver_id = %d))
          AND d.id IS NULL -- Exclude messages deleted by the requesting user
        ORDER BY m.timestamp DESC -- Order by DESC for LIMIT/OFFSET
        LIMIT %d OFFSET %d
    ", $requesting_user_id, $user1_id, $user2_id, $user2_id, $user1_id, $limit, $offset);

    $messages = $wpdb->get_results($messages_query);

    return array_reverse($messages ?: []);
}

function create_deleted_messages_table() {
    global $wpdb;
    $deleted_messages_table = $wpdb->prefix . 'sud_deleted_messages';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$deleted_messages_table'") != $deleted_messages_table) {
        $sql = "CREATE TABLE $deleted_messages_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            deleted_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY message_user (message_id, user_id),
            KEY message_id (message_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

function get_suggested_members($current_user_id, $limit = 6) {
    return get_active_members($current_user_id, $limit);
}

function is_user_blocked($user_id_to_check, $blocker_user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_blocked_users';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD ERROR: Table $table_name does not exist in is_user_blocked function.");
        return false; 
    }

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND blocked_user_id = %d",
        $blocker_user_id,
        $user_id_to_check
    ));
    return $count > 0;
}

function get_blocked_users($blocker_user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_blocked_users';
    
    $blocker_user_id = intval($blocker_user_id);
    if ($blocker_user_id <= 0) return [];

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD ERROR: Table $table_name does not exist in get_blocked_users function.");
        return [];
    }

    $blocked_users_data = $wpdb->get_results($wpdb->prepare(
        "SELECT b.blocked_user_id, b.blocked_at, u.display_name, u.user_email 
         FROM $table_name b
         JOIN {$wpdb->users} u ON b.blocked_user_id = u.ID
         WHERE b.user_id = %d
         ORDER BY b.blocked_at DESC",
        $blocker_user_id 
    ));

    $result_list = [];
    if (!empty($blocked_users_data)) {
        foreach ($blocked_users_data as $user_row) {
            $blocked_id = intval($user_row->blocked_user_id);
            $profile_picture_id = get_user_meta($blocked_id, 'profile_picture', true);
            $profile_pic_url = !empty($profile_picture_id) ? 
                wp_get_attachment_image_url($profile_picture_id, 'thumbnail') : 
                (defined('SUD_IMG_URL') ? SUD_IMG_URL . '/default-profile.jpg' : '');

            $result_list[] = [
                'id'           => $blocked_id,
                'name'         => $user_row->display_name ?: 'User Deleted',
                'email'        => $user_row->user_email,
                'profile_pic'  => $profile_pic_url,
                'blocked_date' => date('M j, Y', strtotime($user_row->blocked_at))
            ];
        }
    }
    return $result_list;
}

function filter_blocked_users($user_list, $current_user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_blocked_users';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        return $user_list;
    }

    $current_user_id = intval($current_user_id);
    if ($current_user_id <= 0) return $user_list;

    $users_who_blocked_current_user = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM $table_name WHERE blocked_user_id = %d",
        $current_user_id
    ));

    $users_blocked_by_current_user = $wpdb->get_col($wpdb->prepare(
        "SELECT blocked_user_id FROM $table_name WHERE user_id = %d",
        $current_user_id
    ));

    $all_blocked_ids_to_exclude = array_unique(array_merge(
        is_array($users_who_blocked_current_user) ? $users_who_blocked_current_user : [],
        is_array($users_blocked_by_current_user) ? $users_blocked_by_current_user : []
    ));
    
    $all_blocked_ids_to_exclude = array_map('intval', $all_blocked_ids_to_exclude);


    if (empty($all_blocked_ids_to_exclude)) {
        return $user_list;
    }

    $filtered_list = [];
    foreach ($user_list as $user_item) {
        $user_id_to_check = 0;
        if (is_object($user_item) && isset($user_item->ID)) {
            $user_id_to_check = (int)$user_item->ID;
        } elseif (is_array($user_item) && isset($user_item['id'])) {
            $user_id_to_check = (int)$user_item['id'];
        } elseif (is_numeric($user_item)) {
            $user_id_to_check = (int)$user_item;
        }

        if ($user_id_to_check > 0 && !in_array($user_id_to_check, $all_blocked_ids_to_exclude)) {
            $filtered_list[] = $user_item;
        }
    }
    return $filtered_list;
}

function get_unread_message_counts($user_id) {
    global $wpdb;
    $user_id = intval($user_id);
    $msg_table = $wpdb->prefix . 'sud_messages';
    $del_table = $wpdb->prefix . 'sud_deleted_messages';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $msg_table)) != $msg_table) create_messages_table();
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $del_table)) != $del_table) create_deleted_messages_table();

    $total_messages = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(m.id)
         FROM $msg_table m
         LEFT JOIN $del_table d ON m.id = d.message_id AND d.user_id = %d
         WHERE m.receiver_id = %d
           AND m.is_read = 0
           AND d.id IS NULL",
        $user_id, $user_id
    ));

    $total_conversations = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT m.sender_id)
         FROM $msg_table m
         LEFT JOIN $del_table d ON m.id = d.message_id AND d.user_id = %d
         WHERE m.receiver_id = %d
           AND m.is_read = 0
           AND d.id IS NULL",
        $user_id, $user_id
    ));

    return ['total_messages' => $total_messages, 'total_conversations' => $total_conversations];
}