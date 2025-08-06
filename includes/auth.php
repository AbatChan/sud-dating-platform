<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/mailer.php');

function is_email_verified($user_id) {
    return (bool)get_user_meta($user_id, 'email_verified', true);
}

function mark_email_verified($user_id) {
    return update_user_meta($user_id, 'email_verified', true);
}

function generate_verification_code($user_id) {
    $code = rand(100000, 999999);
    update_user_meta($user_id, 'verification_code', $code);
    update_user_meta($user_id, 'verification_code_time', time());
    return $code;
}

function verify_code($user_id, $code, $expiry_time = 3600) {
    $stored_code = get_user_meta($user_id, 'verification_code', true);
    $stored_time = get_user_meta($user_id, 'verification_code_time', true);
    if ($stored_code == $code && $stored_time && (time() - $stored_time < $expiry_time)) {
        return true;
    }
    
    return false;
}

function register_user($email, $password, $user_data = []) {
    // Check for multiple account attempts
    $multiple_account_check = check_multiple_account_prevention($email);
    if ($multiple_account_check !== true) {
        return new WP_Error('multiple_accounts', $multiple_account_check);
    }

    $username = strtolower(substr($email, 0, strpos($email, '@')));
    $username = preg_replace('/[^a-z0-9]/', '', $username);
    $username .= rand(100, 999); 

    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    $user = new WP_User($user_id);
    $user->set_role('subscriber');

    // Store registration metadata for multiple account prevention
    store_registration_metadata($user_id);

    if (!empty($user_data)) {
        foreach ($user_data as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }
    }

    $code = generate_verification_code($user_id);
    $user_email = $user->user_email;

    send_verification_code($user_email, $code);

    return $user_id;
}

function is_password_recently_used($user_id, $new_password) {
    $password_history = get_user_meta($user_id, 'password_history', true);

    if (empty($password_history)) {
        $password_history = array();
    }

    foreach ($password_history as $hash) {
        if (wp_check_password($new_password, $hash, $user_id)) {
            return true; 
        }
    }
    
    return false;
}

function update_password_history($user_id, $new_password) {
    $password_history = get_user_meta($user_id, 'password_history', true);

    if (empty($password_history)) {
        $password_history = array();
    }

    $current_hash = wp_get_current_user()->user_pass;
    if (!empty($current_hash)) {
        array_unshift($password_history, $current_hash);
    }

    $password_history = array_slice($password_history, 0, 3);
    update_user_meta($user_id, 'password_history', $password_history);
}

function create_password_reset_link($email) {
    $user = get_user_by('email', $email);

    if (!$user) {
        return new WP_Error('invalid_email', 'No account found with that email address');
    }

    $key = get_password_reset_key($user);
    if (is_wp_error($key)) {
        return $key;
    }

    $reset_link = SUD_URL . "/auth/reset-password?key=$key&login=" . rawurlencode($user->user_login);
    $email_sent = send_password_reset($email, $reset_link);

    if (!$email_sent) {
        return new WP_Error('email_failed', 'Error sending password reset email');
    }

    return [
        'key' => $key,
        'user' => $user,
        'link' => $reset_link
    ];
}

function is_rate_limited($type, $identifier, $limit = 5, $time_period = 3600) {
    $transient_name = $type . '_' . md5($identifier);
    $count = get_transient($transient_name) ?: 0;
    if ($count >= $limit) {
        return true; 
    }
    set_transient($transient_name, $count + 1, $time_period);
    return false; 
}

/**
 * Prevent multiple account creation from same device/IP/patterns
 */
function check_multiple_account_prevention($email) {
    global $wpdb;
    
    // Get user's IP and user agent for fingerprinting
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $fingerprint = md5($user_ip . $user_agent);
    
    // Email domain check temporarily disabled to allow user growth
    // Can be re-enabled later if abuse is detected
    
    // Check 2: IP-based restriction (max 2 accounts per IP per day)
    $accounts_from_ip = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} um
         JOIN {$wpdb->users} u ON um.user_id = u.ID
         WHERE um.meta_key = 'registration_ip' 
         AND um.meta_value = %s
         AND u.user_registered > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        $user_ip
    ));
    
    if ($accounts_from_ip >= 2) {
        error_log("SUD: Multiple account attempt blocked - IP: $user_ip, Accounts: $accounts_from_ip");
        return 'Maximum accounts reached from this location. Please try again tomorrow.';
    }
    
    // Check 3: Device fingerprint restriction (max 2 accounts per device per week)
    $accounts_from_device = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} um
         JOIN {$wpdb->users} u ON um.user_id = u.ID
         WHERE um.meta_key = 'device_fingerprint' 
         AND um.meta_value = %s
         AND u.user_registered > DATE_SUB(NOW(), INTERVAL 7 DAY)",
        $fingerprint
    ));
    
    if ($accounts_from_device >= 2) {
        error_log("SUD: Multiple account attempt blocked - Device fingerprint: $fingerprint, Accounts: $accounts_from_device");
        return 'Maximum accounts reached from this device. Please try again later.';
    }
    
    return true; // Allow registration
}

/**
 * Store user registration metadata for multiple account tracking
 */
function store_registration_metadata($user_id) {
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $fingerprint = md5($user_ip . $user_agent);
    
    update_user_meta($user_id, 'registration_ip', $user_ip);
    update_user_meta($user_id, 'device_fingerprint', $fingerprint);
    update_user_meta($user_id, 'registration_timestamp', time());
    update_user_meta($user_id, 'registration_user_agent', $user_agent);
}