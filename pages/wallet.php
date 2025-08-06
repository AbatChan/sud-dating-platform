<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');

require_login();

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// Use centralized validation for all profile requirements (no photo requirement)
validate_core_profile_requirements($current_user_id, 'wallet');
$display_name = $current_user->display_name;

$profile_picture_id = get_user_meta($current_user->ID, 'profile_picture', true);
$profile_pic_url = !empty($profile_picture_id) ? wp_get_attachment_image_url($profile_picture_id, 'thumbnail') : SUD_IMG_URL . '/default-profile.jpg';

$coin_balance = (float) get_user_meta($current_user->ID, 'coin_balance', true);

$coin_to_usd_rate = defined('SUD_COIN_WITHDRAWAL_RATE_USD') ? SUD_COIN_WITHDRAWAL_RATE_USD : 0.10; 

// Use centralized coin packages configuration
$coin_packages = sud_get_coin_packages();

// Calculate original prices based on discount
foreach ($coin_packages as $index => $package) {
    if ($package['discount'] > 0) {
        $coin_packages[$index]['original_price'] = $package['price'] / (1 - ($package['discount'] / 100));
    } else {
        $coin_packages[$index]['original_price'] = $package['price'];
    }
}

$coin_uses = [
    [
        'icon' => 'fa-gift',
        'title' => 'Send Virtual Gifts',
        'description' => 'Impress your match with virtual gifts that show your appreciation.'
    ],
    [
        'icon' => 'fa-video',
        'title' => 'Video Calls',
        'description' => 'Use coins to initiate video calls with your Sugar Baby or Sugar Daddy.'
    ]
];

$payment_methods = [
    'visa' => 'Visa',
    'mastercard' => 'Mastercard',
    'amex' => 'American Express'
];

$payment_settings = get_option('sud_payment_settings');
$test_mode = isset($payment_settings['test_mode']) ? (bool)$payment_settings['test_mode'] : false;
$stripe_key = sud_get_stripe_publishable_key();

$page_title = "Buy Coins - " . SUD_SITE_NAME;
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
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/wallet.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/payment-modals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
    <?php 
    // Load payment scripts only on this page
    require_once(dirname(__FILE__, 2) . '/includes/payment-loader.php');
    sud_load_payment_scripts('one-time');
    ?>

    <!-- Pass configuration to JavaScript -->
    <script>
        var sud_config_base = {
            sud_url: '<?php echo esc_js(SUD_URL); ?>',
            ajax_url: '<?php echo esc_js(SUD_AJAX_URL); ?>',
            current_user_id: <?php echo esc_js($current_user_id); ?>,
            is_logged_in: true
        };
        
        var sud_payment_settings = {
            test_mode: <?php echo $test_mode ? 'true' : 'false'; ?>,
            stripe_test_publishable_key: '<?php echo esc_js($stripe_key); ?>',
            stripe_live_publishable_key: '<?php echo esc_js($stripe_key); ?>'
        };
        // Ensure sud_payment_config is available for unified-payment.js
        window.sud_payment_config = window.sud_payment_config || {
            stripe_key: '<?php echo esc_js($stripe_key); ?>'
        };
        
        // Set payment nonce for coin purchases
        var sud_wallet_config = {
            payment_nonce: '<?php echo esc_js(wp_create_nonce('sud_one_off_payment_nonce')); ?>'
        };
    </script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
    <script src="<?php echo SUD_JS_URL; ?>/unified-payment.js"></script>
</head>
<body>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>
    <div id="toast-container" class="toast-container"></div>

    <main class="main-content">
        <div class="wallet-container">
            <div class="coin-balance">
                <img src="<?php echo SUD_IMG_URL; ?>/sud-coin.png" alt="SUD Coin" class="coin-icon" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSI0MCIgY3k9IjQwIiByPSI0MCIgZmlsbD0iI0YyRDA0RiIvPjx0ZXh0IHg9IjI1IiB5PSI1MCIgZm9udC1zaXplPSIyOCIgZm9udC13ZWlnaHQ9ImJvbGQiIGZpbGw9IiNGRkZGRkYiPkxNUjwvdGV4dD48L3N2Zz4='">
                <h2 class="balance-title">Your Coin Balance</h2>
                <h1 class="balance-amount"><?php echo number_format($coin_balance); ?></h1>
                 <?php $usd_equivalent = $coin_balance * $coin_to_usd_rate; ?>
                 <span class="usd-equivalent" style="color: #777; font-size: 0.9em;">(Approx. <?php echo esc_html(sprintf("$%.2f USD", $usd_equivalent)); ?> withdrawable value)</span>
            </div>
            <div class="withdraw-action-area">
                <a href="<?php echo SUD_URL; ?>/pages/withdrawal" class="btn-withdraw">
                    <i class="fas fa-hand-holding-usd"></i> Manage Withdrawals
                </a>
                 <p>View withdrawal options and history. Eligibility is checked on the withdrawal page.</p>
            </div>
            <div class="coins-info">
                <h2>What can you do with SUD Coins?</h2>
                <p>SUD Coins enhance your experience on <?php echo esc_html(SUD_SITE_NAME); ?>, giving you access to premium features.</p>

                <div class="coin-uses">
                    <?php foreach ($coin_uses as $use): ?>
                    <div class="use-card">
                        <div class="use-icon">
                            <i class="fas <?php echo $use['icon']; ?>"></i>
                        </div>
                        <h3 class="use-title"><?php echo $use['title']; ?></h3>
                        <p class="use-description"><?php echo $use['description']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <h2>Choose a Coin Package</h2>
            <div class="coins-packages">
                <?php foreach ($coin_packages as $package): 
                    $total_coins = number_format($package['amount'] + $package['bonus']);
                    $package_class = '';
                    $badge_class = '';

                    if ($package['is_popular']) {
                        $package_class = 'package-popular';
                    } elseif ($package['is_best_value']) {
                        $package_class = 'package-best-value';
                        $badge_class = 'best-value-badge';
                    }
                ?>
                <div class="coin-package <?php echo $package_class; ?>">
                    <div class="card-coin-container">
                        <?php if (!empty($package['badge'])): ?>
                        <div class="package-badge <?php echo $badge_class; ?>"><?php echo $package['badge']; ?></div>
                        <?php endif; ?>

                        <div class="coin-amount">
                            <img src="<?php echo SUD_IMG_URL; ?>/sud-coin.png" alt="SUD Coin" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAiIGhlaWdodD0iMzAiIHZpZXdCb3g9IjAgMCAzMCAzMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIxNSIgY3k9IjE1IiByPSIxNSIgZmlsbD0iI0YyRDA0RiIvPjx0ZXh0IHg9IjkiIHk9IjE5IiBmb250LXNpemU9IjExIiBmb250LXdlaWdodD0iYm9sZCIgZmlsbD0iI0ZGRkZGRiI+TDwvdGV4dD48L3N2Zz4='">
                            <span><?php echo $total_coins; ?></span>
                        </div>

                        <?php if ($package['discount'] > 0): ?>
                        <div class="bonus"><?php echo $package['discount']; ?>% EXTRA</div>
                        <?php endif; ?>

                        <div class="price">$<?php echo number_format($package['price'], 2); ?></div>

                        <?php if ($package['original_price'] > $package['price']): ?>
                        <div class="original-price">$<?php echo number_format($package['original_price'], 2); ?></div>
                        <?php endif; ?>
                    </div>
                    <button class="btn-buy" data-amount="<?php echo $package['amount'] + $package['bonus']; ?>" data-price="<?php echo $package['price']; ?>">Buy Now</button>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="secure-payment-container">
                <h3>Secure Payment Options</h3>
                <div class="payment-methods">
                    <?php foreach ($payment_methods as $key => $name): ?>
                    <div class="payment-method">
                        <img src="<?php echo SUD_IMG_URL; ?>/payment/<?php echo $key; ?>.png" alt="<?php echo $name; ?>" 
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCA2MCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNjAiIGhlaWdodD0iMjAiIGZpbGw9IiMzMzMiIHJ4PSIzIi8+PHRleHQgeD0iNSIgeT0iMTQiIGZpbGw9IndoaXRlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTAiPjwvdGV4dD48L3N2Zz4='">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Payment Modal -->
    <div id="payment-modal-wallet" class="payment-modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <div class="purchase-info">
                <h3>Purchase <span id="purchase-amount-wallet">0</span> SUD Coins</h3>
                <div class="purchase-price">$<span id="purchase-price-wallet">0.00</span></div>
            </div>

            <?php if (isset($payment_settings['test_mode']) && $payment_settings['test_mode']): ?>
                <div class="test-mode-warning">
                    <i class="fas fa-exclamation-triangle"></i> Test mode is active. No real charges will be made.
                </div>
            <?php endif; ?>


            <!-- Credit Card Payment Form -->
            <div class="payment-section active" id="card-section-wallet">
                <form id="card-payment-form-wallet">
                    <div class="form-group">
                        <label for="card-email-wallet" class="form-label">Email</label>
                        <input type="email" id="card-email-wallet" class="form-input" placeholder="Email Address" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                        <small>Pre-filled from your account</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="card-name-wallet" class="form-label">Name on Card</label>
                        <input type="text" id="card-name-wallet" class="form-input" placeholder="Full Name" value="<?php echo esc_attr($display_name); ?>" required>
                        <small>Pre-filled from your account</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Card Information</label>
                        <div id="card-element-wallet" class="stripe-card-element"></div>
                        <div id="card-errors-wallet" role="alert" style="color: #fa755a; margin-top: 5px;"></div>
                    </div>

                    <button type="submit" class="btn-confirm">Confirm Purchase</button>
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
            <p id="sud-payment-success-message">Your transaction was successful.</p>
            <p id="sud-payment-success-redirecting" style="display: none;">Redirecting...</p>
        </div>
    </div>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-footer.php'); ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle coin purchase button clicks
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-buy')) {
                    const amount = e.target.getAttribute('data-amount');
                    const price = e.target.getAttribute('data-price');
                    const coinPackages = <?php echo json_encode($coin_packages); ?>;
                    
                    // Find the package configuration
                    let packageConfig = null;
                    for (let pkg of coinPackages) {
                        if ((pkg.amount + pkg.bonus) == amount && pkg.price == price) {
                            packageConfig = pkg;
                            break;
                        }
                    }
                    
                    if (packageConfig) {
                        showPaymentModal('coins', {
                            type: 'coins',
                            name: `${amount} SUD Coins`,
                            amount: price,
                            description: `Purchase ${amount} SUD Coins${packageConfig.bonus > 0 ? ' (includes ' + packageConfig.bonus + ' bonus coins)' : ''}`,
                            coin_amount: amount,
                            bonus_coins: packageConfig.bonus,
                            nonce: sud_wallet_config.payment_nonce
                        });
                    }
                }
            });
        });
    </script>
</body>
</html>