<?php

declare(strict_types=1);

defined( 'ABSPATH' ) or die( 'Cannot access this file directly.' );

/**
 * Centralized AJAX Bootstrap Helper
 * Eliminates code duplication across all AJAX endpoints
 * Provides consistent error handling, headers, and security verification
 */

if (!function_exists('sud_ajax_bootstrap')) {
/**
 * Bootstrap function for all AJAX endpoints
 * Handles WordPress loading, headers, CORS, authentication, nonce verification, and rate limiting
 * 
 * @param array $config Configuration options
 * @return int User ID if authenticated, throws exception on failure
 * @throws Exception On any validation failure
 */
function sud_ajax_bootstrap(array $config = []): int {
    // Default configuration
    $defaults = [
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_ajax_action',
        'nonce_field' => 'nonce',
        'input_data' => null, // For JSON input
        'rate_limit' => null,
        'cors_origin' => null, // Auto-detect from WordPress
        'allow_json_input' => false
    ];
    
    $config = array_merge($defaults, $config);
    
    // 1. Load WordPress if not already loaded
    if (!function_exists('site_url')) {
        $wp_load_path = dirname(__FILE__, 3) . '/wp-load.php';
        if (file_exists($wp_load_path)) {
            require_once($wp_load_path);
        } else {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Server configuration error: Cannot load WordPress.']);
            error_log("AJAX Bootstrap Error: Failed to load wp-load.php from path: " . $wp_load_path);
            exit;
        }
    }
    
    // 2. Load required includes
    require_once(dirname(__FILE__) . '/config.php');
    require_once(dirname(__FILE__) . '/ajax-security.php');
    
    // 3. Set consistent headers
    header('Content-Type: application/json');
    
    // Set CORS headers
    $cors_origin = $config['cors_origin'] ?? site_url();
    header('Access-Control-Allow-Origin: ' . $cors_origin);
    header('Access-Control-Allow-Credentials: true');
    
    // 4. Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: ' . implode(', ', $config['methods']) . ', OPTIONS');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Authorization'); 
        exit(0);
    }
    
    // 5. Get input data (support both form data and JSON)
    $input_data = $config['input_data'];
    if ($config['allow_json_input'] && !$input_data) {
        $json_input = file_get_contents('php://input');
        if ($json_input) {
            $input_data = json_decode($json_input, true);
        }
    }
    
    // 6. Use centralized security verification
    try {
        $user_id = sud_verify_ajax($config);
        return $user_id;
    } catch (Exception $e) {
        // Let sud_verify_ajax handle its own error responses
        throw $e;
    }
}
}

if (!function_exists('sud_ajax_success')) {
/**
 * Standardized success response
 * Ensures consistent JSON structure and proper exit
 * 
 * @param array $data Response data
 * @param int $status_code HTTP status code (optional)
 */
function sud_ajax_success(array $data = [], int $status_code = 200): void {
    if ($status_code !== 200) {
        http_response_code($status_code);
    }
    wp_send_json_success($data);
    exit; // Explicit exit to prevent accidental output
}
}

if (!function_exists('sud_ajax_error')) {
/**
 * Standardized error response  
 * Ensures consistent JSON structure and proper exit
 * 
 * @param string $message Error message
 * @param int $status_code HTTP status code
 * @param array $extra_data Additional error data (optional)
 */
function sud_ajax_error(string $message, int $status_code = 500, array $extra_data = []): void {
    $error_data = array_merge(['message' => $message], $extra_data);
    wp_send_json_error($error_data, $status_code);
    exit; // Explicit exit to prevent accidental output
}
}

if (!function_exists('sud_validate_input')) {
/**
 * Validate and sanitize common input types
 * Centralized input validation to ensure consistency
 * 
 * @param mixed $value Input value
 * @param string $type Validation type
 * @param array $options Additional validation options
 * @return mixed Sanitized value
 * @throws Exception On validation failure
 */
function sud_validate_input($value, string $type, array $options = []) {
    switch ($type) {
        case 'user_id':
            $id = absint($value);
            if ($id <= 0) {
                throw new Exception('Invalid user ID provided');
            }
            return $id;
            
        case 'text':
            $max_length = $options['max_length'] ?? 1000;
            $sanitized = sanitize_text_field($value);
            if (strlen($sanitized) > $max_length) {
                throw new Exception("Text exceeds maximum length of {$max_length} characters");
            }
            return $sanitized;
            
        case 'textarea':
            $max_length = $options['max_length'] ?? 5000;
            $sanitized = sanitize_textarea_field($value);
            if (strlen($sanitized) > $max_length) {
                throw new Exception("Text exceeds maximum length of {$max_length} characters");
            }
            return $sanitized;
            
        case 'email':
            $email = sanitize_email($value);
            if (!is_email($email)) {
                throw new Exception('Invalid email address format');
            }
            return $email;
            
        case 'float':
            $float_value = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($float_value === false) {
                throw new Exception('Invalid numeric value provided');
            }
            if (isset($options['min']) && $float_value < $options['min']) {
                throw new Exception("Value must be at least {$options['min']}");
            }
            if (isset($options['max']) && $float_value > $options['max']) {
                throw new Exception("Value must not exceed {$options['max']}");
            }
            return $float_value;
            
        case 'int':
            $int_value = filter_var($value, FILTER_VALIDATE_INT);
            if ($int_value === false) {
                throw new Exception('Invalid integer value provided');
            }
            if (isset($options['min']) && $int_value < $options['min']) {
                throw new Exception("Value must be at least {$options['min']}");
            }
            if (isset($options['max']) && $int_value > $options['max']) {
                throw new Exception("Value must not exceed {$options['max']}");
            }
            return $int_value;
            
        case 'bool':
        case 'checkbox':
            // Handle checkbox/boolean inputs (true, false, 1, 0, 'on', 'off', etc.)
            if (is_bool($value)) {
                return $value;
            }
            if (is_string($value)) {
                $lower = strtolower(trim($value));
                return in_array($lower, ['1', 'true', 'on', 'yes'], true);
            }
            return (bool) $value;
            
        case 'coordinates':
        case 'latitude':
        case 'longitude':
            // Special handler for lat/lon coordinates with proper range validation
            $coord_value = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($coord_value === false) {
                throw new Exception('Invalid coordinate value provided');
            }
            
            // Validate coordinate ranges
            if ($type === 'latitude' && ($coord_value < -90 || $coord_value > 90)) {
                throw new Exception('Latitude must be between -90 and 90 degrees');
            }
            if ($type === 'longitude' && ($coord_value < -180 || $coord_value > 180)) {
                throw new Exception('Longitude must be between -180 and 180 degrees');
            }
            
            return $coord_value;
            
        case 'array':
            if (!is_array($value)) {
                throw new Exception('Expected array value');
            }
            return $value;
            
        default:
            throw new Exception("Unknown validation type: {$type}");
    }
}
}