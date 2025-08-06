<?php

if (!isset($current_user)) {
    $current_user = wp_get_current_user();
}

$uri         = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path   = parse_url(SUD_URL,    PHP_URL_PATH);
$current_slug = trim(str_replace($base_path, '', $uri), '/') ?: '';

$is_profile_edit_page = ($current_slug === 'profile-edit' || $current_slug === 'pages/profile-edit' || strpos($_SERVER['REQUEST_URI'], 'profile-edit') !== false);

$current_user_id = isset($current_user) && $current_user->ID > 0 ? $current_user->ID : 0;
$display_name = $current_user->display_name ?? 'Guest'; 

$premium_details_header = null;
$header_premium_badge_html = '';
$header_user_is_verified = false;
$completion_percentage = 0;
$unread_message_count = 0;
$notification_count = 0;
$total_unread_count = 0;
$coin_balance = 0;
$profile_pic_url = SUD_IMG_URL . '/default-profile.jpg'; 

if ($current_user_id > 0) {
    if (function_exists('sud_get_user_current_plan_details')) {
        $premium_details_header = sud_get_user_current_plan_details($current_user_id);
    }
    if (!$premium_details_header) {
        $premium_details_header = sud_get_plan_details('free');
    }
    
    // Display warning notice if user has unacknowledged warnings
    if (function_exists('sud_display_warning_notice')) {
        add_action('wp_footer', function() use ($current_user_id) {
            sud_display_warning_notice($current_user_id);
        });
    }
    
    $header_user_is_verified = (bool) get_user_meta($current_user_id, 'is_verified', true);
    $completion_percentage = get_profile_completion_percentage($current_user_id);
    $coin_balance = get_user_meta($current_user_id, 'coin_balance', true) ?: 0;
    if (function_exists('get_user_messages')) {
        require_once(dirname(__FILE__, 3) . '/includes/messaging-functions.php'); 
        $user_messages = get_user_messages($current_user_id);
        $unread_message_count = $user_messages['unread_count'] ?? 0;
    }
    if(function_exists('sud_get_premium_badge_html')) {
        $header_premium_badge_html = sud_get_premium_badge_html($current_user_id, 'header');
    }
    $profile_picture_id = get_user_meta($current_user_id, 'profile_picture', true);
    if(!empty($profile_picture_id)) {
        $profile_pic_url = wp_get_attachment_image_url($profile_picture_id, 'thumbnail');
    }
} else {
    $premium_details_header = sud_get_plan_details('free');
}

$google_api_key_header = '';
if (function_exists('sud_get_google_api_key')) {
    $google_api_key_header = sud_get_google_api_key();
}

$payment_settings = get_option('sud_payment_settings');
$test_mode_header = isset($payment_settings['test_mode']) ? (bool)$payment_settings['test_mode'] : false;

// Get the correct keys based on the mode
if (function_exists('sud_get_stripe_publishable_key') && function_exists('sud_get_paypal_client_id')) {
    $stripe_key_header = sud_get_stripe_publishable_key();
    $paypal_client_id_header = sud_get_paypal_client_id();
} else {
    // Fallback if functions aren't loaded yet (unlikely but safe)
    $stripe_key_header = $test_mode_header ? ($payment_settings['stripe_test_publishable_key'] ?? '') : ($payment_settings['stripe_live_publishable_key'] ?? '');
    $paypal_client_id_header = $test_mode_header ? ($payment_settings['paypal_test_client_id'] ?? '') : ($payment_settings['paypal_live_client_id'] ?? 'sb');
}

$should_load_maps_api_header = $is_profile_edit_page && !empty($google_api_key_header);

if ($should_load_maps_api_header) {
    echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_api_key_header) . '&libraries=places&v=weekly" async defer></script>';
}
?>
<header>
    <div id="location-prompt-modal" class="sud-location-prompt">
        <div class="sud-location-prompt-content">
            <span class="sud-location-prompt-text">
                <i class="fas fa-map-marker-alt"></i> Please enable location to discover members near you.
            </span>
            <button id="location-prompt-enable-btn" class="sud-button sud-button-primary sud-button-small">Enable Location</button>
            <button id="location-prompt-close-btn" class="sud-location-prompt-close">×</button>
        </div>
    </div>
    <script src="<?php echo SUD_JS_URL; ?>/location-helper.js"></script>
    <div class="container">
        <nav class="navbar">
            <div class="logo">
            <?php
                $custom_logo_id = get_theme_mod('custom_logo'); 
                $logo_image_url = '';
                if ($custom_logo_id) {
                    $logo_image_url = wp_get_attachment_image_url($custom_logo_id, 'full');
                }

                if (!empty($logo_image_url)) {
                    echo '<a href="' . esc_url(SUD_URL . '/pages/dashboard') . '"><img src="' . esc_url($logo_image_url) . '" alt="' . esc_attr(get_bloginfo('name')) . ' - Logo"></a>'; 
                } else {
                    echo '<a href="' . esc_url(SUD_URL . '/pages/dashboard') . '"><img src="' . esc_url(SUD_IMG_URL . '/logo.png') . '" alt="' . esc_attr(SUD_SITE_NAME) . '" onerror="this.src=\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjUwIiB2aWV3Qm94PSIwIDAgMTAwIDUwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iNTAiIGZpbGw9IiMxMzBmNDAiLz48dGV4dCB4PSIxMCIgeT0iMzAiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNiIgZmlsbD0id2hpdGUiPkxNUjwvdGV4dD48L3N2Zz4=\'"></a>';
                }
            ?>
            </div>
            
            <button class="mobile-menu-toggle" id="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="nav-menu" id="nav-menu">
                <div class="nav-item <?php echo ($current_slug === 'search') ? 'active' : ''; ?>">
                    <a href="<?php echo SUD_URL; ?>/pages/search" class="nav-link">
                        <i class="fas fa-search"></i> <span>Search</span>
                    </a>
                </div>
                <div class="nav-item <?php echo ($current_slug === 'messages') ? 'active' : ''; ?>">
                    <a href="<?php echo SUD_URL; ?>/pages/messages" class="nav-link">
                        <i class="fas fa-envelope"></i> <span>Messages</span>
                        <?php if ($unread_message_count > 0): ?>
                        <span class="badge"><?php echo $unread_message_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="nav-item <?php echo ($current_slug === 'activity' && isset($_GET['tab']) && $_GET['tab'] === 'my-favorites') ? 'active' : ''; ?>">
                    <a href="<?php echo SUD_URL; ?>/pages/activity?tab=my-favorites" class="nav-link">
                        <i class="fas fa-heart"></i> <span>Favorites</span>
                    </a>
                </div>
                <div class="nav-item <?php echo ($current_slug === 'activity' && isset($_GET['tab']) && $_GET['tab'] === 'notifications') ? 'active' : ''; ?>">
                    <a href="#" class="nav-link" id="notification-toggle">
                        <i class="fas fa-bell"></i>
                        <?php
                        $notification_count = function_exists('get_unread_notification_count') ? 
                            get_unread_notification_count($current_user->ID) : 0;

                        if ($notification_count > 0): 
                        ?>
                        <span class="badge notification-badge"><?php echo $notification_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Notification dropdown -->
                    <div class="notification-dropdown" id="notification-dropdown">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <a href="#" class="mark-all-read">Mark all as read</a>
                        </div>
                        <div class="notification-content" id="notification-list">
                            <!-- Notifications will be loaded here via JavaScript -->
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="notification-footer">
                            <a href="<?php echo SUD_URL; ?>/pages/activity?tab=notifications">View all notifications</a>
                        </div>
                    </div>
                </div>
                <div class="nav-item">
                    <a href="<?php echo SUD_URL; ?>/pages/wallet" class="nav-link wallet-link">
                        <div class="sud-coin">
                            <img src="<?php echo SUD_IMG_URL; ?>/sud-coin.png" alt="SUD" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMiIgZmlsbD0iI0YyRDA0RiIvPjx0ZXh0IHg9IjciIHk9IjE2IiBmb250LXNpemU9IjEwIiBmb250LXdlaWdodD0iYm9sZCIgZmlsbD0iI0ZGRkZGRiI+TDwvdGV4dD48L3N2Zz4='">
                            <span class="coin-count" data-coin-balance><?php echo number_format($coin_balance); ?></span>
                        </div>
                    </a>
                </div>
            </div>
            <div class="user-profile" id="user-profile-menu">
                <a href="<?php echo SUD_URL; ?>/pages/profile" class="user-profile-link">
                    <img src="<?php echo esc_url($profile_pic_url); ?>" alt="Profile Picture">
                    <span><?php echo esc_html($display_name); ?></span>
                    <?php if ($header_user_is_verified): ?>
                        <span class="verified-badge" title="SUD Verified"><img src="<?php echo SUD_IMG_URL; ?>/verified-profile-badge.png" alt="verified" style="width: 16px; height: 16px; margin: 0; vertical-align: middle;"></span>
                    <?php endif; ?>
                    <?php echo $header_premium_badge_html; ?>
                </a>

                <!-- Dropdown toggle - only this opens the menu -->
                <i class="fas fa-chevron-down dropdown-toggle" style="padding: 0;"></i>

                <!-- User dropdown menu -->
                <div class="profile-dropdown" id="profile-dropdown">
                    <?php if (!$header_user_is_verified && !($premium_details_header['verification_badge_auto'] ?? false)): ?>
                    <a href="<?php echo SUD_URL; ?>/pages/premium<?php // -> /pages/get-verified?>" class="profile-dropdown-item">
                        Get UP Verified
                        <span style="display: flex;align-items: center;"><img src="<?php echo SUD_IMG_URL; ?>/verified-profile-badge.png" alt="verified" style="width: 20px; height: 20px; margin-right: 5px;"></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($completion_percentage < 100): ?>
                    <a href="<?php echo SUD_URL; ?>/profile-details" class="profile-dropdown-item">
                        Complete Profile
                        <span class="badge-percentage"><?php echo $completion_percentage; ?>%</span>
                    </a>
                    <?php else: ?>
                    <a href="<?php echo SUD_URL; ?>/pages/profile" class="profile-dropdown-item">
                        View My Profile
                        <span class="badge-percentage"><?php echo $completion_percentage; ?>%</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo SUD_URL; ?>/pages/wallet" class="profile-dropdown-item">
                        Wallet
                        <span style="display: flex;align-items: center;"><img src="<?php echo SUD_IMG_URL; ?>/sud-coin.png" alt="SUD" style="width: 20px; height: 20px; margin-right: 5px;"><span data-coin-balance><?php echo number_format($coin_balance); ?></span></span>
                    </a>
                    <?php $current_plan_details = sud_get_user_current_plan_details($current_user->ID); ?>
                    <a href="<?php echo SUD_URL; ?>/pages/premium" class="profile-dropdown-item">
                        <?php if ($current_plan_details['id'] !== 'free'): ?>
                            <?php echo esc_html($current_plan_details['name']); ?> Member
                            <?php echo sud_get_premium_badge_html($current_user->ID, 'xs'); ?>
                        <?php else: ?>
                            Upgrade to Premium
                            <span class="upgrade-hint-icon">✨</span>
                        <?php endif; ?>
                    </a>
                    <a href="<?php echo SUD_URL; ?>/pages/subscription" class="profile-dropdown-item">
                        Subscription
                        <i class="fas fa-credit-card" style="width: 16px; color: #FF66CC;"></i>
                    </a>
                    <a href="<?php echo SUD_URL; ?>/pages/withdrawal" class="profile-dropdown-item">
                        Withdraw Coins
                        <?php 
                        $user_plan = sud_get_user_current_plan_details($current_user_id);
                        $can_withdraw = ($user_plan['id'] === 'gold' || $user_plan['id'] === 'diamond');
                        if (!$can_withdraw) echo '<span class="upgrade-hint-icon">🔒</span>'; 
                        ?>
                    </a>
                    <a href="<?php echo site_url('/privacy-policy'); ?>" class="profile-dropdown-item">
                        Privacy Policy
                    </a>
                    <a href="<?php echo site_url('/contact'); ?>" class="profile-dropdown-item">
                        Support
                        <?php if (sud_user_can_access_feature($current_user->ID, 'priority_support')): ?>
                             <span class="priority-hint-icon" title="Priority Support Active">⭐</span>
                        <?php endif; ?>
                    </a>
                    <a href="<?php echo site_url('/faq'); ?>" class="profile-dropdown-item">
                        FAQ
                    </a>
                    <a href="<?php echo site_url('/terms-of-service'); ?>" class="profile-dropdown-item">
                        Terms of Service
                    </a>
                    <a href="<?php echo SUD_URL; ?>/pages/settings" class="profile-dropdown-item">
                        Settings
                    </a>
                    <a href="<?php echo wp_logout_url(SUD_URL . '/auth/login'); ?>" class="profile-dropdown-item">
                        Log out
                    </a>
                </div>
            </div>
        </nav>
    </div>
</header>
<link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/ban-system.css">
<?php
// Display ban notice if user is banned
global $sud_user_ban_status;
if ($sud_user_ban_status && function_exists('sud_display_ban_notice')) {
    sud_display_ban_notice($current_user_id);
}
?>
<!-- Internet Connection Tracker -->
<script src="<?php echo SUD_JS_URL; ?>/internet-tracker.js"></script>
<script>
    var sud_config_base = {
        sud_url: '<?php echo SUD_URL; ?>',
        urls: {
            img_path: '<?php echo SUD_IMG_URL; ?>',
            sound_path: '<?php echo SUD_URL; ?>/assets/sounds',
            assets_path: "<?php echo esc_js(SUD_ASSETS_URL); ?>"
        },
        functional_role: '<?php echo esc_js(get_user_meta($current_user_id, 'functional_role', true) ?: 'provider'); ?>',
        current_user_id: <?php echo $current_user_id; ?>,
        is_logged_in: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
        current_user_plan: '<?php echo esc_js($premium_details_header['id'] ?? 'free'); ?>',
        current_user_tier_level: <?php echo (int)($premium_details_header['tier_level'] ?? 0); ?>,
        is_premium_user: <?php echo json_encode(($premium_details_header['id'] ?? 'free') !== 'free'); ?>,
        user_can_view_profiles: <?php echo json_encode(sud_user_can_access_feature($current_user_id, 'viewed_profile')); ?>,
        user_can_use_advanced_filters: <?php echo json_encode(sud_user_can_access_feature($current_user_id, 'advanced_filters')); ?>,
        admin_email: 'support@swipeupdaddy.com',
        ajax_nonce: '<?php echo wp_create_nonce('sud_ajax_action'); ?>'
    };

    document.addEventListener('DOMContentLoaded', function() {
        const locationModal = document.getElementById('location-prompt-modal');
        const enableBtn = document.getElementById('location-prompt-enable-btn');
        const closeBtn = document.getElementById('location-prompt-close-btn');
        const modalText = locationModal ? locationModal.querySelector('.sud-location-prompt-text') : null;

        const userId = <?php echo $current_user_id; ?>; 
        const ajaxUrl = '<?php echo esc_url(SUD_AJAX_URL . '/update-location.php'); ?>';

        if (document.body.classList.contains('sud-location-missing') && locationModal) {
            locationModal.style.display = 'flex'; 
        }

        if (enableBtn) {
            enableBtn.addEventListener('click', function() {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enabling...';
                if (modalText) modalText.innerHTML = '<i class="fas fa-map-marker-alt"></i> Trying to access your location...';
                
                sudGetUserLocation(userId, ajaxUrl,
                    function(result) { 
                        if (modalText) modalText.innerHTML = '<i class="fas fa-check-circle"></i> Location updated!';

                        setTimeout(() => {
                            locationModal.style.display = 'none';
                            document.body.classList.remove('sud-location-missing'); 
                        }, 1500);
                    },
                    function(errorType, errorMessage) { 
                        if (modalText) modalText.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${errorMessage}`;
                        enableBtn.disabled = false;
                        enableBtn.innerHTML = 'Try Again';
                    }
                );
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                locationModal.style.display = 'none';
            });
        }
    });

    // Add the mobile menu toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const navMenu = document.getElementById('nav-menu');
        
        if (mobileMenuToggle && navMenu) {
            mobileMenuToggle.addEventListener('click', function() {
                navMenu.classList.toggle('active');
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!navMenu.contains(event.target) && !mobileMenuToggle.contains(event.target) && navMenu.classList.contains('active')) {
                    navMenu.classList.remove('active');
                }
            });
        }
    });
</script>