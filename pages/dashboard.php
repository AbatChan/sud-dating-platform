<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/location-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');

// Maintenance mode check now handled globally in config.php

require_login();

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// Allow access to dashboard but limit functionality for non-premium users
$is_premium_user = sud_is_user_premium($current_user_id);

// Check trial status for dynamic upgrade prompts
$active_trial = sud_get_active_trial($current_user_id);
$is_on_trial = $active_trial !== false;

// Only redirect new users to swipe if they haven't explicitly chosen to visit dashboard
$just_signed_up = get_user_meta($current_user_id, 'just_completed_profile', true);
$came_from_profile_completion = isset($_GET['profile_completed']) && $_GET['profile_completed'] == '1';
$user_intentionally_visited_dashboard = isset($_GET['from_nav']) || isset($_SERVER['HTTP_REFERER']) && 
    (strpos($_SERVER['HTTP_REFERER'], '/pages/swipe') !== false || 
     strpos($_SERVER['HTTP_REFERER'], '/pages/') !== false);

// Only redirect if user just completed profile AND didn't intentionally navigate here
if (($just_signed_up || $came_from_profile_completion) && !$user_intentionally_visited_dashboard) {
    if ($just_signed_up) {
        delete_user_meta($current_user_id, 'just_completed_profile');
    }
    wp_safe_redirect(SUD_URL . '/pages/swipe');
    exit;
}

// Use centralized validation for all profile requirements
validate_core_profile_requirements($current_user_id, 'dashboard');

$display_name = $current_user->display_name;

$upload_message = '';
$upload_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['user_photos'])) {
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $upload_count = 0;
    $error_message = '';

    $user_photos = get_user_meta($current_user->ID, 'user_photos', true);
    if (!is_array($user_photos)) {
        $user_photos = array();
    }
    foreach ($_FILES['user_photos']['name'] as $key => $value) {
        if (empty($_FILES['user_photos']['name'][$key])) {
            continue;
        }
        if ($_FILES['user_photos']['error'][$key] !== UPLOAD_ERR_OK) {
            $error_message .= "Error uploading file: " . $_FILES['user_photos']['error'][$key] . "<br>";
            continue;
        }
        $file = array(
            'name'     => $_FILES['user_photos']['name'][$key],
            'type'     => $_FILES['user_photos']['type'][$key],
            'tmp_name' => $_FILES['user_photos']['tmp_name'][$key],
            'error'    => $_FILES['user_photos']['error'][$key],
            'size'     => $_FILES['user_photos']['size'][$key]
        );
        $attachment_id = media_handle_sideload($file, 0);
        if (is_wp_error($attachment_id)) {
            $error_message .= "Error uploading '{$file['name']}': " . $attachment_id->get_error_message() . "<br>";
        } else {
            $user_photos[] = $attachment_id;
            $upload_count++;
            if (empty(get_user_meta($current_user->ID, 'profile_picture', true)) && $upload_count === 1) {
                update_user_meta($current_user->ID, 'profile_picture', $attachment_id);
            }
        }
    }

    if ($upload_count > 0) {
        update_user_meta($current_user->ID, 'user_photos', $user_photos);
        update_user_meta($current_user->ID, 'completed_step_13', true); 
        $upload_success = true;
        $upload_message = "Successfully uploaded $upload_count photos!";
        header('Location: ' . SUD_URL . '/pages/dashboard?photos_uploaded=1');
        exit;
    } else if (!empty($error_message)) {
        $upload_message = $error_message;
    }
}

if (isset($_GET['skipped_setup']) && $_GET['skipped_setup'] == '1') {
    $current_user_id_dash = get_current_user_id();
    if ($current_user_id_dash) {
        if (!get_user_meta($current_user_id_dash, 'profile_setup_skipped', true)) {
            update_user_meta($current_user_id_dash, 'profile_setup_skipped', true);
        }
    }
    echo "<script>
            if (window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('skipped_setup');
                window.history.replaceState({path: url.href}, '', url.href);
            }
        </script>";
}

$profile_picture_id = get_user_meta($current_user->ID, 'profile_picture', true);
$profile_pic_url = !empty($profile_picture_id) ? wp_get_attachment_image_url($profile_picture_id, 'medium_large') : SUD_IMG_URL . '/default-profile.jpg';
$user_photos_meta = get_user_meta($current_user->ID, 'user_photos', true);
$user_photos_ids = is_array($user_photos_meta) ? $user_photos_meta : [];
$has_photos = !empty($user_photos_ids);
$photo_urls = []; 
foreach ($user_photos_ids as $photo_id) {
    $photo_url = wp_get_attachment_image_url($photo_id, 'medium_large');
    if ($photo_url) { $photo_urls[] = $photo_url; }
}
$completion_percentage = get_profile_completion_percentage($current_user_id);
$user_messages = get_user_messages($current_user_id);
$unread_message_count = $user_messages['unread_count'] ?? 0;
$premium_details = sud_get_user_current_plan_details($current_user_id);
$subscription_expiry_formatted = null;
if ($is_premium_user) {
    $expiry_raw = get_user_meta($current_user_id, 'subscription_expires', true);
    if ($expiry_raw) {
        $expiry_timestamp = strtotime($expiry_raw);
        if ($expiry_timestamp) {
            $subscription_expiry_formatted = date('F j, Y', $expiry_timestamp);
        }
    }
}

$current_user_functional_role = get_user_meta($current_user_id, 'functional_role', true);
$target_functional_role = '';
$can_view_members = true;
$is_admin_view = current_user_can('manage_options');
$base_meta_query = ['relation' => 'AND'];

if ($is_admin_view) {
    //admin sees all
} else {
    if ($current_user_functional_role === 'provider') {
        $target_functional_role = 'receiver';
    } elseif ($current_user_functional_role === 'receiver') {
        $target_functional_role = 'provider';
    } else {
        $can_view_members = false;
    }

    if ($can_view_members) {
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

// Centralized member display configuration
$members_per_page = 12;           // Members to display per page/load
$background_fetch_size = 40;      // Background pool size for faster loading
$prefetch_threshold = 8;          // When to trigger background refetch (when pool gets this low)

$initial_limit = $members_per_page;
$initial_filter = 'active';
$initial_users_data = [];
$total_count = 0; 

if ($can_view_members) {
    $initial_fetch_args = [
        'meta_query' => $base_meta_query
    ];
    $initial_fetch_args['meta_key']  = 'last_active';
    $initial_fetch_args['orderby']   = 'meta_value_num';
    $initial_fetch_args['order']     = 'DESC';

    $initial_users_data = function_exists('custom_get_users') ? custom_get_users($current_user_id, $initial_limit, 0, $initial_fetch_args) : [];
    $total_count = function_exists('get_total_user_count_filtered') ? get_total_user_count_filtered($current_user_id, $base_meta_query) : 0;

    // Apply profile boost and priority sorting for better visibility
    // Only apply to 'active' filter to preserve distance-based and date-based sorting for other filters
    if (!empty($initial_users_data) && $initial_filter === 'active') {
        if (function_exists('sud_apply_profile_boost')) {
            $initial_users_data = sud_apply_profile_boost($initial_users_data, $current_user_id);
        } else if (function_exists('sud_sort_users_with_priority')) {
            $initial_users_data = sud_sort_users_with_priority($initial_users_data, 'last_active');
        }
    } else if (!empty($initial_users_data) && $initial_filter !== 'active') {
        // For non-active filters, only apply priority user sorting without disrupting the primary sort
        if (function_exists('sud_sort_users_with_priority')) {
            $initial_users_data = sud_sort_users_with_priority($initial_users_data, $initial_filter === 'newest' ? 'user_registered' : 'distance');
        }
    }

    if (!empty($initial_users_data)) {
         usort($initial_users_data, function ($a, $b) {
            $a_is_online = $a['is_online'] ?? false;
            $b_is_online = $b['is_online'] ?? false;

            if ($a_is_online !== $b_is_online) {
                return $a_is_online ? -1 : 1;
            }

            $last_active_a = $a['last_active_timestamp'] ?? 0;
            $last_active_b = $b['last_active_timestamp'] ?? 0;

            return $last_active_b <=> $last_active_a;
        });
    }
}

$initial_filtered_users = $initial_users_data;
$initial_user_count_rendered = count($initial_filtered_users);
$show_load_more_initially = ($can_view_members && $initial_user_count_rendered > 0 && $total_count > $initial_user_count_rendered);

// Premium check already done at the top

update_user_last_active($current_user_id);
$page_title = "Dashboard - " . SUD_SITE_NAME;

$ethnicity_options = function_exists('get_attribute_options') ? get_attribute_options('ethnicity') : [];
$can_use_advanced = sud_user_can_access_feature($current_user_id, 'advanced_filters');
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
    <title><?php echo esc_html($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/search.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/swipe.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard-filter-drawer.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard-modals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        var sud_config_base = {
            sud_url: "<?php echo esc_js(SUD_URL); ?>",
            is_logged_in: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
            current_user_id: <?php echo esc_js($current_user_id); ?>,
            initial_limit: <?php echo esc_js($initial_limit); ?>,
            members_per_page: <?php echo esc_js($members_per_page); ?>,
            background_fetch_size: <?php echo esc_js($background_fetch_size); ?>,
            prefetch_threshold: <?php echo esc_js($prefetch_threshold); ?>,
            urls: {
                img_path: "<?php echo esc_js(SUD_IMG_URL); ?>",
                sound_path: "<?php echo esc_js(SUD_ASSETS_URL . '/sounds'); ?>"
            },
            sounds: {
                message: "<?php echo esc_js(SUD_ASSETS_URL . '/sounds/message.mp3'); ?>",
                notification: "<?php echo esc_js(SUD_ASSETS_URL . '/sounds/notification.mp3'); ?>"
            },
            can_use_advanced_filters: <?php echo json_encode($can_use_advanced); ?>,
            premium_page_url: "<?php echo esc_js($premium_page_url); ?>",
            is_premium_user: <?php echo json_encode($is_premium_user); ?>
        };
    </script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
    <script src="<?php echo SUD_JS_URL; ?>/dashboard-filter-drawer.js"></script>
</head>
<body>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>
    <?php if ($is_premium_user): ?>
    <div class="full-width-banner-container premium-status-banner">
        <img src="<?php echo SUD_IMG_URL; ?>/banner-bg.jpg" alt="" class="banner-background-img" onerror="this.onerror=null; this.src='<?php echo SUD_IMG_URL; ?>/banner-bg.jpg';">
        <div class="banner-content">
            <?php if ($premium_details): ?>
                <?php echo sud_get_premium_badge_html($current_user_id, 'large', ['style' => 'display: block; font-size: 3em; filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.2));']); ?>
                
                <?php if ($is_on_trial && $active_trial): ?>
                    <h2><?php echo esc_html($premium_details['name']); ?> Trial Active</h2>
                    <p><strong><?php echo $active_trial['days_remaining']; ?> days left</strong> in your free trial!</p>
                    <p>Trial expires on <?php echo date('M j, Y', strtotime($active_trial['end'])); ?>.</p>
                    <a href="<?php echo SUD_URL; ?>/pages/premium?direct_pay=true&plan=<?php echo $active_trial['plan']; ?>" class="btn-secondary">Upgrade Now</a>
                <?php else: ?>
                    <h2><?php echo esc_html($premium_details['name']); ?> Active</h2>
                    <?php if ($subscription_expiry_formatted): ?>
                         <p>Your premium access is active until <?php echo esc_html($subscription_expiry_formatted); ?>.</p>
                    <?php else: ?>
                         <p>Enjoy your exclusive premium benefits!</p>
                    <?php endif; ?>
                    <a href="<?php echo SUD_URL; ?>/pages/premium" class="btn-secondary">View Benefits</a>
                <?php endif; ?>
            <?php else: ?>
                <h2>Premium Active</h2>
                <p>Enjoy your exclusive premium benefits!</p>
                 <a href="<?php echo SUD_URL; ?>/pages/premium" class="btn-secondary">View Benefits</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <main class="main-content">
        <div class="container">
            <?php
                if (isset($_GET['message_sent']) && $_GET['message_sent'] == '1'): ?>
                <div class="alert alert-success"><div class="alert-content"><i class="fas fa-check-circle"></i><span>Your message has been sent successfully!</span></div><span class="alert-close">×</span></div>
                <?php endif; ?>
                <?php if (isset($_GET['profile_completed']) && $_GET['profile_completed'] == '1'): ?>
                <div class="alert alert-success"><div class="alert-content"><i class="fas fa-check-circle"></i><span>Your profile has been completed successfully!</span></div><span class="alert-close">×</span></div>
                <?php endif; ?>
                <?php if (isset($_GET['photos_uploaded']) && $_GET['photos_uploaded'] == '1'): ?>
                <div class="alert alert-success"><div class="alert-content"><i class="fas fa-check-circle"></i><span>Your photos have been uploaded successfully!</span></div><span class="alert-close">×</span></div>
                <?php endif; ?>
                <?php if (!empty($upload_message)): ?>
                <div class="alert <?php echo $upload_success ? 'alert-success' : 'alert-error'; ?>"><div class="alert-content"><i class="fas <?php echo $upload_success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i><span><?php echo $upload_message; ?></span></div><span class="alert-close">×</span></div>
            <?php endif; ?>

            <div class='container-profile-premium-complete'>
                <div class="profile-width">
                    <?php if (!$is_premium_user): ?>
                        <div class="featured-banner upgrade-banner">
                            <img src="<?php echo SUD_IMG_URL; ?>/banner-bg.jpg" alt="Featured Banner" onerror="this.src='<?php echo SUD_IMG_URL; ?>/banner-bg.jpg'">
                            <div class="banner-content">
                                <span class="badge-premium">PREMIUM</span>
                                <h2>Unlock Exclusive Features</h2>
                                <p>Upgrade to Premium for advanced search, unlimited messaging, and more visibility.</p>
                                <a href="<?php echo SUD_URL; ?>/pages/premium" class="btn-primary">Upgrade Now</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($completion_percentage < 100): ?>
                        <div class="profile-completion">
                             <h3>Complete your profile</h3>
                             <p>to communicate with other members.</p>
                             <div class="progress-container">
                                 <div class="progress-bar"><div class="progress" style="width: <?php echo $completion_percentage; ?>%"></div></div>
                                 <div class="progress-text"><?php echo $completion_percentage; ?>%</div>
                             </div>
                             <a href="<?php echo SUD_URL; ?>/profile-details" class="btn-primary" style="margin-top: 15px;">Complete Profile</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($completion_percentage < 100 && !$has_photos): ?>
            <div class="photos-section">
                 <h3>Enhance Your Profile with Photos</h3>
                 <p>Upload photos to increase your visibility and connections.</p>
                 <form method="post" enctype="multipart/form-data" id="photo-upload-form">
                     <div class="photo-upload-grid">
                         <?php for ($i = 0; $i < 4; $i++): ?>
                             <div class="photo-upload-box">
                                 <input type="file" name="user_photos[]" accept="image/*" class="photo-input">
                                 <i class="fas fa-plus"></i>
                                 <span>Add Photo</span>
                                 <div class="photo-preview"></div>
                             </div>
                         <?php endfor; ?>
                     </div>
                     <div class="upload-btn-wrap">
                         <button type="submit" class="btn-primary" id="upload-photos-btn">Upload Photos</button>
                     </div>
                 </form>
            </div>
            <?php endif; ?>

            <?php if ($can_view_members): ?>
                <div class="dashboard-controls">
                    <div class="tabs">
                        <div class="tab active <?php echo !$is_premium_user ? 'premium-only-tab' : ''; ?>" data-filter="active">Recently Active</div>
                        <div class="tab <?php echo !$is_premium_user ? 'premium-only-tab' : ''; ?>" data-filter="nearby">Nearby</div>
                        <div class="tab <?php echo !$is_premium_user ? 'premium-only-tab' : ''; ?>" data-filter="newest">Newest</div>
                    </div>
                    <button type="button" class="btn-icon btn-filter mobile-visible <?php echo !$is_premium_user ? 'premium-only-btn' : ''; ?>" id="dashboard-filter-btn" title="Filter Members">
                        <i class="fas fa-filter"></i> <span class="filter-text">Filter</span>
                    </button>
                </div>

                <div class="user-grid" id="user-grid-dashboard">
                    <?php if (!empty($initial_filtered_users)):
                        foreach ($initial_filtered_users as $user):
                             include(dirname(__FILE__, 2) . '/templates/components/user-card.php');
                        endforeach;
                    else: ?>
                        <div class="no-results-placeholder" id="dashboard-no-results">
                            <div class="no-results-icon"><i class="fas fa-users-slash"></i></div>
                            <h3>No Members Found Yet</h3>
                            <p>There are currently no members matching your preferences. Check back later!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="load-more-container" id="load-more-container-dashboard" <?php echo $show_load_more_initially ? '' : 'style="display: none;"'; ?>>
                    <button class="btn-load-more <?php echo !$is_premium_user ? 'premium-only-btn' : ''; ?>" id="load-more-btn" <?php echo !$show_load_more_initially ? 'disabled' : ''; ?>>
                        <?php echo $show_load_more_initially ? 'Load More Members' : 'No More Members'; ?>
                    </button>
                </div>
            <?php else: ?>
                 <div class="no-results-placeholder">
                     <div class="no-results-icon"><i class="fas fa-exclamation-triangle"></i></div>
                     <h3>Cannot Display Members</h3>
                     <p>Your user role is not configured correctly to view other members. Please contact support.</p>
                 </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="toast-container" class="toast-container"></div>
    
    <?php include(dirname(__FILE__, 2) . '/templates/components/message-modal.php'); ?>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-footer.php'); ?>
    
    <div id="dashboard-filter-modal" class="modal">
        <div class="sud-modal-content modal-filter-dashboard">
            <div class="modal-header">
                 <h3 class="modal-title">Filter Members</h3>
                 <button type="button" class="close-modal" title="Close">×</button>
             </div>
            <div class="modal-body">
                 <form id="dashboard-filter-form">
                     <div class="filter-group">
                         <label for="filter-gender">Looking for Gender:</label>
                         <select name="gender" id="filter-gender" class="filter-select">
                             <option value="">Any</option>
                             <option value="Man">Men</option>
                             <option value="Woman">Women</option>
                             <option value="LGBTQ+">LGBTQ+</option>
                         </select>
                     </div>

                     <div class="filter-group">
                        <label>Age Range:</label>
                        <div class="filter-age-inputs">
                             <input type="number" name="min_age" id="filter-min-age" value="18" min="18" max="70" placeholder="Min" class="filter-input age-input">
                             <span>-</span>
                             <input type="number" name="max_age" id="filter-max-age" value="70" min="18" max="70" placeholder="Max" class="filter-input age-input">
                        </div>
                    </div>

                    <div class="filter-group">
                        <label for="filter-location">Location:</label>
                        <input type="text" name="location" id="filter-location" placeholder="City, Country" class="filter-input">
                    </div>

                    <div class="filter-group <?php echo !$can_use_advanced ? 'locked-filter' : ''; ?>">
                        <label for="filter-ethnicity">Ethnicity <?php if (!$can_use_advanced) echo '<span class="premium-tag">(Diamond)</span>'; ?></label>
                        <select name="ethnicity" id="filter-ethnicity" class="filter-select" <?php if (!$can_use_advanced) echo 'disabled'; ?>>
                            <option value="">Any Ethnicity</option>
                             <?php foreach ($ethnicity_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                         <?php if (!$can_use_advanced): ?>
                         <a href="<?php echo esc_url($premium_page_url); ?>?highlight_plan=diamond" class="filter-lock-overlay-wrapper" title="Upgrade to Diamond to use this filter">
                            <div class="filter-lock-overlay"><i class="fas fa-lock"></i><span>Unlock</span></div>
                         </a>
                        <?php endif; ?>
                    </div>

                     <div class="filter-group <?php echo !$can_use_advanced ? 'locked-filter' : ''; ?>">
                         <label>Additional <?php if (!$can_use_advanced) echo '<span class="premium-tag">(Diamond)</span>'; ?></label>
                         <div class="filter-options filter-checkboxes">
                             <label class="checkbox-label <?php if (!$can_use_advanced) echo 'disabled'; ?>">
                                <input type="checkbox" name="verified_only" value="1" <?php if (!$can_use_advanced) echo 'disabled'; ?>>
                                <span>Verified Only</span>
                            </label>
                            <label class="checkbox-label <?php if (!$can_use_advanced) echo 'disabled'; ?>">
                                <input type="checkbox" name="online_only" value="1" <?php if (!$can_use_advanced) echo 'disabled'; ?>>
                                <span>Online Now</span>
                             </label>
                         </div>
                         <?php if (!$can_use_advanced): ?>
                          <a href="<?php echo esc_url($premium_page_url); ?>?highlight_plan=diamond" class="filter-lock-overlay-wrapper wide-overlay" title="Upgrade to Diamond to use this filter">
                            <div class="filter-lock-overlay"><i class="fas fa-lock"></i><span>Unlock Filters</span></div>
                          </a>
                         <?php endif; ?>
                    </div>
                 </form>
             </div>
             <div class="modal-footer">
                 <button type="button" class="modal-btn btn-secondary close-modal-btn">Cancel</button>
                 <button type="button" class="modal-btn btn-primary" id="apply-dashboard-filters">Apply Filters</button>
             </div>
        </div>
    </div>

    <!-- Swipe page quick access button -->
    <?php 
    // Array of catchy phrases for the swipe button
    $swipe_phrases = [
        'Find Your Match!', 
        'Start Swiping Now!', 
        'Your Match Awaits!',
        'Discover Love Today!',
        'Let\'s Find Your Perfect Match!',
        'Ready to Meet Someone?',
        'Start Your Love Story!',
        'Your Next Match is Waiting!',
        'Find Your Connection!',
        'Let Cupid Help You!'
    ];
    // Select a random phrase
    $random_phrase = $swipe_phrases[array_rand($swipe_phrases)];
    ?>
    <a href="<?php echo esc_url(SUD_URL . '/pages/swipe'); ?>" class="swipe-quick-access" title="Go to Swipe Page" aria-label="Go to Swipe Page">
        <div class="swipe-quick-access-content">
            <i class="fas fa-heart"></i>
            <span class="swipe-text"><?php echo esc_html($random_phrase); ?></span>
        </div>
    </a>

    <script>
        $(document).ready(function() {
            $('.photo-input').change(function(e) {
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    var preview = $(this).siblings('.photo-preview');
                    var photoBox = $(this).closest('.photo-upload-box');

                    reader.onload = function(e) {
                        preview.html('<img src="' + e.target.result + '" alt="Photo Preview">');
                        preview.show();
                        photoBox.addClass('has-image');
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });

            $('#photo-upload-form').on('submit', function(e) {
                let hasFiles = false;
                $('.photo-input').each(function() {
                    if (this.files && this.files.length > 0) {
                        hasFiles = true;
                        return false; 
                    }
                });
                if (!hasFiles) {
                    e.preventDefault();
                    alert('Please select at least one photo to upload.');
                    return false;
                }
                $('#upload-photos-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
            });
            $('.alert-close').on('click', function() {
                $(this).closest('.alert').fadeOut('fast');
            });

            // Premium restrictions for free users
            <?php if (!$is_premium_user): ?>
            $('.premium-only-tab').off('click.memberTabSwitch').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#dashboard-premium-modal').addClass('show');
                return false;
            });

            $('.premium-only-btn').off('click.loadMoreMembers click.dashboardFilter').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#dashboard-premium-modal').addClass('show');
                return false;
            });
            <?php endif; ?>
        });
    </script>

    <!-- Dashboard Premium Modal -->
    <div id="dashboard-premium-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <div class="modal-icon"><i class="fas fa-gem"></i></div>
            <h3>Unlock Full Dashboard</h3>
            <p><?php echo $is_on_trial ? 'These features are not available during trial. Upgrade to unlock full access!' : 'Switch between member tabs and load more members with a premium subscription!'; ?></p>
            <ul class="upgrade-benefits-list">
                <li><i class="fas fa-users"></i> Browse All Member Tabs</li>
                <li><i class="fas fa-eye"></i> Load More Members</li>
                <li><i class="fas fa-search-plus"></i> Advanced Search & Filters</li>
                <li><i class="fas fa-star"></i> Unlimited Swipes</li>
            </ul>
            <div class="premium-modal-actions">
                <a href="<?php echo esc_url(SUD_URL . '/pages/premium'); ?>" class="btn-primary"><?php echo $is_on_trial ? 'Upgrade Now' : 'Upgrade to Premium'; ?></a>
                <a href="<?php echo esc_url(SUD_URL . '/pages/swipe'); ?>" class="btn-secondary">Keep Swiping</a>
            </div>
        </div>
    </div>
</body>
</html>