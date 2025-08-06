<?php
/**
 * Centralized Search Filter Functions
 * 
 * This file provides unified search filter processing
 * for consistency between dashboard modal, search page, and mobile modal
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Process and validate search criteria from form data
 * 
 * @param array $form_data Raw form data from GET/POST
 * @param int $current_user_id Current user ID
 * @param bool $allow_advanced Whether user can access advanced filters
 * @return array Validated and processed search criteria
 */
function sud_process_search_criteria($form_data, $current_user_id, $allow_advanced = false) {
    // Initialize default search criteria
    $search_criteria = [
        'gender' => '',
        'looking_for' => '', 
        'min_age' => 18,
        'max_age' => 70,
        'location' => '',
        'ethnicity' => '',
        'verified_only' => false,
        'online_only' => false,
        'body_type' => ''
    ];

    $allowed_search_keys = ['gender', 'looking_for', 'min_age', 'max_age', 'location', 'ethnicity', 'verified_only', 'online_only', 'body_type'];
    
    foreach ($allowed_search_keys as $key) {
        if (isset($form_data[$key]) && $form_data[$key] !== '') { 
            if (in_array($key, ['min_age', 'max_age'])) {
                $search_criteria[$key] = absint($form_data[$key]);
            } elseif ($key === 'ethnicity' || $key === 'verified_only' || $key === 'online_only' || $key === 'body_type') {
                // Advanced filters require premium access
                if ($allow_advanced) {
                    if (in_array($key, ['verified_only', 'online_only'])) {
                        $search_criteria[$key] = filter_var($form_data[$key], FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $search_criteria[$key] = sanitize_text_field($form_data[$key]);
                    }
                }
            } elseif ($key === 'gender' || $key === 'looking_for' || $key === 'location') {
                // Basic filters available to all users
                $search_criteria[$key] = sanitize_text_field($form_data[$key]);
            }
        }
    }

    // Validate age range
    $search_criteria['min_age'] = max(18, $search_criteria['min_age']);
    $search_criteria['max_age'] = min(70, $search_criteria['max_age']);
    if($search_criteria['min_age'] > $search_criteria['max_age']) {
        list($search_criteria['min_age'], $search_criteria['max_age']) = [$search_criteria['max_age'], $search_criteria['min_age']];
    }

    return $search_criteria;
}

/**
 * Apply default gender preferences if no explicit gender filter is set
 * 
 * @param array $search_criteria Current search criteria
 * @param int $current_user_id Current user ID
 * @param bool $is_admin_view Whether this is an admin view
 * @return array Updated search criteria with default gender preferences
 */
function sud_apply_default_gender_preferences($search_criteria, $current_user_id, $is_admin_view = false) {
    // Only apply defaults if no gender is explicitly selected and not admin view
    if (!empty($search_criteria['gender']) || $is_admin_view) {
        return $search_criteria;
    }

    $current_user_gender = get_user_meta($current_user_id, 'gender', true);
    $current_user_looking_for = get_user_meta($current_user_id, 'looking_for', true);

    if ($current_user_gender === 'LGBTQ+' || $current_user_looking_for === 'Everyone') {
        // LGBTQ+ users and users looking for everyone see all genders by default
        // No gender filter applied
    } elseif ($current_user_gender === 'Man') {
        $search_criteria['gender'] = 'Woman';  // Men see Women by default
    } elseif ($current_user_gender === 'Woman') {
        $search_criteria['gender'] = 'Man';    // Women see Men by default
    }

    return $search_criteria;
}

/**
 * Get available gender options for current user
 * 
 * @param int $current_user_id Current user ID
 * @param bool $is_admin_view Whether this is an admin view
 * @return array Available gender options
 */
function sud_get_available_gender_options($current_user_id, $is_admin_view = false) {
    $gender_options = [
        '' => 'All Genders',
        'Man' => 'Men',
        'Woman' => 'Women',
        'LGBTQ+' => 'LGBTQ+'
    ];

    // Admin users can see all options
    if ($is_admin_view) {
        return $gender_options;
    }

    $current_user_gender = get_user_meta($current_user_id, 'gender', true);
    $current_user_looking_for = get_user_meta($current_user_id, 'looking_for', true);

    // All users can search for any gender - no restrictions
    // This allows women to search for women, men to search for men, etc.
    return $gender_options;
}

/**
 * Validate search criteria and return user-friendly error messages
 * 
 * @param array $search_criteria Search criteria to validate
 * @return array|true Array of errors or true if valid
 */
function sud_validate_search_criteria($search_criteria) {
    $errors = [];

    // Validate age range
    if ($search_criteria['min_age'] < 18) {
        $errors[] = 'Minimum age must be at least 18';
    }
    
    if ($search_criteria['max_age'] > 70) {
        $errors[] = 'Maximum age cannot exceed 70';
    }
    
    if ($search_criteria['min_age'] > $search_criteria['max_age']) {
        $errors[] = 'Minimum age cannot be greater than maximum age';
    }

    // Validate location if provided
    if (!empty($search_criteria['location']) && strlen($search_criteria['location']) < 2) {
        $errors[] = 'Location must be at least 2 characters';
    }

    return empty($errors) ? true : $errors;
}

/**
 * Log search activity for debugging and analytics
 * 
 * @param int $current_user_id Current user ID
 * @param array $search_criteria Search criteria used
 * @param array $search_results Search results returned
 */
function sud_log_search_activity($current_user_id, $search_criteria, $search_results) {
    if (!defined('SUD_DEBUG_SEARCH') || !SUD_DEBUG_SEARCH) {
        return;
    }

    $log_data = [
        'user_id' => $current_user_id,
        'criteria' => $search_criteria,
        'results_count' => is_array($search_results) ? count($search_results['users'] ?? []) : 0,
        'timestamp' => current_time('mysql')
    ];

    error_log('[SUD Search] ' . json_encode($log_data));
}