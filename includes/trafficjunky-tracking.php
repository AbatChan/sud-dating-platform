<?php

defined( 'ABSPATH' ) or die( 'Cannot access this file directly.' );

/**
 * TrafficJunky Conversion Tracking Integration
 * Handles conversion tracking for user registrations and plan purchases
 */

/**
 * TrafficJunky tracking configuration
 */
function get_trafficjunky_config() {
    return [
        'member_id' => '1006625721',
        'urls' => [
            'signup' => 'https://ads.trafficjunky.net/ct?a=1000530692&member_id=1006625721',
            'silver' => 'https://ads.trafficjunky.net/ct?a=1000530702&member_id=1006625721',
            'gold' => 'https://ads.trafficjunky.net/ct?a=1000530712&member_id=1006625721',
            'diamond' => 'https://ads.trafficjunky.net/ct?a=1000530722&member_id=1006625721'
        ],
        'values' => [
            'silver' => '11.11',
            'gold' => '44.44', 
            'diamond' => '88.88'
        ]
    ];
}

/**
 * Fire TrafficJunky conversion tracking pixel
 * 
 * @param string $event_type Type of conversion (signup, silver, gold, diamond)
 * @param array $data Additional tracking data
 * @return bool Success status
 */
function fire_trafficjunky_conversion($event_type, $data = []) {
    $config = get_trafficjunky_config();
    
    if (!isset($config['urls'][$event_type])) {
        error_log("TrafficJunky Error: Unknown event type '$event_type'");
        return false;
    }
    
    // Build tracking URL with parameters
    $base_url = $config['urls'][$event_type];
    $params = [
        'cb' => rand(100000, 999999), // Cache buster
        'cti' => $data['transaction_id'] ?? uniqid('tj_', true), // Transaction unique ID
        'ctd' => $data['description'] ?? "SUD $event_type conversion", // Transaction description
        'aclid' => $data['aclid'] ?? '' // Ad click ID (if available)
    ];
    
    // Add transaction value for paid plans
    if (isset($config['values'][$event_type])) {
        $params['ctv'] = $config['values'][$event_type];
    } else if ($event_type === 'signup') {
        $params['ctv'] = ''; // Free signup has no value
    }
    
    // Build final URL
    $tracking_url = $base_url . '&' . http_build_query($params);
    
    // Fire the tracking pixel asynchronously
    return fire_tracking_pixel_async($tracking_url, $event_type, $data);
}

/**
 * Fire tracking pixel asynchronously to avoid blocking the main process
 * 
 * @param string $url Tracking URL to fire
 * @param string $event_type Event type for logging
 * @param array $data Additional data for logging
 * @return bool Success status
 */
function fire_tracking_pixel_async($url, $event_type, $data = []) {
    try {
        // Use WordPress HTTP API for reliable requests
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'blocking' => false, // Non-blocking request
            'headers' => [
                'User-Agent' => 'SUD-TrafficJunky-Tracker/1.0'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log("TrafficJunky Error: " . $response->get_error_message());
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("TrafficJunky Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Track user signup conversion
 * 
 * @param int $user_id WordPress user ID
 * @param array $data Additional tracking data
 * @return bool Success status
 */
function track_signup_conversion($user_id, $data = []) {
    $tracking_data = array_merge($data, [
        'user_id' => $user_id,
        'transaction_id' => "signup_" . $user_id . "_" . time(),
        'description' => 'SUD User Registration'
    ]);
    
    return fire_trafficjunky_conversion('signup', $tracking_data);
}

/**
 * Track plan purchase conversion
 * 
 * @param int $user_id WordPress user ID
 * @param string $plan_type Plan type (silver, gold, diamond)
 * @param string $transaction_id Stripe/PayPal transaction ID
 * @param array $data Additional tracking data
 * @return bool Success status
 */
function track_plan_purchase_conversion($user_id, $plan_type, $transaction_id, $data = []) {
    $plan_type = strtolower($plan_type);
    $config = get_trafficjunky_config();
    
    if (!isset($config['urls'][$plan_type])) {
        error_log("TrafficJunky Error: Invalid plan type '$plan_type'");
        return false;
    }
    
    $tracking_data = array_merge($data, [
        'user_id' => $user_id,
        'plan_type' => $plan_type,
        'transaction_id' => $transaction_id,
        'description' => "SUD " . ucfirst($plan_type) . " Plan Purchase"
    ]);
    
    return fire_trafficjunky_conversion($plan_type, $tracking_data);
}

/**
 * Extract plan type from subscription data
 * 
 * @param array $subscription_data Subscription/payment data
 * @return string|null Plan type or null if not found
 */
function extract_plan_type_from_subscription($subscription_data) {
    // Common patterns to identify plan types
    $plan_indicators = [
        'silver' => ['silver', 'price_silver', 'plan_silver'],
        'gold' => ['gold', 'price_gold', 'plan_gold'], 
        'diamond' => ['diamond', 'price_diamond', 'plan_diamond']
    ];
    
    $data_string = strtolower(json_encode($subscription_data));
    
    foreach ($plan_indicators as $plan => $indicators) {
        foreach ($indicators as $indicator) {
            if (strpos($data_string, $indicator) !== false) {
                return $plan;
            }
        }
    }
    
    return null;
}

