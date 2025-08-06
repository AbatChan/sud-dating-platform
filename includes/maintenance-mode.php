<?php
/**
 * Maintenance Mode System for SUD Standalone Application
 * Controlled from WordPress sud-dashboard
 */

// SUD standalone check - don't require WordPress
if (!defined('SUD_PATH')) {
    define('SUD_PATH', dirname(__DIR__));
}

/**
 * Check if maintenance mode is enabled and display maintenance page
 */
function sud_check_maintenance_mode() {
    // Check if maintenance mode is enabled from WordPress options
    $maintenance_enabled = sud_get_maintenance_option('sud_maintenance_mode', false);
    
    if ($maintenance_enabled) {
        // Skip maintenance for admin IPs or authorized users
        if (sud_is_maintenance_exempt()) {
            return;
        }
        
        sud_show_maintenance_page();
        exit;
    }
}

/**
 * Check if current user/IP is exempt from maintenance mode
 */
function sud_is_maintenance_exempt() {
    // Check for test parameter
    if (isset($_GET['test_maintenance']) && $_GET['test_maintenance'] == '1') {
        return true;
    }
    
    // Check if WordPress is available and user is admin
    if (function_exists('current_user_can') && current_user_can('administrator')) {
        return true;
    }
    
    // Check exempt IPs
    $exempt_ips = sud_get_maintenance_option('sud_maintenance_exempt_ips', []);
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    return in_array($user_ip, $exempt_ips);
}

/**
 * Get maintenance option from WordPress or file fallback
 */
function sud_get_maintenance_option($key, $default = false) {
    // Try WordPress options first
    if (function_exists('get_option')) {
        return get_option($key, $default);
    }
    
    // Fallback to file-based config
    $config_file = SUD_PATH . '/maintenance-config.json';
    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true);
        return $config[$key] ?? $default;
    }
    
    return $default;
}

/**
 * Display the maintenance page
 */
function sud_show_maintenance_page() {
    $maintenance_message = sud_get_maintenance_option('sud_maintenance_message', "We'll be right back! We're currently making some improvements to serve you better.");
    $contact_email = sud_get_maintenance_option('sud_maintenance_contact_email', 'support@swipeupdaddy.com');
    $site_name = sud_get_maintenance_option('sud_site_name', 'SwipeUpDaddy');
    $maintenance_title = sud_get_maintenance_option('sud_maintenance_title', "We'll Be Right Back!");
    $maintenance_icon = '🔧'; // Fixed icon
    
    http_response_code(503);
    header('Retry-After: 3600'); // Tell search engines to retry in 1 hour
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Maintenance - <?php echo htmlspecialchars($site_name); ?></title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap');
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Lato', 'Inter', sans-serif;
                background: #0A0A0A url('<?php echo SUD_URL; ?>/assets/img/bg.jpg') center/cover no-repeat;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #FFFFFF;
                position: relative;
            }
            
            body::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.85);
                z-index: 1;
            }
            
            .maintenance-container {
                background: #161616;
                backdrop-filter: blur(20px);
                padding: 60px 40px;
                border-radius: 16px;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 102, 204, 0.1);
                text-align: center;
                max-width: 600px;
                width: 90%;
                position: relative;
                z-index: 2;
                border: 1px solid rgba(255, 102, 204, 0.2);
            }
            
            .sud-logo {
                width: 120px;
                height: auto;
                margin-bottom: 30px;
                filter: brightness(1.1);
            }
            
            .maintenance-icon {
                font-size: 80px;
                margin-bottom: 20px;
                color: #FF66CC;
                text-shadow: 0 0 20px rgba(255, 102, 204, 0.5);
            }
            
            h1 {
                font-size: 2.5rem;
                margin-bottom: 20px;
                color: #FFFFFF;
                font-weight: 700;
                font-family: 'Lato', sans-serif;
            }
            
            .maintenance-message {
                font-size: 1.2rem;
                line-height: 1.6;
                margin-bottom: 40px;
                color: #CCCCCC;
                font-weight: 400;
            }
            
            .contact-info {
                margin-top: 40px;
                padding-top: 30px;
                border-top: 1px solid rgba(255, 102, 204, 0.2);
            }
            
            .contact-info p {
                margin-bottom: 10px;
                color: #AAAAAA;
                font-size: 0.95rem;
            }
            
            .contact-email {
                color: #FF66CC;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.25s ease;
            }
            
            .contact-email:hover {
                color: #E659B5;
                text-shadow: 0 0 8px rgba(255, 102, 204, 0.3);
            }
            
            .brand-footer {
                margin-top: 30px;
                font-size: 0.9rem;
                color: #666;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            
            .brand-footer .heart {
                color: #FF66CC;
                animation: heartbeat 1.5s ease-in-out infinite;
            }
            
            @keyframes heartbeat {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            
            @media (max-width: 768px) {
                .maintenance-container {
                    padding: 40px 30px;
                    margin: 20px;
                }
                
                h1 {
                    font-size: 2rem;
                }
                
                .maintenance-icon {
                    font-size: 60px;
                }
                
                .sud-logo {
                    width: 100px;
                }
            }
        </style>
    </head>
    <body>
        <div class="maintenance-container">
            <img src="<?php echo SUD_URL; ?>/assets/img/logo.png" alt="<?php echo htmlspecialchars($site_name); ?>" class="sud-logo">
            <div class="maintenance-icon"><?php echo htmlspecialchars($maintenance_icon); ?></div>
            <h1><?php echo htmlspecialchars($maintenance_title); ?></h1>
            <div class="maintenance-message">
                <?php echo nl2br(htmlspecialchars($maintenance_message)); ?>
            </div>
            
            <div class="contact-info">
                <p>Need immediate assistance?</p>
                <p>Contact us at: <a href="mailto:<?php echo esc_attr($contact_email); ?>" class="contact-email"><?php echo esc_html($contact_email); ?></a></p>
            </div>
            
            <div class="brand-footer">
                <span>Made with</span>
                <span class="heart">♥</span>
                <span>by SwipeUpDaddy</span>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Add maintenance mode hook to init (WordPress)
 */
if (function_exists('add_action')) {
    add_action('init', 'sud_check_maintenance_mode', 0);
}

/**
 * Call this at the start of every SUD page
 */
function sud_maintenance_gate() {
    sud_check_maintenance_mode();
}

/**
 * Helper function to enable maintenance mode
 */
function sud_enable_maintenance_mode($message = '', $estimated_time = '', $contact_email = '') {
    update_option('sud_maintenance_mode', true);
    if ($message) update_option('sud_maintenance_message', $message);
    if ($estimated_time) update_option('sud_maintenance_estimated_time', $estimated_time);
    if ($contact_email) update_option('sud_maintenance_contact_email', $contact_email);
}

/**
 * Helper function to disable maintenance mode
 */
function sud_disable_maintenance_mode() {
    delete_option('sud_maintenance_mode');
}

/**
 * Check if maintenance mode is active
 */
function sud_is_maintenance_mode_active() {
    return (bool) get_option('sud_maintenance_mode', false);
}