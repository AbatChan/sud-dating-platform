<?php

$wp_load_path = dirname(__FILE__, 3) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server configuration error: Cannot load WordPress.']);
    error_log("AJAX update-location Error: Failed to load wp-load.php from path: " . $wp_load_path);
    exit;
}

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . site_url());
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type'); 
    exit(0);
}

try {
    // Use centralized security verification
    $user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_ajax_action',
        'rate_limit' => ['requests' => 20, 'window' => 60, 'action' => 'update_location']
    ]);

    $latitude_param = trim($_POST['latitude'] ?? '');
    $longitude_param = trim($_POST['longitude'] ?? '');
    $accuracy_param = trim($_POST['accuracy'] ?? 'unknown');

    // Non-admins can only touch their own row (verify already gave us $user_id)
    if (!current_user_can('edit_users')) {
        // Nothing extra needed - $user_id is already the current user's ID
    }

    // Validation
    $latitude = filter_var($latitude_param, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($longitude_param, FILTER_VALIDATE_FLOAT);

    if ($latitude === false || $longitude === false) {
        throw new Exception('Invalid or missing coordinates.', 400);
    }
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new Exception('Coordinates are outside the valid geographical range.', 400);
    }

    $accuracy = preg_replace('/[^a-zA-Z0-9 \-\(\)\.\:]/', '', $accuracy_param);

    if (!function_exists('update_user_location')) {
        throw new Exception('Server error: Location update functionality is unavailable.', 500);
    }

    // DB Write
    $result = update_user_location($user_id, $latitude, $longitude, $accuracy);
    if (!$result) {
        throw new Exception('An error occurred while updating the location in the database.');
    }

    // Success response
    wp_send_json_success([
        'message' => 'Location updated successfully.',
        'user_id' => $user_id,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'accuracy_received' => $accuracy,
        'city' => get_user_meta($user_id, 'city', true) ?: '',
        'region' => get_user_meta($user_id, 'region', true) ?: '',
        'country' => get_user_meta($user_id, 'country', true) ?: '',
        'formatted_location' => get_user_meta($user_id, 'location_string', true) ?: ''
    ]);

} catch (Exception $e) {
    sud_handle_ajax_error($e);
}