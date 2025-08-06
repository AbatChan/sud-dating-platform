<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');

header('Content-Type: application/json');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'User not logged in.']);
    exit;
}

$current_user_id = get_current_user_id();
$count_requested = isset($_GET['count']) ? intval($_GET['count']) : 10;
$count_requested = max(1, min($count_requested, 20)); 

$result = sud_get_swipe_candidates($current_user_id, $count_requested);

if (empty($result) || !is_array($result)) {
    $result = ['candidates' => [], 'has_more' => false];
}

$candidates_found = $result['candidates'] ?? [];
$has_more_candidates = $result['has_more'] ?? false;

// Ensure boolean integrity before JSON encoding
foreach ($candidates_found as $index => $candidate) {
    if (isset($candidate['has_active_boost'])) {
        $candidates_found[$index]['has_active_boost'] = (bool) $candidate['has_active_boost'];
    }
}

$json_data = [
    'candidates' => $candidates_found,
    'has_more' => $has_more_candidates 
];

wp_send_json_success($json_data);