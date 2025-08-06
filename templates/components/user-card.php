<?php

if (!isset($user) || !is_array($user)) {
    return;
}

$user_id        = isset($user['id']) ? absint($user['id']) : 0;
$name_raw       = isset($user['name']) ? trim($user['name']) : 'User';
$age            = isset($user['age']) && !empty($user['age']) ? absint($user['age']) : '';
$style          = isset($style) ? sanitize_key($style) : 'overlay';
$profile_pic    = isset($user['profile_pic']) ? $user['profile_pic'] : SUD_IMG_URL . '/default-profile.jpg';
$is_online      = !empty($user['is_online']);
$is_verified    = !empty($user['is_verified']);
$is_favorite    = !empty($user['is_favorite']);
$level          = isset($user['level']) ? absint($user['level']) : null;
$premium_badge  = $user['premium_badge_html_small'] ?? '';
$target_tier_level = $user['premium_tier_level'] ?? 0;
$has_active_boost = $user['has_active_boost'] ?? false;
$boost_type = $user['boost_type'] ?? '';
$boost_name = $user['boost_name'] ?? '';

$location_full = isset($user['location_formatted']) && !empty($user['location_formatted'])
                    ? trim($user['location_formatted'])
                    : 'Location not specified';

$location_display = mb_strlen($location_full) > 25
                    ? mb_substr($location_full, 0, 25) . '...' 
                    : $location_full;

$name_display = mb_strlen($name_raw) > 7
                    ? mb_substr($name_raw, 0, 7) . '...' 
                    : $name_raw;

$profile_url = ($user_id > 0 && defined('SUD_URL'))
                ? SUD_URL . '/pages/profile?id=' . $user_id
                : 'javascript:void(0);';

$user_id_attr    = esc_attr($user_id);
$profile_url_attr= esc_attr($profile_url);
$profile_pic_url = esc_url($profile_pic);
$name_attr       = esc_attr($name_raw);
$name_display_html = esc_html($name_display);
$age_html        = esc_html($age);
$location_html   = esc_html($location_display);
$img_path_url    = esc_url(SUD_IMG_URL);
$target_tier_level_attr = esc_attr($target_tier_level);

?>

<?php if ($style == 'overlay'): ?>
<div class="user-card" data-user-id="<?php echo $user_id_attr; ?>" data-profile-url="<?php echo $profile_url_attr; ?>">
    <div class="user-card-img">
        <img src="<?php echo $profile_pic_url; ?>" alt="<?php echo $name_attr; ?>" loading="lazy" onerror="this.src='<?php echo $img_path_url; ?>/default-profile.jpg';">
    </div>

    <div class="user-card-overlay">
        <div class="user-details">
            <div class="top-details">
                <div class="name-age-status">
                    <span class="username"><?php echo $name_display_html; ?><?php if (!empty($age)): ?><span>,</span> <?php echo $age_html; ?><?php endif; ?></span>
                    <?php echo $premium_badge; ?>
                    <?php if ($has_active_boost === true): ?>
                        <span class="boost-badge boost-<?php echo esc_attr($boost_type); ?>" title="<?php echo esc_attr($boost_name); ?> Active">
                            <i class="fas fa-rocket"></i>
                        </span>
                    <?php endif; ?>
                    <?php if ($is_verified): ?>
                        <span class="verified-badge"><img class="verify-icon" src="<?php echo $img_path_url; ?>/verified-profile-badge.png" alt="verified"></span>
                    <?php endif; ?>
                    <?php if ($is_online): ?>
                        <span class="online-status" title="Online Now"><span class="status-dot"></span></span>
                    <?php endif; ?>
                </div>
                <div class="favorite-icon">
                    <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart user-favorite"
                       data-user-id="<?php echo $user_id_attr; ?>"
                       title="<?php echo $is_favorite ? 'Remove Favorite' : 'Add Favorite'; ?>"></i>
                </div>
            </div>
            <div class="location-container">
                <div class="location" title="<?php echo esc_attr($location_full); ?>">
                    <i class="fas fa-map-marker-alt"></i> <?php echo $location_html; ?>
                </div>
                <?php if ($level): ?>
                <div class="badge-container">
                    <span class="user-level">Lv<?php echo esc_html($level); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($style == 'info'): ?>
<div class="user-card user-card-with-info" data-user-id="<?php echo $user_id_attr; ?>" data-profile-url="<?php echo $profile_url_attr; ?>">
    <div class="user-card-img">
        <img src="<?php echo $profile_pic_url; ?>" alt="<?php echo $name_attr; ?>" loading="lazy" onerror="this.src='<?php echo $img_path_url; ?>/default-profile.jpg';">
        <?php echo $premium_badge; ?>
        <?php if ($has_active_boost === true): ?>
            <span class="boost-badge boost-<?php echo esc_attr($boost_type); ?>" title="<?php echo esc_attr($boost_name); ?> Active">
                <i class="fas fa-rocket"></i>
            </span>
        <?php endif; ?>
        <?php if ($is_online): ?><span class="online-status-badge" title="Online Now"></span><?php endif; ?>
    </div>
    <div class="user-card-info">
        <div class="top-details">
            <div class="name-age-status">
                <span class="username" title="<?php echo $name_attr; ?>"><?php echo $name_display_html; ?><?php if (!empty($age)): ?><span>,</span> <?php echo $age_html; ?><?php endif; ?></span>
                 <?php if ($is_verified): ?><span class="verified-badge"><img class="verify-icon" src="<?php echo $img_path_url; ?>/verified-profile-badge.png" alt="verified"></span><?php endif; ?>
                 <?php if ($has_active_boost === true): ?>
                     <span class="boost-badge boost-<?php echo esc_attr($boost_type); ?>" title="<?php echo esc_attr($boost_name); ?> Active">
                         <i class="fas fa-rocket"></i>
                     </span>
                 <?php endif; ?>
            </div>
            <div class="favorite-icon">
                <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart user-favorite"
                    data-user-id="<?php echo $user_id_attr; ?>"
                    title="<?php echo $is_favorite ? 'Remove Favorite' : 'Add Favorite'; ?>"></i>
            </div>
        </div>
        <div class="location-container">
            <div class="location" title="<?php echo esc_attr($location_full); ?>">
                <i class="fas fa-map-marker-alt"></i> <?php echo $location_html; ?>
            </div>
             <?php if ($level): ?>
             <div class="badge-container">
                 <span class="user-level">Lv<?php echo esc_html($level); ?></span>
             </div>
             <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($style == 'compact'): ?>
<div class="user-card user-card-compact" data-user-id="<?php echo $user_id_attr; ?>" data-profile-url="<?php echo $profile_url_attr; ?>">
    <div class="user-avatar">
        <img src="<?php echo $profile_pic_url; ?>" alt="<?php echo $name_attr; ?>" loading="lazy" onerror="this.src='<?php echo $img_path_url; ?>/default-profile.jpg';">
        <?php if ($is_online): ?>
            <span class="online-indicator" title="Online Now"></span>
        <?php endif; ?>
    </div>
    <div class="user-card-info">
        <div class="top-details">
            <div class="name-age-status">
                <span class="username" title="<?php echo $name_attr; ?>"><?php echo $name_display_html; ?></span>
                 <?php if ($is_verified): ?><span class="verified-badge"><img class="verify-icon" src="<?php echo $img_path_url; ?>/verified-profile-badge.png" alt="verified"></span><?php endif; ?>
            </div>
             <div class="favorite-icon">
                 <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart user-favorite" data-user-id="<?php echo $user_id_attr; ?>"></i>
             </div>
        </div>
        <div class="location" title="<?php echo esc_attr($location_full); ?>">
            <i class="fas fa-map-marker-alt"></i> <?php echo $location_html; ?>
        </div>
    </div>
</div>
<?php endif; ?>