<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');

// Maintenance mode check now handled globally in config.php

require_login();

$current_user_id = get_current_user_id();
$current_wp_user = wp_get_current_user();
$user_display_name = '';
if ($current_wp_user && $current_wp_user->ID) {
    $user_display_name = esc_js($current_wp_user->first_name ?: $current_wp_user->display_name);
}

$current_user_functional_role = get_user_meta($current_user_id, 'functional_role', true);

$initial_candidate_count = 40;
$initial_candidates = [];
if (function_exists('sud_get_swipe_candidates')) {
    $result = sud_get_swipe_candidates($current_user_id, $initial_candidate_count);
    $initial_candidates = $result['candidates'] ?? [];
    
    // Ensure boolean integrity for JSON encoding
    foreach ($initial_candidates as $index => $candidate) {
        if (isset($candidate['has_active_boost'])) {
            $initial_candidates[$index]['has_active_boost'] = (bool) $candidate['has_active_boost'];
        }
    }
} else {
    error_log("SUD Error: sud_get_swipe_candidates function not found in swipe.php");
}

$remaining_swipes = 0;
if (function_exists('sud_get_daily_swipe_limit') && function_exists('sud_get_user_swipe_count_today')) {
    $remaining_swipes = sud_get_daily_swipe_limit($current_user_id) - sud_get_user_swipe_count_today($current_user_id);
    $remaining_swipes = max(0, $remaining_swipes);
} else {
    error_log("SUD Error: Swipe limit/count functions not found in swipe.php");
}

$has_premium = false;
if (function_exists('sud_is_user_premium')) {
    $has_premium = sud_is_user_premium($current_user_id);
}

// Sidebar specific data
$sidebar_profile_pic_url = SUD_IMG_URL . '/default-profile.jpg'; // Default
$profile_picture_id = get_user_meta($current_user_id, 'profile_picture', true);
if (!empty($profile_picture_id)) {
    $sidebar_profile_pic_url_temp = wp_get_attachment_image_url($profile_picture_id, 'thumbnail');
    if ($sidebar_profile_pic_url_temp) {
        $sidebar_profile_pic_url = $sidebar_profile_pic_url_temp;
    }
}
$sidebar_user_name = $current_wp_user && $current_wp_user->ID ? ($current_wp_user->first_name ?: $current_wp_user->display_name) : 'User';

$user_match_count = 0;
if (function_exists('sud_get_user_total_match_count')) {
    $user_match_count = sud_get_user_total_match_count($current_user_id);
}

$user_likes_received_count = 0;
if (function_exists('sud_get_user_likes_received_count')) {
    $user_likes_received_count = sud_get_user_likes_received_count($current_user_id);
}

$current_user_plan_id = 'free';
if (function_exists('sud_get_user_premium_plan_id')) {
    $current_user_plan_id = sud_get_user_premium_plan_id($current_user_id);
}

$swipe_up_balance = function_exists('sud_get_user_swipe_up_balance') ? sud_get_user_swipe_up_balance($current_user_id) : 0;

$user_plan_details = sud_get_user_current_plan_details($current_user_id);
$user_tier_level = isset($user_plan_details['tier_level']) ? (int)$user_plan_details['tier_level'] : 0;

// Check trial status for dynamic upgrade prompts
$active_trial = sud_get_active_trial($current_user_id);
$is_on_trial = $active_trial !== false;

// Use centralized validation for all profile requirements
validate_core_profile_requirements($current_user_id, 'swipe');

// Track signup conversion for new users who reach the main app for the first time
if (isset($_GET['first_time']) && $_GET['first_time'] == '1') {
    $already_tracked = get_user_meta($current_user_id, 'sud_trafficjunky_signup_tracked', true);
    
    if (!$already_tracked && function_exists('track_signup_conversion')) {
        track_signup_conversion($current_user_id, [
            'registration_source' => 'app_first_access',
            'aclid' => $_GET['aclid'] ?? ''
        ]);

        update_user_meta($current_user_id, 'sud_trafficjunky_signup_tracked', current_time('mysql'));
    }
}

$page_title = "Discover Matches - " . SUD_SITE_NAME;
$boost_packages = sud_get_boost_packages();
$swipe_up_packages = sud_get_swipe_up_packages();
$payment_settings = get_option('sud_payment_settings');
$test_mode = isset($payment_settings['test_mode']) ? (bool)$payment_settings['test_mode'] : false;
$stripe_key = sud_get_stripe_publishable_key();
$paypal_client_id = sud_get_paypal_client_id();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <?php
        if ( function_exists( 'get_site_icon_url' ) && ( $icon_url = get_site_icon_url() ) ) {
            echo '<link rel="icon" href="' . esc_url( $icon_url ) . '" />';
        }
    ?>
    <title><?php echo esc_html($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/buttons.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/swipe.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/payment-modals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
    <?php 
    require_once(dirname(__FILE__, 2) . '/includes/payment-loader.php');
    sud_load_payment_scripts('one-time');
    ?>
    <script>
        var sud_payment_settings = <?php echo json_encode($payment_settings); ?>;
        var sud_swipe_page_config = {
            ajax_url: "<?php echo esc_js(SUD_AJAX_URL . '/process-swipe.php'); ?>",
            swipe_nonce: "<?php echo esc_js(wp_create_nonce('sud_swipe_action_nonce')); ?>",
            payment_nonce: "<?php echo esc_js(wp_create_nonce('sud_one_off_payment_nonce')); ?>",
            initial_candidates: <?php echo json_encode($initial_candidates); ?>,
            remaining_swipes: <?php echo intval($remaining_swipes); ?>,
            is_premium: <?php echo $has_premium ? 'true' : 'false'; ?>,
            img_path_url: "<?php echo esc_js(SUD_IMG_URL); ?>",
            premium_url: "<?php echo esc_js(SUD_URL . '/pages/premium'); ?>",
            currentUserFirstName: "<?php echo $user_display_name; ?>",
            sud_url: "<?php echo esc_js(SUD_URL); ?>",
            swipe_up_balance: <?php echo intval($swipe_up_balance); ?>,
            user_plan_id: "<?php echo esc_js($current_user_plan_id); ?>",
            user_tier_level: <?php echo intval($user_tier_level); ?>,
            sounds: {
                match: "<?php echo esc_js(SUD_ASSETS_URL . '/sounds/match.mp3'); ?>",
                cash: "<?php echo esc_js(SUD_ASSETS_URL . '/sounds/coin_drop.mp3'); ?>"
            }
        };
    </script>
    <script src="<?php echo SUD_JS_URL; ?>/swipe-page.js"></script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
</head>
<body class="sud-swipe-page-body">
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>
    <div id="toast-container" class="toast-container"></div>
    
    <?php if (isset($_GET['first_time']) && $_GET['first_time'] == '1'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (typeof SUD !== 'undefined' && SUD.showToast) {
                    SUD.showToast('success', 'Welcome to Swipe!', 'Start swiping to find your perfect match. Swipe right to like, left to pass.');
                }
            }, 1000);
        });
    </script>
    <?php endif; ?>

    <div class="swipe-page-layout"> 
        <aside id="sud-swipe-sidebar-left">
            <div class="sidebar-profile-section">
                <img src="<?php echo esc_url($sidebar_profile_pic_url); ?>" alt="Your Profile Picture" class="sidebar-profile-pic">
                <h3 class="sidebar-user-name"><?php echo esc_html($sidebar_user_name); ?></h3>
            </div>
            <div class="sidebar-stats">
                <div class="stat-item">
                    <a href="<?php echo esc_url(SUD_URL . '/pages/activity?tab=my-matches'); ?>" class="stat-link">
                        <span class="stat-value"><?php echo esc_html($user_match_count); ?></span>
                        <span class="stat-label">Matches</span>
                    </a>
                </div>
                <div class="stat-item">
                    <a href="<?php echo esc_url(SUD_URL . '/pages/activity?tab=likes-received'); ?>" class="stat-link">
                        <span class="stat-value"><?php echo esc_html($user_likes_received_count); ?></span>
                        <span class="stat-label">Likes Received</span>
                    </a>
                </div>
            </div>
            <div class="sidebar-actions">
                <a href="<?php echo esc_url(SUD_URL . '/pages/profile'); ?>" class="btn btn-sidebar">View My Profile</a>
                <a href="<?php echo esc_url(SUD_URL . '/pages/profile-edit'); ?>" class="btn btn-sidebar">Edit Profile</a>
                <?php if (!$has_premium): ?>
                    <a href="<?php echo esc_url(SUD_URL . '/pages/premium'); ?>" class="btn btn-sidebar btn-premium-upgrade">Upgrade to Premium ✨</a>
                <?php endif; ?>
            </div>
        </aside>

        <main class="swipe-main-container">
            <div id="swipe-deck-loading" class="swipe-message-fullscreen" style="display: none;">
                <i class="fas fa-spinner fa-spin"></i> Loading Profiles...
            </div>
            <div id="swipe-deck-empty" class="swipe-message-fullscreen" style="display: none;">
                <i class="fas fa-users-slash"></i> No more new profiles to show right now. Check back later!
            </div>

            <div id="swipe-deck-skeleton-area">
                <div class="skeleton-card-swipe" style="z-index: 3; transform: translateY(0px) scale(1);">
                    <div class="skeleton-image"></div>
                    <div class="skeleton-info-area"><div class="skeleton-text skeleton-name"></div><div class="skeleton-text skeleton-location"></div></div><div class="shimmer"></div>
                </div>
                <div class="skeleton-card-swipe" style="z-index: 2; transform: translateY(8px) scale(0.96); opacity: 0.7;">
                    <div class="skeleton-image"></div>
                    <div class="skeleton-info-area"><div class="skeleton-text skeleton-name"></div><div class="skeleton-text skeleton-location"></div></div><div class="shimmer"></div>
                </div>
                <div class="skeleton-card-swipe" style="z-index: 1; transform: translateY(16px) scale(0.92); opacity: 0.4;">
                    <div class="skeleton-image"></div>
                    <div class="skeleton-info-area"><div class="skeleton-text skeleton-name"></div><div class="skeleton-text skeleton-location"></div></div><div class="shimmer"></div>
                </div>
            </div>

            <div id="swipe-controls-skeleton">
                <div class="skeleton-button"></div><div class="skeleton-button"></div><div class="skeleton-button"></div><div class="skeleton-button"></div><div class="skeleton-button"></div>
            </div>
            <div id="remaining-swipes-skeleton"></div>

            <div id="swipe-deck-area" style="display: none;"></div>

            <div id="swipe-controls" style="display: none;">
                <button id="swipe-reverse-btn" class="swipe-action-btn reverse" title="Reverse Last Swipe"><i class="fas fa-undo"></i></button>
                <button id="swipe-pass-btn" class="swipe-action-btn pass" title="Pass"><i class="fas fa-times"></i></button>
                <button id="swipe-boost-btn" class="swipe-action-btn boost" title="Swipe Up - Instant Match">
                    <img src="<?php echo SUD_IMG_URL; ?>/swipe_up.png" alt="Swipe Up" class="boost-icon-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                    <i class="fas fa-rocket boost-icon-fallback" style="display: none;"></i>
                </button>
                <button id="swipe-like-btn" class="swipe-action-btn like" title="Like"><i class="fas fa-heart"></i></button>
                <button id="boost-quick-access-btn" class="swipe-action-btn boost-quick-access" title="Boost Your Profile"><i class="fas fa-rocket"></i></button>
            </div>
            <div id="remaining-swipes-display-container" style="text-align: center; margin-top: 10px; display: none;">
                Swipes remaining: <span id="remaining-swipes-count"><?php echo intval($remaining_swipes); ?></span>
            </div>
        </main>
    </div>

    <!-- MODALS -->
    <div id="swipe-limit-modal" class="modal"><div class="sud-modal-content"><span class="close-modal">×</span><div class="modal-icon"><i class="fas fa-lock"></i></div><h3 id="swipe-limit-modal-title">You've Hit Your Daily Swipe Limit</h3><p id="swipe-limit-modal-message">Daddies are just getting started… and so are you. <?php echo $is_on_trial ? 'Unlimited swipes not available in trial. Upgrade to Gold to unlock:' : 'Upgrade now to unlock:'; ?></p><ul class="upgrade-benefits-list"><li><i class="fas fa-infinity"></i> Unlimited swiping</li><li><i class="fas fa-star"></i> Priority match placement</li><li><i class="fas fa-bolt"></i> Faster access to sugar daddies ready to spoil you</li></ul><div class="modal-actions"><a href="<?php echo esc_url(SUD_URL . '/pages/premium?highlight_plan=gold&direct_pay=true&plan=gold'); ?>" class="btn btn-primary"><?php echo $is_on_trial ? 'Upgrade to Gold' : 'Unlock Unlimited Swipes'; ?></a><button type="button" class="btn btn-secondary close-modal-btn">Maybe Later</button></div></div></div>
    <div id="match-notification-modal" class="modal match-modal"><div class="sud-modal-content"><div class="match-animation-container"><img id="match-user1-img" src="<?php echo esc_url(get_user_meta($current_user_id, 'profile_picture', true) ? wp_get_attachment_image_url(get_user_meta($current_user_id, 'profile_picture', true), 'thumbnail') : SUD_IMG_URL . '/default-profile.jpg'); ?>" alt="Your Profile Picture"><img id="match-user2-img" src="<?php echo esc_url(SUD_IMG_URL . '/default-profile.jpg'); ?>" alt="Matched User Profile Picture"><div class="match-heart-icon"><i class="fas fa-heart"></i></div></div><h2 id="match-modal-title">It's a Match!</h2><p id="match-modal-description">You and <strong id="match-user-name">User</strong> have liked each other.</p><div class="modal-actions"><a href="#" id="match-send-message-btn" class="btn btn-primary">Send a Message</a><button type="button" id="match-keep-swiping-btn" class="btn btn-secondary close-modal-btn">Keep Swiping</button></div><div id="confetti-container"></div></div></div>
    <div id="one-swipe-left-modal" class="modal"><div class="sud-modal-content"><span class="close-modal">×</span><div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div><h3>Last Swipe!</h3><p>You're about to use your last free swipe for today. After this, you'll need to <?php echo $is_on_trial ? 'upgrade to Gold' : 'upgrade'; ?> to keep discovering new profiles.</p><p><strong><?php echo $is_on_trial ? 'Unlimited swipes not available in trial. Upgrade to Gold now' : 'Upgrade now'; ?> to unlock unlimited swiping and more features!</strong></p><div class="modal-actions"><a href="<?php echo esc_url(SUD_URL . '/pages/premium?highlight_plan=gold&direct_pay=true&plan=gold'); ?>" class="btn btn-primary upgrade-from-warning-btn"><?php echo $is_on_trial ? 'Upgrade to Gold' : 'Upgrade to Premium'; ?></a><button type="button" class="btn btn-secondary continue-last-swipe-btn">Use Last Swipe</button></div></div></div>
    <div id="boost-modal" class="modal"><div class="sud-modal-content"><span class="close-modal">×</span><div class="modal-icon"><i class="fas fa-rocket"></i></div><h3>Want More Messages? Get Boosted.</h3><p class="boost-intro">We'll push your profile to the top of elite daddies' inboxes — so you get seen (and spoiled) faster. <span class="boost-stats">💡 Boosted profiles get 3.2x more messages within 24 hours.</span></p><div class="boost-options"><?php foreach ($boost_packages as $boost): $option_class = $boost['is_popular'] ? 'boost-option popular' : 'boost-option';?><div class="<?php echo $option_class; ?>"><div class="boost-header"><h4><?php echo $boost['icon']; ?> <?php echo $boost['name']; ?></h4><div class="boost-price">$<?php echo number_format($boost['price'], 2); ?></div></div><p class="boost-feature-summary"><?php echo $boost['description']; ?></p><button class="btn btn-primary boost-btn" data-boost-type="<?php echo $boost['id']; ?>"><?php echo $boost['id'] === 'mini' ? 'Send Mini Boost' : ($boost['id'] === 'power' ? 'Activate Power Boost' : 'Launch Diamond Blast'); ?></button></div><?php endforeach; ?></div><p class="boost-fomo">⏳ These daddies spend fast. Boost now — or get buried below the scroll.</p></div></div>
    <div id="swipe-up-modal" class="modal"><div class="sud-modal-content"><span class="close-modal">×</span><div class="modal-icon"><i class="fas fa-bolt"></i></div><h3>💎 Unlock Instant Matches! 💎</h3><?php if ($current_user_functional_role === 'receiver'): ?><p class="boost-intro">Tired of waiting? Swipe Up gives you the power to instantly match with a Daddy—no waiting, no hesitation. When you swipe up, you're showing them you're ready to be spoiled! Plus, every time a Daddy swipes up on you, he's paying to prove he's serious.</p><?php else: ?><p class="boost-intro">Swipe Up instantly connects you with the Baby you want—no waiting, no games. When you Swipe Up, you're showing her you're ready to spoil and you've paid to prove it. Get noticed and start chatting right away with the one you want.</p><?php endif; ?><div class="boost-options swipe-up-packages"><?php foreach ($swipe_up_packages as $package): $option_class = $package['is_popular'] ? 'boost-option popular' : 'boost-option';?><div class="<?php echo $option_class; ?>"><div class="boost-header"><h4><?php echo $package['name']; ?></h4><div class="boost-price">$<?php echo number_format($package['price'], 2); ?></div><?php if (isset($package['badge'])): ?><span class="popular-badge"><?php echo $package['badge']; ?></span><?php endif; ?></div><p class="boost-feature-summary"><?php echo $package['description']; ?></p><button class="btn btn-primary swipe-up-btn" data-package="<?php echo $package['id']; ?>">Purchase</button></div><?php endforeach; ?></div><p class="boost-fomo">Don't miss out on your perfect match! Swipe Up to connect instantly.</p></div></div>
    <div id="reverse-upgrade-modal" class="modal"><div class="sud-modal-content"><span class="close-modal">×</span><div class="modal-icon"><i class="fas fa-undo-alt"></i></div><h3>⏪ Unlock Reverse Swipe!</h3><p>Made a mistake? No problem! The Reverse feature lets you undo your last swipe and get a second chance with that perfect match. Available with premium subscription.</p><ul class="upgrade-benefits-list"><li><i class="fas fa-undo-alt"></i> Reverse your last swipe instantly</li><li><i class="fas fa-heart-crack"></i> Fix accidental passes on perfect matches</li><li><i class="fas fa-clock-rotate-left"></i> Turn back time on swiping mistakes</li></ul><div class="modal-actions"><a href="<?php echo esc_url(SUD_URL . '/pages/premium'); ?>" class="btn btn-primary"><?php echo $is_on_trial ? '💳 Complete Payment' : '🚀 Upgrade to Premium'; ?></a><button type="button" class="btn btn-secondary close-modal-btn">Continue Swiping</button></div></div></div>
    <div id="claim-free-swipe-up-modal" class="modal claim-modal-no-dismiss"><div class="sud-modal-content"><div class="modal-icon"><i class="fas fa-gift"></i></div><h3>Want to Stand Out?</h3><p>Claim Your <strong>Free Swipe Up</strong> to Match Instantly with any Sugar Daddy/Mommy you really like!</p><ul class="upgrade-benefits-list"><li><i class="fas fa-bolt"></i> Instantly match without waiting for them to like you back</li><li><i class="fas fa-eye"></i> Show them you're serious and ready to connect</li><li><i class="fas fa-comment-dots"></i> Start messaging right away</li></ul><div class="modal-actions"><button type="button" id="claim-free-swipe-up-btn" class="btn btn-primary">🎁 Claim My Free Swipe Up</button></div></div></div>

    <div id="match-required-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <div class="modal-icon"><i class="fas fa-heart-broken"></i></div>
            <h3>Match Required</h3>
            <div class="match-required-user">
                <img id="match-required-user-img" src="<?php echo esc_url(SUD_IMG_URL . '/default-profile.jpg'); ?>" alt="User Picture">
                <p>You need to match with <strong id="match-required-user-name">this user</strong> before you can send them a message.</p>
            </div>
            <p class="match-required-instruction">Keep swiping to see if they like you back, or use a Swipe Up to match with them instantly!</p>
            <div class="modal-actions">
                <button type="button" id="match-required-instant-match-btn" class="btn btn-primary">
                    <i class="fas fa-bolt"></i> Instant Match
                </button>
                <a href="#" id="match-required-profile-link" class="btn btn-secondary">View Profile</a>
            </div>
        </div>
    </div>
    
    <div id="one-off-purchase-modal" class="payment-modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <div class="purchase-info">
                <h3 id="purchase-item-name">Purchase Item</h3>
                <div class="purchase-price"><span id="purchase-total-price">$0.00</span></div>
                <p id="purchase-item-description">Complete your purchase below.</p>
            </div>
            <?php if ($test_mode): ?>
                <div class="test-mode-warning">
                    <i class="fas fa-exclamation-triangle"></i> Test mode is active. No real charges will be made.
                </div>
            <?php endif; ?>
            <div class="payment-section active" id="card-section-purchase">
                <form id="card-payment-form-purchase">
                    <div class="form-group">
                        <label for="card-email-purchase" class="form-label">Email Address</label>
                        <input type="email" id="card-email-purchase" class="form-input" 
                               placeholder="Email Address" 
                               value="<?php echo esc_attr($current_wp_user->user_email ?? ''); ?>" required>
                        <small>Pre-filled from your account</small>
                    </div>
                    <div class="form-group">
                        <label for="card-name-purchase" class="form-label">Name on Card</label>
                        <input type="text" id="card-name-purchase" class="form-input" 
                               placeholder="Full Name" 
                               value="<?php echo esc_attr($current_wp_user->display_name ?? ''); ?>" required>
                        <small>Pre-filled from your account</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Card Information</label>
                        <div id="card-element-purchase" class="stripe-card-element"></div>
                        <div id="card-errors-purchase" role="alert" style="color: #fa755a; margin-top: 5px;"></div>
                    </div>
                    <button type="submit" class="btn-confirm">Complete Purchase</button>
                </form>
            </div>
        </div>
    </div>

    <div id="sud-payment-success-popup" class="sud-success-popup" style="display: none;">
        <div class="sud-success-popup-content">
            <div class="sud-success-icon">
                <!-- Container for the Lottie Animation -->
                <div id="sud-payment-lottie-container"></div>
            </div>
            <h3 id="sud-payment-success-title">Success!</h3>
            <p id="sud-payment-success-message">Your boost purchase was successful.</p>
            <p id="sud-payment-success-redirecting" style="display: none;">Updating your profile...</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('not_matched') && urlParams.get('not_matched') === '1') {
                const userId = urlParams.get('user_id');
                if (userId) {
                    $.ajax({
                        url: "<?php echo esc_js(SUD_AJAX_URL . '/get-user-preview.php'); ?>",
                        type: 'GET',
                        data: { user_id: userId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.data) {
                                $('#match-required-user-name').text(response.data.name);
                                $('#match-required-user-img').attr('src', response.data.profile_pic);
                                $('#match-required-profile-link').attr('href', response.data.profile_url);
                                $('#match-required-instant-match-btn').data('user-id', userId);
                                $('#match-required-modal').addClass('show');
                            }
                        }
                    });
                    const newUrl = window.location.pathname + window.location.hash;
                    window.history.replaceState({}, document.title, newUrl);
                }
            }

            $('#match-required-instant-match-btn').on('click', function() {
                const userId = $(this).data('user-id');
                if (!userId) return;

                if (sud_swipe_page_config.swipe_up_balance > 0) {
                    $('#match-required-modal').removeClass('show');
                    SUD.showToast('info', 'Using Swipe Up...', `Instantly matching you with this user.`);

                    if (typeof SUD !== 'undefined' && SUD.SwipePage && typeof SUD.SwipePage.performSwipeAction === 'function') {
                        SUD.SwipePage.performSwipeAction('swipe_up', null, userId);
                    } else {
                        console.error("SwipePage.performSwipeAction is not accessible.");
                    }
                } else {
                    $('#match-required-modal').removeClass('show');
                    $('#swipe-up-modal').addClass('show');
                }
            });

            // Other event handlers
            document.addEventListener('click', function(e) {
                if (e.target.closest('.boost-btn')) {
                    e.preventDefault();
                    const button = e.target.closest('.boost-btn');
                    const boostType = button.getAttribute('data-boost-type');
                    const boostPackages = <?php echo json_encode($boost_packages); ?>;
                    const boostConfig = boostPackages[boostType];
                    if (boostConfig) {
                        const paymentConfig = { type: 'boost', boost_type: boostType, name: boostConfig.name, amount: boostConfig.price, description: boostConfig.description };
                        $('#boost-modal').removeClass('show');
                        showPaymentModal('boost', paymentConfig);
                    }
                }
                
                if (e.target.closest('.swipe-up-btn')) {
                    e.preventDefault();
                    const button = e.target.closest('.swipe-up-btn');
                    const packageType = button.getAttribute('data-package');
                    const swipeUpPackages = <?php echo json_encode($swipe_up_packages); ?>;
                    const packageConfig = swipeUpPackages[packageType];
                    if (packageConfig) {
                         const paymentConfig = { type: 'swipe-up', package_type: packageType, name: packageConfig.name, amount: packageConfig.price, description: packageConfig.description, quantity: packageConfig.quantity };
                        $('#swipe-up-modal').removeClass('show');
                        showPaymentModal('swipe-up', paymentConfig);
                    }
                }
            });
        });
    </script>
</body>
</html>