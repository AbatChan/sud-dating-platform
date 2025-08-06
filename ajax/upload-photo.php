<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');

try {
    // Use centralized security verification
    $user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_ajax_action',
        'rate_limit' => ['requests' => 5, 'window' => 300, 'action' => 'upload_photo'] // 5 uploads per 5 minutes
    ]);

    // Validate file upload using centralized validation
    $file = SUD_AJAX_Security::validate_file_upload('photo', ['jpg', 'jpeg', 'png'], 5242880); // 5MB limit

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $attachment_id = media_handle_upload('photo', 0);

    if (is_wp_error($attachment_id)) {
        throw new Exception($attachment_id->get_error_message());
    }

    $user_photos = get_user_meta($user_id, 'user_photos', true);
    if (!is_array($user_photos)) {
        $user_photos = [];
    }

    $user_photos[] = $attachment_id;

    update_user_meta($user_id, 'user_photos', $user_photos);

    if (count($user_photos) === 1 || empty(get_user_meta($user_id, 'profile_picture', true))) {
        update_user_meta($user_id, 'profile_picture', $attachment_id);
    }

    $image_url = wp_get_attachment_image_url($attachment_id, 'medium_large');

    wp_send_json_success([
        'attachment_id' => $attachment_id,
        'image_url' => $image_url
    ]);
    
} catch (Exception $e) {
    sud_handle_ajax_error($e);
}