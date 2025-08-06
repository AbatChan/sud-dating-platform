<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');

header('Content-Type: application/json');

try {
    // Use centralized security verification
    $current_user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_swipe_action_nonce', // Keep existing nonce for swipes
        'rate_limit' => ['requests' => 50, 'window' => 60, 'action' => 'process_swipe'] // Allow many swipes per minute
    ]);
    $swiped_user_id = SUD_AJAX_Security::validate_user_id($_POST['swiped_user_id'] ?? 0, false);
    $swipe_type = isset($_POST['swipe_type']) ? sanitize_text_field($_POST['swipe_type']) : '';


    if (!in_array($swipe_type, ['like', 'pass', 'swipe_up'])) {
        throw new Exception('Invalid swipe type');
    }

    if ($current_user_id === $swiped_user_id) {
        throw new Exception('You cannot swipe on yourself');
    }

    if ($swipe_type === 'swipe_up') {
        if (!function_exists('sud_deduct_swipe_up') || !function_exists('sud_get_user_swipe_up_balance')) {
            throw new Exception('Swipe Up feature is currently unavailable. Please contact support.');
        }

        if (sud_deduct_swipe_up($current_user_id)) {
            sud_record_swipe($current_user_id, $swiped_user_id, 'like'); 

            $is_match = sud_check_and_process_match($current_user_id, $swiped_user_id, true); 
            
            // Trigger swipe monitoring action (only one action for instant matches)
            do_action('sud_user_swiped', $current_user_id, $swiped_user_id, 'swipe_up', $is_match);

            $match_user_details = get_user_profile_data($swiped_user_id);
            
            // Calculate remaining regular swipes for consistency with frontend
            $remaining_swipes_count = sud_get_daily_swipe_limit($current_user_id) - sud_get_user_swipe_count_today($current_user_id);
            $remaining_swipes_count = max(0, $remaining_swipes_count);
            
            wp_send_json_success([
                'message' => 'Instant Match! Your Swipe Up was successful.',
                'swipe_type' => 'swipe_up', 
                'is_match' => $is_match,
                'match_user_details' => $match_user_details ? [
                    'id' => $match_user_details['id'],
                    'name' => $match_user_details['name'],
                    'profile_pic' => $match_user_details['profile_pic'],
                    'profile_url' => SUD_URL . '/pages/profile?id=' . $match_user_details['id'],
                    'message_url' => SUD_URL . '/pages/messages?user=' . $match_user_details['id'],
                ] : null,
                'new_swipe_up_balance' => sud_get_user_swipe_up_balance($current_user_id),
                'remaining_swipes' => $remaining_swipes_count,
                'limit_reached' => $remaining_swipes_count <= 0
            ]);
            exit;
        } else {
            throw new Exception('You have no Swipe Ups remaining.');
        }
    }

    // Only check regular swipe limits for like/pass actions that consume daily limit
    if (in_array($swipe_type, ['like', 'pass'])) {
        $has_remaining = sud_has_remaining_swipes($current_user_id);
        
        if (!$has_remaining) {
            // Check if user is on trial for dynamic messaging
            $active_trial = sud_get_active_trial($current_user_id);
            $is_on_trial = $active_trial !== false;
            
            $limit_data = [
                'message' => 'You have reached your daily swipe limit.',
                'remaining_swipes' => 0,
                'limit_reached' => true,
                'is_on_trial' => $is_on_trial,
                'show_modal' => true
            ];
            
            if ($is_on_trial) {
                $trial_plan = $active_trial['plan'] ?? 'gold';
                $plan_name = ucfirst($trial_plan);
                $limit_data['upgrade_message'] = "Trial swipe limit reached. Upgrade to {$plan_name} for unlimited swiping!";
                $limit_data['upgrade_url'] = SUD_URL . "/pages/premium?direct_pay=true&plan={$trial_plan}";
                $limit_data['upgrade_text'] = "Upgrade to {$plan_name}";
            } else if (!sud_is_user_premium($current_user_id)) {
                $limit_data['upgrade_message'] = 'Upgrade now to unlock unlimited swiping and more features!';
                $limit_data['upgrade_url'] = SUD_URL . '/pages/premium?highlight_plan=gold';
                $limit_data['upgrade_text'] = 'Unlock Unlimited Swipes';
            }
            
            // Return success with limit data to trigger proper modal instead of 403 error
            wp_send_json_success($limit_data);
            exit;
        }
    }

    $swipe_recorded = sud_record_swipe($current_user_id, $swiped_user_id, $swipe_type);

    if (!$swipe_recorded) {
        throw new Exception('You have already swiped on this user.');
    }

    // Increment swipe count
    sud_increment_user_swipe_count($current_user_id);

    // Trigger swipe monitoring action for regular swipes
    do_action('sud_user_swiped', $current_user_id, $swiped_user_id, $swipe_type, false);

    $is_match = false;
    $match_user_details = null;

    if ($swipe_type === 'like') {
        if (sud_check_and_process_match($current_user_id, $swiped_user_id, false)) {
            $is_match = true;
            // Trigger match monitoring action
            do_action('sud_match_created', $current_user_id, $swiped_user_id, 'like', true);
            
            $match_user_details_raw = get_user_profile_data($swiped_user_id);
            if ($match_user_details_raw) {
                $match_user_details = [
                    'id' => $match_user_details_raw['id'],
                    'name' => $match_user_details_raw['name'],
                    'profile_pic' => $match_user_details_raw['profile_pic'],
                    'profile_url' => SUD_URL . '/pages/profile?id=' . $match_user_details_raw['id'],
                    'message_url' => SUD_URL . '/pages/messages?user=' . $match_user_details_raw['id'],
                ];
            }
        }
    }

    $remaining_swipes_count = sud_get_daily_swipe_limit($current_user_id) - sud_get_user_swipe_count_today($current_user_id);
    $remaining_swipes_count = max(0, $remaining_swipes_count);

    wp_send_json_success([
        'message' => 'Swipe processed.',
        'swipe_type' => $swipe_type,
        'is_match' => $is_match,
        'match_user_details' => $is_match ? $match_user_details : null,
        'remaining_swipes' => $remaining_swipes_count,
        'limit_reached' => $remaining_swipes_count <= 0
    ]);
    
} catch (Exception $e) {
    sud_handle_ajax_error($e);
}