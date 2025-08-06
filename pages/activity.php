<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_login();

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

validate_core_profile_requirements($current_user_id, 'activity');
$display_name = $current_user->display_name;

require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/premium-functions.php'); 
require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/swipe-functions.php');

$is_current_user_premium = sud_is_user_premium($current_user_id);
$can_view_profiles = sud_user_can_access_feature($current_user_id, 'viewed_profile');

$my_favorites_data = get_user_favorites($current_user_id);
$favorited_me_users = get_who_favorited_me($current_user_id);
$profile_viewers_data = get_profile_viewers($current_user_id); 
$notifications = get_user_notifications($current_user_id, 50);

// Check if swipe-related tables exist before querying
global $wpdb;
$swipes_table_name = $wpdb->prefix . 'sud_user_swipes';
$likes_table_name = $wpdb->prefix . 'sud_user_likes';
$swipes_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$swipes_table_name'") == $swipes_table_name;
$likes_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$likes_table_name'") == $likes_table_name;

if ($swipes_table_exists) {
    $users_who_liked_list = sud_get_users_who_liked($current_user_id);
    $user_likes_received_count = sud_get_user_likes_received_count($current_user_id);
} else {
    $users_who_liked_list = [];
    $user_likes_received_count = 0;
    error_log("SUD Warning: sud_user_swipes table does not exist. Likes received disabled.");
}

if ($likes_table_exists) {
    $user_instant_matches_list = sud_get_user_instant_matches($current_user_id);
} else {
    $user_instant_matches_list = [];
    error_log("SUD Warning: sud_user_likes table does not exist. Instant matches disabled.");
}

$user_instant_match_count = count($user_instant_matches_list);
$instant_match_ids = array_column($user_instant_matches_list, 'id');

$all_matches_list = sud_get_user_matches($current_user_id);

$user_matches_list = array_filter($all_matches_list, function($match) use ($instant_match_ids) {
    return isset($match['id']) && !in_array($match['id'], $instant_match_ids);
});
$regular_match_count = count($user_matches_list); 

if (isset($_GET['tab']) && $_GET['tab'] === 'notifications') {
    mark_all_notifications_read($current_user_id);
}

$user_messages = get_user_messages($current_user_id);
$unread_message_count = $user_messages['unread_count'];
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'notifications';

$tab_titles = [
    'notifications' => 'My Notifications',
    'profile-views' => 'Profile Views',
    'favorited-me' => 'Who Favorited Me',
    'my-favorites' => 'My Favorites',
    'my-matches' => 'My Matches',
    'instant-matches' => 'Instant Matches',
    'likes-received' => 'Likes Received'
];

$page_title = $tab_titles[$active_tab] ?? 'Activity Center';
$premium_page_url = SUD_URL . '/pages/premium'; 
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
    <title><?php echo $page_title; ?> - <?php echo esc_html(SUD_SITE_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/activity.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/swipe.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/ban-system.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
    <style>
    @keyframes confetti-fall {
        0% {
            transform: translateY(-100vh) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(100vh) rotate(720deg);
            opacity: 0;
        }
    }
    
    #confetti-container {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: hidden;
    }
    </style>
</head>
<body>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>
    <div id="toast-container" class="toast-container"></div>

    <main class="main-content activity-content">
        <div class="container">
            <h1><?php echo $page_title; ?></h1>

            <div class="activity-tabs">
                 <a href="?tab=notifications" class="activity-tab <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i> 
                    <span class="tab-text">Notifications</span>
                </a>
                <a href="?tab=profile-views" class="activity-tab <?php echo $active_tab === 'profile-views' ? 'active' : ''; ?> <?php echo !$can_view_profiles ? 'locked-tab' : ''; ?>">
                    <i class="fas fa-eye"></i> 
                    <span class="tab-text">Views</span>
                    <?php if (!$can_view_profiles): ?>
                        <i class="fas fa-lock tab-lock-icon" title="Upgrade to Gold or higher to access this feature"></i>
                    <?php endif; ?>
                </a>
                <a href="?tab=favorited-me" class="activity-tab <?php echo $active_tab === 'favorited-me' ? 'active' : ''; ?> <?php echo !$can_view_profiles ? 'locked-tab' : ''; ?>">
                    <i class="fas fa-heart"></i> 
                    <span class="tab-text">Favorited</span>
                     <?php if (!$can_view_profiles): ?>
                        <i class="fas fa-lock tab-lock-icon" title="Upgrade to Gold or higher to access this feature"></i>
                    <?php endif; ?>
                </a>
                <a href="?tab=my-favorites" class="activity-tab <?php echo $active_tab === 'my-favorites' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i> 
                    <span class="tab-text">Favorites</span>
                </a>
                <a href="?tab=my-matches" class="activity-tab <?php echo $active_tab === 'my-matches' ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends"></i> 
                    <span class="tab-text">Matches</span>
                    <span class="count-badge"><?php echo esc_html($regular_match_count); ?></span>
                </a>
                <a href="?tab=instant-matches" class="activity-tab <?php echo $active_tab === 'instant-matches' ? 'active' : ''; ?>">
                    <i class="fas fa-bolt"></i> 
                    <span class="tab-text">Instant</span>
                    <span class="count-badge"><?php echo esc_html($user_instant_match_count); ?></span>
                </a>
                <a href="?tab=likes-received" class="activity-tab <?php echo $active_tab === 'likes-received' ? 'active' : ''; ?> <?php echo !$is_current_user_premium && !empty($users_who_liked_list) ? 'locked-tab' : ''; ?>">
                    <i class="fas fa-thumbs-up"></i> 
                    <span class="tab-text">Liked</span>
                    <span class="count-badge"><?php echo esc_html($user_likes_received_count); ?></span>
                    <?php if (!$is_current_user_premium && !empty($users_who_liked_list)): ?>
                        <i class="fas fa-lock tab-lock-icon" title="Upgrade to Gold or higher to see who liked you"></i>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Notifications Tab -->
            <div id="notifications-tab" class="tab-content <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>">
                <?php if (!empty($notifications)): ?>
                    <div class="notification-actions" style="margin-bottom: 20px; text-align: right;">
                        <button id="clear-all-notifications" class="btn btn-secondary" style="background: #262626; color: #FFFFFF; border: 1px solid #404040; padding: 8px 16px; border-radius: 8px; font-size: 14px; cursor: pointer;">
                            <i class="fas fa-trash"></i> Clear All Notifications
                        </button>
                    </div>
                <?php endif; ?>
                <div class="notification-list">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach($notifications as $notification): ?>
                            <?php 
                                // Use centralized notification display function
                                $notification_data = get_notification_display_data($notification, $current_user_id);
                            ?>
                            <div class="notification-item <?php 
                                if ($notification_data['is_premium_teaser']) {
                                    echo 'premium-teaser';
                                }
                            ?>" data-id="<?php echo $notification->id; ?>" data-type="<?php echo $notification->type; ?>" data-related-id="<?php echo $notification->related_id; ?>" <?php if ($notification_data['is_premium_teaser']): ?>data-upgrade-url="<?php echo esc_url($notification_data['upgrade_url']); ?>"<?php endif; ?>>
                                <?php echo $notification_data['avatar_html']; ?>
                                <div class="notification-content">
                                    <div class="notification-content-text"><?php echo esc_html($notification->content); ?></div>
                                    <div class="notification-time"><?php echo $notification_data['time_ago']; ?></div>
                                </div>
                                <?php if ($notification_data['is_premium_teaser']): ?>
                                    <span class="premium-indicator"><i class="fas fa-lock"></i></span>
                                <?php endif; ?>
                                <?php if (!$notification_data['is_read']): ?>
                                    <span class="unread-indicator"></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-items">
                            <i class="far fa-bell"></i>
                            <h3>No Notifications Yet</h3>
                            <p>Your notifications will appear here when someone views your profile, sends you a message, or adds you to their favorites.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Views Tab -->
            <div id="profile-views-tab" class="tab-content <?php echo $active_tab === 'profile-views' ? 'active' : ''; ?> <?php echo !$can_view_profiles ? 'locked-content' : ''; ?>">
                <?php if ($can_view_profiles): ?>
                    <?php if (!empty($profile_viewers_data)): ?>
                        <div class="user-list">
                            <?php
                            $included_cards = [];
                            foreach($profile_viewers_data as $viewer_id => $view_timestamp):
                                $user = get_user_profile_data($viewer_id);
                                if (!$user) continue;
                                $activity_type = 'profile_view';
                                $timestamp = $view_timestamp;
                                $card_id = 'pv-' . $user['id']; 
                                if (!in_array($card_id, $included_cards)) {
                                    $is_card_visible_to_user = true; 
                                    include(dirname(__FILE__, 2) . '/templates/components/user-card-list.php');
                                    $included_cards[] = $card_id;
                                }
                            ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-items">
                            <i class="far fa-eye"></i>
                            <h3>No Profile Views</h3>
                            <p>No one has viewed your profile yet. Complete your profile to increase your visibility.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if (!empty($profile_viewers_data)): ?>
                        <div class="user-list">
                            <?php
                            $included_cards = [];
                            $display_count = 0;
                            $max_blurred_display = 6; 

                            foreach($profile_viewers_data as $viewer_id => $view_timestamp):
                                if ($display_count >= $max_blurred_display) break;
                                $user = get_user_profile_data($viewer_id);
                                if (!$user) continue;
                                $activity_type = 'profile_view';
                                $timestamp = $view_timestamp;
                                $card_id = 'pv-blurred-' . $user['id']; 
                                $is_card_visible_to_user = false;

                                if (!in_array($card_id, $included_cards)):
                            ?>
                                <div class="locked-user-card" data-upgrade-url="<?php echo esc_url($premium_page_url); ?>?highlight_plan=gold">
                                    <?php 
                                        include(dirname(__FILE__, 2) . '/templates/components/user-card-list.php');
                                        $included_cards[] = $card_id;
                                        $display_count++;
                                    ?>
                                </div>
                            <?php 
                                endif;
                            endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-items">
                            <i class="far fa-eye"></i>
                            <h3>No Profile Views</h3>
                            <p>No one has viewed your profile yet. Upgrade to Gold to see who views you in the future!</p>
                            <a href="<?php echo esc_url($premium_page_url); ?>?highlight_plan=gold" class="btn btn-primary" style="margin-top: 15px;">Upgrade to Gold</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Favorited Me Tab -->
            <div id="favorited-me-tab" class="tab-content <?php echo $active_tab === 'favorited-me' ? 'active' : ''; ?> <?php echo !$can_view_profiles ? 'locked-content' : ''; ?>">
                 <?php if ($can_view_profiles): ?>
                    <?php if (!empty($favorited_me_users)): ?>
                        <div class="user-list">
                            <?php
                             $included_cards = [];
                             foreach($favorited_me_users as $user_fav):
                                if (!$user_fav || empty($user_fav['id'])) continue;

                                $user = $user_fav;
                                $activity_type = 'favorite_me';
                                $timestamp = $user['favorited_at'];
                                $card_id = 'fm-' . $user['id'];
                                if (!in_array($card_id, $included_cards)) {
                                     $is_card_visible_to_user = true; 
                                    include(dirname(__FILE__, 2) . '/templates/components/user-card-list.php');
                                     $included_cards[] = $card_id;
                                }
                             ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-items">
                            <i class="far fa-heart"></i>
                            <h3>No Favorites Yet</h3>
                            <p>No one has favorited your profile yet. Complete your profile to attract more attention.</p>
                        </div>
                    <?php endif; ?>
                 <?php else: ?>
                     <?php if (!empty($favorited_me_users)): ?>
                        <div class="user-list">
                            <?php
                            $included_cards = [];
                            $display_count = 0;
                            $max_blurred_display = 6; 

                            foreach($favorited_me_users as $user_fav):
                                if ($display_count >= $max_blurred_display) break;
                                if (!$user_fav || empty($user_fav['id'])) continue;

                                $user = $user_fav;
                                $timestamp = $user_fav['favorited_at'];
                                $activity_type = 'favorite_me'; 
                                $card_id = 'fm-blurred-' . $user_fav['id'];
                                $is_card_visible_to_user = false; 

                                if (!in_array($card_id, $included_cards)):
                            ?>
                                <div class="locked-user-card" data-upgrade-url="<?php echo esc_url($premium_page_url); ?>?highlight_plan=gold">
                                    <?php 
                                        include(dirname(__FILE__, 2) . '/templates/components/user-card-list.php');
                                        $included_cards[] = $card_id;
                                        $display_count++;
                                    ?>
                                </div>
                            <?php 
                                endif;
                            endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-items">
                            <i class="far fa-heart"></i>
                            <h3>No Favorites Yet</h3>
                            <p>No one has favorited your profile yet. Upgrade to Gold to see who favorites you!</p>
                            <a href="<?php echo esc_url($premium_page_url); ?>?highlight_plan=gold" class="btn btn-primary" style="margin-top: 15px;">Upgrade to Gold</a>
                        </div>
                    <?php endif; ?>
                 <?php endif; ?>
            </div>

            <!-- My Favorites Tab -->
            <div id="my-favorites-tab" class="tab-content <?php echo $active_tab === 'my-favorites' ? 'active' : ''; ?>">
                <?php if (!empty($my_favorites_data)): ?>
                    <div class="user-list">
                         <?php
                         $included_cards = [];
                         foreach($my_favorites_data as $fav_item):
                            $user = get_user_profile_data($fav_item['id']); 
                            if (!$user) continue;
                            $activity_type = 'my_favorite';
                            $timestamp = $fav_item['favorited_at'];
                            $card_id = 'mf-' . $user['id'];
                            if (!in_array($card_id, $included_cards)) {
                                $is_card_visible_to_user = true; 
                                include(dirname(__FILE__, 2) . '/templates/components/user-card-list.php');
                                $included_cards[] = $card_id;
                            }
                         ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        <i class="far fa-star"></i>
                        <h3>No Favorites</h3>
                        <p>You haven't added any favorites yet. Browse profiles and click the heart icon to add them.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Matches Tab -->
            <div id="my-matches-tab" class="tab-content <?php echo $active_tab === 'my-matches' ? 'active' : ''; ?>">
                <?php if (!empty($user_matches_list)): ?>
                    <div class="user-list">
                        <?php
                        $included_cards = [];
                        foreach($user_matches_list as $match_user):
                            if (!$match_user || empty($match_user['id'])) continue;
                            $user = $match_user;
                            $activity_type = 'my_match';
                            $timestamp = $user['matched_at'] ?? time();
                            $card_id = 'match-' . $user['id'];
                            if (!in_array($card_id, $included_cards)) {
                                $is_card_visible_to_user = true; 
                                include(dirname(__FILE__, 2) . '/templates/components/user-card-list.php');
                                $included_cards[] = $card_id;
                            }
                        ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        <i class="fas fa-user-friends"></i>
                        <h3>No Matches Yet</h3>
                        <p>You haven't matched with anyone yet. Keep swiping to find your connections!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Instant Matches Tab -->
            <div id="instant-matches-tab" class="tab-content <?php echo $active_tab === 'instant-matches' ? 'active' : ''; ?>">
                <?php if (!empty($user_instant_matches_list)): ?>
                    <div class="user-list">
                        <?php
                        $included_cards = [];
                        foreach($user_instant_matches_list as $instant_match_user):
                            if (!$instant_match_user || empty($instant_match_user['id'])) continue;
                            $user = $instant_match_user;
                            $activity_type = 'instant_match';
                            $timestamp = $user['matched_at'] ?? time();
                            $card_id = 'instant-match-' . $user['id'];

                            if (in_array($card_id, $included_cards)) continue;
                            $included_cards[] = $card_id;

                            $is_card_visible_to_user = true;
                            include(dirname(__FILE__, 2) . '/templates/components/user-card-list.php');
                        endforeach;
                        ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        <i class="fas fa-bolt" style="font-size: 3rem; color: #f39c12; margin-bottom: 1rem;"></i>
                        <h3>No Instant Matches</h3>
                        <p>Use Swipe Up on the Discover page to instantly match with someone you like!</p>
                        <a href="<?php echo SUD_URL; ?>/pages/swipe" class="btn btn-primary" style="margin-top: 1rem;">Start Swiping</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Likes Received Tab -->
            <div id="likes-received-tab" class="tab-content <?php echo $active_tab === 'likes-received' ? 'active' : ''; ?> <?php echo !$is_current_user_premium && !empty($users_who_liked_list) ? 'locked-content' : ''; ?>">
                <?php if (!$is_current_user_premium && !empty($users_who_liked_list)): ?>
                    <div class="user-list">
                        <?php
                        $included_cards = [];
                        $display_count = 0;
                        $max_blurred_display = 6; 

                        foreach($users_who_liked_list as $liked_user):
                            if ($display_count >= $max_blurred_display) break;
                            if (!$liked_user || empty($liked_user['id'])) continue;

                            $user = $liked_user; 
                            $timestamp = $liked_user['liked_at'] ?? time();
                            $activity_type = 'like_received'; 
                            $card_id = 'lr-blurred-' . $user['id'];
                            $is_card_visible_to_user = false; 

                            if (!in_array($card_id, $included_cards)):
                        ?>
                            <div class="locked-user-card" data-upgrade-url="<?php echo esc_url($premium_page_url); ?>?highlight_plan=gold">
                                <?php 
                                    include(dirname(__FILE__, 2) . '/templates/components/user-card-list.php');
                                    $included_cards[] = $card_id;
                                    $display_count++;
                                ?>
                            </div>
                        <?php 
                            endif;
                        endforeach; 

                        if (count($users_who_liked_list) > $max_blurred_display): ?>
                            <div class="blurred-user-card-list-more">
                                 <a href="<?php echo esc_url($premium_page_url); ?>?highlight_plan=gold" class="upgrade-prompt-overlay-more">
                                     <i class="fas fa-thumbs-up"></i>
                                     <span>+<?php echo count($users_who_liked_list) - $max_blurred_display; ?> More Likes</span>
                                     <span class="upgrade-cta">Upgrade to See All</span>
                                 </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($is_current_user_premium && !empty($users_who_liked_list)): ?>
                    <div class="user-list">
                        <?php
                        $included_cards = [];
                        foreach($users_who_liked_list as $liked_user):
                            if (!$liked_user || empty($liked_user['id'])) continue;
                            $user = $liked_user; 
                            $activity_type = 'like_received';
                            $timestamp = $user['liked_at'] ?? time();
                            $card_id = 'lr-' . $user['id'];
                            if (!in_array($card_id, $included_cards)) {
                                $is_card_visible_to_user = true;
                                include(dirname(__FILE__, 2) . '/templates/components/user-card-list.php');
                                $included_cards[] = $card_id;
                            }
                        ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        <i class="far fa-thumbs-up"></i>
                        <h3>No Likes Received Yet</h3>
                        <p>No one has liked your profile yet. Make sure your profile is complete and engaging!</p>
                        <?php if (!$is_current_user_premium): ?>
                            <p>Upgrade to Gold to see who likes you as soon as they do!</p>
                            <a href="<?php echo esc_url($premium_page_url); ?>?highlight_plan=gold" class="btn btn-primary" style="margin-top: 15px;">Upgrade to Gold</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Match Notification Modal -->
    <div id="match-notification-modal" class="modal match-modal">
        <div class="sud-modal-content">
            <div class="match-animation-container">
                <img id="match-user1-img" src="<?php echo esc_url(get_user_meta($current_user_id, 'profile_picture', true) ? wp_get_attachment_image_url(get_user_meta($current_user_id, 'profile_picture', true), 'thumbnail') : SUD_IMG_URL . '/default-profile.jpg'); ?>" alt="Your Profile Picture">
                <img id="match-user2-img" src="<?php echo esc_url(SUD_IMG_URL . '/default-profile.jpg'); ?>" alt="Matched User Profile Picture">
                <div class="match-heart-icon">
                    <i class="fas fa-heart"></i>
                </div>
            </div>
            <h2 id="match-modal-title">It's a Match!</h2>
            <p id="match-modal-description">You and <strong id="match-user-name">User</strong> have liked each other.</p>
            <div class="modal-actions">
                <a href="#" id="match-send-message-btn" class="btn btn-primary">Send a Message</a>
                <button type="button" id="match-keep-swiping-btn" class="btn btn-secondary close-modal-btn">Continue</button>
            </div>
            <div id="confetti-container"></div>
        </div>
    </div>
    
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-footer.php'); ?>
    
    <script>
    // Like Back functionality
    function showMatchNotification(matchData) {
        const modal = document.getElementById('match-notification-modal');
        const userNameEl = document.getElementById('match-user-name');
        const user2ImgEl = document.getElementById('match-user2-img');
        const messageBtn = document.getElementById('match-send-message-btn');
        
        if (matchData) {
            userNameEl.textContent = matchData.name;
            user2ImgEl.src = matchData.profile_pic;
            messageBtn.href = matchData.message_url;
        }
        
        modal.classList.add('show');
        
        // Add some confetti effect
        createConfetti();
    }
    
    function createConfetti() {
        const container = document.getElementById('confetti-container');
        if (!container) return;
        
        container.innerHTML = '';
        
        for (let i = 0; i < 20; i++) {
            const confetti = document.createElement('div');
            confetti.style.cssText = `
                position: absolute;
                width: 10px;
                height: 10px;
                background: ${['#ff4081', '#ff6b6b', '#35f5f1', '#ffdd59'][Math.floor(Math.random() * 4)]};
                left: ${Math.random() * 100}%;
                animation: confetti-fall ${2 + Math.random() * 3}s linear infinite;
                animation-delay: ${Math.random() * 2}s;
            `;
            container.appendChild(confetti);
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Like back button functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.like-back-btn')) {
                e.preventDefault();
                const btn = e.target.closest('.like-back-btn');
                const userId = btn.getAttribute('data-user-id');
                
                if (!userId) return;
                
                // Disable button and show loading
                btn.disabled = true;
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Matching...';
                
                // Send like back request
                fetch('<?php echo SUD_AJAX_URL; ?>/like-back.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'target_user_id=' + encodeURIComponent(userId)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.is_match && data.match_data) {
                            // Show match popup first
                            showMatchNotification(data.match_data);
                            
                            // Add event listener to refresh when modal closes
                            const modal = document.getElementById('match-notification-modal');
                            const refreshHandler = () => {
                                window.location.reload();
                            };
                            
                            // Refresh when user clicks "Continue" button
                            document.getElementById('match-keep-swiping-btn').addEventListener('click', refreshHandler, { once: true });
                            
                            // Also refresh if they click outside the modal to close it
                            modal.addEventListener('click', function(e) {
                                if (e.target === modal) {
                                    refreshHandler();
                                }
                            }, { once: true });
                            
                        } else {
                            // Just liked back, no match yet - refresh immediately to update tabs
                            window.location.reload();
                        }
                    } else {
                        // Error handling
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                        alert(data.message || 'Failed to like back. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    alert('An error occurred. Please try again.');
                });
            }
        });
        
        const clearButton = document.getElementById('clear-all-notifications');
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                if (confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
                    const button = this;
                    const originalText = button.innerHTML;
                    
                    // Show loading state
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';
                    button.disabled = true;
                    
                    fetch('<?php echo SUD_AJAX_URL; ?>/get-notifications.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=delete_all'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Reload the page to show empty state
                            window.location.reload();
                        } else {
                            alert('Failed to clear notifications. Please try again.');
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                        button.innerHTML = originalText;
                        button.disabled = false;
                    });
                }
            });
        }
    });
    </script>
</body>
</html>