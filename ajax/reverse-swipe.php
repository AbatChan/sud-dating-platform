<?php
// Check if this is being called directly vs through WordPress
if (!defined('ABSPATH')) {
    // If called directly, we need to bootstrap WordPress
    require_once(dirname(__FILE__, 2) . '/../wp-load.php');
}

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');

try {
    // Use centralized security verification
    $current_user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_swipe_action_nonce',
        'nonce_field' => '_ajax_nonce',
        'rate_limit' => ['requests' => 10, 'window' => 60, 'action' => 'reverse_swipe']
    ]);

// Check if user has silver tier or higher
if (!function_exists('sud_get_user_current_plan_details')) {
    wp_send_json_error(['message' => 'Premium functions not available.']);
    exit;
}

$user_plan_details = sud_get_user_current_plan_details($current_user_id);
$user_tier_level = isset($user_plan_details['tier_level']) ? (int)$user_plan_details['tier_level'] : 0;

if ($user_tier_level < 1) {
    wp_send_json_error(['message' => 'This is a premium feature, go premium to use']);
    exit;
}

$swiped_user_id = intval($_POST['user_id'] ?? 0);
$swipe_type = sanitize_text_field($_POST['swipe_type'] ?? '');

if (empty($swiped_user_id) || empty($swipe_type)) {
    wp_send_json_error(['message' => 'Invalid parameters.']);
    exit;
}

if (!in_array($swipe_type, ['like', 'pass'])) {
    wp_send_json_error(['message' => 'Invalid swipe type for reversal.']);
    exit;
}

// Verify the swiped user exists
$swiped_user = get_user_by('id', $swiped_user_id);
if (!$swiped_user) {
    wp_send_json_error(['message' => 'User not found.']);
    exit;
}

global $wpdb;

// Check if there's a swipe record to reverse
$swipe_table = $wpdb->prefix . 'sud_user_swipes';
$swipe_record = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$swipe_table} 
     WHERE swiper_user_id = %d AND swiped_user_id = %d 
     ORDER BY swipe_timestamp DESC LIMIT 1",
    $current_user_id,
    $swiped_user_id
));

if (!$swipe_record) {
    wp_send_json_error(['message' => 'No swipe record found to reverse.']);
    exit;
}

// Check if the swipe was made recently (within last 10 swipes for example)
$recent_swipes = $wpdb->get_results($wpdb->prepare(
    "SELECT swiped_user_id FROM {$swipe_table} 
     WHERE swiper_user_id = %d 
     ORDER BY swipe_timestamp DESC LIMIT 10",
    $current_user_id
));

$can_reverse = false;
foreach ($recent_swipes as $recent_swipe) {
    if ($recent_swipe->swiped_user_id == $swiped_user_id) {
        $can_reverse = true;
        break;
    }
}

if (!$can_reverse) {
    wp_send_json_error(['message' => 'This swipe is too old to reverse.']);
    exit;
}

// Delete the swipe record
$deleted = $wpdb->delete(
    $swipe_table,
    [
        'swiper_user_id' => $current_user_id,
        'swiped_user_id' => $swiped_user_id
    ],
    ['%d', '%d']
);

if ($deleted === false) {
    wp_send_json_error(['message' => 'Failed to reverse swipe.']);
    exit;
}

// If it was a like that created a match, we should also remove the match flag
if ($swipe_record->swipe_type === 'like' && $swipe_record->is_match == 1) {
    // Remove the match flag from the other user's like record as well
    $wpdb->update(
        $swipe_table,
        ['is_match' => 0],
        [
            'swiper_user_id' => $swiped_user_id,
            'swiped_user_id' => $current_user_id,
            'swipe_type' => 'like'
        ],
        ['%d'],
        ['%d', '%d', '%s']
    );
}

// Give back a swipe if not premium (to compensate for the reversed swipe)
$remaining_swipes = null;
if (!function_exists('sud_is_user_premium') || !sud_is_user_premium($current_user_id)) {
    // Get current daily swipe count
    if (function_exists('sud_get_user_swipe_count_today') && function_exists('sud_get_daily_swipe_limit')) {
        $current_swipe_count = sud_get_user_swipe_count_today($current_user_id);
        $daily_limit = sud_get_daily_swipe_limit($current_user_id);
        
        // Since we reversed a swipe, effectively reduce the daily count by 1
        // We do this by decrementing their swipe count for today
        $swipe_counts_table = $wpdb->prefix . 'sud_daily_swipe_counts';
        $today = current_time('Y-m-d');
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$swipe_counts_table} 
             SET swipe_count = GREATEST(0, swipe_count - 1) 
             WHERE user_id = %d AND date = %s",
            $current_user_id,
            $today
        ));
        
        // Calculate remaining swipes
        $new_swipe_count = sud_get_user_swipe_count_today($current_user_id);
        $remaining_swipes = max(0, $daily_limit - $new_swipe_count);
    }
}

    wp_send_json_success([
        'message' => "Reversed your {$swipe_type} on {$swiped_user->display_name}!",
        'remaining_swipes' => $remaining_swipes
    ]);
    
} catch (Exception $e) {
    sud_handle_ajax_error($e);
}
?>