<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/payment-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/pricing-config.php');

// Maintenance mode check now handled globally in config.php

require_login();

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// Use centralized validation for all profile requirements
validate_core_profile_requirements($current_user_id, 'premium');
$display_name = $current_user->display_name;
$profile_picture_id = get_user_meta($current_user_id, 'profile_picture', true);
$profile_pic_url = !empty($profile_picture_id) ? wp_get_attachment_image_url($profile_picture_id, 'thumbnail') : SUD_IMG_URL . '/default-profile.jpg';
$completion_percentage = get_profile_completion_percentage($current_user_id);

require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');
$user_messages = get_user_messages($current_user_id);
$unread_message_count = $user_messages['unread_count'];
$current_plan = get_user_meta($current_user_id, 'premium_plan', true) ?: 'free';
$subscription_expires = get_user_meta($current_user_id, 'subscription_expires', true) ?: false;

// Check trial status
$active_trial = sud_get_active_trial($current_user_id);
$available_trials = sud_get_available_trials($current_user_id);

// Use centralized pricing configuration
$premium_plans = sud_get_all_plans_with_pricing();

$payment_settings = get_option('sud_payment_settings');
$test_mode = isset($payment_settings['test_mode']) ? (bool)$payment_settings['test_mode'] : false;
$stripe_key = sud_get_stripe_publishable_key();
// PayPal removed - using Stripe only

$page_title = "Premium Membership - " . SUD_SITE_NAME;
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
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/premium.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/payment-modals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
    <?php 
    // Load payment scripts only on this page
    require_once(dirname(__FILE__, 2) . '/includes/payment-loader.php');
    sud_load_payment_scripts('subscription');
    ?>

    <!-- Pass configuration to JavaScript -->
    <script>
        var sud_payment_settings = {
            test_mode: <?php echo $test_mode ? 'true' : 'false'; ?>,
            stripe_test_publishable_key: '<?php echo esc_js($stripe_key); ?>',
            stripe_live_publishable_key: '<?php echo esc_js($stripe_key); ?>',
            // PayPal configuration removed
        };
        // Ensure sud_payment_config is available for unified-payment.js
        window.sud_payment_config = window.sud_payment_config || {};
        window.sud_payment_config.stripe_key = '<?php echo esc_js($stripe_key); ?>';
        window.sud_payment_config.complete_trial_nonce = '<?php echo esc_js(wp_create_nonce('sud_complete_trial_setup')); ?>';
        window.sud_payment_config.base_url = '<?php echo esc_js(SUD_URL); ?>';
        
        // Set payment nonce for subscriptions
        var sud_premium_config = {
            subscription_nonce: '<?php echo esc_js(wp_create_nonce('sud_subscription_nonce')); ?>'
        };
    </script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
</head>
<body>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>
    <div id="toast-container" class="toast-container"></div>

    <main class="main-content">
        <div class="premium-container">
            <div class="premium-header">
                <h1>Upgrade Your Experience</h1>
                <p>Enhance your experience on <?php echo esc_html(SUD_SITE_NAME); ?> with premium features designed to help you find meaningful connections faster.</p>
                
                <?php if ($active_trial): ?>
                    <div class="trial-status-banner">
                        <h3 style="margin: 0 0 5px 0;">🎉 Free Trial Active</h3>
                        <p>You're currently on a <strong><?php echo ucfirst($active_trial['plan']); ?> Plan</strong> free trial with <strong><?php echo $active_trial['days_remaining']; ?> days</strong> remaining.</p>
                        <p style="font-size: 14px; opacity: 0.9;">Trial expires on <?php echo date('M j, Y', strtotime($active_trial['end'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="premium-plans">
                <!-- Add billing toggle -->
                <div class="billing-toggle">
                    <label class="switch-toggle">
                        <input type="checkbox" id="billing-toggle" name="billing-toggle">
                        <span class="toggle-slider"></span>
                        <span class="toggle-labels">
                            <span class="monthly active">Monthly</span>
                            <span class="annually">Annually (Save 20%)</span>
                        </span>
                    </label>
                </div>
                <div class="packages-container">
                    <?php foreach ($premium_plans as $plan):
                        $plan_class = 'plan-' . $plan['id'];
                        $is_current = ($current_plan === $plan['id']);

                        if ($plan['is_popular']) {
                            $plan_class .= ' plan-popular';
                        }
                    ?>
                    <div class="premium-plan <?php echo $plan_class; ?>" data-plan-id="<?php echo $plan['id']; ?>">
                        <?php if (!empty($plan['badge'])): ?>
                        <div class="plan-badge" style="background-color: <?php echo $plan['color']; ?>"><?php echo $plan['badge']; ?></div>
                        <?php endif; ?>

                        <h3 class="plan-name"><?php echo $plan['name']; ?></h3>
                        <div class="plan-price">
                            <div class="monthly-price" style="display: block;">
                                $<?php echo number_format($plan['price_monthly'], 2); ?>
                                <span class="price-period">/month</span>
                            </div>
                            <div class="annual-price" style="display: none;">
                                $<?php echo number_format($plan['annual_monthly_price'], 2); ?>
                                <span class="price-period">/month</span>
                                <div class="annual-total">billed annually at $<?php echo number_format($plan['price_annually'], 2); ?></div>
                                <div class="annual-saving">Save <?php echo $plan['annual_discount_percent']; ?>%</div>
                            </div>
                        </div>

                        <div class="plan-benefits">
                            <?php foreach ($plan['benefits'] as $benefit): ?>
                            <div class="benefit-item">
                                <span class="benefit-icon"><i class="fas fa-check-circle"></i></span>
                                <span><?php echo $benefit; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php 
                        $is_trial_available = isset($available_trials[$plan['id']]);
                        $is_current_trial = $active_trial && $active_trial['plan'] === $plan['id'];
                        $is_free_plan = ($plan['id'] === 'free');
                        $user_is_free = ($current_plan === 'free');
                        
                        if ($is_current_trial): ?>
                            <a href="<?php echo SUD_URL; ?>/pages/subscription" class="btn trial-current-plan" style="text-decoration: none; display: block; text-align: center;">
                                Current Trial - <?php echo $active_trial['days_remaining']; ?> days left
                            </a>
                        <?php elseif ($is_current): ?>
                            <a href="<?php echo SUD_URL; ?>/pages/subscription" class="btn-subscribe btn-current current-plan-link" style="text-decoration: none; display: block; text-align: center; cursor: pointer;">
                                Current Plan
                            </a>
                        <?php elseif ($is_free_plan && $user_is_free): ?>
                            <div class="current-plan-indicator">
                                Current Plan
                            </div>
                        <?php elseif ($is_free_plan && !$user_is_free): ?>
                            <button class="btn btn-downgrade btn-trial-outline" 
                                    data-plan="free"
                                    data-action="downgrade">
                                Downgrade
                            </button>
                        <?php elseif ($is_trial_available && !$active_trial): ?>
                            <button class="btn btn-choice btn-trial-solid" 
                                    data-plan="<?php echo esc_attr($plan['id']); ?>"
                                    data-price-monthly="<?php echo esc_attr($plan['price_monthly']); ?>"
                                    data-price-annually="<?php echo esc_attr($plan['price_annually']); ?>"
                                    data-stripe-price-monthly="<?php echo esc_attr($plan['stripe_price_id_monthly'] ?? ''); ?>"
                                    data-stripe-price-annually="<?php echo esc_attr($plan['stripe_price_id_annually'] ?? ''); ?>"
                                    data-action="choice">
                                Try 3-Day Free Trial
                            </button>
                        <?php elseif ($is_trial_available && $active_trial && $active_trial['plan'] !== $plan['id']): ?>
                            <button class="btn btn-trial btn-trial-outline" 
                                    data-plan="<?php echo esc_attr($plan['id']); ?>"
                                    data-price-monthly="<?php echo esc_attr($plan['price_monthly']); ?>"
                                    data-price-annually="<?php echo esc_attr($plan['price_annually']); ?>"
                                    data-action="trial">
                                Switch to <?php echo $plan['name']; ?> Trial
                            </button>
                        <?php else: ?>
                            <button class="btn-subscribe btn-trial-solid" 
                                    data-plan="<?php echo esc_attr($plan['id']); ?>"
                                    data-price-monthly="<?php echo esc_attr($plan['price_monthly']); ?>"
                                    data-price-annually="<?php echo esc_attr($plan['price_annually']); ?>"
                                    data-stripe-price-monthly="<?php echo esc_attr($plan['stripe_price_id_monthly'] ?? ''); ?>"
                                    data-stripe-price-annually="<?php echo esc_attr($plan['stripe_price_id_annually'] ?? ''); ?>"
                                    data-paypal-plan-monthly="<?php echo esc_attr($plan['paypal_plan_id_monthly'] ?? ''); ?>"
                                    data-paypal-plan-annually="<?php echo esc_attr($plan['paypal_plan_id_annually'] ?? ''); ?>">
                                Subscribe Now
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="secure-payment">
                <h3>Secure Payment Options</h3>
                <div class="payment-methods">
                    <div class="payment-method">
                        <img src="<?php echo SUD_IMG_URL; ?>/payment/visa.png" alt="Visa" 
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCA2MCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNjAiIGhlaWdodD0iMjAiIGZpbGw9IiMzMzMiIHJ4PSIzIi8+PHRleHQgeD0iNSIgeT0iMTQiIGZpbGw9IndoaXRlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTAiPlZpc2E8L3RleHQ+PC9zdmc+'">
                    </div>
                    <div class="payment-method">
                        <img src="<?php echo SUD_IMG_URL; ?>/payment/mastercard.png" alt="Mastercard" 
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCA2MCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNjAiIGhlaWdodD0iMjAiIGZpbGw9IiMzMzMiIHJ4PSIzIi8+PHRleHQgeD0iNSIgeT0iMTQiIGZpbGw9IndoaXRlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTAiPk1hc3RlcmNhcmQ8L3RleHQ+PC9zdmc+'">
                    </div>
                    <div class="payment-method">
                        <img src="<?php echo SUD_IMG_URL; ?>/payment/amex.png" alt="American Express" 
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCA2MCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNjAiIGhlaWdodD0iMjAiIGZpbGw9IiMzMzMiIHJ4PSIzIi8+PHRleHQgeD0iNSIgeT0iMTQiIGZpbGw9IndoaXRlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTAiPkFtZXg8L3RleHQ+PC9zdmc+'">
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="payment-modal-premium" class="payment-modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>

            <div class="purchase-info">
                <h3>Subscribe to <span id="plan-name">Premium</span> (<span id="billing-period">Monthly</span>)</h3>
                <div class="purchase-price"><span id="plan-price">$0.00</span>/month</div>
                <p id="annual-price-note" style="display: none;">Billed annually at <span class="total-price">$0.00</span></p>
            </div>

            <?php if (isset($payment_settings['test_mode']) && $payment_settings['test_mode']): ?>
                <div class="test-mode-warning">
                    <i class="fas fa-exclamation-triangle"></i> Test mode is active. No real charges will be made.
                </div>
            <?php endif; ?>


            <!-- Credit Card Payment Form -->
            <div class="payment-section active" id="card-section-premium">
                <form id="card-payment-form-premium">
                    <div class="form-group">
                        <label for="premium-email" class="form-label">Email Address</label>
                        <input type="email" id="premium-email" class="form-input" required 
                               value="<?php echo esc_attr($current_user->user_email); ?>" readonly>
                        <small>Pre-filled from your account</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="card-name-premium" class="form-label">Name on Card</label>
                        <input type="text" id="card-name-premium" class="form-input" placeholder="Full Name" required
                               value="<?php echo esc_attr($current_user->display_name); ?>">
                        <small>Pre-filled from your account</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Card Information</label>
                        <div id="card-element-premium" class="stripe-card-element"></div>
                        <div id="card-errors-premium" role="alert" style="color: #fa755a; margin-top: 5px;"></div>
                    </div>

                    <button type="submit" class="btn-confirm">Start Subscription</button>
                </form>
            </div>

        </div>
    </div>

    <!-- Choice Modal -->
    <div id="choice-modal" class="payment-modal" style="display: none;">
        <div class="sud-modal-content">
            <span class="close-modal close-choice-modal">&times;</span>
            <div class="choice-info">
                <h3>Choose Your Option</h3>
                <p>$<span id="choice-price">33.33</span><span id="choice-billing-text">/month</span> • <span id="choice-plan-name">Gold Plan</span></p>
            </div>
            <div class="choice-buttons">
                <button class="btn choice-trial-btn">Try 3-Day Free Trial</button>
                <button class="btn choice-payment-btn">Subscribe Now</button>
            </div>
        </div>
    </div>

    <!-- Trial Modal -->
    <div id="trial-modal" class="payment-modal" style="display: none;">
        <div class="sud-modal-content">
            <span class="close-modal">&times;</span>
            <div class="trial-info">
                <h3>Start Your 3-Day FREE Trial</h3>
                <p>$<span id="trial-price">33.33</span><span id="trial-billing-text">/month</span> • <span id="trial-plan-name">Gold Tier</span></p>
            </div>
            
            <?php if (isset($payment_settings['test_mode']) && $payment_settings['test_mode']): ?>
                <div class="test-mode-warning">
                    <i class="fas fa-exclamation-triangle"></i> Test mode is active. No real charges will be made.
                </div>
            <?php endif; ?>


            <!-- Credit Card Section -->
            <div class="payment-section active" id="trial-card-section">
                <form id="trial-form" novalidate>
                    <div class="form-group">
                        <label for="trial-email">Email Address</label>
                        <input type="email" id="trial-email" name="email" required 
                               value="<?php echo esc_attr($current_user->user_email); ?>" readonly>
                        <small>Pre-filled from your account</small>
                    </div>
                    <div class="form-group">
                        <label for="trial-cardholder-name">Name on Card</label>
                        <input type="text" id="trial-cardholder-name" name="cardholder_name" required 
                               value="<?php echo esc_attr($current_user->display_name); ?>">
                        <small>Pre-filled from your account</small>
                    </div>
                    <div class="form-group">
                        <label>Card Information</label>
                        <div id="trial-card-element" class="stripe-card-element">
                            <!-- Stripe Card Element will be mounted here -->
                        </div>
                        <div id="trial-card-errors" class="card-errors" role="alert"></div>
                    </div>
                    <button type="submit" id="start-trial-btn">Start My FREE Trial</button>
                    
                    <!-- Hidden fields for secure processing -->
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('sud_start_trial_secure'); ?>">
                    <input type="hidden" name="action" value="start_trial_secure">
                </form>
            </div>

            
            <p class="trial-footer-text">
                We'll send you a reminder 1 day before it ends. 
                <a href="<?php echo site_url('/terms-of-service'); ?>" target="_blank">View cancellation policy</a>.
            </p>
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
            const urlParams = new URLSearchParams(window.location.search);
            const highlightPlan = urlParams.get('highlight_plan');
            if (highlightPlan) {
                const planElement = document.querySelector('.premium-plan[data-plan-id="' + highlightPlan + '"]');
                if (planElement) {
                    planElement.classList.add('highlighted-plan');
                    planElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            
            // Handle direct payment URL parameters
            const directPay = urlParams.get('direct_pay');
            const planParam = urlParams.get('plan');
            if (directPay === 'true' && planParam) {
                // Find the plan element
                const planElement = document.querySelector('.premium-plan[data-plan-id="' + planParam + '"]');
                if (planElement) {
                    // Look for any clickable button that can trigger payment/subscription
                    let targetButton = planElement.querySelector('.btn-subscribe:not(.current-plan-link)') || 
                                     planElement.querySelector('.btn-choice') || 
                                     planElement.querySelector('.btn-trial') ||
                                     planElement.querySelector('button[data-action="choice"]') ||
                                     planElement.querySelector('button[data-action="trial"]');
                    
                    if (targetButton && !targetButton.disabled) {
                        // Trigger the modal after a short delay to ensure page is loaded
                        setTimeout(() => {
                            targetButton.click();
                        }, 500);
                    }
                }
            }
            
            // Handle subscription button clicks
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-subscribe') && !e.target.disabled && !e.target.classList.contains('current-plan-link')) {
                    const button = e.target;
                    const planId = button.getAttribute('data-plan');

                    const monthlyPrice = parseFloat(button.getAttribute('data-price-monthly'));
                    const annualPrice = parseFloat(button.getAttribute('data-price-annually'));

                    // Get the plan name from the plan's card element for the modal title
                    const planName = button.closest('.premium-plan').querySelector('.plan-name').textContent || 'Premium';
                    
                    const billingToggle = document.getElementById('billing-toggle');
                    const isAnnual = billingToggle && billingToggle.checked;


                    // Get the correct Stripe price ID for current test/live mode
                    const billing_cycle = isAnnual ? 'annual' : 'monthly';
                    <?php foreach ($premium_plans as $plan): ?>
                    <?php if ($plan['id'] !== 'free'): ?>
                    if (planId === '<?php echo $plan['id']; ?>') {
                        var stripePriceId = '<?php echo sud_get_stripe_price_id($plan['id'], 'monthly'); ?>';
                        if (isAnnual) {
                            stripePriceId = '<?php echo sud_get_stripe_price_id($plan['id'], 'annual'); ?>';
                        }
                    }
                    <?php endif; ?>
                    <?php endforeach; ?>

                    const price = isAnnual ? annualPrice : monthlyPrice;
                    if (isNaN(price)) {
                        console.error("Price calculation failed. Check the data-price-* attributes on the button.", {
                            planId: planId,
                            isAnnual: isAnnual,
                            monthlyPrice: monthlyPrice,
                            annualPrice: annualPrice
                        });
                        alert("An error occurred. Please refresh the page and try again.");
                        return;
                    }

                    showPaymentModal('subscription', {
                        type: 'subscription',
                        plan_name: planName,
                        amount: price,
                        description: `${planName} subscription - ${isAnnual ? 'billed annually' : 'billed monthly'}`,
                        plan_id: planId,
                        billing_cycle: isAnnual ? 'annual' : 'monthly',
                        stripe_price_id: stripePriceId, 
                        nonce: sud_premium_config.subscription_nonce
                    });
                }
            });

            // Handle downgrade button clicks
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-downgrade') || (e.target.getAttribute && e.target.getAttribute('data-action') === 'downgrade')) {
                    e.preventDefault();
                    if (confirm('Are you sure you want to downgrade to the Free plan? You will lose access to premium features immediately.')) {
                        // Redirect to subscription management page to handle downgrade
                        window.location.href = '<?php echo SUD_URL; ?>/pages/subscription?action=downgrade';
                    }
                }
            });

            // Handle choice button clicks
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-choice')) {
                    e.preventDefault();
                    const button = e.target;
                    const planId = button.getAttribute('data-plan');
                    const monthlyPrice = parseFloat(button.getAttribute('data-price-monthly'));
                    const annualPrice = parseFloat(button.getAttribute('data-price-annually'));
                    const planName = button.closest('.premium-plan').querySelector('.plan-name').textContent || 'Premium';
                    
                    // Check billing cycle from main page toggle
                    const billingToggle = document.getElementById('billing-toggle');
                    const isAnnual = billingToggle && billingToggle.checked;
                    const priceText = isAnnual ? (annualPrice/12).toFixed(2) : monthlyPrice.toFixed(2);
                    const billingText = isAnnual ? `/month (billed annually at $${annualPrice.toFixed(2)})` : '/month';
                    
                    // Update choice modal content
                    document.getElementById('choice-plan-name').textContent = planName;
                    document.getElementById('choice-price').textContent = priceText;
                    document.getElementById('choice-billing-text').textContent = billingText;
                    
                    // Store plan data for both trial and subscription buttons
                    const choiceModal = document.getElementById('choice-modal');
                    choiceModal.setAttribute('data-plan', planId);
                    choiceModal.setAttribute('data-price-monthly', monthlyPrice);
                    choiceModal.setAttribute('data-price-annually', annualPrice);
                    choiceModal.setAttribute('data-plan-name', planName);
                    
                    // Set trial button data
                    const trialBtn = choiceModal.querySelector('.choice-trial-btn');
                    const paymentBtn = choiceModal.querySelector('.choice-payment-btn');
                    
                    trialBtn.setAttribute('data-plan', planId);
                    trialBtn.setAttribute('data-price-monthly', monthlyPrice);
                    trialBtn.setAttribute('data-price-annually', annualPrice);
                    
                    // Set payment button data
                    paymentBtn.setAttribute('data-plan', planId);
                    paymentBtn.setAttribute('data-price-monthly', monthlyPrice);
                    paymentBtn.setAttribute('data-price-annually', annualPrice);
                    paymentBtn.setAttribute('data-stripe-price-monthly', button.getAttribute('data-stripe-price-monthly'));
                    paymentBtn.setAttribute('data-stripe-price-annually', button.getAttribute('data-stripe-price-annually'));
                    
                    // Show choice modal
                    choiceModal.classList.add('show');
                    choiceModal.style.display = 'flex';
                }
            });

            // Handle choice modal button clicks
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('choice-trial-btn')) {
                    e.preventDefault();
                    // Close choice modal and open trial modal
                    const choiceModal = document.getElementById('choice-modal');
                    choiceModal.classList.remove('show');
                    choiceModal.style.display = 'none';
                    
                    // Get plan data from choice modal
                    const planId = choiceModal.getAttribute('data-plan');
                    const monthlyPrice = parseFloat(choiceModal.getAttribute('data-price-monthly'));
                    const annualPrice = parseFloat(choiceModal.getAttribute('data-price-annually'));
                    const planName = choiceModal.getAttribute('data-plan-name');
                    
                    // Check billing cycle from main page toggle
                    const billingToggle = document.getElementById('billing-toggle');
                    const isAnnual = billingToggle && billingToggle.checked;
                    const priceText = isAnnual ? (annualPrice/12).toFixed(2) : monthlyPrice.toFixed(2);
                    const billingText = isAnnual ? `/month (billed annually at $${annualPrice.toFixed(2)})` : '/month';
                    
                    // Update trial modal content
                    document.getElementById('trial-price').textContent = priceText;
                    document.getElementById('trial-plan-name').textContent = planName;
                    document.getElementById('trial-billing-text').textContent = billingText;
                    
                    // Store plan data for form submission
                    document.getElementById('trial-form').setAttribute('data-plan', planId);
                    
                    // Show trial modal
                    const trialModal = document.getElementById('trial-modal');
                    trialModal.classList.add('show');
                    trialModal.style.display = 'flex';
                }
                
                if (e.target.classList.contains('choice-payment-btn')) {
                    e.preventDefault();
                    // Close choice modal and open payment modal
                    const choiceModal = document.getElementById('choice-modal');
                    choiceModal.classList.remove('show');
                    choiceModal.style.display = 'none';
                    
                    // Trigger subscription flow
                    const button = e.target;
                    const planId = button.getAttribute('data-plan');
                    const monthlyPrice = parseFloat(button.getAttribute('data-price-monthly'));
                    const annualPrice = parseFloat(button.getAttribute('data-price-annually'));
                    const planName = choiceModal.getAttribute('data-plan-name');
                    
                    const billingToggle = document.getElementById('billing-toggle');
                    const isAnnual = billingToggle && billingToggle.checked;
                    
                    // Use the same subscription logic

                    const billing_cycle = isAnnual ? 'annual' : 'monthly';
                    <?php foreach ($premium_plans as $plan): ?>
                    <?php if ($plan['id'] !== 'free'): ?>
                    if (planId === '<?php echo $plan['id']; ?>') {
                        var stripePriceId = '<?php echo sud_get_stripe_price_id($plan['id'], 'monthly'); ?>';
                        if (isAnnual) {
                            stripePriceId = '<?php echo sud_get_stripe_price_id($plan['id'], 'annual'); ?>';
                        }
                    }
                    <?php endif; ?>
                    <?php endforeach; ?>

                    const price = isAnnual ? annualPrice : monthlyPrice;
                    
                    showPaymentModal('subscription', {
                        type: 'subscription',
                        plan_name: planName,
                        amount: price,
                        description: `${planName} subscription - ${isAnnual ? 'billed annually' : 'billed monthly'}`,
                        plan_id: planId,
                        billing_cycle: isAnnual ? 'annual' : 'monthly',
                        stripe_price_id: stripePriceId, 
                        nonce: sud_premium_config.subscription_nonce
                    });
                }
            });

            // Close choice modal
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('close-choice-modal') || e.target.id === 'choice-modal') {
                    const modal = document.getElementById('choice-modal');
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                }
            });


            // Handle trial button clicks (Switch to [plan] Trial buttons)
            document.addEventListener('click', function(e) {
                if (e.target.hasAttribute('data-action') && e.target.getAttribute('data-action') === 'trial') {
                    e.preventDefault();
                    const button = e.target;
                    const planId = button.getAttribute('data-plan');
                    const monthlyPrice = parseFloat(button.getAttribute('data-price-monthly'));
                    const annualPrice = parseFloat(button.getAttribute('data-price-annually'));
                    
                    // Check billing cycle from main page toggle
                    const billingToggle = document.querySelector('#billing-toggle');
                    const isAnnual = billingToggle ? billingToggle.checked : false;
                    const selectedPrice = isAnnual ? annualPrice : monthlyPrice;
                    const billingText = isAnnual ? '/year' : '/month';
                    
                    // Get plan name
                    const planNames = {
                        'silver': 'Silver',
                        'gold': 'Gold', 
                        'diamond': 'Diamond'
                    };
                    const planName = planNames[planId] || planId.charAt(0).toUpperCase() + planId.slice(1);
                    
                    // Update trial modal content
                    document.getElementById('trial-plan-name').textContent = planName;
                    document.getElementById('trial-price').textContent = selectedPrice.toFixed(2); // Remove duplicate $, HTML already has it
                    document.getElementById('trial-billing-text').textContent = billingText;
                    
                    // Store plan data in trial modal AND form
                    const trialModal = document.getElementById('trial-modal');
                    const trialForm = document.getElementById('trial-form');
                    
                    trialModal.setAttribute('data-plan', planId);
                    trialModal.setAttribute('data-price-monthly', monthlyPrice);
                    trialModal.setAttribute('data-price-annually', annualPrice);
                    trialModal.setAttribute('data-plan-name', planName);
                    
                    // IMPORTANT: Set plan data on the form for secure trial processing
                    if (trialForm) {
                        trialForm.setAttribute('data-plan', planId);
                    }
                    
                    // Show trial modal
                    trialModal.classList.add('show');
                    trialModal.style.display = 'flex';
                }
            });

            // Close trial modal
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('close-modal') || e.target.id === 'trial-modal') {
                    const modal = document.getElementById('trial-modal');
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                }
            });


            // Handle trial form submission
            const trialForm = document.getElementById('trial-form');
            if (trialForm) {
                trialForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Check if secure trial system is loaded
                    if (window.SUDSecureTrial && typeof window.SUDSecureTrial.handleTrialSubmission === 'function') {
                        // Use new secure system
                        window.SUDSecureTrial.handleTrialSubmission();
                        return;
                    }
                    
                    // Fallback error if secure system not loaded
                    alert('Secure payment system not loaded. Please refresh the page and try again.');
                    return;
                });
            }
        });
    </script>
    
    <!-- Console suppression for Stripe errors (must run before Stripe SDK) -->
    <script>
    // Set debug mode based on environment
    window.SUD_DEBUG = (window.location.hostname === 'localhost' || window.location.hostname.includes('staging') || window.location.hostname.includes('dev'));
    
    if (!window.SUD_DEBUG) {
        const origError = console.error;
        console.error = function(...args) {
            const msg = args.join(' ');
            if (msg.includes('api.stripe.com') && (msg.includes('402') || msg.includes('Payment Required'))) return;
            origError.apply(this, args);
        };
    }
    </script>
    
    <!-- Secure Trial Payment System -->
    <script src="<?php echo SUD_JS_URL; ?>/stripe-error-utils.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo SUD_JS_URL; ?>/secure-trial.js?v=<?php echo time(); ?>"></script>
</body>
</html>