<?php

if (!isset($user) || !is_array($user) || !isset($activity_type) || !isset($is_card_visible_to_user) || !isset($premium_page_url)) {
    return; 
}
$user_id = $user['id'] ?? 0;

if ($is_card_visible_to_user) {
    $name_raw           = $user['name'] ?? 'User ' . $user_id;
    $profile_pic        = $user['profile_pic'] ?? ($user['image'] ?? SUD_IMG_URL . '/default-profile.jpg');
    $is_online          = !empty($user['is_online']);
    $is_verified        = !empty($user['is_verified']);
    $is_favorite        = !empty($user['is_favorite']); 
    $target_tier_level  = $user['premium_tier_level'] ?? 0;
    $location_full      = isset($user['location_formatted']) && !empty($user['location_formatted'])
                            ? trim($user['location_formatted'])
                            : 'Location not specified';
    $location_display   = mb_strlen($location_full) > 25
                            ? mb_substr($location_full, 0, 22) . '...'
                            : $location_full;
} else {
    $name_raw           = 'Someone';
    $profile_pic        = SUD_IMG_URL . '/default-profile.jpg'; 
    $is_online          = false;
    $is_verified        = false;
    $is_favorite        = false; 
    $target_tier_level  = 0;
    $location_full      = 'Location hidden';
    $location_display   = 'Location hidden';
}

$timestamp = $timestamp ?? time();
$unix_timestamp = time();
if (is_numeric($timestamp)) {
    $unix_timestamp = $timestamp;
} elseif (is_string($timestamp)) {
    $converted_ts = strtotime($timestamp);
    if ($converted_ts !== false) {
        $unix_timestamp = $converted_ts;
    }
}
$time_ago = human_time_diff($unix_timestamp, current_time('timestamp')) . ' ago';

$base_activity_type = str_replace('_blurred', '', $activity_type);
$activity_message = '';
switch ($base_activity_type) {
    case 'favorite_me': $activity_message = 'Favorited you'; break;
    case 'my_favorite': $activity_message = 'You favorited'; break;
    case 'profile_view': $activity_message = 'Viewed your profile'; break;
    default: $activity_message = 'Interacted'; break;
}

$profile_url = ($user_id > 0 && defined('SUD_URL'))
                ? SUD_URL . '/pages/profile?id=' . $user_id
                : 'javascript:void(0);';

$user_id_attr      = esc_attr($user_id);
$profile_url_attr  = esc_attr($profile_url);
$profile_pic_url   = esc_url($profile_pic);
$name_html         = esc_html($name_raw);
$location_html     = esc_html($location_display);
$img_path_url      = esc_url(SUD_IMG_URL);
$target_tier_level_attr = esc_attr($target_tier_level);
$card_id_attr      = isset($card_id) ? esc_attr($card_id) : '';

?>

<div class="user-card-list <?php echo !$is_card_visible_to_user ? 'blurred-user-card-list' : ''; ?>"
     data-user-id="<?php echo $user_id_attr; ?>"
     <?php echo !empty($profile_url_attr) && $is_card_visible_to_user ? 'data-profile-url="' . $profile_url_attr . '"' : ''; ?>
     <?php echo !empty($card_id_attr) ? 'id="' . $card_id_attr . '"' : ''; ?>>

    <?php if (!$is_card_visible_to_user): ?>
        <a href="<?php echo esc_url($premium_page_url); ?>" class="upgrade-prompt-overlay">
            <i class="fas fa-lock"></i>
            <span>Upgrade to See</span>
        </a>
        <div class="user-card-list-container blurred-content">
    <?php else: ?>
        <div class="user-card-list-container">
    <?php endif; ?>

        <div class="user-card-list-avatar">
            <img src="<?php echo $profile_pic_url; ?>" alt="<?php echo esc_attr($name_raw); ?>" loading="lazy" onerror="this.src='<?php echo $img_path_url; ?>/default-profile.jpg';">
            <?php if ($is_online): ?>
                <span class="online-indicator" title="Online Now"></span>
            <?php endif; ?>
        </div>

        <div class="user-card-list-info">
            <div class="user-card-list-header">
                <div class="user-name-status">
                    <h4><?php echo $name_html; ?></h4>
                    <?php if ($is_verified): ?>
                        <span class="verified-badge" title="SUD Verified"><img src="<?php echo $img_path_url; ?>/verified-profile-badge.png" alt="verified" style="width: 16px; height: 16px; vertical-align: text-bottom; margin-left: 4px;"></span>
                    <?php endif; ?>
                </div>
                <div class="user-activity-time"><?php echo esc_html($time_ago); ?></div>
            </div>
            <div class="user-location" title="<?php echo esc_attr($location_full); ?>">
                <i class="fas fa-map-marker-alt"></i> <?php echo $location_html; ?>
            </div>
            <div class="user-activity">
                <span class="activity-type"><?php echo esc_html($activity_message); ?></span>
            </div>
        </div>

        <?php if ($is_card_visible_to_user): ?>
            <div class="user-card-list-actions">
                <?php
                $show_favorite_button = ($activity_type === 'my_favorite' || $activity_type === 'favorite_me');
                $show_like_back_button = ($activity_type === 'like_received');
                $current_user_fav_status = function_exists('is_user_favorite') ? is_user_favorite($user_id) : false;
                
                // Check if already matched - hide like back button if already matched
                if ($show_like_back_button && function_exists('sud_are_users_matched')) {
                    $current_user_id = get_current_user_id();
                    $are_already_matched = sud_are_users_matched($current_user_id, $user_id);
                    if ($are_already_matched) {
                        $show_like_back_button = false;
                    }
                }
                ?>
                <?php if ($show_like_back_button): ?>
                <button type="button"
                        class="like-back-btn"
                        data-user-id="<?php echo $user_id_attr; ?>"
                        title="Like Back - Match">
                    <i class="fas fa-heart"></i>
                    <span>Match</span>
                </button>
                <?php endif; ?>
                <?php if ($show_favorite_button): ?>
                <button type="button"
                        style="top:50px;"
                        class="favorite-btn user-favorite icon-only-display <?php echo $current_user_fav_status ? 'favorited' : ''; ?>"
                        data-user-id="<?php echo $user_id_attr; ?>"
                        title="<?php echo $current_user_fav_status ? 'Remove Favorite' : 'Add Favorite'; ?>">
                    <i class="<?php echo $current_user_fav_status ? 'fas' : 'far'; ?> fa-heart"></i>
                </button>
                <?php endif; ?>
                <?php if ($activity_type !== 'my_favorite'): ?>
                    <a href="<?php echo SUD_URL . '/pages/messages?user=' . $user_id; ?>"
                    class="message-btn"
                    data-target-tier-level="<?php echo $target_tier_level_attr; ?>"
                    title="Send Message">
                        <i class="fas fa-envelope"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>