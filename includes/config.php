<?php

if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}

if (!isset($_SESSION['join_session_id'])) {
    $_SESSION['join_session_id'] = session_id() . '_' . time();
}

if (!function_exists('site_url')) {
    $wp_load_path = dirname(__FILE__, 3) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        error_log("SUD Critical Error: Could not load WordPress environment from config.php. Path tried: " . $wp_load_path);
        die("A critical error occurred loading the site environment.");
    }
}

require_once(__DIR__ . '/brand-config.php');

require_once(SUD_INCLUDES_DIR . '/database.php');
require_once(SUD_INCLUDES_DIR . '/database-setup.php');
require_once(SUD_INCLUDES_DIR . '/pricing-config.php');
require_once(SUD_INCLUDES_DIR . '/trial-cron.php');
require_once(SUD_INCLUDES_DIR . '/maintenance-mode.php');

// Global maintenance mode check - affects all pages
if (function_exists('sud_maintenance_gate')) {
    sud_maintenance_gate();
}

require_once(SUD_INCLUDES_DIR . '/text-helpers.php');
require_once(SUD_INCLUDES_DIR . '/premium-functions.php');
require_once(SUD_INCLUDES_DIR . '/user-functions.php');
require_once(SUD_INCLUDES_DIR . '/location-functions.php');
require_once(SUD_INCLUDES_DIR . '/profile-functions.php');
require_once(SUD_INCLUDES_DIR . '/messaging-functions.php'); 
require_once(SUD_INCLUDES_DIR . '/notification-functions.php');
require_once(SUD_INCLUDES_DIR . '/swipe-functions.php');
require_once(SUD_INCLUDES_DIR . '/mailer.php');
require_once(SUD_INCLUDES_DIR . '/site-helpers.php');
require_once(SUD_INCLUDES_DIR . '/ban-warning-system.php');
require_once(SUD_INCLUDES_DIR . '/trafficjunky-tracking.php');

function get_current_step() {
    global $current_slug;
    return SUD_STEPS[$current_slug] ?? 'unknown';
}

function redirect_to_step($step) {
    $file = array_search($step, SUD_STEPS);
    if ($file) {
        wp_safe_redirect(rtrim(SUD_URL, '/') . '/' . ltrim($file, '/'));
        exit;
    }
    wp_safe_redirect(home_url());
    exit;
}

function handle_error($message = "An unexpected error occurred.") {
    error_log("SUD Error Handled: " . $message);
    $error_page_url = rtrim(SUD_URL, '/') . '/error.html';
    if (strpos($_SERVER['REQUEST_URI'], '/error.html') === false) {
        wp_safe_redirect($error_page_url);
        exit;
    } else {
        die("An critical error occurred. Please try again later.");
    }
}

function require_login($redirect_to = null) {
    if (!is_user_logged_in()) {
        $sud_login_url = SUD_URL . '/auth/login'; 
        $redirect_param = $redirect_to ?? $_SERVER['REQUEST_URI'];
        $login_url_with_redirect = add_query_arg('redirect_to', urlencode($redirect_param), $sud_login_url);
        wp_safe_redirect($login_url_with_redirect);
        exit;
    }
    
    $user_id = get_current_user_id();
    
    // Check ban status for non-admin users
    if (!is_excluded_admin($user_id)) {
        $ban_status = sud_check_ban_status($user_id);
        if ($ban_status) {
            // User is banned - ban notice will be displayed in header template
            // Store ban status in global for header access
            global $sud_user_ban_status;
            $sud_user_ban_status = $ban_status;
        }
    }
    
    return $user_id; 
}

function is_excluded_admin($user_id) {
    if (!$user_id || !get_userdata($user_id)) {
        return false;
    }
    // Exclude administrators and chat moderators from public areas
    return user_can($user_id, 'administrator') || user_can($user_id, 'sud_chat_moderator');
}

function get_profile_completion_percentage(int $user_id): int {
    if ($user_id <= 0) return 0;
    $completed_steps_count = 0;
    $total_steps_defined = 14;
    $applicable_steps_count = $total_steps_defined;
    $functional_role = get_user_meta($user_id, 'functional_role', true);
    $is_receiver_role = ($functional_role === 'receiver');
    $skipped_steps_for_receiver = [1, 2, 3];
    if ($is_receiver_role) {
        $applicable_steps_count -= count($skipped_steps_for_receiver);
    }
    for ($i = 0; $i < $total_steps_defined; $i++) {
        if ($is_receiver_role && in_array($i, $skipped_steps_for_receiver)) {
            continue;
        }
        if (get_user_meta($user_id, 'completed_step_' . $i, true)) {
            $completed_steps_count++;
        }
    }
    if ($applicable_steps_count <= 0) return 100;
    $percentage = ($completed_steps_count / $applicable_steps_count) * 100;
    return (int) round($percentage);
}

function check_and_trigger_location_update(int $user_id): void {
    if ($user_id <= 0 || is_excluded_admin($user_id)) {
        return;
    }

    $needs_update_flag = (bool) get_user_meta($user_id, 'location_needs_update', true);
    $latitude = get_user_meta($user_id, 'latitude', true);
    $longitude = get_user_meta($user_id, 'longitude', true);
    $is_location_missing = empty($latitude) || empty($longitude);
    $should_trigger_update = ($needs_update_flag || $is_location_missing);

    if ($should_trigger_update) {
        if ($is_location_missing) {
            add_filter('body_class', function($classes) {
                if (!is_array($classes)) $classes = [];
                if (!in_array('sud-location-missing', $classes)) {
                    $classes[] = 'sud-location-missing';
                }
                return $classes;
            });
        }

        static $sud_location_script_enqueued = false;
        if (!$sud_location_script_enqueued) {
            add_action('wp_footer', function () use ($user_id) {
                $on_setup_page = ($GLOBALS['current_slug'] === 'profile-setup');

                $ajax_url = esc_url(SUD_AJAX_URL . '/update-location.php');
                $user_id_js = esc_js($user_id);
                ?>
                <script id="sud-location-updater-script">
                document.addEventListener('DOMContentLoaded', function() {
                    const sudUserId = '<?php echo $user_id_js; ?>';
                    const sudAjaxUrl = '<?php echo $ajax_url; ?>';
                    const onSetupPage = <?php echo $on_setup_page ? 'true' : 'false'; ?>;

                    function updateLocationFormFields(data) {
                        if (!onSetupPage) return;

                        console.log('[SUD AutoLocation] Attempting to update form fields with:', data);
                        const locationInput = document.getElementById('location-autocomplete-input');
                        const latInput = document.getElementById('latitude');
                        const lngInput = document.getElementById('longitude');
                        const cityInput = document.getElementById('city_google');
                        const regionInput = document.getElementById('region_google');
                        const countryInput = document.getElementById('country_google');
                        const statusMsg = document.getElementById('location-status-message');

                        if (locationInput && latInput && lngInput && cityInput && regionInput && countryInput) {
                            latInput.value = data.latitude || '';
                            lngInput.value = data.longitude || '';
                            cityInput.value = data.city || '';
                            regionInput.value = data.region || '';
                            countryInput.value = data.country || '';
                            locationInput.value = data.location_string || '';

                            locationInput.dispatchEvent(new Event('input', { bubbles: true }));

                            if (typeof checkFormValidity === 'function') {
                                checkFormValidity();
                            }
                            if (statusMsg) {
                                statusMsg.textContent = 'Location automatically detected!';
                                statusMsg.className = 'sud-location-status-message sud-location-message-success';
                            }
                            console.log('[SUD AutoLocation] Form fields updated.');
                        } else {
                            console.warn('[SUD AutoLocation] Could not find all required form fields to update.');
                        }
                    }

                    function showLocationStatus(message, type = 'info') {
                        if (!onSetupPage) return;
                        const statusMsg = document.getElementById('location-status-message');
                        if (statusMsg) {
                            statusMsg.textContent = message;
                            statusMsg.className = `sud-location-status-message sud-location-message-${type}`;
                        }
                    }

                    function updateLmrUserLocation(userId, lat, lng, acc) {
                        showLocationStatus('Saving detected location...', 'info');
                        const formData = new FormData();
                        formData.append('user_id', userId);
                        formData.append('latitude', lat);
                        formData.append('longitude', lng);
                        formData.append('accuracy', acc || 'unknown (JS)');

                        fetch(sudAjaxUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: formData
                        })
                        .then(response => response.ok ? response.json() : response.text().then(text => Promise.reject(`HTTP error ${response.status}: ${text}`)))
                        .then(result => {
                            if (result && result.success) {
                                updateLocationFormFields({
                                    latitude: result.latitude || lat,
                                    longitude: result.longitude || lng,
                                    city: result.city || '',
                                    region: result.region || '',
                                    country: result.country || '',
                                    location_string: result.formatted_location || ''
                                });
                                document.dispatchEvent(new CustomEvent('sud:locationUpdated', { detail: result }));
                            } else {
                                console.error('[SUD AutoLocation] Location update failed via AJAX:', result ? result.message : 'Unknown server error');
                                showLocationStatus('Failed to save location. Please use the search or contact support.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('[SUD AutoLocation] Location update fetch error:', error);
                            showLocationStatus('Network error saving location. Please check connection or contact support.', 'error');
                        });
                    }

                    // --- Function to get IP location ---
                    function getLmrIpBasedLocation(userId) {
                         showLocationStatus('Attempting IP-based location...', 'info');
                         fetch('https://ipapi.co/json/', { cache: "no-store" })
                            .then(response => response.ok ? response.json() : Promise.reject('IP API fetch failed'))
                            .then(data => {
                                if (data && data.latitude && data.longitude) {
                                     updateLmrUserLocation(userId, data.latitude, data.longitude, `low (IP: ${data.ip})`);
                                } else {
                                     showLocationStatus('Could not determine location via IP. Please use search.', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('[SUD AutoLocation] IP Location fetch error:', error);
                                showLocationStatus('Could not determine location. Please use search or contact support.', 'error');
                            });
                    }

                    if (sudUserId && sudUserId !== '0' && navigator.geolocation) {
                        showLocationStatus('Requesting browser location...', 'info');
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                updateLmrUserLocation( sudUserId, position.coords.latitude, position.coords.longitude, position.coords.accuracy );
                            },
                            (error) => {
                                console.warn(`[SUD AutoLocation] Geolocation error: ${error.message} (Code: ${error.code})`);
                                let userMessage = 'Could not get precise location. Please use the search.';
                                let errorType = 'error';
                                switch(error.code) {
                                    case error.PERMISSION_DENIED:
                                        userMessage = 'Location access denied. Please enable it or use the search.';
                                        showLocationStatus(userMessage, errorType);
                                        return;
                                    case error.POSITION_UNAVAILABLE:
                                        userMessage = 'Location information unavailable. Trying fallback...';
                                        break;
                                    case error.TIMEOUT:
                                        userMessage = 'Location request timed out. Trying fallback...';
                                        break;
                                }
                                showLocationStatus(userMessage, 'info');
                                getLmrIpBasedLocation(sudUserId);
                            },
                            { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
                        );
                    } else if (sudUserId && sudUserId !== '0') {
                        getLmrIpBasedLocation(sudUserId);
                    }
                });
                </script>
                <?php
            });
            $sud_location_script_enqueued = true;
        }
    } else {
        add_filter('body_class', function($classes) {
            if (!is_array($classes)) $classes = [];
            $classes = array_filter($classes, function($c) { return $c !== 'sud-location-missing'; });
            return $classes;
        });
    }
}

function sud_initialize_core_hooks(): void {
    add_action('wp_login', function($user_login, $user) {
        if ($user instanceof WP_User) {
            check_and_trigger_location_update($user->ID);
            if (function_exists('update_user_last_active')) {
                update_user_last_active($user->ID);
            }
        }
    }, 15, 2);
    add_action('wp', function() {
        if (is_user_logged_in() && !is_admin()) {
            $user_id = get_current_user_id();
            check_and_trigger_location_update($user_id);

            if (function_exists('update_user_last_active')) {
                $transient_key = 'sud_last_active_updated_' . $user_id;
                if (false === get_transient($transient_key)) {
                    update_user_last_active($user_id);
                    set_transient($transient_key, time(), 60); 
                }
            }
        }
    });
}
sud_initialize_core_hooks(); 

/**
 * Centralized profile validation - ensures all required fields are completed
 * Call this on every protected page after require_login()
 */
function validate_core_profile_requirements($user_id = null, $current_page = '') {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id || is_excluded_admin($user_id)) {
        return; // Skip validation for admins
    }
    
    // Core data validation (same as dashboard.php:37-47)
    $gender = get_user_meta($user_id, 'gender', true);
    $looking_for = get_user_meta($user_id, 'looking_for', true);
    $functional_role = get_user_meta($user_id, 'functional_role', true);

    if (empty($gender) || empty($looking_for) || empty($functional_role)) {
        $target_path_segment = '';
        $reason = 'core_data_missing_from_' . $current_page . '_gender';
        
        if (!empty($gender) && (empty($looking_for) || empty($functional_role))) {
            $target_path_segment = 'looking-for';
            $reason = 'core_data_missing_from_' . $current_page . '_lookingfor';
        }
        
        $redirect_url = add_query_arg('setup_reason', $reason, trailingslashit(SUD_URL) . $target_path_segment);
        wp_safe_redirect($redirect_url);
        exit;
    }

    // Profile setup completion check (same as dashboard.php:49-54)
    $profile_setup_complete = get_user_meta($user_id, 'profile_setup_complete', true);
    if (!$profile_setup_complete) {
        $redirect_url = add_query_arg('setup_reason', 'profile_setup_incomplete_from_' . $current_page, SUD_URL . '/profile-setup');
        wp_safe_redirect($redirect_url);
        exit;
    }

    // Step 0 validation (interests/relationship terms) - required for all pages
    $is_step0_completed = get_user_meta($user_id, 'completed_step_0', true);
    $relationship_terms = get_user_meta($user_id, 'relationship_terms', true);
    $has_valid_terms = is_array($relationship_terms) && count($relationship_terms) >= 3;
    
    if (!$is_step0_completed || !$has_valid_terms) {
        $redirect_url = add_query_arg([
            'active' => 0,
            'step_error' => urlencode('Please select at least 3 relationship terms to continue.')
        ], SUD_URL . '/profile-details');
        wp_safe_redirect($redirect_url);
        exit;
    }

    // Step 13 validation (photos) - required for most pages except settings/profile-edit
    $pages_without_photo_requirement = ['profile-edit', 'settings', 'wallet', 'withdrawal'];
    $requires_photos = !in_array($current_page, $pages_without_photo_requirement);
    
    if ($requires_photos) {
        $is_step13_completed = get_user_meta($user_id, 'completed_step_13', true);
        $profile_picture_id = get_user_meta($user_id, 'profile_picture', true);

        if (!$is_step13_completed || empty($profile_picture_id)) {
            $redirect_url = add_query_arg([
                'active' => 13,
                'step_error' => urlencode('A profile picture is required to access this feature.')
            ], SUD_URL . '/profile-details');
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}

add_action('sud_new_message_sent', 'sud_handle_monitored_account_message', 10, 4);
add_action('sud_user_swiped', 'sud_handle_monitored_account_swipe', 10, 4);
add_action('sud_match_created', 'sud_handle_monitored_account_swipe', 10, 4);
function sud_check_database_setup() {
    $sud_db_version_option = 'sud_db_version';
    $current_db_version = '2.6';
    $installed_db_version = get_option($sud_db_version_option);

    if ($installed_db_version != $current_db_version) {
        if (function_exists('sud_create_all_tables')) {
            error_log("SUD Config: Running database table setup/update. Installed: $installed_db_version, Target: $current_db_version");

            if (!function_exists('dbDelta')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            }

            sud_create_all_tables();

            update_option($sud_db_version_option, $current_db_version);
            error_log("SUD Config: Database table setup/update completed. New version set to: " . $current_db_version);
        } else {
            error_log("SUD Config Error: sud_create_all_tables function not found during setup check.");
        }
    }
}

add_action('init', 'sud_check_database_setup');
if (!function_exists('sud_set_default_notification_settings_array')) {
    function sud_set_default_notification_settings_array($user_id) {
        // Only apply if these defaults haven't been set before for this user
        if (get_user_meta($user_id, 'sud_default_settings_applied_v2', true)) {
            return;
        }

        $default_settings_array = [
            'email_notifications'    => true,
            'message_notifications'  => true,
            'favorite_notifications' => true,
            'view_notifications'     => true,
            'hide_online_status'     => false,
        ];
        update_user_meta($user_id, 'user_settings', $default_settings_array);
        update_user_meta($user_id, 'sud_default_settings_applied_v2', '1');
    }
    add_action('user_register', 'sud_set_default_notification_settings_array', 10, 1);
}

if (!function_exists('sud_get_admin_notification_settings')) {
    function sud_get_admin_notification_settings() {
        $default_settings = [
            'admin_email_notifications' => true,
            'admin_payment_notifications' => true,
            'admin_swipe_notifications' => false, // Default disabled for swipe spam
            'admin_match_notifications' => true,
            'admin_message_notifications' => false,
            'admin_registration_notifications' => true,
            'admin_trial_notifications' => true,
            'admin_system_notifications' => true,
            'enabled_admin_users' => [], // Array of admin user IDs who should receive emails
            'enable_all_admins' => true // If true, send to all admins; if false, only to enabled_admin_users
        ];
        
        $saved_settings = get_option('sud_admin_notification_settings', []);
        return array_merge($default_settings, $saved_settings);
    }
}

if (!function_exists('sud_update_admin_notification_settings')) {
    function sud_update_admin_notification_settings($settings) {
        return update_option('sud_admin_notification_settings', $settings);
    }
}

if (!function_exists('sud_get_eligible_admin_users')) {
    function sud_get_eligible_admin_users() {
        $settings = sud_get_admin_notification_settings();
        
        // Get all admin users
        $all_admin_users = get_users(['role' => 'administrator']);
        
        // If enable_all_admins is true, return all admins
        if ($settings['enable_all_admins'] ?? true) {
            return $all_admin_users;
        }
        
        // Otherwise, filter to only enabled admin users
        $enabled_admin_ids = $settings['enabled_admin_users'] ?? [];
        if (empty($enabled_admin_ids)) {
            return []; // No specific admins enabled
        }
        
        $eligible_admins = [];
        foreach ($all_admin_users as $admin_user) {
            if (in_array($admin_user->ID, $enabled_admin_ids)) {
                $eligible_admins[] = $admin_user;
            }
        }
        
        return $eligible_admins;
    }
}

if (!function_exists('sud_admin_should_receive_email')) {
    function sud_admin_should_receive_email($notification_type) {
        $settings = sud_get_admin_notification_settings();
        
        // Master toggle check
        if (!($settings['admin_email_notifications'] ?? true)) {
            return false;
        }
        
        // Specific notification type check
        return $settings[$notification_type] ?? true;
    }
}