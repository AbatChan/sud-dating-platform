<?php

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/user-functions.php');

if (!function_exists('sud_send_event_notification_email')) {
    if (file_exists(__DIR__ . '/mailer.php')) {
        require_once(__DIR__ . '/mailer.php');
    } else {
        error_log("SUD CRITICAL: mailer.php not found in notification-functions.php");
    }
}

function create_notifications_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_notifications';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            content text NOT NULL,
            related_id bigint(20) DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0 NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

function delete_all_user_notifications($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_notifications';

    if (empty($user_id) || !is_numeric($user_id)) {
        return false;
    }

    $result = $wpdb->delete(
        $table_name,
        ['user_id' => $user_id], 
        ['%d']                   
    );

    return ($result !== false);
}

function add_notification($user_id, $type, $content, $related_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_notifications';

    create_notifications_table();

    $result = $wpdb->insert(
        $table_name,
        [
            'user_id' => $user_id,
            'type' => $type,
            'content' => $content,
            'related_id' => $related_id,
            'is_read' => 0,
            'timestamp' => current_time('mysql')
        ],
        ['%d', '%s', '%s', '%d', '%d', '%s']
    );

    if ($result === false) {
        return false;
    }

    return $wpdb->insert_id;
}

function add_message_notification($receiver_id, $sender_id, $message_id, $message_preview) {
    $sender = get_userdata($sender_id);
    if (!$sender) {
        return false;
    }

    $content = $sender->display_name . ' sent you a message: "' . $message_preview . '"';
    return add_notification($receiver_id, 'message', $content, $sender_id);
}

function get_unread_notification_count($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_notifications';

    create_notifications_table();

    return (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name
         WHERE user_id = %d AND is_read = 0",
        $user_id
    ));
}

function get_user_notifications($user_id, $limit = 20, $offset = 0, $type = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_notifications';

    create_notifications_table();

    $query = "SELECT * FROM $table_name WHERE user_id = %d";
    $params = [$user_id];

    if ($type) {
        $query .= " AND type = %s";
        $params[] = $type;
    }

    $query .= " ORDER BY timestamp DESC LIMIT %d OFFSET %d";
    $params[] = $limit;
    $params[] = $offset;

    $notifications = $wpdb->get_results(
        $wpdb->prepare($query, $params)
    );
    return $notifications ?: [];
}

function mark_notification_read($notification_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_notifications';

    $result = $wpdb->update(
        $table_name,
        ['is_read' => 1],
        ['id' => $notification_id],
        ['%d'],
        ['%d']
    );

    return $result !== false;
}

function mark_all_notifications_read($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_notifications';

    $unread_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND is_read = 0",
        $user_id
    ));

    if ((int)$unread_count === 0) {
        return true; 
    }

    $result = $wpdb->update(
        $table_name,
        ['is_read' => 1],
        ['user_id' => $user_id, 'is_read' => 0],
        ['%d'],
        ['%d', '%d']
    );
    return $result !== false;
}

function add_profile_view_notification($viewer_id, $profile_owner_id) {
    if ($viewer_id == $profile_owner_id) {
        return false; 
    }
    $viewer_user_data = get_userdata($viewer_id); 
    $profile_owner_data = get_userdata($profile_owner_id); 

    if (!$viewer_user_data || !$profile_owner_data) {
        return false;
    }

    // Don't track views from admins or chat moderators
    if (user_can($viewer_id, 'administrator') || user_can($viewer_id, 'sud_moderate_chat')) {
        return false;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_notifications';
    create_notifications_table(); 

    $recent_notification = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name
         WHERE user_id = %d AND type = 'profile_view' AND related_id = %d
         AND timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)", // Check within the last 24 hours
        $profile_owner_id, $viewer_id
    ));

    if ($recent_notification) {
        return false; 
    }

    $content_for_in_site_notification = '';
    $recipient_can_see_viewer = sud_user_can_access_feature($profile_owner_id, 'viewed_profile');

    if ($recipient_can_see_viewer) {
        $content_for_in_site_notification = esc_html($viewer_user_data->display_name) . ' viewed your profile.';
    } else {
        $content_for_in_site_notification = 'Someone new viewed your profile! Upgrade to Premium to see who it was.';
    }

    $profile_views = get_user_meta($profile_owner_id, 'profile_viewers', true);
    if (!is_array($profile_views)) {
        $profile_views = [];
    }
    $profile_views[$viewer_id] = current_time('timestamp'); 
    update_user_meta($profile_owner_id, 'profile_viewers', $profile_views);

    // For free users, use null related_id to hide real user identity
    $notification_related_id = $recipient_can_see_viewer ? $viewer_id : null;
    $in_site_notification_id = add_notification($profile_owner_id, 'profile_view', $content_for_in_site_notification, $notification_related_id);

    if ($profile_owner_data && function_exists('sud_send_event_notification_email')) {
        if (!preg_match('/@sud\.com$/i', $profile_owner_data->user_email)) {
            $email_details_html = "";
            $email_cta_text = "";
            $email_cta_link = "";

            if ($recipient_can_see_viewer) {
                 $email_details_html = "<p>Good news, " . esc_html($profile_owner_data->display_name) . "!</p>" .
                                       "<p><strong>" . esc_html($viewer_user_data->display_name) . "</strong> recently viewed your profile. This could be a great opportunity to connect!</p>";
                 $email_cta_text = "View " . esc_html($viewer_user_data->display_name) . "'s Profile";
                 $email_cta_link = SUD_URL . '/pages/profile?id=' . $viewer_id;
            } else {
                 $email_details_html = "<p>Hello " . esc_html($profile_owner_data->display_name) . ",</p>" .
                                       "<p>Someone new has taken an interest in your profile! Want to find out who it is and see if there's a connection?</p>" .
                                       "<p>Upgrade to Premium to unlock full access to who views your profile, plus enjoy many other exclusive features designed to help you find your match.</p>";
                 $email_cta_text = "Upgrade to See Who Viewed You";
                 $email_cta_link = SUD_URL . '/pages/premium';
            }

            // Send email notification to user (unless they've disabled it in settings)
            sud_send_event_notification_email(
                $profile_owner_id, 
                $profile_owner_data->display_name, 
                'profile_view', 
                $viewer_user_data->display_name, 
                SUD_URL . '/pages/profile?id=' . $viewer_id, 
                $email_details_html, 
                $email_cta_text, 
                $email_cta_link  
            );
        }
    }
    return $in_site_notification_id; 
}

if (!function_exists('add_favorite_notification')) {
    function add_favorite_notification($user_id_who_favorited, $user_id_who_was_favorited) {
        $actor_user_data = get_userdata($user_id_who_favorited); 
        $recipient_user_data = get_userdata($user_id_who_was_favorited); 

        if (!$actor_user_data || !$recipient_user_data) {
            error_log("SUD Favorite Notif: Invalid actor or recipient ID. Actor: {$user_id_who_favorited}, Recipient: {$user_id_who_was_favorited}");
            return false;
        }

        if ($user_id_who_favorited == $user_id_who_was_favorited) {
            return false; 
        }

        $content_for_in_site_notification = '';
        $recipient_can_see_favoriter = sud_user_can_access_feature($user_id_who_was_favorited, 'viewed_profile'); 

        if ($recipient_can_see_favoriter) {
            $content_for_in_site_notification = esc_html($actor_user_data->display_name) . ' added you to their favorites.';
        } else {
            $content_for_in_site_notification = 'Someone added you to their favorites! Upgrade to Premium to find out who.';
        }

        // For free users, use null related_id to hide real user identity
        $notification_related_id = $recipient_can_see_favoriter ? $user_id_who_favorited : null;
        $in_site_notification_id = add_notification($user_id_who_was_favorited, 'new_favorite', $content_for_in_site_notification, $notification_related_id);

        if (function_exists('sud_send_event_notification_email')) {
            if (!preg_match('/@sud\.com$/i', $recipient_user_data->user_email)) {
                $email_details_html = "";
                $email_cta_text = "";
                $email_cta_link = "";
                $actor_profile_link = SUD_URL . '/pages/profile?id=' . $user_id_who_favorited;

                if ($recipient_can_see_favoriter) {
                    $email_details_html = "<p>You've caught someone's eye, " . esc_html($recipient_user_data->display_name) . "!</p>" .
                                          "<p><strong>" . esc_html($actor_user_data->display_name) . "</strong> has added you to their list of favorites. This could be a meaningful connection!</p>";
                    $email_cta_text = "View " . esc_html($actor_user_data->display_name) . "'s Profile";
                    $email_cta_link = $actor_profile_link;
                } else {
                    $email_details_html = "<p>Hello " . esc_html($recipient_user_data->display_name) . ",</p>" .
                                          "<p>Someone special has added you to their favorites! This is a great sign they're interested in getting to know you better.</p>" .
                                          "<p>Want to see who it is and explore a potential match? Upgrade to Premium to unlock this feature and many more!</p>";
                    $email_cta_text = "Upgrade to See Who Favorited You";
                    $email_cta_link = SUD_URL . '/pages/premium';
                }

                // Send email notification to user (unless they've disabled it in settings)
                sud_send_event_notification_email(
                    $user_id_who_was_favorited, 
                    $recipient_user_data->display_name, 
                    'new_favorite', 
                    $actor_user_data->display_name, 
                    $actor_profile_link, 
                    $email_details_html, 
                    $email_cta_text, 
                    $email_cta_link  
                );
            }
        }
        return $in_site_notification_id; 
    }
}

if (!function_exists('sud_send_match_notification')) {
    function sud_send_match_notification($user_id1, $user_id2, $is_instant_match = false) {
        $user1_data = get_userdata($user_id1);
        $user2_data = get_userdata($user_id2);

        if (!$user1_data || !$user2_data) {
            error_log("SUD Match Notif: Invalid user IDs. User1: {$user_id1}, User2: {$user_id2}");
            return false;
        }

        if ($is_instant_match) {
            // For instant matches: user1 used Swipe Up to instantly match with user2
            $content_for_user1 = 'Instant Match! You instantly matched with ' . esc_html($user2_data->display_name) . ' using Swipe Up.';
            $content_for_user2 = 'Instant Match! ' . esc_html($user1_data->display_name) . ' used Swipe Up to instantly match with you.';
        } else {
            // For regular matches: both users liked each other
            $content_for_user1 = 'It\'s a match! You and ' . esc_html($user2_data->display_name) . ' liked each other.';
            $content_for_user2 = 'It\'s a match! ' . esc_html($user1_data->display_name) . ' also liked you.';
        }

        add_notification($user_id1, 'match', $content_for_user1, $user_id2);
        add_notification($user_id2, 'match', $content_for_user2, $user_id1);

        if (function_exists('sud_send_event_notification_email')) {
            if ($is_instant_match) {
                // Email to User 1 (who used Swipe Up)
                $email_details_user1 = "<p>Congratulations, " . esc_html($user1_data->display_name) . "!</p>" .
                                       "<p>You've instantly matched with <strong>" . esc_html($user2_data->display_name) . "</strong> using Swipe Up! Start a conversation now!</p>";
                
                // Email to User 2 (who received the instant match)
                $email_details_user2 = "<p>Congratulations, " . esc_html($user2_data->display_name) . "!</p>" .
                                       "<p><strong>" . esc_html($user1_data->display_name) . "</strong> used Swipe Up to instantly match with you! They must really be interested!</p>";
            } else {
                // Email to User 1
                $email_details_user1 = "<p>Congratulations, " . esc_html($user1_data->display_name) . "!</p>" .
                                       "<p>You have a new match with <strong>" . esc_html($user2_data->display_name) . "</strong>. This is a great opportunity to start a conversation!</p>";
                
                // Email to User 2
                $email_details_user2 = "<p>Congratulations, " . esc_html($user2_data->display_name) . "!</p>" .
                                       "<p><strong>" . esc_html($user1_data->display_name) . "</strong> has also liked you back, making it a match! Why not send them a message?</p>";
            }
            
            // Send email notifications to both matched users (unless they've disabled it in settings)
            sud_send_event_notification_email(
                $user_id1,
                $user1_data->display_name,
                'new_match',
                $user2_data->display_name,
                SUD_URL . '/pages/profile?id=' . $user_id2,
                $email_details_user1,
                "View " . esc_html($user2_data->display_name) . "'s Profile",
                SUD_URL . '/pages/profile?id=' . $user_id2
            );

            sud_send_event_notification_email(
                $user_id2,
                $user2_data->display_name,
                'new_match',
                $user1_data->display_name,
                SUD_URL . '/pages/profile?id=' . $user_id1,
                $email_details_user2,
                "View " . esc_html($user1_data->display_name) . "'s Profile",
                SUD_URL . '/pages/profile?id=' . $user_id1
            );
        }
        return true;
    }
}

if (!function_exists('is_sud_dashboard_loaded')) {
    function is_sud_dashboard_loaded() {
        return defined('SUD_DASHBOARD_LOADED');
    }
}

function add_ban_notification($user_id, $reason = '') {
    $content = "Your account has been banned. " . ($reason ? "Reason: $reason" : "");
    return add_notification($user_id, 'account_banned', $content);
}

function add_warning_notification($user_id, $reason = '') {
    $content = "Your account has received a warning. " . ($reason ? "Reason: $reason" : "");
    return add_notification($user_id, 'account_warning', $content);
}

function add_profile_hidden_notification($user_id, $reason = '') {
    $content = "Your profile has been hidden. " . ($reason ? "Reason: $reason" : "");
    return add_notification($user_id, 'profile_hidden', $content);
}

/**
 * Centralized notification handler for consistent display across header bell and activity page
 */
function get_notification_display_data($notification, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $related_user_id = $notification->related_id;
    $related_user = null;
    $profile_pic = SUD_IMG_URL . '/default-profile.jpg';
    $is_premium_notification = ($notification->type === 'profile_view' || $notification->type === 'new_favorite');
    $is_system_notification = in_array($notification->type, ['account_banned', 'account_warning', 'account_unbanned', 'boost_purchased', 'coins_purchased', 'super_swipe_purchased', 'subscription']);
    
    // Handle premium notifications
    $is_premium_teaser = $is_premium_notification && empty($related_user_id);
    $can_view_profiles = sud_user_can_access_feature($user_id, 'viewed_profile');
    
    if ($is_premium_teaser) {
        $profile_pic = SUD_IMG_URL . '/blurred-user.png';
    } elseif ($related_user_id && !$is_system_notification) {
        $related_user = get_userdata($related_user_id);
        if ($related_user) {
            $profile_pic_id = get_user_meta($related_user_id, 'profile_picture', true);
            if (!empty($profile_pic_id)) {
                $profile_pic_url = wp_get_attachment_image_url($profile_pic_id, 'thumbnail');
                if ($profile_pic_url) {
                    $profile_pic = $profile_pic_url;
                }
            }
        }
    }
    
    // Generate avatar HTML
    $avatar_html = '';
    if ($is_system_notification) {
        switch ($notification->type) {
            case 'account_banned':
                $avatar_html = '<div class="notification-avatar system-notification-icon ban-icon"><i class="fas fa-ban"></i></div>';
                break;
            case 'account_warning':
                $avatar_html = '<div class="notification-avatar system-notification-icon warning-icon"><i class="fas fa-exclamation-triangle"></i></div>';
                break;
            case 'account_unbanned':
                $avatar_html = '<div class="notification-avatar system-notification-icon unban-icon"><i class="fas fa-check-circle"></i></div>';
                break;
            case 'boost_purchased':
                $avatar_html = '<div class="notification-avatar system-notification-icon boost-icon"><i class="fas fa-rocket"></i></div>';
                break;
            case 'coins_purchased':
                $avatar_html = '<div class="notification-avatar system-notification-icon coins-icon"><i class="fas fa-coins"></i></div>';
                break;
            case 'super_swipe_purchased':
                $avatar_html = '<div class="notification-avatar system-notification-icon super-swipe-icon"><i class="fas fa-heart"></i></div>';
                break;
            case 'subscription':
                $avatar_html = '<div class="notification-avatar system-notification-icon subscription-icon"><i class="fas fa-crown"></i></div>';
                break;
        }
    } else {
        $avatar_html = '<img src="' . esc_url($profile_pic) . '" alt="User" class="notification-avatar">';
    }
    
    return [
        'id' => $notification->id,
        'type' => $notification->type,
        'content' => $notification->content,
        'related_id' => $related_user_id,
        'is_read' => (bool)$notification->is_read,
        'timestamp' => $notification->timestamp,
        'time_ago' => human_time_diff(strtotime($notification->timestamp), current_time('timestamp')) . ' ago',
        'profile_pic' => $profile_pic,
        'avatar_html' => $avatar_html,
        'is_premium_teaser' => $is_premium_teaser,
        'is_system_notification' => $is_system_notification,
        'can_view_profiles' => $can_view_profiles,
        'upgrade_url' => $is_premium_teaser ? SUD_URL . '/pages/premium?highlight_plan=gold' : null
    ];
}