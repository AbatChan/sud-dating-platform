<?php
/**
 * Enhanced Ban and Warning System
 * Provides comprehensive ban/warning functionality with notifications
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/mailer.php');
require_once(__DIR__ . '/notification-functions.php');
require_once(__DIR__ . '/text-helpers.php');

/**
 * Convert number to ordinal (1st, 2nd, 3rd, etc.)
 */
function get_ordinal_number($number) {
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
        return $number . 'th';
    } else {
        return $number . $ends[$number % 10];
    }
}

/**
 * Check if user is banned and handle accordingly
 */
function sud_check_ban_status($user_id) {
    if (!$user_id || is_excluded_admin($user_id)) {
        return false;
    }
    
    $is_banned = get_user_meta($user_id, 'is_banned', true);
    $ban_reason = get_user_meta($user_id, 'ban_reason', true);
    $ban_date = get_user_meta($user_id, 'ban_date', true);
    
    if ($is_banned) {
        return [
            'banned' => true,
            'reason' => $ban_reason ?: 'Account banned for violation of terms of service',
            'date' => $ban_date ?: current_time('mysql'),
            'ban_id' => get_user_meta($user_id, 'ban_id', true)
        ];
    }
    
    return false;
}

/**
 * Get user warning status
 */
function sud_get_warning_status($user_id) {
    if (!$user_id) {
        return false;
    }
    
    $warning_count = get_user_meta($user_id, 'warning_count', true) ?: 0;
    $last_warning = get_user_meta($user_id, 'last_warning_date', true);
    $last_warning_reason = get_user_meta($user_id, 'last_warning_reason', true);
    
    if ($warning_count > 0) {
        return [
            'count' => $warning_count,
            'last_date' => $last_warning,
            'last_reason' => $last_warning_reason,
            'acknowledged' => get_user_meta($user_id, 'warning_acknowledged', true)
        ];
    }
    
    return false;
}

/**
 * Ban user with reason and notifications
 */
function sud_ban_user($user_id, $reason = '', $admin_id = null) {
    if (!$user_id || is_excluded_admin($user_id)) {
        return false;
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $ban_id = 'BAN_' . time() . '_' . $user_id;
    $ban_date = current_time('mysql');
    $default_reason = 'Your account has been suspended for violating our community guidelines';
    $ban_reason = !empty($reason) ? $reason : $default_reason;
    
    // Update user meta
    update_user_meta($user_id, 'is_banned', 1);
    update_user_meta($user_id, 'ban_reason', $ban_reason);
    update_user_meta($user_id, 'ban_date', $ban_date);
    update_user_meta($user_id, 'ban_id', $ban_id);
    update_user_meta($user_id, 'banned_by', $admin_id ?: get_current_user_id());
    
    // Add notification with full message
    if (function_exists('add_notification')) {
        $formatted_reason = sud_format_reason_text($ban_reason);
        add_notification($user_id, 'account_banned', "Your account has been banned {$formatted_reason}");
    }
    
    // Send email notification
    send_ban_email($user_id, $ban_reason, $ban_date);
    
    return [
        'success' => true,
        'ban_id' => $ban_id,
        'message' => 'User has been banned successfully'
    ];
}

/**
 * Unban user
 */
function sud_unban_user($user_id, $admin_id = null) {
    if (!$user_id) {
        return false;
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Remove ban meta
    delete_user_meta($user_id, 'is_banned');
    delete_user_meta($user_id, 'ban_reason');
    delete_user_meta($user_id, 'ban_date');
    delete_user_meta($user_id, 'ban_id');
    update_user_meta($user_id, 'unbanned_by', $admin_id ?: get_current_user_id());
    update_user_meta($user_id, 'unban_date', current_time('mysql'));
    
    // Add notification
    if (function_exists('add_notification')) {
        add_notification($user_id, 'account_unbanned', 'Your account has been reinstated. Welcome back!');
    }
    
    // Send email notification
    send_unban_email($user_id);
    
    
    return [
        'success' => true,
        'message' => 'User has been unbanned successfully'
    ];
}

/**
 * Issue warning to user
 */
function sud_warn_user($user_id, $reason = '', $admin_id = null) {
    if (!$user_id || is_excluded_admin($user_id)) {
        return false;
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $warning_count = get_user_meta($user_id, 'warning_count', true) ?: 0;
    $warning_count++;
    
    $warning_id = 'WARN_' . time() . '_' . $user_id;
    $warning_date = current_time('mysql');
    $default_reason = 'Please review our community guidelines to avoid further action';
    $warning_reason = !empty($reason) ? $reason : $default_reason;
    
    // Update user meta
    update_user_meta($user_id, 'warning_count', $warning_count);
    update_user_meta($user_id, 'last_warning_date', $warning_date);
    update_user_meta($user_id, 'last_warning_reason', $warning_reason);
    update_user_meta($user_id, 'last_warning_id', $warning_id);
    update_user_meta($user_id, 'warned_by', $admin_id ?: get_current_user_id());
    delete_user_meta($user_id, 'warning_acknowledged'); // Reset acknowledgment
    
    // Add notification with full message
    if (function_exists('add_notification')) {
        // Convert number to ordinal (1st, 2nd, 3rd, etc.)
        $ordinal = get_ordinal_number($warning_count);
        $formatted_reason = sud_format_reason_text($warning_reason);
        add_notification($user_id, 'account_warning', "You have received your {$ordinal} warning {$formatted_reason}");
    }
    
    // Send email notification
    send_warning_email($user_id, $warning_reason, $warning_count);
    
    return [
        'success' => true,
        'warning_id' => $warning_id,
        'warning_count' => $warning_count,
        'message' => "Warning #{$warning_count} issued successfully"
    ];
}

/**
 * Acknowledge warning (user confirms they've read it)
 */
function sud_acknowledge_warning($user_id) {
    if (!$user_id) {
        return false;
    }
    
    update_user_meta($user_id, 'warning_acknowledged', current_time('mysql'));
    return true;
}

/**
 * Display ban notice (call this on protected pages)
 */
function sud_display_ban_notice($user_id) {
    $ban_status = sud_check_ban_status($user_id);
    
    if ($ban_status) {
        $ban_date = date('F j, Y', strtotime($ban_status['date']));
        
        echo '<div class="sud-ban-notice">';
        echo '<div class="sud-ban-content">';
        echo '<h2>🚫 Account Suspended</h2>';
        echo '<p><strong>Your account has been suspended on ' . esc_html($ban_date) . '</strong></p>';
        echo '<p><strong>Reason:</strong> ' . sud_format_display_text($ban_status['reason']) . '</p>';
        echo '<p>If you believe this is an error, please contact our support team.</p>';
        echo '<div class="sud-ban-actions">';
        echo '<a href="mailto:' . 'support@swipeupdaddy.com' . '" class="sud-button sud-button-primary">Contact Support</a>';
        echo '<a href="' . wp_logout_url(home_url()) . '" class="sud-button sud-button-secondary">Logout</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Add body class to lock scroll
        echo '<script>document.body.classList.add("sud-banned");</script>';
        
        return true;
    }
    
    return false;
}

/**
 * Display warning notice (dismissible)
 */
function sud_display_warning_notice($user_id) {
    $warning_status = sud_get_warning_status($user_id);
    
    if ($warning_status && !$warning_status['acknowledged']) {
        $warning_date = date('F j, Y', strtotime($warning_status['last_date']));
        $ordinal = get_ordinal_number($warning_status['count']);
        
        echo '<div id="sud-warning-notice" class="sud-warning-notice">';
        echo '<div class="sud-warning-content">';
        echo '<h3>⚠️ Your ' . $ordinal . ' Warning</h3>';
        echo '<p><strong>Date:</strong> ' . esc_html($warning_date) . '</p>';
        echo '<p><strong>Reason:</strong> ' . sud_format_display_text($warning_status['last_reason']) . '</p>';
        echo '<p>Please review our community guidelines to avoid further action on your account.</p>';
        echo '<div class="sud-warning-actions">';
        echo '<button id="acknowledge-warning" class="sud-button sud-button-primary">I Understand</button>';
        echo '<a href="mailto:' . 'support@swipeupdaddy.com' . '" class="sud-button sud-button-secondary">Contact Support</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("acknowledge-warning").addEventListener("click", function() {
                fetch("' . SUD_AJAX_URL . '/acknowledge-warning.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        user_id: ' . $user_id . ',
                        nonce: "' . wp_create_nonce('acknowledge_warning') . '"
                    })
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById("sud-warning-notice").style.display = "none";
                    }
                });
            });
        });
        </script>';
        
        return true;
    }
    
    return false;
}

/**
 * Send ban email notification
 */
function send_ban_email($user_id, $reason, $ban_date) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $subject = "Account Suspended - " . get_bloginfo('name');
    $email_heading = "Account Suspended";
    
    $ban_date_formatted = date('F j, Y \\a\\t g:i A', strtotime($ban_date));
    
    $clean_reason = sud_clean_text($reason);
    $body_html = "<p>Dear " . esc_html($user->display_name) . ",</p>";
    $body_html .= "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    $body_html .= "<h3 style='margin-top: 0; color: #721c24;'>🚫 Account Suspended</h3>";
    $body_html .= "<p><strong>Your account has been suspended on:</strong> " . esc_html($ban_date_formatted) . "</p>";
    $body_html .= "<p><strong>Reason:</strong> " . esc_html($clean_reason) . "</p>";
    $body_html .= "</div>";
    $body_html .= "<p>This action was taken to maintain a safe and positive environment for all users.</p>";
    $body_html .= "<p>If you believe this suspension was issued in error, please contact our support team for review.</p>";
    
    $button_details = [
        ['text' => 'Contact Support', 'link' => 'mailto:' . 'support@swipeupdaddy.com']
    ];
    
    if (!function_exists('sud_get_styled_email_html')) {
        return false;
    }
    
    $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html, $button_details);
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_name = get_bloginfo('name');
    $from_email = 'support@swipeupdaddy.com';
    $headers[] = "From: {$from_name} <{$from_email}>";
    
    return wp_mail($user->user_email, $subject, $message_html, $headers);
}

/**
 * Send unban email notification
 */
function send_unban_email($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $subject = "Account Reinstated - " . get_bloginfo('name');
    $email_heading = "Welcome Back!";
    
    $body_html = "<p>Dear " . esc_html($user->display_name) . ",</p>";
    $body_html .= "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    $body_html .= "<h3 style='margin-top: 0; color: #155724;'>✅ Account Reinstated</h3>";
    $body_html .= "<p>Your account has been reviewed and reinstated. You now have full access to all features.</p>";
    $body_html .= "</div>";
    $body_html .= "<p>We appreciate your understanding and look forward to your continued participation in our community.</p>";
    $body_html .= "<p>Please remember to follow our community guidelines to ensure a positive experience for everyone.</p>";
    
    $button_details = [
        ['text' => 'Access Your Account', 'link' => SUD_URL . '/pages/dashboard']
    ];
    
    $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html, $button_details);
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_name = get_bloginfo('name');
    $from_email = 'support@swipeupdaddy.com';
    $headers[] = "From: {$from_name} <{$from_email}>";
    
    return wp_mail($user->user_email, $subject, $message_html, $headers);
}

/**
 * Send warning email notification
 */
function send_warning_email($user_id, $reason, $warning_count) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $ordinal = get_ordinal_number($warning_count);
    $subject = "Account Warning - Your {$ordinal} Warning - " . get_bloginfo('name');
    $email_heading = "Account Warning";
    
    $clean_reason = sud_clean_text($reason);
    $body_html = "<p>Dear " . esc_html($user->display_name) . ",</p>";
    $body_html .= "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    $body_html .= "<h3 style='margin-top: 0; color: #856404;'>⚠️ Your {$ordinal} Warning</h3>";
    $body_html .= "<p><strong>Reason:</strong> " . esc_html($clean_reason) . "</p>";
    $body_html .= "</div>";
    $body_html .= "<p>This warning has been issued to help maintain a safe and positive environment for all users.</p>";
    $body_html .= "<p>Please review our community guidelines and adjust your behavior accordingly.</p>";
    $body_html .= "<p><strong>Note:</strong> Multiple warnings may result in account suspension.</p>";
    
    $button_details = [
        ['text' => 'Review Guidelines', 'link' => home_url('/community-guidelines')],
        ['text' => 'Contact Support', 'link' => 'mailto:' . 'support@swipeupdaddy.com']
    ];
    
    if (!function_exists('sud_get_styled_email_html')) {
        return false;
    }
    
    $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html, $button_details);
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_name = get_bloginfo('name');
    $from_email = 'support@swipeupdaddy.com';
    $headers[] = "From: {$from_name} <{$from_email}>";
    
    return wp_mail($user->user_email, $subject, $message_html, $headers);
}

/**
 * Get ban/warning statistics for admin dashboard
 */
function sud_get_moderation_stats() {
    global $wpdb;
    
    $banned_count = $wpdb->get_var("
        SELECT COUNT(DISTINCT user_id) 
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'is_banned' AND meta_value = '1'
    ");
    
    $warned_count = $wpdb->get_var("
        SELECT COUNT(DISTINCT user_id) 
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'warning_count' AND CAST(meta_value AS UNSIGNED) > 0
    ");
    
    $recent_bans = $wpdb->get_results("
        SELECT u.user_login, u.display_name, um1.meta_value as ban_date, um2.meta_value as ban_reason
        FROM {$wpdb->users} u
        JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'ban_date'
        JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'ban_reason'
        JOIN {$wpdb->usermeta} um3 ON u.ID = um3.user_id AND um3.meta_key = 'is_banned' AND um3.meta_value = '1'
        ORDER BY um1.meta_value DESC
        LIMIT 5
    ");
    
    return [
        'banned_count' => $banned_count ?: 0,
        'warned_count' => $warned_count ?: 0,
        'recent_bans' => $recent_bans ?: []
    ];
}
?>