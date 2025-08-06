<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');

$uri         = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path   = parse_url(SUD_URL, PHP_URL_PATH);
$current_slug = trim(str_replace($base_path, '', $uri), '/') ?: '';

$is_profile_details = ($current_slug === 'profile-details');
$is_profile_setup   = ($current_slug === 'profile-setup');
$body_class         = $is_profile_details ? 'profile-details-page' : '';

// Additional check for profile-setup (fallback)
$is_profile_setup = $is_profile_setup || (strpos($_SERVER['REQUEST_URI'], 'profile-setup') !== false);

// Ensure payment functions are loaded
if (!function_exists('sud_get_google_api_key')) {
    require_once(dirname(__FILE__, 2) . '/includes/payment-functions.php');
}

$google_api_key = function_exists('sud_get_google_api_key') ? sud_get_google_api_key() : '';
$load_google_maps = $is_profile_setup && !empty($google_api_key);

if ($load_google_maps) {
    echo "<script>window.SUD_GOOGLE_API_KEY = " . json_encode($google_api_key) . ";</script>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? esc_html($page_title) : 'Complete Your Profile | ' . esc_html(get_bloginfo('name')); ?></title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/ban-system.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.9.6/lottie.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <!-- Enhanced Location Helper -->
    <script src="<?php echo SUD_JS_URL; ?>/location-helper.js"></script>
    
    <!-- Internet Connection Tracker -->
    <script src="<?php echo SUD_JS_URL; ?>/internet-tracker.js"></script>

    <?php
        if ($load_google_maps && !empty($google_api_key)) {
            echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_api_key) . '&libraries=places&v=weekly&callback=initMap" async defer></script>';
        }
        if ( function_exists( 'get_site_icon_url' ) && ( $icon_url = get_site_icon_url() ) ) {
            echo '<link rel="icon" href="' . esc_url( $icon_url ) . '" />';
        }

        // Prefetching for the registration flow pages
        $uri       = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base_path = parse_url(SUD_URL, PHP_URL_PATH);
        $slug      = trim( str_replace($base_path, '', $uri), '/');
        
        $next_pages = [
            '' => 'looking-for',
            'looking-for' => 'account-signup',
            'account-signup' => 'introduction-signup',
            'introduction-signup' => 'verify-email',
            'verify-email' => 'profile-setup',
            'profile-setup' => 'welcome',
            'welcome' => 'profile-details'
        ];

        if (isset($next_pages[$slug])) {
            $next_slug = $next_pages[$slug];
            echo '<link rel="prefetch" href="'. SUD_URL .'/'. $next_slug .'">';
            echo '<link rel="prefetch" href="'. SUD_CSS_URL .'/style.css">';
            echo '<link rel="prefetch" href="'. SUD_JS_URL  .'/script.js">';
        }
    ?>
    
    <?php wp_head(); ?>
</head>
<body class="<?php echo $body_class; ?>" <?php
    if (is_user_logged_in()) {
        $current_user_id_header = wp_get_current_user()->ID;
        $user_gender = get_user_meta($current_user_id_header, 'gender', true);
        $functional_role = get_user_meta($current_user_id_header, 'functional_role', true);
        if ($user_gender) {
            echo ' data-gender="' . esc_attr($user_gender) . '"';
        }
        if ($functional_role) {
            echo ' data-functional-role="' . esc_attr($functional_role) . '"';
        }
    }
    ?>>
    
    <?php
    // Display ban notice if user is banned
    if (is_user_logged_in()) {
        $current_user_id = wp_get_current_user()->ID;
        
        // Check ban status directly here since require_login() might not be called
        if (!is_excluded_admin($current_user_id) && function_exists('sud_check_ban_status')) {
            $ban_status = sud_check_ban_status($current_user_id);
            if ($ban_status && function_exists('sud_display_ban_notice')) {
                sud_display_ban_notice($current_user_id);
            }
        }
        
        // Also check global ban status from require_login()
        global $sud_user_ban_status;
        if ($sud_user_ban_status && function_exists('sud_display_ban_notice')) {
            sud_display_ban_notice($current_user_id);
        }
    }
    ?>
    
    <!-- Menu overlay for mobile -->
    <div class="menu-overlay"></div>
    
    <div class="join-container">
        <?php if (!$is_profile_details): ?>
            <header class="join-header">
                <div class="logo">
                    <a href="<?php echo esc_url(site_url());?>">
                        <?php
                            $custom_logo_id = get_theme_mod('custom_logo');
                            $logo_image_url = '';
                            if ($custom_logo_id) {
                                $logo_image_url = wp_get_attachment_image_url($custom_logo_id, 'full');
                            }
                            if (!empty($logo_image_url)) {
                                echo '<img src="' . esc_url($logo_image_url) . '" alt="' . esc_attr(get_bloginfo('name')) . ' - Logo">';
                            } else {
                                echo '<img src="' . esc_url(SUD_IMG_URL . '/logo.png') . '" alt="' . esc_attr(SUD_SITE_NAME) . '" onerror="this.src=\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjUwIiB2aWV3Qm94PSIwIDAgMTAwIDUwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iNTAiIGZpbGw9IiMxMzBmNDAiLz48dGV4dCB4PSIxMCIgeT0iMzAiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNiIgZmlsbD0id2hpdGUiPkxNUjwvdGV4dD48L3N2Zz4=\'"></a>';
                            }
                        ?>
                    </a>
                </div>
            
                <button class="mobile-menu-toggle" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="nav-links">
                    <button class="mobile-menu-close" aria-label="Close menu">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <?php if (is_user_logged_in()): ?>
                        <?php
                        $current_user = wp_get_current_user();
                        $profile_picture_id = get_user_meta($current_user->ID, 'profile_picture', true);
                        $profile_pic_url = !empty($profile_picture_id) ? wp_get_attachment_image_url($profile_picture_id, 'thumbnail') : SUD_IMG_URL . '/default-profile.jpg';
                        ?>
                        <!-- Profile section (moved to top on mobile) -->
                        <div class="user-dropdown">
                            <a href="<?php echo SUD_URL . '/pages/profile'; ?>" class="user-profile-link">
                                <img src="<?php echo esc_url($profile_pic_url); ?>" alt="Profile" class="profile-mini">
                                <span><?php echo esc_html($current_user->display_name); ?></span>
                            </a>
                            <span class="dropdown-toggle">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                            <div class="user-dropdown-menu">
                                <div class="dropdown-content">
                                    <a href="<?php echo SUD_URL . '/profile-details'; ?>">
                                        <i class="fas fa-user-edit"></i> Edit Profile
                                    </a>
                                    <a href="<?php echo SUD_URL . '/pages/settings'; ?>">
                                        <i class="fas fa-cog"></i> Settings
                                    </a>
                                    <a href="<?php echo wp_logout_url(SUD_URL . '/auth/login'); ?>">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Main navigation items -->
                        <a href="<?php echo SUD_URL . '/pages/dashboard'; ?>" class="dashboard-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="<?php echo SUD_URL . '/pages/messages'; ?>" class="messages-link">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                        </a>
                        
                        <!-- Dropdown items as direct menu items (mobile only) -->
                        <a href="<?php echo SUD_URL . '/profile-details'; ?>" class="edit-profile-link">
                            <i class="fas fa-user-edit"></i>
                            <span>Edit Profile</span>
                        </a>
                        <a href="<?php echo SUD_URL . '/pages/settings'; ?>" class="settings-link">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        <a href="<?php echo wp_logout_url(SUD_URL . '/auth/login'); ?>" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                        
                    <?php else: ?>
                        <!-- Login/Join buttons -->
                        <div class="auth-buttons">
                            <a href="<?php echo SUD_URL . '/auth/login'; ?>" class="login-link">
                                <i class="fas fa-sign-in-alt"></i>
                                <span>Login</span>
                            </a>
                            <a href="<?php echo SUD_URL; ?>" class="join-link">
                                <i class="fas fa-user-plus"></i>
                                <span>Join</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </header>
        <?php endif; ?>
        <div class="join-content">