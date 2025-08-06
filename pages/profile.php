<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/profile-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');

require_login();

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// Use centralized validation for all profile requirements
validate_core_profile_requirements($current_user_id, 'profile');
$display_name = $current_user->display_name;
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $current_user_id;
$is_own_profile = ($profile_id == $current_user_id);

$fallbacks = [
    'id' => 0,
    'name' => 'Anonymous User',
    'age' => '',
    'location' => 'Location not specified',
    'city' => '',
    'region' => '',
    'country' => '',
    'about_me' => 'This user hasn\'t written anything about themselves yet.',
    'gender' => 'Not specified',
    'looking_for' => 'Not specified',
    'relationship_status' => 'Not specified',
    'occupation' => 'Not specified',
    'ethnicity' => 'Not specified',
    'race' => 'Not specified',
    'annual_income' => 'Not specified',
    'net_worth' => 'Not specified',
    'dating_budget' => 'Not specified',
    'smoke' => 'Not specified',
    'drink' => 'Not specified',
    'dating_style' => [],
    'interests' => [],
    'profile_pic' => SUD_IMG_URL . '/default-profile.jpg',
    'image' => SUD_IMG_URL . '/default-profile.jpg',
    'photos' => [],
    'member_since' => '',
    'registration_date' => '',
    'last_active' => '',
    'is_online' => false,
    'is_verified' => false,
    'is_favorite' => false,
    'completion_percentage' => 0,
    'latitude' => null,
    'longitude' => null,
];

$profile_data = get_user_profile_data($profile_id);

if (!$profile_data) {
    if (!$is_own_profile) {
        header('Location: ' . SUD_URL . '/pages/swipe');
        exit;
    } else {
        $profile_data = [
            'id' => $current_user_id,
            'name' => $display_name,
        ];
    }
}

$profile_details = get_complete_user_profile($profile_id);
if (!$profile_details) {
    $profile_details = [];
}

$profile = array_merge($fallbacks, $profile_details, $profile_data);
$is_verified = isset($profile['is_verified']) ? (bool)$profile['is_verified'] : false;
$is_online = isset($profile['is_online']) ? (bool)$profile['is_online'] : false;
$photos = isset($profile['photos']) ? $profile['photos'] : [];
$detailed_location_parts = [];
$city = trim(get_profile_value($profile, 'city', $fallbacks));
$region = trim(get_profile_value($profile, 'region', $fallbacks));
$country = trim(get_profile_value($profile, 'country', $fallbacks));

if (!empty($city) && $city !== $fallbacks['city']) $detailed_location_parts[] = $city;
// if (!empty($region) && $region !== $fallbacks['region']) $detailed_location_parts[] = $region;
if (!empty($country) && $country !== $fallbacks['country']) $detailed_location_parts[] = $country;

$detailed_location_parts = array_filter($detailed_location_parts, function($value) use ($fallbacks) {
    return !empty($value) && $value !== $fallbacks['location'] && $value !== $fallbacks['city'] && $value !== $fallbacks['region'] && $value !== $fallbacks['country'];
});

if (!empty($detailed_location_parts)) {
    $location = implode(', ', $detailed_location_parts);
} else {
    $general_location = trim(get_profile_value($profile, 'location', $fallbacks));
    if (!empty($general_location) && $general_location !== $fallbacks['location']) {
        $location = $general_location;
    } else {
        $location = $fallbacks['location']; 
    }
}

$member_since = $profile['member_since'] ?: ($profile['registration_date'] ?: '');
if (empty($member_since) && function_exists('get_user_registered_date')) {
    $member_since = get_user_registered_date($profile_id);
} else if (empty($member_since)) {
    $user_data = get_userdata($profile_id);
    $member_since = $user_data ? $user_data->user_registered : '';
    if (!empty($member_since)) {
        $member_since = date('Y/m/d', strtotime($member_since));
    }
}

$last_active = $profile['last_active'];
if (empty($last_active) && function_exists('get_user_last_active')) {
    $last_active = get_user_last_active($profile_id);
} else if (empty($last_active)) {
    $last_active_timestamp = get_user_meta($profile_id, 'last_active', true);
    if ($last_active_timestamp) {
        $last_active = $last_active_timestamp;
    }
}

if (!$is_own_profile && is_user_logged_in() && function_exists('is_excluded_admin') && !is_excluded_admin($profile_id)) {
    if (function_exists('add_profile_view_notification')) {
        add_profile_view_notification($current_user_id, $profile_id);
    }
}

$is_favorite = isset($profile['is_favorite']) ? (bool)$profile['is_favorite'] : false;
if (!$is_favorite && function_exists('is_user_favorite')) {
    $is_favorite = is_user_favorite($profile_id);
}

$is_profile_blocked = false;
if (!$is_own_profile && function_exists('is_user_blocked')) {
    $is_profile_blocked = is_user_blocked($profile_id, $current_user_id);
}

if (!$is_own_profile && isset($_POST['toggle_block'])) {
    if (function_exists('toggle_user_block')) {
        $block_status_to_set = isset($_POST['block_status']) ? ($_POST['block_status'] == '1') : true; 
        toggle_user_block($current_user_id, $profile_id, $block_status_to_set);
        if ($block_status_to_set && function_exists('toggle_user_favorite')) {
            toggle_user_favorite($profile_id, false);
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$current_user_profile_picture_id = get_user_meta($current_user_id, 'profile_picture', true);
$current_user_profile_pic_url = !empty($current_user_profile_picture_id) ? wp_get_attachment_image_url($current_user_profile_picture_id, 'thumbnail') : SUD_IMG_URL . '/default-profile.jpg';
$completion_percentage = function_exists('get_profile_completion_percentage') ? get_profile_completion_percentage($current_user_id) : 0;
$user_messages = get_user_messages($current_user_id);
$unread_message_count = $user_messages['unread_count'];
$coin_balance = get_user_meta($current_user_id, 'coin_balance', true) ?: 0;

$page_title = $is_own_profile ? 'My Profile' : get_profile_value($profile, 'name', $fallbacks) . "'s Profile";

function format_profile_attribute($value) {
    if (empty($value)) return '';
    $value = str_replace('_', ' ', $value);
    if (strpos($value, 'non ') === 0) {
        return 'Non-' . ucfirst(substr($value, 4));
    }
    return ucwords($value);
}

function get_profile_value($profile, $key, $fallbacks) {
    if (isset($profile[$key]) && !empty($profile[$key])) {
        if (is_array($profile[$key])) {
            return $profile[$key];
        }
        return $profile[$key];
    }
    return isset($fallbacks[$key]) ? $fallbacks[$key] : '';
}

function format_date_display($date_string) {
    if (empty($date_string)) return 'Not available';
    $date = strtotime($date_string);
    if (!$date) return $date_string;
    return date('F j, Y', $date);
}

function format_last_active($time_value) {
    if (empty($time_value)) return 'Unknown';
    $date = is_numeric($time_value) ? (int)$time_value : strtotime($time_value);
    if (!$date) return (string)$time_value;

    $now = time();
    $diff = $now - $date;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) { $m = floor($diff/60); return $m.' minute'.($m > 1 ? 's' : '').' ago'; }
    if ($diff < 86400) { $h = floor($diff/3600); return $h.' hour'.($h > 1 ? 's' : '').' ago'; }
    if ($diff < 604800) { $d = floor($diff/86400); return $d.' day'.($d > 1 ? 's' : '').' ago'; }
    return date('Y/m/d', $date);
}

$viewer_is_premium = function_exists('sud_is_user_premium') ? sud_is_user_premium($current_user_id) : false;
$viewer_has_full_photo_access = $is_own_profile || $viewer_is_premium;
$can_view_full_cover = $is_own_profile || $viewer_is_premium;
$premium_page_url = SUD_URL . '/pages/premium';

$profile_picture_url_for_cover = get_profile_value($profile, 'profile_pic', $fallbacks);
$is_default_picture = (strpos($profile_picture_url_for_cover, 'default-profile.jpg') !== false);
$should_lock_cover = !$is_own_profile && !$viewer_is_premium && !$is_default_picture;
$blurred_cover_url = SUD_IMG_URL . '/blurred-user.png';
$cover_image_url_to_display = $should_lock_cover ? $blurred_cover_url : $profile_picture_url_for_cover;

$target_tier_level = $profile['premium_tier_level'] ?? 0;
$target_user_name = esc_attr(get_profile_value($profile, 'name', $fallbacks));
$message_page_url = SUD_URL . '/pages/messages?user=' . $profile_id;

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
    <title><?php echo esc_html($page_title); ?> - <?php echo esc_html(SUD_SITE_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/messages.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/profile.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/swipe.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/messages-modals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        var sud_profile_id = <?php echo json_encode($profile_id); ?>;
        var sud_config = {
            profile_id: <?php echo json_encode($profile_id); ?>,
            profile_name: <?php echo json_encode(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?>,
            sud_url: '<?php echo esc_js(SUD_URL); ?>',
            ajax_nonce: '<?php echo wp_create_nonce('sud_ajax_action'); ?>'
        };
    </script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
</head>

<body>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>

    <main class="main-content">
        <div class="container">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-picture">
                        <img src="<?php echo esc_url(get_profile_value($profile, 'profile_pic', $fallbacks)); ?>" alt="<?php echo esc_attr(get_profile_value($profile, 'name', $fallbacks)); ?>">
                            <?php if ($is_online): ?>
                                <span class="online-indicator"></span>
                            <?php endif; ?>
                    </div>
                    <div class="profile-cover-container">
                        <div class="profile-cover <?php echo $should_lock_cover ? 'cover-blurred' : ''; ?>"
                        style="background-image: url('<?php echo esc_url($cover_image_url_to_display); ?>');">
                            <?php if ($should_lock_cover): ?>
                                <a href="<?php echo esc_url($premium_page_url); ?>" class="cover-image-lock-overlay" title="Upgrade to view cover photo clearly">
                                    <div class="lock-content">
                                        <i class="fas fa-lock"></i>
                                        <span>Upgrade to View</span>
                                    </div>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-title">
                        <h1>
                            <?php echo esc_html(get_profile_value($profile, 'name', $fallbacks)); ?>
                            <?php if (!empty($profile['age'])): ?>, <?php echo esc_html($profile['age']); ?><?php endif; ?>
                            <?php echo $profile['premium_badge_html_medium'] ?? ''; ?>
                            <?php if ($is_verified): ?>
                                <span class="verified-badge"><img src="<?php echo SUD_IMG_URL; ?>/verified-profile-badge.png" alt="verified" style="width: 20px; height: 20px; margin-bottom: -4px;"></span>
                            <?php endif; ?>
                            <?php if ($profile['has_active_boost'] === true): ?>
                                <span class="boost-badge boost-<?php echo esc_attr($profile['boost_type'] ?: 'mini'); ?>" title="<?php echo esc_attr($profile['boost_name'] ?: 'Profile Boost'); ?> Active">
                                    <i class="fas fa-rocket"></i>
                                </span>
                            <?php endif; ?>
                            <?php if ($is_online): ?>
                                <span class="online-status" title="Online Now">
                                    <span class="status-dot"></span> Online
                                </span>
                            <?php endif; ?>
                        </h1>
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo esc_html($location); ?>
                    </div>

                    <div class="profile-metadata">
                        <div class="metadata-item">
                            <span class="metadata-label">Last Active</span>
                            <span class="metadata-value"><?php echo format_last_active($last_active); ?></span>
                        </div>
                        <div class="metadata-item">
                            <span class="metadata-label">Member Since</span>
                            <span class="metadata-value"><?php echo format_date_display($member_since); ?></span>
                        </div>
                        <div class="metadata-item">
                            <span class="metadata-label">Recent Location</span>
                            <span class="metadata-value">
                                <?php

                                echo esc_html($location);
                                ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!$is_own_profile): ?>
                        <div class="profile-actions">
                            <a href="<?php echo esc_url($message_page_url); ?>"
                               class="btn-message profile-message-link <?php echo $is_profile_blocked ? 'disabled' : ''; ?>"
                               data-target-user-id="<?php echo $profile_id; ?>"
                               <?php echo $is_profile_blocked ? 'aria-disabled="true" onclick="return false;"' : ''; ?>
                               title="Send Message">
                                <i class="fas fa-envelope"></i> Message
                            </a>
                            <button type="button"
                                    class="btn-favorite user-favorite <?php echo $is_favorite ? 'favorited' : ''; ?> <?php echo $is_profile_blocked ? 'disabled' : ''; ?>"
                                    data-user-id="<?php echo $profile_id; ?>"
                                    data-target-tier-level="<?php echo $target_tier_level; ?>"
                                    <?php echo $is_profile_blocked ? 'disabled' : ''; ?>
                                    title="<?php echo $is_favorite ? 'Favorited' : 'Favorite'; ?>">
                                <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart"></i>
                                <?php echo $is_favorite ? 'Favorited' : 'Favorite'; ?>
                            </button>

                            <div class="user-profile-dropdown">
                                <button class="user-dropdown-toggle" title="More Options" >
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="user-utility-dropdown-menu">
                                    <?php if ($is_profile_blocked): ?>
                                         <a href="#" class="user-dropdown-item" data-modal-target="#unblock-modal"> <i class="fas fa-check-circle"></i> Unblock <?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?> </a>
                                    <?php else: ?>
                                         <a href="#" class="user-dropdown-item" data-modal-target="#block-modal"> <i class="fas fa-ban"></i> Block <?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?> </a>
                                    <?php endif; ?>
                                    <a href="#" class="user-dropdown-item" data-modal-target="#report-modal"> <i class="fas fa-flag"></i> Report <?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?></a>
                                </div>
                            </div>
                        </div>
                         <?php if ($is_profile_blocked): ?>
                            <div class="blocked-profile-notice">
                                <p><i class="fas fa-ban"></i> You have blocked this user. Unblock them to interact.</p>
                            </div>
                         <?php endif; ?>
                    <?php else: ?>
                        <div class="profile-actions">
                            <a href="<?php echo SUD_URL; ?>/pages/profile-edit" class="btn-edit">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                            <a href="<?php echo SUD_URL; ?>/pages/settings" class="btn-settings">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-content <?php echo $is_profile_blocked ? 'content-blocked' : ''; ?>">
                     <?php if ($is_profile_blocked): ?>
                         <div class="blocked-content-overlay"></div>
                     <?php endif; ?>
                    <div class="profile-section">
                        <h2>About Me</h2>
                        <div class="about-content">
                            <p><?php echo nl2br(esc_html(get_profile_value($profile, 'about_me', $fallbacks))); ?></p>
                        </div>
                    </div>

                    <div class="profile-section">
                        <h2>Basic Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Gender</span>
                                <span class="info-value"><?php echo esc_html(get_attribute_display_label('gender', get_profile_value($profile, 'gender', $fallbacks))); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Relationship Status</span>
                                <span class="info-value"><?php echo esc_html(get_attribute_display_label('relationship_status', get_profile_value($profile, 'relationship_status', $fallbacks))); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Occupation</span>
                                <span class="info-value"><?php echo esc_html(get_profile_value($profile, 'occupation', $fallbacks)); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Ethnicity</span>
                                <span class="info-value"><?php echo esc_html(get_attribute_display_label('ethnicity', get_profile_value($profile, 'ethnicity', $fallbacks))); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Race</span>
                                <span class="info-value"><?php echo esc_html(get_attribute_display_label('race', get_profile_value($profile, 'race', $fallbacks))); ?></span>
                            </div>
                            
                            <?php 
                            $user_functional_role = get_user_meta($profile_id, 'functional_role', true);
                            if ($user_functional_role === 'provider'): 
                            ?>
                                <div class="info-item">
                                    <span class="info-label">Annual Income</span>
                                    <span class="info-value"><?php
                                        $income = get_profile_value($profile, 'annual_income', $fallbacks);
                                        echo is_numeric($income) ? '$' . number_format(intval($income)) : esc_html($income);
                                    ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Net Worth</span>
                                    <span class="info-value"><?php
                                        $worth = get_profile_value($profile, 'net_worth', $fallbacks);
                                        echo is_numeric($worth) ? '$' . number_format(intval($worth)) : esc_html($worth);
                                    ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Dating Budget</span>
                                    <span class="info-value"><?php
                                        $budget = get_profile_value($profile, 'dating_budget', $fallbacks);
                                        echo esc_html(get_attribute_display_label('dating_budget', $budget, $fallbacks['dating_budget']));
                                    ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="info-item">
                                <span class="info-label">Smoking</span>
                                <span class="info-value"><?php echo esc_html(get_attribute_display_label('smoke', get_profile_value($profile, 'smoke', $fallbacks))); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Drinking</span>
                                <span class="info-value"><?php echo esc_html(get_attribute_display_label('drink', get_profile_value($profile, 'drink', $fallbacks))); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Photos Section -->
                    <div class="profile-section">
                        <h2>Photos</h2>
                        <div class="photos-grid">
                            <?php
                            $total_photos = count($photos);
                            $max_slots_for_free_viewer = 4;

                            if ($total_photos > 0):
                                if ($viewer_has_full_photo_access):
                                    foreach ($photos as $index => $photo):
                            ?>
                                <div class="photo-item">
                                    <img src="<?php echo esc_url($photo['url'] ?? ''); ?>" alt="Photo <?php echo $index + 1; ?>" class="photo-img">
                                </div>
                            <?php
                                endforeach;
                                else:
                            ?>
                                <div class="photo-item">
                                    <img src="<?php echo esc_url($photos[0]['url'] ?? ''); ?>" alt="Photo 1" class="photo-img">
                                </div>

                                <?php
                                $locked_slots_to_show = min(3, max(0, $total_photos - 1));
                                $dummy_slots_to_show = max(0, $max_slots_for_free_viewer - 1 - $total_photos);

                                for ($i = 1; $i < $total_photos && $i < $max_slots_for_free_viewer; $i++):
                                    $photo = $photos[$i];
                                ?>
                                    <div class="photo-item locked">
                                        <a href="<?php echo SUD_URL; ?>/pages/premium" title="Upgrade to Premium to view all photos">
                                            <img src="<?php echo esc_url($photo['thumbnail'] ?? SUD_IMG_URL . '/blurred-locked-user.jpg'); // Use real thumbnail ?>" alt="Locked Photo" class="photo-img blurred-photo">
                                            <div class="padlock-overlay">
                                                <i class="fas fa-lock"></i>
                                                <span>Upgrade to View</span>
                                            </div>
                                        </a>
                                    </div>
                                <?php
                                endfor;
                                
                                $start_dummy_index = $total_photos;
                                for ($i = $start_dummy_index; $i < $max_slots_for_free_viewer; $i++):
                                ?>
                                    <div class="photo-item locked extra-dummy">
                                        <a href="<?php echo SUD_URL; ?>/pages/premium" title="Upgrade to Premium to view all photos">
                                            <img src="<?php echo SUD_IMG_URL . '/blurred-locked-user.jpg'; ?>" alt="Locked Photo" class="photo-img blurred-photo">
                                            <div class="padlock-overlay">
                                                <i class="fas fa-lock"></i>
                                                <span>Upgrade to View</span>
                                            </div>
                                        </a>
                                    </div>
                                <?php
                                endfor;
                                ?>
                            <?php
                                endif;
                            else: 
                            ?>
                                <div class="photo-placeholder">
                                    <i class="fas fa-image"></i>
                                    <p>No photos available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Interests Section -->
                    <div class="profile-section">
                        <h2>Interests / Relationship Terms™</h2> <?php // Clarify label ?>
                        <div class="interests-container">
                            <?php
                            $relationship_terms = get_profile_value($profile, 'relationship_terms', $fallbacks);
                            if (!empty($relationship_terms) && is_array($relationship_terms)):
                                foreach($relationship_terms as $term): // Use $term variable
                            ?>
                                <span class="interest-tag"><?php echo esc_html(get_attribute_display_label('interests', $term)); // Lookup using 'interests' options ?></span>
                            <?php
                                endforeach;
                            else:
                            ?>
                                <p class="empty-field">No terms specified yet</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Dating Style Section -->
                    <div class="profile-section">
                        <h2>Dating Style</h2>
                        <div class="dating-styles-container">
                            <?php
                            $dating_styles = get_profile_value($profile, 'dating_styles', $fallbacks);
                            if (!empty($dating_styles) && is_array($dating_styles)):
                                foreach($dating_styles as $style):
                            ?>
                                <span class="dating-style-tag"><?php echo esc_html(get_attribute_display_label('dating_style', $style)); ?></span>
                            <?php
                                endforeach;
                            else:
                            ?>
                                <p class="empty-field">No dating style specified yet</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-section">
                        <h2>Looking For</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Age Range</span>
                                <span class="info-value">
                                    <?php echo esc_html(get_profile_value($profile, 'looking_for_age_min', $fallbacks)); ?> -
                                    <?php echo esc_html(get_profile_value($profile, 'looking_for_age_max', $fallbacks)); ?> years
                                </span>
                            </div>
                            <div class="info-item info-item-full">
                                <span class="info-label">Ethnicities / Race</span>
                                <div class="info-value tag-list">
                                    <?php
                                    $looking_ethnicities = get_profile_value($profile, 'looking_for_ethnicities', $fallbacks);
                                    if (!empty($looking_ethnicities) && is_array($looking_ethnicities)) {
                                        if (count($looking_ethnicities) === 1 && $looking_ethnicities[0] === SUD_ANY_ETHNICITY_KEY) {
                                            echo '<span class="tag">Open to Any Ethnicity</span>';
                                        } else {
                                            $displayed_tags_looking = 0;
                                            foreach ($looking_ethnicities as $combo) {
                                                if ($combo === SUD_ANY_ETHNICITY_KEY) continue;
                                                list($ethnicity_val, $race_val) = array_pad(explode('|', $combo, 2), 2, null);

                                                $ethnicity_label = get_attribute_display_label('ethnicity', $ethnicity_val, ucfirst($ethnicity_val));
                                                $race_label = $race_val ? get_attribute_display_label('race', $race_val, ucfirst($race_val)) : '';

                                                if ($ethnicity_label !== 'Not specified') {
                                                    echo '<span class="tag">' . esc_html($ethnicity_label . (($race_label && $race_label !== 'Not specified') ? ', ' . $race_label : '')) . '</span></br>';
                                                    $displayed_tags_looking++;
                                                }
                                            }
                                            if ($displayed_tags_looking === 0) {
                                                echo '<p class="empty-field" style="margin:0;">No specific preferences set</p>';
                                            }
                                        }
                                    } else {
                                        echo '<p class="empty-field" style="margin:0;">No preferences set</p>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                        $has_appearance_data = !empty(get_profile_value($profile, 'height', $fallbacks)) ||
                                              !empty(get_profile_value($profile, 'body_type', $fallbacks)) ||
                                              !empty(get_profile_value($profile, 'eye_color', $fallbacks)) ||
                                              !empty(get_profile_value($profile, 'hair_color', $fallbacks));
                        // Add checks for ethnicity/race if they should also trigger this section's display
                        // $has_appearance_data = $has_appearance_data || !empty(get_profile_value($profile, 'ethnicity', $fallbacks));
                        // $has_appearance_data = $has_appearance_data || !empty(get_profile_value($profile, 'race', $fallbacks));

                        if ($has_appearance_data):
                    ?>
                    <div class="profile-section">
                        <h2>Appearance</h2>
                        <div class="info-grid">
                            <?php if (!empty(get_profile_value($profile, 'height', $fallbacks))): ?>
                            <div class="info-item">
                                <span class="info-label">Height</span>
                                <span class="info-value"><?php echo esc_html(get_profile_value($profile, 'height', $fallbacks)); ?> cm</span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty(get_profile_value($profile, 'body_type', $fallbacks))): ?>
                            <div class="info-item">
                                <span class="info-label">Body Type</span>
                                <span class="info-value"><?php echo esc_html(get_attribute_display_label('body_type', get_profile_value($profile, 'body_type', $fallbacks))); ?></span>
                            </div>
                             <?php endif; ?>
                            <?php if (!empty(get_profile_value($profile, 'eye_color', $fallbacks))): ?>
                            <div class="info-item">
                                <span class="info-label">Eye Color</span>
                                <span class="info-value"><?php echo esc_html(get_attribute_display_label('eye_color', get_profile_value($profile, 'eye_color', $fallbacks))); ?></span>
                            </div>
                            <?php endif; ?>
                             <?php if (!empty(get_profile_value($profile, 'hair_color', $fallbacks))): ?>
                            <div class="info-item">
                                <span class="info-label">Hair Color</span>
                                <span class="info-value"><?php echo esc_html(get_attribute_display_label('hair_color', get_profile_value($profile, 'hair_color', $fallbacks))); ?></span>
                            </div>
                             <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include(dirname(__FILE__, 2) . '/templates/components/message-modal.php'); ?>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-footer.php'); ?>

    <!-- Toast Notifications Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Modals for Block/Report -->
    <div id="block-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <h3>Block <?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?></h3>
            <p>Are you sure you want to block <strong id="block-username-profile"><?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?></strong>? You will no longer see their profile, and they won't see yours.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" id="confirm-block-profile" data-action="block">Block <?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?></button>
                <button type="button" class="btn btn-secondary close-modal-btn">Cancel</button>
            </div>
        </div>
    </div>
    <div id="unblock-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <h3>Unblock <?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?></h3>
            <p>Are you sure you want to unblock <strong id="unblock-username-profile"><?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?></strong>? They will be able to view your profile and contact you again.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-success" id="confirm-unblock-profile" data-action="unblock">Unblock <?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?></button>
                <button type="button" class="btn btn-secondary close-modal-btn">Cancel</button>
            </div>
        </div>
    </div>
    <div id="report-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <h3>Report <?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?></h3>
            <p>Please select a reason for reporting <strong id="report-username-profile"><?php echo esc_html(get_profile_value($profile, 'name', $fallbacks) ?: 'User'); ?></strong>:</p>
            <form id="report-profile-form">
                <div class="form-group">
                    <select id="report-profile-reason" name="report_reason" class="form-input" required>
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
                    <label for="report-profile-details">Details:</label>
                    <textarea id="report-profile-details" name="report_details" class="form-input" rows="3"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-danger">Submit Report</button>
                    <button type="button" class="btn btn-secondary close-modal-btn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Handle "Other" reason selection to show/hide details field
        document.addEventListener('DOMContentLoaded', function() {
            const reportReasonSelect = document.getElementById('report-profile-reason');
            const otherReasonGroup = document.getElementById('report-other-reason-group');
            
            if (reportReasonSelect && otherReasonGroup) {
                reportReasonSelect.addEventListener('change', function() {
                    if (this.value === 'other') {
                        otherReasonGroup.style.display = 'block';
                    } else {
                        otherReasonGroup.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>