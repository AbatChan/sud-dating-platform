<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');

header('Content-Type: application/json');

try {
    // Use centralized security verification
    $moderator_id = sud_verify_ajax([
        'methods' => ['GET'],
        'require_auth' => true,
        'require_nonce' => false,
        'rate_limit' => ['requests' => 20, 'window' => 60, 'action' => 'get_moderator_gifts']
    ]);
    
    // Check if user has moderator permissions
    if (!current_user_can('sud_send_gifts') && !current_user_can('manage_options')) {
        throw new Exception('Access denied: You do not have permission to send gifts.');
    }
    
    global $wpdb;
    $gifts_table = $wpdb->prefix . 'sud_gifts';
    $gifts_img_url = SUD_IMG_URL . '/gifts/';
    
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $gifts_table)) != $gifts_table) {
        throw new Exception("Gifts data store is unavailable.");
    }
    
    $gifts_data = $wpdb->get_results(
        "SELECT id, name, icon, image_filename, cost
         FROM {$gifts_table}
         WHERE is_active = 1
         ORDER BY sort_order ASC, cost ASC, name ASC
         LIMIT 20"
    );
    
    if ($gifts_data === null) {
        error_log("SUD Get Moderator Gifts Error: WPDB query failed. Error: " . $wpdb->last_error);
        throw new Exception("Could not retrieve gifts from the database.");
    }
    
    $gifts_list = [];
    if (!empty($gifts_data)) {
        foreach ($gifts_data as $gift) {
            $image_url = null;
            if (!empty($gift->image_filename)) {
                $image_url = $gifts_img_url . sanitize_file_name($gift->image_filename);
            }
            
            $gifts_list[] = [
                'id' => (int)$gift->id,
                'name' => esc_html($gift->name),
                'icon' => !empty($gift->icon) ? esc_html($gift->icon) : '🎁',
                'image_url' => $image_url,
                'cost' => (int)$gift->cost,
                'cost_formatted' => number_format((int)$gift->cost) . ' coins'
            ];
        }
    }
    
    
    wp_send_json_success(['gifts' => $gifts_list]);
    
} catch (Exception $e) {
    sud_handle_ajax_error($e);
}
?>