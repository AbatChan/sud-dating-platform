<?php

require_once(dirname(__FILE__, 3) . '/wp-load.php'); 
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/database-setup.php');

header('Content-Type: application/json');

global $wpdb;
$gifts_table = $wpdb->prefix . 'sud_gifts';
$default_icon = '🎁';
$gifts_img_url = SUD_IMG_URL . '/gifts/'; 

try {
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $gifts_table)) != $gifts_table) {
        throw new Exception("Gifts data store is unavailable.");
    }

    $gifts_data = $wpdb->get_results(
        "SELECT id, name, icon, image_filename, cost
         FROM {$gifts_table}
         WHERE is_active = 1
         ORDER BY sort_order ASC, name ASC"
    );

    if ($gifts_data === null) {
        error_log("SUD Get Gifts Error: WPDB query failed. Error: " . $wpdb->last_error);
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
                'icon' => !empty($gift->icon) ? esc_html($gift->icon) : null, 
                'image_url' => $image_url, 
                'cost' => (int)$gift->cost,
            ];
        }
    }

    wp_send_json_success(['gifts' => $gifts_list]);

} catch (Exception $e) {
    error_log("SUD Get Gifts Exception: " . $e->getMessage());
    wp_send_json_error(['message' => 'Could not load available gifts at this time.'], 500);
}

?>