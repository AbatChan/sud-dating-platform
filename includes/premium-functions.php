<?php

defined( 'ABSPATH' ) or die( 'Cannot access this file directly.' );

// Load pricing-config.php for trial functions
require_once(dirname(__FILE__) . '/pricing-config.php');

function sud_is_user_premium($user_id) {
    if (empty($user_id) || !is_numeric($user_id)) {
        return false;
    }
    
    // Check if user is on trial first - trial users ARE premium
    if (function_exists('sud_is_user_on_trial') && sud_is_user_on_trial($user_id)) {
        return true;
    }
    
    // Check regular premium subscription
    $plan_id = get_user_meta($user_id, 'premium_plan', true);
    $expires = get_user_meta($user_id, 'subscription_expires', true);

    if (empty($plan_id) || $plan_id === 'free' || empty($expires)) {
        return false;
    }

    $expiry_timestamp = strtotime($expires);
    if ($expiry_timestamp === false || $expiry_timestamp <= strtotime(current_time('mysql', true))) {
        update_user_meta($user_id, 'premium_plan', 'free');
        return false;
    }

    return true;
}

function sud_get_user_premium_plan_id($user_id) {
    // Check if user is on trial first - return trial plan ID
    if (function_exists('sud_is_user_on_trial') && sud_is_user_on_trial($user_id)) {
        if (function_exists('sud_get_active_trial')) {
            $active_trial = sud_get_active_trial($user_id);
            if ($active_trial && isset($active_trial['plan'])) {
                return $active_trial['plan']; // Return 'diamond', 'gold', etc.
            }
        }
    }
    
    // Check regular premium subscription
    if (sud_is_user_premium($user_id)) {
        return get_user_meta($user_id, 'premium_plan', true);
    }
    return 'free';
}

function sud_get_plan_details($plan_id) {
    $plan_id = sanitize_key($plan_id);
    $all_plans = defined('SUD_PREMIUM_PLANS') ? SUD_PREMIUM_PLANS : [];
    return $all_plans[$plan_id] ?? $all_plans['free'];
}

function sud_get_user_current_plan_details($user_id) {
    // Check if user is on trial first - trials get limited swipes but full premium features
    if (function_exists('sud_is_user_on_trial') && sud_is_user_on_trial($user_id)) {
        // Get the actual trial plan ID from trial metadata
        if (function_exists('sud_get_active_trial')) {
            $active_trial = sud_get_active_trial($user_id);
            if ($active_trial && isset($active_trial['plan'])) {
                $trial_plan_id = $active_trial['plan'];
                $plan_details = sud_get_plan_details($trial_plan_id);
                
                // Apply trial swipe limitations (keeps all other premium features)
                if (function_exists('sud_get_trial_capabilities')) {
                    $trial_limits = sud_get_trial_capabilities($trial_plan_id);
                    if ($trial_limits) {
                        // Override only swipe limits, keep everything else from full plan
                        return array_merge($plan_details, $trial_limits);
                    }
                }
                
                return $plan_details;
            }
        }
    }
    
    // Not on trial - check regular premium plan
    $current_plan_id = sud_get_user_premium_plan_id($user_id);
    return sud_get_plan_details($current_plan_id);
}

function sud_user_can_access_feature($user_id, $feature_key, $compare_value = null) {
    $plan_details = sud_get_user_current_plan_details($user_id);

    if (!isset($plan_details[$feature_key])) {
        // If the feature key doesn't exist for the user's plan, check the 'free' plan as a fallback.
        $free_plan_details = sud_get_plan_details('free');
        if (!isset($free_plan_details[$feature_key])) {
            return false;
        }
        $feature_value = $free_plan_details[$feature_key];
    } else {
        $feature_value = $plan_details[$feature_key];
    }

    if (is_bool($feature_value)) {
        return $feature_value === true;
    }

    if (is_numeric($feature_value)) {
        if (is_numeric($compare_value)) {
            return $feature_value >= $compare_value;
        }
        return $feature_value > 0;
    }

    if (is_string($feature_value)) {
        if ($compare_value !== null) {
            return strtolower($feature_value) === strtolower($compare_value);
        }
        return !empty($feature_value) && !in_array(strtolower($feature_value), ['none', 'limited', 'false', '0']);
    }

    return false;
}

function sud_get_premium_badge_html($user_id, $size = 'small', $attributes = []) {
    $plan_details = sud_get_user_current_plan_details($user_id);

    if (empty($plan_details['icon']) || $plan_details['id'] === 'free') {
        return '';
    }

    $plan_id = $plan_details['id'];
    $icon_class = esc_attr($plan_details['icon']);
    $badge_title = esc_attr($plan_details['badge_title'] ?? $plan_details['name']);
    $color_style = !empty($plan_details['color']) ? 'color:' . esc_attr($plan_details['color']) . ';' : '';
    $size_class = 'badge-size-' . sanitize_html_class($size);

    $attr_string = '';
    if (!empty($color_style)) {
        $attributes['style'] = isset($attributes['style']) ? rtrim($attributes['style'], '; ') . '; ' . $color_style : $color_style;
    }
    foreach ($attributes as $key => $value) {
        $attr_string .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
    }

    return sprintf(
        '<span class="premium-badge %s %s" title="%s"%s><i class="%s"></i></span>',
        esc_attr('plan-' . $plan_id),
        esc_attr($size_class),
        $badge_title,
        $attr_string,
        $icon_class
    );
}

function sud_should_show_ads($user_id) {
    $plan_details = sud_get_user_current_plan_details($user_id);
    return isset($plan_details['ads']) && $plan_details['ads'] === true;
}

/**
 * Check if user has an active boost
 */
function sud_user_has_active_boost($user_id) {
    $boost_end_time = get_user_meta($user_id, 'active_boost_end_time', true);
    $current_time = time();
    $has_boost = !empty($boost_end_time) && $current_time < $boost_end_time;

    return $has_boost;
}

/**
 * Get user's boost multiplier based on active boost
 */
function sud_get_user_boost_multiplier($user_id) {
    if (!sud_user_has_active_boost($user_id)) {
        return 1; // No boost
    }
    
    $boost_type = get_user_meta($user_id, 'active_boost_type', true);
    
    // Different boost types give different multipliers
    switch ($boost_type) {
        case 'mini':
            return 2; // 2x visibility
        case 'power':
            return 3; // 3x visibility  
        case 'diamond':
            return 5; // 5x visibility
        default:
            return 1;
    }
}

/**
 * Clear expired boosts for a user
 */
function sud_clear_expired_boost($user_id) {
    $boost_end_time = get_user_meta($user_id, 'active_boost_end_time', true);
    if (!empty($boost_end_time) && time() >= $boost_end_time) {
        delete_user_meta($user_id, 'active_boost_type');
        delete_user_meta($user_id, 'active_boost_name');
        delete_user_meta($user_id, 'active_boost_end_time');
        delete_user_meta($user_id, 'active_boost_started');
        return true;
    }
    return false;
}



function sud_apply_profile_boost($users, $current_user_id_for_prefs = 0) {
    if (empty($users) || !is_array($users)) {
        return $users;
    }

    $currentUserGender = null;
    $currentUserLookingFor = null;

    if ($current_user_id_for_prefs > 0) {
        $currentUserGender = get_user_meta($current_user_id_for_prefs, 'gender', true);
        $currentUserLookingFor = get_user_meta($current_user_id_for_prefs, 'looking_for', true);
    }

    usort($users, function ($a, $b) use ($currentUserGender, $currentUserLookingFor) {
        // 0. Priority Users (SUD team members) - Highest priority
        $is_priority_a = function_exists('sud_is_priority_user') ? sud_is_priority_user($a['id'] ?? 0) : false;
        $is_priority_b = function_exists('sud_is_priority_user') ? sud_is_priority_user($b['id'] ?? 0) : false;
        
        if ($is_priority_a !== $is_priority_b) {
            return $is_priority_a ? -1 : 1; // Priority users first
        }
        
        // 1. Profile Boost (DESC) - including active purchased boosts
        $boost_a = isset($a['premium_capabilities']['profile_boost']) ? (int)$a['premium_capabilities']['profile_boost'] : 0;
        $boost_b = isset($b['premium_capabilities']['profile_boost']) ? (int)$b['premium_capabilities']['profile_boost'] : 0;
        
        // Add active boost multipliers  
        $user_id_a = isset($a['id']) ? $a['id'] : (isset($a['ID']) ? $a['ID'] : 0);
        $user_id_b = isset($b['id']) ? $b['id'] : (isset($b['ID']) ? $b['ID'] : 0);
        
        if ($user_id_a) {
            sud_clear_expired_boost($user_id_a); // Clean up expired boosts
            $boost_a *= sud_get_user_boost_multiplier($user_id_a);
        }
        if ($user_id_b) {
            sud_clear_expired_boost($user_id_b); // Clean up expired boosts
            $boost_b *= sud_get_user_boost_multiplier($user_id_b);
        }
        
        if ($boost_a !== $boost_b) {
            return $boost_b <=> $boost_a;
        }

        // 2. Gender Preference Match (DESC score)
        if ($currentUserGender && $currentUserLookingFor && $currentUserLookingFor !== 'LGBTQ+') {
            $score_a = 0;
            $score_b = 0;

            $profileGenderA = isset($a['gender']) ? $a['gender'] : '';
            $profileGenderB = isset($b['gender']) ? $b['gender'] : '';

            if ($currentUserLookingFor === 'Woman') {
                if ($profileGenderA === 'Woman') $score_a = 1;
                if ($profileGenderB === 'Woman') $score_b = 1;
            } elseif ($currentUserLookingFor === 'Man') {
                if ($profileGenderA === 'Man') $score_a = 1;
                if ($profileGenderB === 'Man') $score_b = 1;
            }
            // Add more specific heterosexual matching if needed, e.g.:
            // elseif ($currentUserGender === 'Man' && $currentUserLookingFor === 'Woman' && $profileGenderA === 'Woman') $score_a = 1;
            // elseif ($currentUserGender === 'Woman' && $currentUserLookingFor === 'Man' && $profileGenderA === 'Man') $score_a = 1;

            if ($score_a !== $score_b) {
                return $score_b <=> $score_a;
            }
        }

        // 3. Last Active Timestamp (DESC)
            $last_active_a = isset($a['last_active_timestamp']) ? (int)$a['last_active_timestamp'] : 0;
            $last_active_b = isset($b['last_active_timestamp']) ? (int)$b['last_active_timestamp'] : 0;
        if ($last_active_a !== $last_active_b) {
            return $last_active_b <=> $last_active_a; 
        }

        // 4. Distance (ASC - closer first)
        $distance_a = isset($a['distance']) ? (float)$a['distance'] : PHP_INT_MAX;
        $distance_b = isset($b['distance']) ? (float)$b['distance'] : PHP_INT_MAX;
        return $distance_a <=> $distance_b;
    });

    return $users;
}

function sud_can_interact_by_tier($viewer_id, $target_id) {
    if (empty($viewer_id) || empty($target_id) || $viewer_id == $target_id) {
        return false; 
    }

    $viewer_plan = sud_get_user_current_plan_details($viewer_id);
    $target_plan = sud_get_user_current_plan_details($target_id);

    $viewer_level = isset($viewer_plan['tier_level']) ? (int)$viewer_plan['tier_level'] : 0;
    $target_level = isset($target_plan['tier_level']) ? (int)$target_plan['tier_level'] : 0;

    return ($viewer_level >= $target_level || $target_level === 0);
}

/**
 * Automatically verify a user (for Diamond tier)
 */
function sud_auto_verify_user($user_id) {
    if (!$user_id || !is_numeric($user_id)) {
        return false;
    }
    
    // Set verification status
    $result = update_user_meta($user_id, 'is_verified', true);
    
    if ($result !== false) {
        // Send notification about verification
        if (function_exists('send_notification')) {
            send_notification($user_id, 'verification_granted', [
                'title' => 'Verification Badge Granted',
                'message' => 'Congratulations! Your Diamond membership includes automatic verification. Your profile now displays the verified badge.',
                'type' => 'premium'
            ]);
        }
        
        return true;
    }
    
    return false;
}

/**
 * Remove verification from a user (when downgrading from Diamond)
 */
function sud_remove_auto_verification($user_id) {
    if (!$user_id || !is_numeric($user_id)) {
        return false;
    }
    
    // Only remove if they were auto-verified (not manually verified by admin)
    $verification_type = get_user_meta($user_id, 'verification_type', true);
    
    // If no verification type is set, assume it was auto-verification
    if (empty($verification_type) || $verification_type === 'auto') {
        $result = update_user_meta($user_id, 'is_verified', false);
        delete_user_meta($user_id, 'verification_type');
        
        if ($result !== false) {
            
            // Send notification about verification removal
            if (function_exists('send_notification')) {
                send_notification($user_id, 'verification_removed', [
                    'title' => 'Verification Status Updated',
                    'message' => 'Your verification badge was tied to your Diamond membership. Consider upgrading back to Diamond for automatic verification.',
                    'type' => 'info'
                ]);
            }
            
            return true;
        }
    }
    
    return false;
}

/**
 * Check and apply/remove verification based on current plan
 */
function sud_update_verification_based_on_plan($user_id) {
    if (!$user_id || !is_numeric($user_id)) {
        return false;
    }
    
    $plan_details = sud_get_user_current_plan_details($user_id);
    $should_be_verified = $plan_details['verification_badge_auto'] ?? false;
    $is_currently_verified = (bool) get_user_meta($user_id, 'is_verified', true);
    
    if ($should_be_verified && !$is_currently_verified) {
        // Should be verified but isn't - grant verification
        update_user_meta($user_id, 'verification_type', 'auto');
        return sud_auto_verify_user($user_id);
    } elseif (!$should_be_verified && $is_currently_verified) {
        // Shouldn't be verified but is - check if it's auto-verification
        $verification_type = get_user_meta($user_id, 'verification_type', true);
        if (empty($verification_type) || $verification_type === 'auto') {
            return sud_remove_auto_verification($user_id);
        }
    }
    
    return true;
}

/**
 * Handle verification when user plan changes
 */
function sud_handle_plan_verification_update($user_id, $new_plan_id, $old_plan_id = null) {
    if (!$user_id || !is_numeric($user_id)) {
        return false;
    }
    
    $new_plan = sud_get_plan_details($new_plan_id);
    $old_plan = $old_plan_id ? sud_get_plan_details($old_plan_id) : null;
    
    $new_auto_verify = $new_plan['verification_badge_auto'] ?? false;
    $old_auto_verify = $old_plan ? ($old_plan['verification_badge_auto'] ?? false) : false;
    
    // If moving to a plan that has auto-verification
    if ($new_auto_verify && !$old_auto_verify) {
        update_user_meta($user_id, 'verification_type', 'auto');
        return sud_auto_verify_user($user_id);
    }
    
    // If moving from a plan that had auto-verification to one that doesn't
    if (!$new_auto_verify && $old_auto_verify) {
        return sud_remove_auto_verification($user_id);
    }
    
    return true;
}

/**
 * Sync verification status for all users based on their current plan
 * Useful for fixing existing Diamond users who should be verified
 */
function sud_sync_all_user_verifications() {
    $users = get_users([
        'meta_key' => 'premium_plan',
        'meta_value' => 'diamond',
        'fields' => 'ID'
    ]);
    
    $updated_count = 0;
    foreach ($users as $user_id) {
        if (sud_update_verification_based_on_plan($user_id)) {
            $updated_count++;
        }
    }
    
    return $updated_count;
}

/**
 * Check if manual verification should be preserved
 */
function sud_preserve_manual_verification($user_id) {
    $verification_type = get_user_meta($user_id, 'verification_type', true);
    return (!empty($verification_type) && $verification_type === 'manual');
}

?>