<?php
/**
 * Centralized Pricing Configuration
 * 
 * This file contains all pricing definitions used throughout the application.
 * Update prices here to ensure consistency across all pages and functionality.
 */

defined( 'ABSPATH' ) or die( 'Cannot access this file directly.' );

// =====================================================
// PREMIUM SUBSCRIPTION PLANS
// =====================================================

define('SUD_PREMIUM_PLANS', [
    'free' => [
        'id' => 'free',
        'name' => 'Free Tier',
        'tier_level' => 0,
        // Pricing info (can be null or 0 for free)
        'price_monthly' => 0,
        'annual_discount_percent' => 0,
        'stripe_price_id_monthly' => '',
        'stripe_price_id_annually' => '',
        'paypal_plan_id_monthly' => '',
        'paypal_plan_id_annually' => '',
        'badge' => null,
        'color' => '#cccccc',
        'benefits' => [
            '10 daily swipes',
            '10 free messages per match',
            'Basic profile access',
            'Limited features'
        ],
        'is_popular' => false,
        'is_best_value' => false,
        // Capabilities
        'swipe_limit' => 10,
        'allowance_range' => 'none',
        'chat_scope' => 'limited',
        'withdrawal_scope' => 'none',
        'ads' => true,
        'advanced_filters' => false,
        'viewed_profile' => false,
        'profile_boost' => 0,
        'priority_support' => false,
        'verification_badge_auto' => false,
        'icon' => null,
        'badge_title' => 'Free Member',
        'free_swipe_ups_daily' => 0,
        'weekly_mini_boosts' => 0,
        'priority_inbox_placement' => false,
        'premium_messaging' => false,
    ],
    // COMMENTED OUT: Silver plan discontinued in favor of free trials for Gold/Diamond
    /*
    'silver' => [
        'id' => 'silver',
        'name' => 'Silver Tier',
        'tier_level' => 1,
        // Pricing info
        'price_monthly' => 11.11,
        'annual_discount_percent' => 20,
        'stripe_price_id_monthly' => 'price_1Rio8kCfTkgCeM6m71OYtvjk',
        'stripe_price_id_annually' => 'price_1Rio8kCfTkgCeM6mJWK8OVoQ',
        'stripe_price_id_monthly_test' => 'price_1RhqouCfTkgCeM6mVeoUcSxD',
        'stripe_price_id_annually_test' => 'price_1RhqsoCfTkgCeM6mA14Q4aj9',
        'paypal_plan_id_monthly' => 'P-9AN07512L4618453KNBXJ4SY',
        'paypal_plan_id_annually' => 'P-0HA26801YP029351LNBXJ5NY',
        'badge' => 'BASIC',
        'color' => '#C0C0C0',
        'benefits' => [
            '30 daily swipes',
            'Unlimited messaging',
            'Access to global sugar daddies',
            '1 daily swipe-up credit (instant match)',
            'Basic profile visibility',
            'Standard customer support'
        ],
        'is_popular' => false,
        'is_best_value' => false,
        // Capabilities
        'swipe_limit' => 30,
        'allowance_range' => '500-1000',
        'chat_scope' => 'worldwide',
        'withdrawal_scope' => 'none',
        'ads' => false,
        'advanced_filters' => false,
        'viewed_profile' => false,
        'profile_boost' => 2,
        'priority_support' => false,
        'verification_badge_auto' => false,
        'icon' => 'fas fa-medal',
        'badge_title' => 'Silver Member',
        'free_swipe_ups_daily' => 1,
        'weekly_mini_boosts' => 0,
        'priority_inbox_placement' => false,
        'premium_messaging' => true,
    ],
    */
    'gold' => [
        'id' => 'gold',
        'name' => 'Gold Tier',
        'tier_level' => 2,
        // Pricing info
        'price_monthly' => 33.33,
        'annual_discount_percent' => 20,
        'stripe_price_id_monthly' => 'price_1Rio8iCfTkgCeM6mk86mGHt1',
        'stripe_price_id_annually' => 'price_1Rio8iCfTkgCeM6mJpRXhyit',
        'stripe_price_id_monthly_test' => 'price_1RhqyXCfTkgCeM6mrTL3itiZ',
        'stripe_price_id_annually_test' => 'price_1RhqyXCfTkgCeM6mogDInD3n',
        'paypal_plan_id_monthly' => 'P-7GA98131UB015584VNBXJ5YI',
        'paypal_plan_id_annually' => 'P-18147339WX651560XNBXJ6EY',
        'badge' => 'POPULAR',
        'color' => '#FFD700',
        'benefits' => [
            'Unlimited swipes',
            'Unlimited messaging',
            'Access to global sugar daddies',
            'Unlimited cash withdrawals (PAYPAL)',
            '5 daily swipe-up credits (instant match)',
            '1 free profile boost monthly',
            'Priority inbox placement',
            'More daily visibility',
            'Premium customer support'
        ],
        'is_popular' => true,
        'is_best_value' => false,
        'free_trial_days' => 3,
        'trial_available' => true,
        // Full Capabilities
        'swipe_limit' => 999999,
        'allowance_range' => '2000-7000',
        'chat_scope' => 'worldwide',
        'withdrawal_scope' => 'worldwide',
        'ads' => false,
        'advanced_filters' => false,
        'viewed_profile' => true,
        'profile_boost' => 5,
        'priority_support' => false,
        'verification_badge_auto' => false,
        'icon' => 'fas fa-award',
        'badge_title' => 'Gold Member',
        'free_swipe_ups_daily' => 5,
        'weekly_mini_boosts' => 1,
        'priority_inbox_placement' => true,
        'premium_messaging' => true,
    ],
    'diamond' => [
        'id' => 'diamond',
        'name' => 'Diamond Tier',
        'tier_level' => 3,
        // Pricing info
        'price_monthly' => 55.55,
        'annual_discount_percent' => 20,
        'stripe_price_id_monthly' => 'price_1Rio8dCfTkgCeM6m9SjoMFY6',
        'stripe_price_id_annually' => 'price_1Rio8dCfTkgCeM6maOfWZano',
        'stripe_price_id_monthly_test' => 'price_1Rhr0WCfTkgCeM6m7HF2eqIs',
        'stripe_price_id_annually_test' => 'price_1Rhr0WCfTkgCeM6mE7plvbhH',
        'paypal_plan_id_monthly' => 'P-78C31511TA908333GNBXJ60Q',
        'paypal_plan_id_annually' => 'P-6SD42043VB1007112NBXJ63I',
        'badge' => 'ELITE',
        'color' => '#81c4d3',
        'benefits' => [
            'Everything in Gold',
            'Access to advance search & filter tools',
            'Match with elite daddies offering $7K–$10K+ allowances',
            '10 daily swipe-up credits (instant match)',
            '5 free profile boosts monthly',
            'Featured placement in Elite Sugar Baby carousel',
            'Priority customer support',
            'Background verification badge'
        ],
        'is_popular' => false,
        'is_best_value' => true,
        'free_trial_days' => 3,
        'trial_available' => true,
        // Full Capabilities
        'swipe_limit' => 999999,
        'allowance_range' => '7000-20000+',
        'chat_scope' => 'worldwide',
        'withdrawal_scope' => 'worldwide',
        'ads' => false,
        'advanced_filters' => true,
        'viewed_profile' => true,
        'profile_boost' => 10,
        'priority_support' => true,
        'verification_badge_auto' => true,
        'icon' => 'fas fa-gem',
        'badge_title' => 'Diamond Member',
        'free_swipe_ups_daily' => 10,
        'weekly_mini_boosts' => 5,
        'priority_inbox_placement' => true,
        'premium_messaging' => true,
    ]
]);

// =====================================================
// BOOST PACKAGES
// =====================================================

define('SUD_BOOST_PACKAGES', [
    'mini' => [
        'id' => 'mini',
        'name' => 'Mini Boost',
        'price' => 9.99,
        'description' => 'Notify 20 verified sugar daddies',
        'icon' => '🟡',
        'is_popular' => false
    ],
    'power' => [
        'id' => 'power',
        'name' => 'Power Boost',
        'price' => 19.99,
        'description' => 'Notify 50 top-spending daddies',
        'icon' => '🟣',
        'is_popular' => true
    ],
    'diamond' => [
        'id' => 'diamond',
        'name' => 'Diamond Blast',
        'price' => 39.99,
        'description' => 'Notify 100 elite daddies with $7K+ budgets',
        'icon' => '🔵',
        'is_popular' => false
    ]
]);

// =====================================================
// SWIPE UP PACKAGES
// =====================================================

define('SUD_SWIPE_UP_PACKAGES', [
    'small' => [
        'id' => 'small',
        'name' => '5 Swipe Ups',
        'price' => 4.99,
        'quantity' => 5,
        'description' => '5 instant match opportunities',
        'is_popular' => false
    ],
    'medium' => [
        'id' => 'medium',
        'name' => '10 Swipe Ups',
        'price' => 8.99,
        'quantity' => 10,
        'description' => '10 instant match opportunities',
        'is_popular' => true,
        'badge' => 'Best Value'
    ],
    'large' => [
        'id' => 'large',
        'name' => '20 Swipe Ups',
        'price' => 17.99,
        'quantity' => 20,
        'description' => '20 instant match opportunities',
        'is_popular' => false
    ]
]);

// =====================================================
// COIN PACKAGES
// =====================================================

define('SUD_COIN_PACKAGES', [
    [
        'amount' => 100,
        'bonus' => 0,
        'price' => 10.00,
        'discount' => 0,
        'badge' => '',
        'is_popular' => false,
        'is_best_value' => false
    ],
    [
        'amount' => 300,
        'bonus' => 30,
        'price' => 30.00,
        'discount' => 10,
        'badge' => '+30 BONUS',
        'is_popular' => false,
        'is_best_value' => false
    ],
    [
        'amount' => 500,
        'bonus' => 75,
        'price' => 50.00,
        'discount' => 13,
        'badge' => '+75 BONUS',
        'is_popular' => true,
        'is_best_value' => false
    ],
    [
        'amount' => 1000,
        'bonus' => 200,
        'price' => 100.00,
        'discount' => 17,
        'badge' => '+200 BONUS',
        'is_popular' => false,
        'is_best_value' => false
    ],
    [
        'amount' => 3000,
        'bonus' => 750,
        'price' => 300.00,
        'discount' => 20,
        'badge' => '+750 BONUS',
        'is_popular' => false,
        'is_best_value' => false
    ],
    [
        'amount' => 8000,
        'bonus' => 2500,
        'price' => 800.00,
        'discount' => 24,
        'badge' => 'BEST VALUE',
        'is_popular' => false,
        'is_best_value' => true
    ]
]);

// =====================================================
// HELPER FUNCTIONS FOR PRICING CALCULATIONS
// =====================================================

/**
 * Calculate the annual price for a plan
 * 
 * @param string $plan_id The plan ID (silver, gold, diamond)
 * @return float The annual price with discount applied
 */
function sud_get_annual_price($plan_id) {
    $plans = SUD_PREMIUM_PLANS;
    if (!isset($plans[$plan_id])) {
        return 0;
    }
    
    $plan = $plans[$plan_id];
    $monthly_price = $plan['price_monthly'];
    $discount_percent = $plan['annual_discount_percent'];
    
    // Calculate: (monthly * 12) * (1 - discount/100)
    return ($monthly_price * 12) * (1 - ($discount_percent / 100));
}

/**
 * Calculate the monthly price when paying annually
 * 
 * @param string $plan_id The plan ID (silver, gold, diamond)
 * @return float The effective monthly price when paying annually
 */
function sud_get_annual_monthly_price($plan_id) {
    return sud_get_annual_price($plan_id) / 12;
}

/**
 * Get all pricing data for a plan
 * 
 * @param string $plan_id The plan ID (silver, gold, diamond)
 * @return array|null Plan data with calculated prices
 */
function sud_get_plan_pricing($plan_id) {
    $plans = SUD_PREMIUM_PLANS;
    if (!isset($plans[$plan_id])) {
        return null;
    }
    
    $plan = $plans[$plan_id];
    $plan['price_annually'] = sud_get_annual_price($plan_id);
    $plan['annual_monthly_price'] = sud_get_annual_monthly_price($plan_id);
    $plan['annual_savings'] = ($plan['price_monthly'] * 12) - $plan['price_annually'];
    
    return $plan;
}

/**
 * Get all premium plans with calculated pricing
 * 
 * @return array All plans with calculated annual pricing
 */
function sud_get_all_plans_with_pricing() {
    $plans = [];
    foreach (array_keys(SUD_PREMIUM_PLANS) as $plan_id) {
        $plans[$plan_id] = sud_get_plan_pricing($plan_id);
    }
    return $plans;
}

/**
 * Get boost packages
 * 
 * @return array All boost packages
 */
function sud_get_boost_packages() {
    return SUD_BOOST_PACKAGES;
}

/**
 * Get swipe up packages
 * 
 * @return array All swipe up packages
 */
function sud_get_swipe_up_packages() {
    return SUD_SWIPE_UP_PACKAGES;
}

/**
 * Get coin packages
 * 
 * @return array All coin packages
 */
function sud_get_coin_packages() {
    return SUD_COIN_PACKAGES;
}

/**
 * Get the appropriate Stripe price ID based on test/live mode
 * 
 * @param string $plan_id The plan ID (silver, gold, diamond)
 * @param string $billing_cycle 'monthly' or 'annual'
 * @return string The appropriate Stripe price ID
 */
function sud_get_stripe_price_id($plan_id, $billing_cycle = 'monthly') {
    $plan = sud_get_plan_pricing($plan_id);
    if (!$plan) {
        return '';
    }
    
    $is_test_mode = function_exists('sud_is_test_mode') ? sud_is_test_mode() : false;
    
    if ($billing_cycle === 'annual') {
        return $is_test_mode ? 
            ($plan['stripe_price_id_annually_test'] ?? '') : 
            ($plan['stripe_price_id_annually'] ?? '');
    } else {
        return $is_test_mode ? 
            ($plan['stripe_price_id_monthly_test'] ?? '') : 
            ($plan['stripe_price_id_monthly'] ?? '');
    }
}

// =====================================================
// FREE TRIAL FUNCTIONS
// =====================================================

/**
 * Check if user has used their free trial for a specific plan
 * 
 * @param int $user_id
 * @param string $plan_id (gold, diamond)
 * @return bool
 */
function sud_has_used_trial($user_id, $plan_id) {
    $used_trials = get_user_meta($user_id, 'sud_used_trials', true);
    if (!is_array($used_trials)) {
        $used_trials = [];
    }
    return in_array($plan_id, $used_trials);
}

/**
 * Check if user has any active trial
 * 
 * @param int $user_id
 * @return array|false Trial data or false
 */
function sud_get_active_trial($user_id) {
    $trial_plan = get_user_meta($user_id, 'sud_trial_plan', true);
    $trial_start = get_user_meta($user_id, 'sud_trial_start', true);
    $trial_end = get_user_meta($user_id, 'sud_trial_end', true);
    
    if (!$trial_plan || !$trial_start || !$trial_end) {
        return false;
    }
    
    // Check if trial is still active
    if (time() > strtotime($trial_end)) {
        return false;
    }
    
    return [
        'plan' => $trial_plan,
        'start' => $trial_start,
        'end' => $trial_end,
        'days_remaining' => ceil((strtotime($trial_end) - time()) / (24 * 60 * 60))
    ];
}

/**
 * Start a free trial for a user
 * 
 * @param int $user_id
 * @param string $plan_id
 * @param array $payment_details
 * @return bool
 */
function sud_start_trial($user_id, $plan_id, $payment_details = []) {
    $plans = SUD_PREMIUM_PLANS;
    if (!isset($plans[$plan_id]) || !($plans[$plan_id]['trial_available'] ?? false)) {
        return false;
    }
    
    // Check if user already used trial for this plan
    if (sud_has_used_trial($user_id, $plan_id)) {
        return false;
    }
    
    // Check if user has active trial - allow switching between plans
    $active_trial = sud_get_active_trial($user_id);
    $is_switching_plans = false;
    $existing_end_date = null;
    
    if ($active_trial) {
        // If switching to a different plan during active trial, preserve end date
        if ($active_trial['plan'] !== $plan_id) {
            $is_switching_plans = true;
            $existing_end_date = $active_trial['end'];
        } else {
            // Same plan - don't allow restart
            return false;
        }
    }
    
    $trial_days = $plans[$plan_id]['free_trial_days'] ?? 3;
    
    if ($is_switching_plans && $existing_end_date) {
        // Preserve existing trial end date when switching plans
        $start_date = get_user_meta($user_id, 'sud_trial_start', true) ?: current_time('mysql');
        $end_date = $existing_end_date;
    } else {
        // New trial - set fresh dates
        $start_date = current_time('mysql');
        $end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +' . $trial_days . ' days'));
    }
    
    // Set trial metadata
    update_user_meta($user_id, 'sud_trial_plan', $plan_id);
    update_user_meta($user_id, 'sud_trial_start', $start_date);
    update_user_meta($user_id, 'sud_trial_end', $end_date);
    update_user_meta($user_id, 'premium_plan', $plan_id);
    update_user_meta($user_id, 'subscription_expires', $end_date);
    
    // Legacy insecure payment storage - deprecated in favor of secure SetupIntents
    // Note: New trials use sud_trial_secure_payment instead of sud_trial_payment_details
    if (!empty($payment_details)) {
        // Only store for backwards compatibility with old system
        // New secure trials store payment method IDs, not raw card data
        update_user_meta($user_id, 'sud_trial_payment_details', $payment_details);
    }
    
    // Mark trial as used
    $used_trials = get_user_meta($user_id, 'sud_used_trials', true);
    if (!is_array($used_trials)) {
        $used_trials = [];
    }
    $used_trials[] = $plan_id;
    update_user_meta($user_id, 'sud_used_trials', $used_trials);
    
    return true;
}

/**
 * Cancel a user's trial and revert to free plan
 * 
 * @param int $user_id
 * @return bool
 */
function sud_cancel_trial($user_id) {
    $trial = sud_get_active_trial($user_id);
    if (!$trial) {
        return false;
    }
    
    // Send trial cancellation email before removing trial data
    if (function_exists('send_trial_cancellation_email')) {
        $trial_data = [
            'plan' => $trial['plan'],
            'cancelled_date' => current_time('mysql'),
            'end' => $trial['end']
        ];
        send_trial_cancellation_email($user_id, $trial_data);
    }
    
    // Revert to free plan
    update_user_meta($user_id, 'premium_plan', 'free');
    delete_user_meta($user_id, 'subscription_expires');
    delete_user_meta($user_id, 'sud_trial_plan');
    delete_user_meta($user_id, 'sud_trial_start');
    delete_user_meta($user_id, 'sud_trial_end');
    delete_user_meta($user_id, 'sud_trial_payment_details'); // Legacy insecure data
    delete_user_meta($user_id, 'sud_trial_secure_payment'); // New secure data
    
    return true;
}

/**
 * Check if user is eligible for any trial
 * 
 * @param int $user_id
 * @return array Available trial plans
 */
function sud_get_available_trials($user_id) {
    $plans = SUD_PREMIUM_PLANS;
    $available = [];
    
    foreach ($plans as $plan_id => $plan) {
        if (($plan['trial_available'] ?? false) && !sud_has_used_trial($user_id, $plan_id)) {
            $available[$plan_id] = $plan;
        }
    }
    
    return $available;
}

/**
 * Get trial-specific swipe limitations (keeps all other premium features)
 * 
 * @param string $plan_id
 * @return array|null Trial swipe limits only
 */
function sud_get_trial_capabilities($plan_id) {
    $trial_limits = [
        'gold' => [
            'swipe_limit' => 20,
            'free_swipe_ups_daily' => 3,
        ],
        'diamond' => [
            'swipe_limit' => 30,
            'free_swipe_ups_daily' => 3,
        ]
    ];
    
    return $trial_limits[$plan_id] ?? null;
}

/**
 * Check if user is currently on a trial (not full subscription)
 * 
 * @param int $user_id
 * @return bool
 */
function sud_is_user_on_trial($user_id) {
    $trial = sud_get_active_trial($user_id);
    return $trial !== false;
}