<?php
/**
 * SUD Centralized AJAX Security System
 * 
 * This file provides centralized security, validation, and rate limiting
 * for all AJAX endpoints. Use this instead of duplicating security checks.
 */

class SUD_AJAX_Security {
    
    private static $rate_limits = [];
    
    /**
     * Master security verification for all AJAX endpoints
     * 
     * @param array $config Configuration options
     * @return int Current user ID
     */
    public static function verify_request($config = []) {
        $defaults = [
            'methods' => ['POST'],
            'require_auth' => true,
            'require_nonce' => true,
            'nonce_action' => 'sud_ajax_action',
            'nonce_field' => null, // Custom nonce field name
            'input_data' => null, // For JSON input with custom nonce field
            'rate_limit' => null, // ['requests' => 10, 'window' => 60]
            'check_blocked' => false,
            'allow_self_action' => true
        ];
        
        $config = array_merge($defaults, $config);
        
        // Set JSON header
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        // Method verification
        if (!in_array($_SERVER['REQUEST_METHOD'], $config['methods'])) {
            self::send_error('Invalid request method', 405);
        }
        
        // Authentication check
        if ($config['require_auth'] && !is_user_logged_in()) {
            self::send_error('Authentication required', 401);
        }
        
        $current_user_id = $config['require_auth'] ? get_current_user_id() : 0;
        
        // CSRF protection
        if ($config['require_nonce']) {
            self::verify_nonce($config['nonce_action'], $config['nonce_field'], $config['input_data']);
        }
        
        // Rate limiting
        if ($config['rate_limit'] && $current_user_id) {
            self::check_rate_limit($current_user_id, $config['rate_limit']);
        }
        
        return $current_user_id;
    }
    
    /**
     * Centralized nonce verification
     */
    private static function verify_nonce($action, $custom_field = null, $input_data = null) {
        $nonce = null;
        
        // Handle custom nonce field for JSON input
        if ($custom_field && $input_data && is_array($input_data)) {
            $nonce = $input_data[$custom_field] ?? null;
        }
        // Handle custom nonce field for POST data
        elseif ($custom_field) {
            $nonce = $_POST[$custom_field] ?? null;
        }
        // Default behavior - check multiple possible field names for backward compatibility
        else {
            $nonce = $_POST['nonce'] ?? $_POST['_ajax_nonce'] ?? $_POST['sud_nonce'] ?? $_POST['sud_settings_nonce'] ?? $_POST['sud_withdrawal_nonce'] ?? null;
        }
        
        if (!$nonce || !wp_verify_nonce($nonce, $action)) {
            self::send_error('Security check failed', 403);
        }
    }
    
    /**
     * Centralized rate limiting
     */
    private static function check_rate_limit($user_id, $config) {
        $requests = $config['requests'] ?? 10;
        $window = $config['window'] ?? 60;
        $action = $config['action'] ?? debug_backtrace()[2]['file'];
        
        $cache_key = "sud_rate_limit_{$user_id}_" . md5($action);
        $current_time = time();
        
        // Get current rate limit data
        $rate_data = get_transient($cache_key);
        if (!$rate_data) {
            $rate_data = ['count' => 0, 'window_start' => $current_time];
        }
        
        // Reset window if expired
        if ($current_time - $rate_data['window_start'] >= $window) {
            $rate_data = ['count' => 0, 'window_start' => $current_time];
        }
        
        // Check limit
        if ($rate_data['count'] >= $requests) {
            $wait_time = $window - ($current_time - $rate_data['window_start']);
            self::send_error("Too many requests. Please wait {$wait_time} seconds.", 429);
        }
        
        // Increment counter
        $rate_data['count']++;
        set_transient($cache_key, $rate_data, $window);
    }
    
    /**
     * Centralized error response
     */
    private static function send_error($message, $code = 400) {
        if (!headers_sent()) {
            http_response_code($code);
        }
        wp_send_json_error(['message' => $message], $code);
        exit;
    }
    
    /**
     * Validate and sanitize user ID
     */
    public static function validate_user_id($user_id, $allow_self = false) {
        $current_user_id = get_current_user_id();
        $user_id = intval($user_id);
        
        if ($user_id <= 0) {
            throw new InvalidArgumentException('Invalid user ID');
        }
        
        if (!$allow_self && $user_id === $current_user_id) {
            throw new InvalidArgumentException('Cannot perform this action on yourself');
        }
        
        // Verify user exists
        if (!get_userdata($user_id)) {
            throw new InvalidArgumentException('User not found');
        }
        
        return $user_id;
    }
    
    /**
     * Validate and sanitize message text
     */
    public static function validate_message($message, $max_length = 5000) {
        $message = trim($message);
        
        if (empty($message)) {
            throw new InvalidArgumentException('Message cannot be empty');
        }
        
        if (mb_strlen($message) > $max_length) {
            throw new InvalidArgumentException("Message is too long (max {$max_length} characters)");
        }
        
        // Remove dangerous HTML but preserve normal characters
        $message = wp_strip_all_tags($message);
        $message = wp_kses($message, array());
        
        // Final check after sanitization
        if (empty(trim($message))) {
            throw new InvalidArgumentException('Message cannot be empty after processing');
        }
        
        return nl2br($message);
    }
    
    /**
     * Validate blocked user status
     */
    public static function check_user_blocked($user1_id, $user2_id) {
        if (function_exists('is_user_blocked')) {
            if (is_user_blocked($user1_id, $user2_id) || is_user_blocked($user2_id, $user1_id)) {
                throw new Exception('Cannot perform action due to block status');
            }
        }
    }
    
    /**
     * Validate user matching status
     */
    public static function check_users_matched($user1_id, $user2_id) {
        if (function_exists('sud_are_users_matched') && !sud_are_users_matched($user1_id, $user2_id)) {
            throw new Exception('Users must be matched to perform this action');
        }
    }
    
    /**
     * Validate file upload
     */
    public static function validate_file_upload($file_key, $allowed_types = ['jpg', 'jpeg', 'png'], $max_size = 5242880) {
        if (empty($_FILES[$file_key])) {
            throw new InvalidArgumentException('No file uploaded');
        }
        
        $file = $_FILES[$file_key];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $max_mb = round($max_size / 1024 / 1024, 1);
            throw new InvalidArgumentException("File too large. Maximum size: {$max_mb}MB");
        }
        
        // Check file type
        $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_type, $allowed_types)) {
            $allowed_str = implode(', ', $allowed_types);
            throw new InvalidArgumentException("Invalid file type. Allowed: {$allowed_str}");
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg', 
            'png' => 'image/png'
        ];
        
        if (!in_array($mime_type, array_values($allowed_mimes))) {
            throw new InvalidArgumentException('Invalid file format');
        }
        
        return $file;
    }
    
    /**
     * Centralized success response
     */
    public static function send_success($data = [], $message = 'Success') {
        $response = ['message' => $message];
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        wp_send_json_success($response);
        exit;
    }
    
    /**
     * Handle exceptions and send appropriate error responses
     */
    public static function handle_exception($e) {
        $message = $e->getMessage();
        $code = 500;
        
        // Special handling for upgrade required - return as success with upgrade flag
        if (strpos($message, 'upgrade required:') === 0) {
            wp_send_json_success(['upgrade_required' => true, 'message' => $message]);
            exit;
        }
        
        if ($e instanceof InvalidArgumentException) {
            $code = 400;
        } elseif (strpos($message, 'Authentication') !== false) {
            $code = 401;
        } elseif (strpos($message, 'Security') !== false || strpos($message, 'block') !== false) {
            $code = 403;
        } elseif (strpos($message, 'not found') !== false) {
            $code = 404;
        } elseif (strpos($message, 'many requests') !== false) {
            $code = 429;
        }
        
        self::send_error($message, $code);
    }
}

/**
 * Convenience function for common AJAX security verification
 */
function sud_verify_ajax($config = []) {
    return SUD_AJAX_Security::verify_request($config);
}

/**
 * Convenience function for handling AJAX exceptions
 */
function sud_handle_ajax_error($e) {
    SUD_AJAX_Security::handle_exception($e);
}
?>