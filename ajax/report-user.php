<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');
header('Content-Type: application/json');

try {
    // Use centralized security verification
    $reporter_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_ajax_action',
        'rate_limit' => ['requests' => 5, 'window' => 300, 'action' => 'report_user'] // 5 reports per 5 minutes
    ]);
    $reported_user_id = SUD_AJAX_Security::validate_user_id($_POST['user_id'] ?? 0, false);
    $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
    $details = isset($_POST['details']) ? sanitize_textarea_field($_POST['details']) : '';

    if (empty($reason)) {
        throw new Exception('You must select a reason for reporting');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_reports';

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            reporter_id bigint(20) NOT NULL,
            reported_user_id bigint(20) NOT NULL,
            reason varchar(50) NOT NULL,
            details text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            admin_notes text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY reporter_id (reporter_id),
            KEY reported_user_id (reported_user_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    $result = $wpdb->insert(
        $table_name,
        [
            'reporter_id' => $reporter_id,
            'reported_user_id' => $reported_user_id,
            'reason' => $reason,
            'details' => $details
        ]
    );

    if ($result === false) {
        throw new Exception('Database error while submitting report');
    }

    $admin_email = get_option('admin_email');
    $reporter = get_userdata($reporter_id);
    $reported = get_userdata($reported_user_id);

    $subject = 'New User Report: ' . $reported->display_name . ' (' . $reason . ')';
    $message = "A new user report has been submitted:\n\n";
    $message .= "Reporter: " . $reporter->display_name . " (ID: " . $reporter_id . ")\n";
    $message .= "Reported User: " . $reported->display_name . " (ID: " . $reported_user_id . ")\n";
    $message .= "Reason: " . $reason . "\n\n";

    if (!empty($details)) {
        $message .= "Additional Details:\n" . $details . "\n\n";
    }

    $message .= "View in admin panel: " . admin_url('admin.php?page=sud-user-reports');
    wp_mail($admin_email, $subject, $message);
    wp_send_json_success(['message' => 'Report submitted successfully']);
    
} catch (Exception $e) {
    sud_handle_ajax_error($e);
}