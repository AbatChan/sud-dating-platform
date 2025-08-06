<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');

// Require login for this page
require_login();

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$display_name = $current_user->display_name;

require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');

// Use centralized validation for all profile requirements
validate_core_profile_requirements($current_user_id, 'messages');

$user_conversations_data = get_user_conversations_preview($current_user_id);
$conversation_threads = $user_conversations_data['threads'] ?? [];
$display_unread_count = $user_conversations_data['unread_conversation_count'] ?? 0;

$active_partner_id = isset($_GET['user']) ? intval($_GET['user']) : null;
$active_chat_partner = null;
$active_thread_messages = [];
$is_new_conversation = false;
$error_message = null;

$viewer_blocked_partner = false;
$partner_blocked_viewer = false;
$is_viewing_blocked_user = false;

$current_user_functional_role = get_user_meta($current_user_id, 'functional_role', true);
$target_functional_role = '';
$can_search = true;
$base_meta_query = ['relation' => 'AND'];

if (current_user_can('manage_options')) {
    // Admin can see suggestions of any role
} else {
    if ($current_user_functional_role === 'provider') {
        $target_functional_role = 'receiver';
    } elseif ($current_user_functional_role === 'receiver') {
        $target_functional_role = 'provider';
    } else {
        $can_search = false;
        $target_functional_role = '';
        error_log("Messages Page: User {$current_user_id} has undefined functional_role, cannot show suggestions.");
    }

    if ($can_search && !empty($target_functional_role)) {
        $base_meta_query['role_clause'] = [
            'key' => 'functional_role',
            'value' => $target_functional_role,
            'compare' => '='
        ];
    }
}

$current_gender = get_user_meta( $current_user_id, 'gender', true );
if ( $current_gender === 'Man' ) {
    $base_meta_query['gender_clause'] = [
        'key'     => 'gender',
        'value'   => 'Woman',
        'compare' => '='
    ];
} elseif ( $current_gender === 'Woman' ) {
    $base_meta_query['gender_clause'] = [
        'key'     => 'gender',
        'value'   => 'Man',
        'compare' => '='
    ];
}

if ($active_partner_id && $active_partner_id !== $current_user_id) {
    $active_chat_partner = get_user_profile_data($active_partner_id);

    if ($active_chat_partner && function_exists('is_user_blocked')) {
        $viewer_blocked_partner = is_user_blocked($active_partner_id, $current_user_id);
        $partner_blocked_viewer = is_user_blocked($current_user_id, $active_partner_id);
    } else if (!$active_chat_partner) {
        $active_partner_id = null;
        $error_message = "The specified user could not be found.";
    }

    $is_viewing_blocked_user = $viewer_blocked_partner || $partner_blocked_viewer;
    
    // Check if users are matched (required for messaging)
    $are_users_matched = false;
    if ($active_chat_partner && function_exists('sud_are_users_matched')) {
        $are_users_matched = sud_are_users_matched($current_user_id, $active_partner_id);
    }

    if ($is_viewing_blocked_user) {
        $active_thread_messages = [];
    }
    else if (!$are_users_matched && $active_chat_partner) {
        // Users are not matched, redirect to swipe page
        $redirect_url = add_query_arg([
            'not_matched' => '1',
            'user_id' => $active_partner_id
        ], SUD_URL . '/pages/swipe');
        wp_safe_redirect($redirect_url);
        exit;
    }
    else if ($active_chat_partner) {
        $existing_thread_preview = null;
        foreach ($conversation_threads as &$thread_preview_ref) {
            if ($thread_preview_ref['user_id'] == $active_partner_id) {
                $existing_thread_preview = &$thread_preview_ref;
                break;
            }
        }
        unset($thread_preview_ref);
        if ($existing_thread_preview) {
            $active_thread_messages = get_conversation_thread($current_user_id, $active_partner_id, $current_user_id, 30, 0);
            if ($existing_thread_preview['unread_count'] > 0) {
                mark_messages_as_read($active_partner_id, $current_user_id);
                $existing_thread_preview['unread_count'] = 0;

                $display_unread_count = 0;
                foreach ($conversation_threads as $tp) {
                    $display_unread_count += $tp['unread_count'];
                }
            }
            $is_new_conversation = false;
        }
        else {
            $is_new_conversation = true;
            $active_thread_messages = [];
        }
    }
    else if (!$error_message) {
        $active_partner_id = null;
        $error_message = "Could not load user data.";
    }

} 
$unread_conversations = array_filter($conversation_threads, function($thread) {
    return isset($thread['unread_count']) && $thread['unread_count'] > 0;
});

$suggested_members = []; 

if (empty($conversation_threads) && $can_search && function_exists('custom_get_users')) {
    $suggestions_limit = 6; 

    $suggested_args = [
        'orderby'    => 'last_active', 
        'order'      => 'DESC',
        'meta_query' => $base_meta_query
    ];

    $suggested_members = custom_get_users(
        $current_user_id,
        $suggestions_limit,
        0, 
        $suggested_args
    );
}

update_user_last_active($current_user_id);

$current_user_profile_data = get_user_profile_data($current_user_id);
$header_data = [
    'current_user' => $current_user,
    'display_name' => $display_name,
    'profile_pic_url' => $current_user_profile_data['profile_pic'] ?? SUD_IMG_URL . '/default-profile.jpg',
    'completion_percentage' => $current_user_profile_data['completion_percentage'] ?? 0,
    'unread_message_count' => $display_unread_count,
    'coin_balance' => get_user_meta($current_user_id, 'coin_balance', true) ?: 0,
    'header_user_is_verified' => $current_user_profile_data['is_verified'] ?? false,
];

// Set page title
$page_title = "Messages - " . SUD_SITE_NAME;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        if ( function_exists( 'get_site_icon_url' ) && ( $icon_url = get_site_icon_url() ) ) {
            echo '<link rel="icon" href="' . esc_url( $icon_url ) . '" />';
        }
    ?>
    <title><?php echo esc_html($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/messages.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/messages-modals.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/swipe.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php
        $sent_message_count_to_active = null;
        $is_sender_premium = sud_is_user_premium($current_user_id);
        
        if (!$is_sender_premium && $active_partner_id) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'sud_messages';
            $sent_message_count_to_active = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE sender_id = %d AND receiver_id = %d",
                    $current_user_id,
                    $active_partner_id
                )
            );
        }
    ?>
    <script>
        var sud_page_specific_config = {
            active_partner_id: <?php echo $active_partner_id ? $active_partner_id : 'null'; ?>,
            active_partner_name: '<?php echo $active_chat_partner ? esc_js($active_chat_partner['name']) : ''; ?>',
            is_blocked_view: <?php echo $is_viewing_blocked_user ? 'true' : 'false'; ?>,
            is_sender_premium: <?php echo json_encode($is_sender_premium); ?>,
            sent_message_count: <?php echo json_encode($sent_message_count_to_active); ?>,
            free_message_limit: <?php echo defined('SUD_FREE_MESSAGE_LIMIT_PER_USER') ? (int)SUD_FREE_MESSAGE_LIMIT_PER_USER : 10; ?>,
            ajax_nonce: '<?php echo wp_create_nonce('sud_ajax_action'); ?>',
            clear_messages_nonce: '<?php echo wp_create_nonce('sud_clear_messages_nonce'); ?>'
        };
    </script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
</head>
<body>
    <?php
        extract($header_data);
        include(dirname(__FILE__, 2) . '/templates/components/user-header.php');
    ?>
    <div id="toast-container" class="toast-container"></div>

    <main class="main-content">
        <div class="container">
             <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo esc_html($error_message); ?></div>
             <?php endif; ?>

            <div class="messages-container <?php echo $is_viewing_blocked_user ? 'is-blocked-view' : ''; ?>">
                

                <div class="messages-wrapper">
                    <div class="message-sidebar">
                    <div class="message-tabs">
                        <div class="tab active" data-tab="all">Messages</div>
                        <div class="tab" data-tab="unread">Unread <span class="unread-tab-count <?php echo $display_unread_count > 0 ? '' : 'hidden'; ?>"><?php echo $display_unread_count; ?></span></div>
                    </div>
                        <!-- All Conversations Tab -->
                        <div class="conversations-container all-conversations active">
                            <?php if (empty($conversation_threads)): ?>
                                <div class="no-conversations"> <p>No conversations yet.</p> </div>
                            <?php else: ?>
                                <?php foreach($conversation_threads as $thread): ?>
                                    <div class="conversation-item <?php echo ($active_partner_id == $thread['user_id']) ? 'active' : ''; ?>"
                                        data-user-id="<?php echo $thread['user_id']; ?>"
                                        <?php
                                            $last_message_time_unix = $thread['last_message_timestamp_unix'] ?? null; 

                                            if (is_null($last_message_time_unix)) {
                                                $last_message_raw = $thread['last_message_time_raw'] ?? null;
                                            
                                                if (!is_null($last_message_raw)) {
                                                    $timestamp = strtotime($last_message_raw);
                                                    if ($timestamp !== false) {
                                                        $last_message_time_unix = $timestamp;
                                                    } else {
                                                        error_log('[SUD Warning] messages.php: Could not parse date string from last_message_time_raw: ' . $last_message_raw);
                                                    }
                                                }
                                            }
                                            
                                            if ($last_message_time_unix) {
                                                echo ' data-last-timestamp-unix="' . esc_attr($last_message_time_unix) . '"';
                                            }
                                        ?>
                                        >
                                        <div class="user-avatar">
                                            <img src="<?php echo esc_url($thread['profile_pic']); ?>" alt="<?php echo esc_attr($thread['name']); ?>" onerror="this.src='<?php echo SUD_IMG_URL; ?>/default-profile.jpg';">
                                            <?php if (!empty($thread['is_online'])): ?>
                                                <span class="online-indicator"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="conversation-info">
                                            <div class="conversation-header">
                                                <div class="name-badge-wrapper">
                                                    <h4><?php echo esc_html($thread['name']); ?></h4>
                                                    <?php
                                                        echo $thread['premium_badge_html_sidebar'] ?? '';
                                                        if (!empty($thread['is_verified'])):
                                                    ?>
                                                    <span class="verified-badge-sidebar"><img src="<?php echo SUD_IMG_URL; ?>/verified-profile-badge.png" alt="verified"></span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="time"><?php echo esc_html($thread['last_message_time']); ?></span>
                                            </div>
                                            <p class="message-preview"><?php echo $thread['last_message']; ?></p>
                                        </div>
                                        <?php if ($thread['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $thread['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Unread Conversations Tab -->
                        <div class="conversations-container unread-conversations">
                            <?php if (empty($unread_conversations)): ?>
                                <div class="no-conversations"> <p>No unread messages.</p> </div>
                            <?php else: ?>
                                <?php foreach($unread_conversations as $thread): ?>
                                    <div class="conversation-item <?php echo ($active_partner_id == $thread['user_id']) ? 'active' : ''; ?>" data-user-id="<?php echo $thread['user_id']; ?>">
                                        <div class="user-avatar">
                                            <img src="<?php echo esc_url($thread['profile_pic']); ?>" alt="<?php echo esc_attr($thread['name']); ?>" onerror="this.src='<?php echo SUD_IMG_URL; ?>/default-profile.jpg';">
                                            <?php if (!empty($thread['is_online'])): ?>
                                                <span class="online-indicator"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="conversation-info">
                                            <div class="conversation-header">
                                                 <div class="name-badge-wrapper">
                                                    <h4><?php echo esc_html($thread['name']); ?></h4>
                                                    <?php if (get_user_meta($thread['user_id'], 'is_verified', true)): ?>
                                                        <span class="verified-badge-sidebar"><img src="<?php echo SUD_IMG_URL; ?>/verified-profile-badge.png" alt="verified"></span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="time"><?php echo esc_html($thread['last_message_time']); ?></span>
                                            </div>
                                            <?php
                                                $preview_text_all = '';
                                                $last_message_content_all = $thread['last_message_raw'] ?? $thread['last_message'];
                                                $gift_prefix = "SUD_GIFT::";

                                                if (strpos($last_message_content_all, $gift_prefix) === 0) {
                                                    $parts = explode('::', substr($last_message_content_all, strlen($gift_prefix)));
                                                    // Format: {gift_id}::{sender_id}::{receiver_id}::{icon_or_url}::{name}::{cost}::{usd_value}
                                                    if (count($parts) >= 5) {
                                                        $gift_sender_id = intval($parts[1]);
                                                        $gift_display_element = $parts[3];
                                                        $gift_name = $parts[4];

                                                        if ($gift_sender_id === $current_user_id) {
                                                            $preview_text_all = "You sent " . esc_html($gift_name);
                                                        } else {
                                                            $preview_text_all = '<i class="fas fa-gift" style="margin-right: 4px; font-size: 0.9em;"></i> Gift Received';
                                                        }
                                                    } else {
                                                        $preview_text_all = '[Gift]';
                                                    }
                                                } else {
                                                    $preview_text_all = esc_html($thread['last_message']);
                                                }
                                            ?>
                                            <p class="message-preview"><?php echo $preview_text_all; ?></p>
                                        </div>
                                        <span class="unread-badge"><?php echo $thread['unread_count']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="message-main">
                        <?php if ($active_chat_partner): ?>
                            <div class="message-header">
                                <div class="message-user-info">
                                    <a href="<?php echo SUD_URL; ?>/pages/profile?id=<?php echo $active_chat_partner['id']; ?>">
                                        <img src="<?php echo esc_url($active_chat_partner['profile_pic']); ?>" alt="<?php echo esc_attr($active_chat_partner['name']); ?>" onerror="this.src='<?php echo SUD_IMG_URL; ?>/default-profile.jpg';">
                                    </a>
                                    <div>
                                         <a href="<?php echo SUD_URL; ?>/pages/profile?id=<?php echo $active_chat_partner['id']; ?>" style="color: inherit;text-decoration: none;display: flex;align-items: center;gap: 5px;">
                                            <h3><?php echo esc_html($active_chat_partner['name']); ?></h3>
                                            <?php echo $active_chat_partner['premium_badge_html_medium'] ?? ''; ?>
                                            <?php if ($active_chat_partner['is_verified']): ?>
                                                <span class="verified-badge-header"><img src="<?php echo SUD_IMG_URL; ?>/verified-profile-badge.png" alt="verified"></span>
                                            <?php endif; ?>
                                         </a>
                                         <?php
                                            $status_text = 'Offline';
                                            $status_class = 'offline';
                                            $five_minutes_ago = current_time('timestamp', true) - 300;
                                            $one_hour_ago = current_time('timestamp', true) - 3600;

                                            if (!empty($active_chat_partner['is_online'])) {
                                                $status_text = 'Online';
                                                $status_class = 'online';
                                            } elseif (!empty($active_chat_partner['last_active_timestamp']) && $active_chat_partner['last_active_timestamp'] > $one_hour_ago) {
                                                $status_text = 'Active ' . esc_html($active_chat_partner['last_active']);
                                                $status_class = 'recent';
                                            }
                                        ?>
                                        <span class="status <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="message-actions">
                                    <!-- Coin Balance Display -->
                                    <div class="message-coin-balance" title="Your coin balance">
                                        <img src="<?php echo SUD_IMG_URL; ?>/sud-coin.png" alt="SUD" style="width: 18px; height: 18px; margin-right: 4px;" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHZpZXdCb3g9IjAgMCAxOCAxOCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSI5IiBjeT0iOSIgcj0iOSIgZmlsbD0iI0YyRDA0RiIvPjx0ZXh0IHg9IjMiIHk9IjEyIiBmb250LXNpemU9IjgiIGZvbnQtd2VpZ2h0PSJib2xkIiBmaWxsPSIjRkZGRkZGIj5MPC90ZXh0Pjwvc3ZnPg=='">
                                        <span class="coin-count" data-coin-balance><?php echo number_format($coin_balance); ?></span>
                                    </div>
                                    <button type="button" class="btn-icon" id="initiate-video-call" title="Video Call" data-user-id="<?php echo $active_chat_partner['id']; ?>" <?php echo ($viewer_blocked_partner || $partner_blocked_viewer) ? 'disabled' : ''; ?>> <i class="fas fa-video"></i> </button>
                                    <button type="button" class="btn-icon" id="send-gift" title="Send Gift" data-user-id="<?php echo $active_chat_partner['id']; ?>" <?php echo ($viewer_blocked_partner || $partner_blocked_viewer) ? 'disabled' : ''; ?>> <i class="fas fa-gift"></i> </button>
                                    <a href="<?php echo SUD_URL; ?>/pages/profile?id=<?php echo $active_chat_partner['id']; ?>" class="btn-icon" title="View Profile"> <i class="fas fa-user"></i> </a>
                                    <div class="dropdown">
                                        <button type="button" class="btn-icon message-dropdown-toggle" title="More Options"> <i class="fas fa-ellipsis-v"></i> </button>
                                        <div class="dropdown-menu">
                                            <a href="#" class="dropdown-item" id="view-blocked-list"> <i class="fas fa-user-slash"></i> Blocked Users </a>
                                            <hr style="margin: 5px 0; border-color: var(--sud-border);">
                                            <?php if ($viewer_blocked_partner): ?>
                                                <a href="#" class="dropdown-item" id="unblock-user-dropdown" data-user-id="<?php echo $active_chat_partner['id']; ?>"> <i class="fas fa-check-circle"></i> Unblock <?php echo esc_html($active_chat_partner['name']); ?> </a>
                                            <?php else: ?>
                                                <a href="#" class="dropdown-item" id="block-user" data-user-id="<?php echo $active_chat_partner['id']; ?>"> <i class="fas fa-ban"></i> Block <?php echo esc_html($active_chat_partner['name']); ?> </a>
                                            <?php endif; ?>

                                            <a href="#" class="dropdown-item" id="report-user" data-user-id="<?php echo $active_chat_partner['id']; ?>"> <i class="fas fa-flag"></i> Report <?php echo esc_html($active_chat_partner['name']); ?> </a>
                                            <a href="#" class="dropdown-item" id="clear-messages" data-user-id="<?php echo $active_chat_partner['id']; ?>"> <i class="fas fa-trash-alt"></i> Clear Conversation </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($viewer_blocked_partner || $partner_blocked_viewer): ?>
                                <div class="blocked-overlay">
                                    <?php if ($viewer_blocked_partner): ?>
                                        <p><i class="fas fa-ban"></i> You have blocked <?php echo esc_html($active_chat_partner['name']); ?>. Unblock them to send messages or gifts.</p>
                                        <button type="button" class="btn btn-secondary" id="unblock-user-overlay" data-user-id="<?php echo $active_chat_partner['id']; ?>">Unblock <?php echo esc_html($active_chat_partner['name']); ?></button>
                                    <?php elseif ($partner_blocked_viewer): ?>
                                        <p><i class="fas fa-shield-alt"></i> <?php echo esc_html($active_chat_partner['name']); ?> has blocked you. You cannot send messages or gifts.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="message-content">
                                 <?php if ($is_new_conversation && !$is_viewing_blocked_user): ?>
                                    <div class="no-messages-yet"> <p>Start a conversation with <?php echo esc_html($active_chat_partner['name']); ?>!</p> </div>
                                 <?php elseif (empty($active_thread_messages) && !$is_viewing_blocked_user): ?>
                                    <div class="loading-spinner" style="text-align:center; padding: 50px;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
                                    <?php elseif (!$is_viewing_blocked_user): ?>
                                    <?php
                                   
                                    $last_date_rendered = null;
                                    $gift_prefix = "SUD_GIFT::";

                                    foreach($active_thread_messages as $msg):
                                        if (!isset($msg->timestamp) || !isset($msg->message) || !isset($msg->sender_id)) continue;
                                        $msg_time = strtotime($msg->timestamp);
                                        if ($msg_time === false) continue;
                                        $msg_date = date('Y-m-d', $msg_time);

                                        if ($msg_date !== $last_date_rendered) {
                                            $today_date = date('Y-m-d'); $yesterday_date = date('Y-m-d', strtotime('-1 day'));
                                            if ($msg_date == $today_date) $display_date = 'Today';
                                            elseif ($msg_date == $yesterday_date) $display_date = 'Yesterday';
                                            else $display_date = date('F j, Y', $msg_time);
                                            echo '<div class="date-separator"><span>' . esc_html($display_date) . '</span></div>';
                                            $last_date_rendered = $msg_date;
                                        }

                                        $formatted_time = date('h:i A', $msg_time);
                                        $message_content = stripslashes($msg->message);
                                        $sender_id = (int)$msg->sender_id;
                                        $is_outgoing = ($sender_id === $current_user_id);

                                        if (strpos($message_content, $gift_prefix) === 0) {
                                            $parts = explode('::', substr($message_content, strlen($gift_prefix)));
                                            // NEW Format: {gift_id}::{sender_id}::{receiver_id}::{icon_or_url}::{name}::{cost}::{usd_value}
                                            if (count($parts) >= 7) {
                                                $gift_sender_id = intval($parts[1]);
                                                $gift_receiver_id = intval($parts[2]);
                                                $display_element = $parts[3];
                                                $gift_name = $parts[4];
                                                $gift_cost = intval($parts[5]);
                                                $gift_usd_value = number_format((float)$parts[6], 2);
                                                $is_sender_viewing_this_gift = ($gift_sender_id === $current_user_id);
            
                                                $display_text = $is_sender_viewing_this_gift
                                                    ? "You sent " . esc_html($gift_name)
                                                    : ( ($active_chat_partner ? esc_html($active_chat_partner['name']) : 'Someone') . " sent you " . esc_html($gift_name) );
                                                $icon_html = '';
                                                if (filter_var($display_element, FILTER_VALIDATE_URL) && (strpos($display_element, '.png') !== false || strpos($display_element, '.webp') !== false)) {
                                                    $icon_html = '<img src="' . esc_url($display_element) . '" alt="' . esc_attr($gift_name) . '" class="gift-image-in-message">';
                                                } elseif (preg_match('/^\s?fa[srbld]?\sfa-/', $display_element) || preg_match('/^(fas|far|fab|fal|fad)\s/', $display_element)) {
                                                    $safe_icon_classes = preg_replace('/[^a-z0-9\s\-]/i', '', trim($display_element));
                                                    $icon_html = '<i class="' . esc_attr($safe_icon_classes) . '" style="font-size: 1.8em; margin-right: 10px; vertical-align: middle;"></i>';
                                                } elseif (!empty($display_element)) {
                                                    $icon_html = '<span style="font-size: 1.8em; margin-right: 10px; vertical-align: middle;">' . esc_html($display_element) . '</span>';
                                                } else {
                                                    $icon_html = '<span style="font-size: 1.8em; margin-right: 10px; vertical-align: middle;">🎁</span>';
                                                }

                                                $details_action_html = '';
                                                if ($gift_receiver_id === $current_user_id) {
                                                    $value_details_html = '<span class="gift-value-details">(' . esc_html($gift_cost) . ' <img src="' . SUD_IMG_URL . '/sud-coin.png" alt="c" class="coin-xxs"> / ~$' . esc_html($gift_usd_value) . ' USD)</span>';
                                                    $withdraw_button_html = '
                                                        <button class="btn-withdraw-gift" data-gift-log-id="unknown_php_' . $msg->id . '" data-gift-value-usd="' . esc_attr($gift_usd_value) . '" title="Withdraw $' . esc_attr($gift_usd_value) . '">
                                                            <i class="fas fa-hand-holding-usd"></i> Withdraw
                                                        </button>';
                                                    $details_action_html = '
                                                    <div class="gift-details-action">
                                                        ' . $value_details_html . '
                                                        ' . $withdraw_button_html . '
                                                    </div>';
                                                }
                                                ?>

                                                <div class="system-message gift-message <?php echo $is_sender_viewing_this_gift ? 'gift-sent' : 'gift-received'; ?>" data-id="<?php echo $msg->id; ?>" data-date-raw="<?php echo $msg_date; ?>">
                                                    <div class="gift-message-main">
                                                    <?php echo $icon_html; ?>
                                                            <span style="vertical-align: middle;"><?php echo $display_text; ?></span>
                                                            <span class="message-time system-time"><?php echo esc_html($formatted_time); ?></span>
                                                        </div>
                                                        <?php echo $details_action_html; ?>
                                                    </div>
                                                    <?php
                                                } else {
                                                ?>
                                                <div class="system-message" data-id="<?php echo $msg->id; ?>" data-date-raw="<?php echo $msg_date; ?>">
                                                    <span>System event could not be displayed.</span>
                                                    <span class="message-time system-time"><?php echo esc_html($formatted_time); ?></span>
                                                </div>
                                                <?php
                                            }
                                        } else {
                                            $bubble_class = $is_outgoing ? 'outgoing' : 'incoming';
                                            $sender_pic = $is_outgoing ? '' : esc_url($active_chat_partner['profile_pic']);
                                            $sender_name = $is_outgoing ? '' : esc_attr($active_chat_partner['name']);
                                            $safe_message_html = nl2br(esc_html($message_content));
                                            ?>
                                            <div class="message-bubble <?php echo $bubble_class; ?>" data-id="<?php echo $msg->id; ?>" data-date-raw="<?php echo $msg_date; ?>">
                                                <?php if (!$is_outgoing): ?>
                                                    <img src="<?php echo $sender_pic; ?>" alt="<?php echo $sender_name; ?>" class="message-avatar" onerror="this.src='<?php echo SUD_IMG_URL; ?>/default-profile.jpg';">
                                                <?php endif; ?>
                                                <div class="message-text">
                                                    <p><?php echo $safe_message_html; ?></p>
                                                    <span class="message-time"><?php echo esc_html($formatted_time); ?></span>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                    endforeach; ?>
                             <?php endif; ?>
                            </div>

                            <div class="gift-drawer-container collapsed" id="gift-drawer">
                                <div class="gift-drawer-scrollable">
                                    <div class="gift-drawer-items" id="gift-drawer-items">
                                        <!-- Gifts loaded by JS here -->
                                        <div class="gift-placeholder">Loading gifts... <i class="fas fa-spinner fa-spin"></i></div>
                                    </div>
                                </div>
                                <?php if (!$is_viewing_blocked_user): ?>
                                <button class="gift-drawer-toggle" id="gift-drawer-toggle" title="Show More Gifts">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                                <?php endif; ?>
                            </div>

                            <div class="message-input <?php echo ($viewer_blocked_partner || $partner_blocked_viewer) ? 'disabled-input' : ''; ?>">
                                <form id="message-form">
                                    <input type="hidden" name="receiver_id" id="receiver-id" value="<?php echo $active_chat_partner['id']; ?>">
                                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('sud_ajax_action'); ?>">
                                    <div class="emoji-toggle" id="emoji-button" title="Insert Emoji"> <i class="far fa-smile"></i> </div>
                                    <div class="emoji-picker-container" id="emoji-picker-container"> <div class="simple-emoji-grid"></div> </div>
                                    <textarea name="message_text" id="message-input"
                                            placeholder="<?php
                                                if ($viewer_blocked_partner) {
                                                    echo 'You blocked this user';
                                                } elseif ($partner_blocked_viewer) {
                                                    echo 'You are blocked by this user';
                                                } else {
                                                    echo 'Type your message...';
                                                }
                                            ?>"
                                            required
                                            <?php echo ($viewer_blocked_partner || $partner_blocked_viewer) ? 'disabled' : ''; ?>></textarea>
                                    <button type="submit" class="send-btn" title="Send Message" <?php echo ($viewer_blocked_partner || $partner_blocked_viewer) ? 'disabled' : ''; ?>> <i class="fas fa-paper-plane"></i> </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="no-conversation-selected">
                                <?php if (empty($conversation_threads)): ?>
                                    <div class="empty-messages-container">
                                        <div class="empty-messages-icon">
                                             <img src="<?php echo SUD_IMG_URL; ?>/message-icon.png" alt="No Messages" onerror="this.style.display='none'">
                                             <i class="far fa-comment-dots fa-3x" style="color:#ccc; <?php if(file_exists(dirname(__FILE__, 2).'/assets/img/message-icon.png')) echo 'display:none;'; ?>"></i>
                                        </div>
                                        <h3>No messages yet</h3>
                                        <p>Start a conversation with other members.</p>
                                        <a href="<?php echo SUD_URL; ?>/pages/search" class="btn-primary">Browse Members</a>
                                    </div>
                                <?php else: ?>
                                    <div class="select-conversation">
                                        <div class="select-conversation-icon"> <i class="far fa-comments fa-3x"></i> </div>
                                        <h3>Select a conversation</h3>
                                        <p>Choose someone from the list to view your chat history.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modals -->
    <div id="block-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <h3>Block <?php echo esc_html($active_chat_partner['name'] ?? 'User'); ?></h3>
            <p>Are you sure you want to block <strong id="block-username"><?php echo esc_html($active_chat_partner['name'] ?? 'User'); ?></strong>? You will no longer see their messages or profile, and they won't see yours.</p>
            <div class="modal-actions">
                <button id="confirm-block-btn" class="btn btn-danger">Block <?php echo esc_html($active_chat_partner['name'] ?? 'User'); ?></button>
                <button class="btn btn-secondary close-modal-btn">Cancel</button>
            </div>
        </div>
    </div>

    <div id="report-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <h3>Report <?php echo esc_html($active_chat_partner['name'] ?? 'User'); ?></h3>
            <p>Please select a reason for reporting <strong id="report-username"><?php echo esc_html($active_chat_partner['name'] ?? 'User'); ?></strong>:</p>
            <form id="report-user-form">
                <input type="hidden" id="reported-user-id-modal" name="reported_user_id">
                <div class="form-group">
                    <select id="report-reason" name="report_reason" class="form-input" required>
                        <option value="">Select a reason...</option>
                        <option value="spam">Spam or unsolicited promotion</option>
                        <option value="harassment">Harassment or abusive behavior</option>
                        <option value="inappropriate_content">Inappropriate photos or content</option>
                        <option value="fake_profile">Fake profile or scam</option>
                        <option value="underage">User appears to be underage</option>
                        <option value="other">Other (please specify)</option>
                    </select>
                </div>
                <div class="form-group" id="report-other-reason-group" style="display: none;">
                    <label for="report-other-reason">Details:</label>
                    <textarea id="report-other-reason" name="report_other_reason" class="form-input" rows="3"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-danger">Submit Report</button>
                    <button type="button" class="btn btn-secondary close-modal-btn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="clear-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <h3>Clear Conversation</h3>
            <p>Are you sure you want to clear all messages in this conversation? This action cannot be undone.</p>
            <div class="modal-actions">
                <button id="confirm-clear-btn" class="btn btn-danger">Clear Messages</button>
                <button class="btn btn-secondary close-modal-btn">Cancel</button>
            </div>
        </div>
    </div>

    <div id="gift-modal" class="modal gift-modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <h3>Send a Gift to <span id="gift-recipient-name">User</span> <i class="fas fa-gift"></i></h3>
            <div id="gift-selection-area" class="gift-grid">
                <!-- Gifts loaded by JS -->
            </div>
            <div class="gift-purchase-summary">
                <p>Balance: <span id="gift-modal-balance"><?php echo number_format($header_data['coin_balance']); ?></span> <img src="<?php echo SUD_IMG_URL; ?>/sud-coin.png" alt="coins" class="coin-xs"></p>
                <p id="gift-modal-cost" style="display:none; font-weight: bold;">Cost: <span id="gift-cost-value">0</span> <img src="<?php echo SUD_IMG_URL; ?>/sud-coin.png" alt="coins" class="coin-xs"></p>
            </div>
            <div class="modal-actions">
                <button id="send-gift-btn" class="btn btn-primary" disabled>Send Gift</button>
                <a href="<?php echo SUD_URL; ?>/pages/wallet" class="btn btn-secondary">Buy Coins</a>
            </div>
        </div>
    </div>
    
    <div id="blocked-users-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <h3>Blocked Users</h3>
            <div id="blocked-users-list-container">
                <!-- Blocked users will be loaded here -->
                <p class="loading-text">Loading blocked users...</p>
            </div>
             <div class="modal-actions">
                <button type="button" class="btn btn-secondary close-modal-btn">Close</button>
            </div>
        </div>
    </div>

    <div id="upgrade-prompt-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <div class="modal-icon"><i class="fas fa-crown"></i></div>
            <h3 id="upgrade-prompt-title">Upgrade for Full Access</h3>
            <p id="upgrade-prompt-message">This feature is available for premium members. Upgrade your plan to enjoy this and other exclusive benefits!</p>
            <div class="modal-actions">
                <a href="<?php echo SUD_URL . '/pages/premium'; ?>" class="btn btn-primary btn-upgrade-action">Upgrade Now</a>
                <button class="btn btn-secondary close-modal-btn">Maybe Later</button>
            </div>
        </div>
    </div>

</body>
</html>