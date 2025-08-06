<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');

header('Content-Type: application/json');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Not authenticated.'], 401);
    exit;
}

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    wp_send_json_error(['message' => 'Invalid user ID specified.'], 400);
    exit;
}

$user_id = intval($_GET['user_id']);
$user_data = get_userdata($user_id);

if (!$user_data) {
    wp_send_json_error(['message' => 'User not found.'], 404);
    exit;
}

$profile_picture_id = get_user_meta($user_id, 'profile_picture', true);
$profile_pic = !empty($profile_picture_id) 
    ? wp_get_attachment_image_url($profile_picture_id, 'thumbnail') 
    : SUD_IMG_URL . '/default-profile.jpg';

$user_preview = [
    'id'           => $user_id,
    'name'         => esc_html($user_data->first_name ?: $user_data->display_name),
    'profile_pic'  => esc_url($profile_pic),
    'profile_url'  => esc_url(SUD_URL . '/pages/profile?id=' . $user_id)
];

wp_send_json_success($user_preview);