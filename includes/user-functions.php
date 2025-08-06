<?php

if (!defined('SUD_URL') && file_exists(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
} elseif (!defined('SUD_URL')) {
    $wp_load_path = dirname(__FILE__, 3) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        error_log("user-functions.php could not load WordPress environment or SUD config.");
    }
}

function get_attribute_display_label($attribute, $value, $fallback = 'Not specified') {
    if (empty($value)) {
        return $fallback;
    }
    $options = get_attribute_options($attribute);
    if (isset($options[$value])) {
        return $options[$value]; 
    }
    return !empty($fallback) ? $fallback : ucwords(str_replace('_', ' ', $value));
}

function calculate_age($date_of_birth) {
    if (empty($date_of_birth)) {
        return null;
    }
    try {
        $dob_date = new DateTime($date_of_birth);
        $now = new DateTime();
        return $now->diff($dob_date)->y;
    } catch (Exception $e) {
        return null;
    }
}

function get_user_full_name($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return '';
    }

    if (!empty($user->display_name)) {
        return $user->display_name;
    }

    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);

    if (!empty($first_name) && !empty($last_name)) {
        return $first_name . ' ' . $last_name;
    } elseif (!empty($first_name)) {
        return $first_name;
    }
    return $user->user_login;
}

function get_user_profile_data($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return null; 
    }
    $user_id = $user->ID; 
    $profile_picture_id = get_user_meta($user_id, 'profile_picture', true);
    $profile_pic_url = !empty($profile_picture_id) ?
        wp_get_attachment_image_url($profile_picture_id, 'medium_large') 
        : SUD_IMG_URL . '/default-profile.jpg';

    $date_of_birth = get_user_meta($user_id, 'date_of_birth', true);
    $age = calculate_age($date_of_birth); 
    $gender = get_user_meta($user_id, 'gender', true);
    $looking_for = get_user_meta($user_id, 'looking_for', true);

    $latitude = get_user_meta($user_id, 'latitude', true);
    $longitude = get_user_meta($user_id, 'longitude', true);
    $city = get_user_meta($user_id, 'city', true);
    $region = get_user_meta($user_id, 'region', true);
    $country = get_user_meta($user_id, 'country', true);
    $location_string_raw = get_user_meta($user_id, 'location_string', true);
    $location_formatted = format_user_location($user_id);

    $completion_percentage = function_exists('get_profile_completion_percentage') ? get_profile_completion_percentage($user_id) : 0;
    $last_active = get_user_meta($user_id, 'last_active', true);
    $is_verified = (bool) get_user_meta($user_id, 'is_verified', true);

    $user_settings = get_user_settings($user_id);
    $hide_online = $user_settings['hide_online_status'] ?? false;
    $is_online = !$hide_online && $last_active && ($last_active > (current_time('timestamp', true) - 300)); 

    $is_favorite = (is_user_logged_in() && function_exists('is_user_favorite')) ? is_user_favorite($user_id) : false;

    $plan_details = (function_exists('sud_get_user_current_plan_details'))
        ? sud_get_user_current_plan_details($user_id)
        : ['id' => 'free', 'name' => 'Free Tier', 'tier_level' => 0, 'capabilities' => []];
    $premium_badge_html_small = (function_exists('sud_get_premium_badge_html'))
        ? sud_get_premium_badge_html($user_id, 'small')
        : '';
    $premium_badge_html_medium = (function_exists('sud_get_premium_badge_html'))
        ? sud_get_premium_badge_html($user_id, 'medium')
        : '';

    // Check if user has active boost
    $has_active_boost = false;
    $boost_type = '';
    $boost_name = '';
    
    if (function_exists('sud_user_has_active_boost') && sud_user_has_active_boost($user_id)) {
        $has_active_boost = true;
        $boost_type = get_user_meta($user_id, 'active_boost_type', true) ?: 'mini';
        $boost_name = get_user_meta($user_id, 'active_boost_name', true) ?: 'Profile Boost';
    }

    $user_data = [
        'id' => $user_id,
        'name' => get_user_full_name($user_id), 

        'profile_pic' => $profile_pic_url,
        'image' => $profile_pic_url,
        'age' => $age ?: '', 
        'gender' => $gender ?: '',
        'looking_for' => $looking_for ?: '',

        'latitude' => $latitude ? (float)$latitude : null, 
        'longitude' => $longitude ? (float)$longitude : null,
        'city' => $city ?: '',
        'region' => $region ?: '',
        'country' => $country ?: '',
        'location_string_raw' => $location_string_raw ?: '', 
        'location_formatted' => $location_formatted, 

        'completion_percentage' => (int) $completion_percentage,
        'online' => $is_online,
        'is_online' => $is_online,
        'is_verified' => $is_verified,
        'last_active_timestamp' => $last_active ? (int)$last_active : 0, 
        'last_active' => $last_active ? human_time_diff($last_active, current_time('timestamp', true)) . ' ago' : 'Never', 
        'is_favorite' => $is_favorite,

        'premium_plan_id' => $plan_details['id'] ?? 'free',
        'premium_plan_name' => $plan_details['name'] ?? 'Free Tier',
        'is_premium' => (($plan_details['id'] ?? 'free') !== 'free'),
        'premium_tier_level' => isset($plan_details['tier_level']) ? (int)$plan_details['tier_level'] : 0,
        'premium_badge_html_small' => $premium_badge_html_small,
        'premium_badge_html_medium' => $premium_badge_html_medium,
        'premium_capabilities' => $plan_details ?? SUD_PREMIUM_CAPABILITIES['free'],

        // Boost status - ensure proper data types
        'has_active_boost' => $has_active_boost ? true : false,
        'boost_type' => $boost_type ? (string) $boost_type : '',
        'boost_name' => $boost_name ? (string) $boost_name : '',

    ];
    
    return $user_data;
}

function format_user_location($user_id) {
    $city = get_user_meta($user_id, 'city', true);
    // $region = get_user_meta($user_id, 'region', true);
    $country = get_user_meta($user_id, 'country', true);
    $location_string = get_user_meta($user_id, 'location_string', true); 

    $parts = [];

    if (!empty($city)) $parts[] = trim($city);
    // if (!empty($region)) $parts[] = trim($region);
    if (!empty($country)) $parts[] = trim($country);

    $parts = array_unique(array_filter($parts));

    if (!empty($parts)) {
        return implode(', ', $parts);
    }

    if (!empty($location_string)) {
        return esc_html(trim($location_string));
    }
    return 'Location not specified';
}

function update_user_last_active($user_id) {
    $user_id = intval($user_id);
    if ($user_id <= 0) {
        return false;
    }
    return update_user_meta($user_id, 'last_active', current_time('timestamp', true));
}

function custom_get_user_data($user_ids) {
    $users = [];
   
    foreach ($user_ids as $user_id) {
        $user_data = get_user_profile_data($user_id);
        if ($user_data) {
            // Debug boost data for each user - check data types
            $has_boost = $user_data['has_active_boost'] ?? 'missing';
            $boost_type = $user_data['boost_type'] ?? 'missing';
           
            // Ensure boolean is preserved correctly
            if (isset($user_data['has_active_boost'])) {
                $user_data['has_active_boost'] = (bool) $user_data['has_active_boost'];
            }
            
            $users[] = $user_data;
        }
    }
    return $users;
}

function is_user_favorite($favorite_id) {
    if (!is_user_logged_in()) {
       return false;
   }
   global $wpdb;
   $current_user_id = get_current_user_id();
   $table_favorites = $wpdb->prefix . 'sud_favorites';
   $favorite_id_int = intval($favorite_id);

   if ($favorite_id_int <= 0 || $favorite_id_int === $current_user_id) {
       return false;
   }

   static $fav_table_exists = null; 
   if ($fav_table_exists === null) {
       $fav_table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_favorites)) == $table_favorites);
   }
   if (!$fav_table_exists) {
       error_log("Error: Favorites table '{$table_favorites}' not found in is_user_favorite.");
       return false;
   }

   $count = $wpdb->get_var($wpdb->prepare(
       "SELECT COUNT(*) FROM $table_favorites WHERE user_id = %d AND favorite_id = %d",
       $current_user_id,
       $favorite_id_int
   ));
   return ($count > 0);
}

function toggle_user_favorite($favorite_user_id, $status = true) {
    if (!is_user_logged_in()) {
        return false;
    }

    global $wpdb;
    $current_user_id = get_current_user_id();
    $table_favorites = $wpdb->prefix . 'sud_favorites';
    $favorite_user_id_int = intval($favorite_user_id);

    if ($favorite_user_id_int <= 0 || $current_user_id === $favorite_user_id_int) {
        return false; 
    }

    static $fav_toggle_table_exists = null;
    if ($fav_toggle_table_exists === null) {
        $fav_toggle_table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_favorites)) == $table_favorites);
    }
    if (!$fav_toggle_table_exists) {
        error_log("Error: Favorites table '{$table_favorites}' not found in toggle_user_favorite.");
        return false;
    }

    $is_currently_favorite = is_user_favorite($favorite_user_id_int); 

    if ($status === true) { 
        if ($is_currently_favorite) {
            return true; 
        }
        $result = $wpdb->insert(
            $table_favorites,
            ['user_id' => $current_user_id, 'favorite_id' => $favorite_user_id_int, 'created_at' => current_time('mysql', 1)],
            ['%d', '%d', '%s']
        );
        if ($result === 1) {
            if (function_exists('add_notification')) {
                $current_user_data = get_userdata($current_user_id);
                if ($current_user_data) {
                    $recipient_is_premium = sud_is_user_premium($favorite_user_id_int);
                    $recipient_can_view = sud_user_can_access_feature($favorite_user_id_int, 'viewed_profile');
        
                    if ($recipient_can_view) {
                        $content = esc_html($current_user_data->display_name) . ' added you to their favorites.';
                    } else {
                        $content = 'Someone favorited you! Upgrade to Premium to find out who.';
                    }
                    add_notification($favorite_user_id_int, 'favorite', $content, $current_user_id);
                }
            }
            return true;
        } else {
            error_log("DB Error adding favorite: User $current_user_id -> $favorite_user_id_int. Error: " . $wpdb->last_error);
            return false; 
        }
    } else { 
        if (!$is_currently_favorite) {
            return true; 
        }
        $result = $wpdb->delete(
            $table_favorites,
            ['user_id' => $current_user_id, 'favorite_id' => $favorite_user_id_int],
            ['%d', '%d']
        );
        if ($result !== false) { 

            return true;
        } else {
            error_log("DB Error removing favorite: User $current_user_id -> $favorite_user_id_int. Error: " . $wpdb->last_error);
            return false; 
        }
    }
}

function get_user_favorites($user_id) {
    global $wpdb;
    $user_id_int = intval($user_id);
    if ($user_id_int <= 0) return [];

    $table_favorites = $wpdb->prefix . 'sud_favorites';

    static $get_fav_table_exists = null;
    if ($get_fav_table_exists === null) {
        $get_fav_table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_favorites)) == $table_favorites);
    }
    if (!$get_fav_table_exists) {
        error_log("Error: Favorites table '{$table_favorites}' not found in get_user_favorites.");
        return [];
    }

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT favorite_id, created_at FROM $table_favorites WHERE user_id = %d ORDER BY created_at DESC, favorite_id DESC",
        $user_id_int
    ), ARRAY_A);

    if (empty($results)) return [];

    $favorite_ids = [];
    $timestamps = [];
    foreach ($results as $row) {
        $fav_id = intval($row['favorite_id']);
        if ($fav_id > 0) {
            $favorite_ids[] = $fav_id;
            $timestamps[$fav_id] = $row['created_at']; 
        }
    }
    $favorite_ids = array_unique($favorite_ids);
    if (empty($favorite_ids)) return [];

    $favorite_profiles = custom_get_user_data($favorite_ids); 

    foreach ($favorite_profiles as &$profile) { 
        if (isset($timestamps[$profile['id']])) {
            $profile['favorited_at'] = $timestamps[$profile['id']]; 
        }
    }
    unset($profile); 

    return $favorite_profiles; 
}

function get_user_settings($user_id) {
    $defaults = [
        'email_notifications' => true,
        'message_notifications' => true,
        'favorite_notifications' => true,
        'view_notifications' => true,
        'match_notifications' => true,
        'private_profile' => false,
        'hide_online_status' => false,
    ];

    $user_id = intval($user_id);
    if ($user_id <= 0) return $defaults;

    $settings = get_user_meta($user_id, 'user_settings', true);
    if (!is_array($settings)) {
        $settings = [];
    }
    return array_merge($defaults, $settings);
}

function update_user_settings($user_id, $settings) {
    $user_id = intval($user_id);
    if ($user_id <= 0 || !is_array($settings)) {
        return false;
    }

    $current_settings = get_user_settings($user_id); 
    $allowed_keys = array_keys($current_settings); 
    $new_settings = $current_settings;

    foreach ($settings as $key => $value) {
        if (in_array($key, $allowed_keys)) {

            $new_settings[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
    }

    return update_user_meta($user_id, 'user_settings', $new_settings);
}

function get_who_favorited_me($user_id) {
    global $wpdb;
    $user_id_int = intval($user_id);
    if ($user_id_int <= 0) return [];
    $table_favorites = $wpdb->prefix . 'sud_favorites';

    static $who_fav_table_exists = null;
    if ($who_fav_table_exists === null) {
        $who_fav_table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_favorites)) == $table_favorites);
    }
     if (!$who_fav_table_exists) {
        error_log("Error: Favorites table '{$table_favorites}' not found in get_who_favorited_me.");
        return [];
     }

    $favorited_by_results = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, created_at FROM $table_favorites WHERE favorite_id = %d ORDER BY created_at DESC, user_id DESC",
        $user_id_int
    ), ARRAY_A);

    if (empty($favorited_by_results)) return [];

    $favoriter_ids = [];
    $timestamps = [];
    foreach ($favorited_by_results as $row) {
        $favoriter_id = intval($row['user_id']);

        if ($favoriter_id > 0 && $favoriter_id !== $user_id_int) {
            $favoriter_ids[] = $favoriter_id;
            $timestamps[$favoriter_id] = $row['created_at'];
        }
    }
    $favoriter_ids = array_unique($favoriter_ids);
    if (empty($favoriter_ids)) return [];
    $favoriter_profiles = custom_get_user_data($favoriter_ids);

    foreach ($favoriter_profiles as &$profile) {
        if (isset($timestamps[$profile['id']])) {
            $profile['favorited_at'] = $timestamps[$profile['id']];
        }
    }
    unset($profile);
    return $favoriter_profiles;
}

function get_profile_viewers($user_id) {
    $user_id = intval($user_id);
    if ($user_id <= 0) return [];

    $profile_views = get_user_meta($user_id, 'profile_viewers', true);
    if (!is_array($profile_views)) {
        return []; 
    }

    $validated_views = [];
    foreach ($profile_views as $viewer_id => $timestamp) {
        $v_id = filter_var($viewer_id, FILTER_VALIDATE_INT);
        $ts = filter_var($timestamp, FILTER_VALIDATE_INT); 
        if ($v_id && $v_id > 0 && $ts && $v_id !== $user_id) { 
            $validated_views[$v_id] = $ts;
        }
    }

    arsort($validated_views);
    return $validated_views;
}

function add_profile_view($profile_user_id, $viewer_user_id, $max_viewers = 50) {
    $profile_user_id = intval($profile_user_id);
    $viewer_user_id = intval($viewer_user_id);

    if ($profile_user_id <= 0 || $viewer_user_id <= 0 || $profile_user_id === $viewer_user_id) {
        return false;
    }

    // Don't track views from admins or chat moderators
    if (user_can($viewer_user_id, 'administrator') || user_can($viewer_user_id, 'sud_moderate_chat')) {
        return false;
    }

    $viewers = get_profile_viewers($profile_user_id);
    $viewers[$viewer_user_id] = current_time('timestamp', true);
    arsort($viewers);

    if (count($viewers) > $max_viewers) {
        $viewers = array_slice($viewers, 0, $max_viewers, true); 
    }

    $result = update_user_meta($profile_user_id, 'profile_viewers', $viewers);
    if ($result && function_exists('add_notification')) {
        $viewer_data = get_userdata($viewer_user_id);
        if ($viewer_data) {
            $recipient_is_premium = sud_is_user_premium($profile_user_id);
            $recipient_can_view = sud_user_can_access_feature($profile_user_id, 'viewed_profile');
   
            if ($recipient_can_view) {
                $content = esc_html($viewer_data->display_name) . ' viewed your profile.';
            } else {
                $content = 'Someone viewed your profile! Upgrade to Premium to see who it was.';
            }
            add_notification($profile_user_id, 'profile_view', $content, $viewer_user_id);
        }
    }
    return $result;
}

function search_users( $criteria, $page = 1, $per_page = 12, $base_meta_query = [] ) {
    global $wpdb;
    $current_user_id = get_current_user_id();
    $page            = max(1, intval($page));
    $per_page        = max(1, intval($per_page));
    $offset          = ( $page - 1 ) * $per_page;

    $exclude_ids = get_excluded_user_ids( $current_user_id );

    $location_ids = [];
    if ( ! empty( $criteria['location'] ) ) {
        $raw  = sanitize_text_field( $criteria['location'] );
        $like = '%' . $wpdb->esc_like( $raw ) . '%';

        $location_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT user_id
                 FROM {$wpdb->usermeta}
                 WHERE meta_key IN ('city','region','country','location_string')
                   AND meta_value LIKE %s",
                $like
            )
        );

        $location_ids = array_diff( $location_ids, $exclude_ids );
        if ( empty( $location_ids ) ) {
            return [
                'users'    => [],
                'total'    => 0,
                'page'     => $page,
                'pages'    => 0,
                'per_page' => $per_page,
            ];
        }
    }

    $query_args = [
        'fields'      => 'ID',
        'number'      => $per_page,
        'offset'      => $offset,
        'meta_key'    => 'last_active',
        'orderby'     => 'meta_value_num',
        'order'       => 'DESC',
        'exclude'     => $exclude_ids,
        'meta_query'  => $base_meta_query,
        'count_total' => true,
    ];

    if ( ! empty( $location_ids ) ) {
        $query_args['include'] = $location_ids;
        unset( $query_args['exclude'] );
    }

    if ( ! empty( $criteria['min_age'] ) && ! empty( $criteria['max_age'] ) ) {
        $today         = new DateTime();
        $latest_birth  = (clone $today)->modify( '-' . intval( $criteria['min_age'] ) . ' years' )->format( 'Y-m-d' );
        $earliest_birth = (clone $today)
            ->modify( '-' . ( intval( $criteria['max_age'] ) + 1 ) . ' years' )
            ->modify( '+1 day' )
            ->format( 'Y-m-d' );

        $query_args['meta_query'][] = [
            'key'     => 'date_of_birth',
            'value'   => [ $earliest_birth, $latest_birth ],
            'compare' => 'BETWEEN',
            'type'    => 'DATE',
        ];
    }

    if ( ! empty( $criteria['gender'] ) ) {
        $query_args['meta_query'][] = [
            'key'     => 'gender',
            'value'   => (array) $criteria['gender'],
            'compare' => 'IN',
        ];
    }
    if ( ! empty( $criteria['looking_for'] ) ) {
        $query_args['meta_query'][] = [
            'key'     => 'looking_for',
            'value'   => sanitize_text_field( $criteria['looking_for'] ),
            'compare' => '=',
        ];
    }

    if ( sud_user_can_access_feature( $current_user_id, 'advanced_filters' ) ) {
        if ( ! empty( $criteria['ethnicity'] ) ) {
            $query_args['meta_query'][] = [
                'key'     => 'ethnicity',
                'value'   => sanitize_text_field( $criteria['ethnicity'] ),
                'compare' => '=',
            ];
        }
        if ( ! empty( $criteria['verified_only'] ) ) {
            $query_args['meta_query'][] = [
                'key'     => 'is_verified',
                'value'   => '1',
                'compare' => '=',
            ];
        }
        if ( ! empty( $criteria['online_only'] ) ) {
            $threshold = current_time( 'timestamp', true ) - 300;
            $query_args['meta_query'][] = [
                'key'     => 'last_active',
                'value'   => $threshold,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ];
        }
        if ( ! empty( $criteria['body_type'] ) ) {
            $query_args['meta_query'][] = [
                'key'     => 'body_type',
                'value'   => sanitize_text_field( $criteria['body_type'] ),
                'compare' => '=',
            ];
        }
    }

    $query_args['meta_query'][] = [ 'key' => 'profile_completed',           'value' => '1' ];
    $query_args['meta_query'][] = [ 'key' => 'user_photos',    'compare' => 'EXISTS' ];
    $query_args['meta_query'][] = [ 'key' => 'user_photos',    'value' => 'a:0:{}', 'compare' => '!=' ];
    $query_args['meta_query'][] = [ 'key' => 'user_photos',    'value' => '',      'compare' => '!=' ];

    $user_query = new WP_User_Query( $query_args );
    $total      = $user_query->get_total();

    unset( $query_args['count_total'] );
    $user_query = new WP_User_Query( $query_args );
    $user_ids   = $user_query->get_results();

    $users = ! empty( $user_ids ) ? custom_get_user_data( $user_ids ) : [];
    
    // Apply centralized profile visibility validation (80% completion + photo)  
    // Keep fetching more users until we have the required number per page
    if (!empty($users) && function_exists('sud_is_user_profile_visible')) {
        $filtered_users = array_filter($users, function($user_profile) {
            return sud_is_user_profile_visible($user_profile['id'] ?? 0);
        });
        $filtered_users = array_values($filtered_users);
        
        // If we don't have enough users after filtering, fetch more to fill the page
        $attempt = 1;
        $max_attempts = 5; // Prevent infinite loops
        $fetch_multiplier = 2; // Fetch 2x more users each attempt
        
        while (count($filtered_users) < $per_page && $attempt <= $max_attempts) {
            $additional_offset = ($page - 1) * $per_page + (count($users) * $attempt);
            $additional_limit = $per_page * $fetch_multiplier;
            
            // Fetch more users with same criteria but different offset
            $additional_query_args = $query_args;
            $additional_query_args['offset'] = $additional_offset;
            $additional_query_args['number'] = $additional_limit;
            unset($additional_query_args['count_total']);
            
            $additional_query = new WP_User_Query($additional_query_args);
            $additional_user_ids = $additional_query->get_results();
            
            if (empty($additional_user_ids)) {
                break; // No more users available
            }
            
            $additional_users = custom_get_user_data($additional_user_ids);
            $additional_filtered = array_filter($additional_users, function($user_profile) {
                return sud_is_user_profile_visible($user_profile['id'] ?? 0);
            });
            
            $filtered_users = array_merge($filtered_users, array_values($additional_filtered));
            $users = array_merge($users, $additional_users);
            $attempt++;
        }
        
        // Limit to exactly the requested per_page amount
        $users = array_slice($filtered_users, 0, $per_page);
    }
    
    $pages = $total ? ceil( $total / $per_page ) : 0;

    return [
        'users'    => $users,
        'total'    => (int) $total,
        'page'     => $page,
        'pages'    => (int) $pages,
        'per_page' => $per_page,
    ];
}

function get_total_user_count_filtered($current_user_id, $meta_query_args = []) {
    $exclude_ids = get_excluded_user_ids($current_user_id);

    $final_meta_query = $meta_query_args;
    if (empty($final_meta_query) || !is_array($final_meta_query)) {
         $final_meta_query = ['relation' => 'AND'];
    } elseif (!isset($final_meta_query['relation'])) {
         $final_meta_query['relation'] = 'AND';
    }
    $final_meta_query['completion_clause'] = ['key' => 'profile_completed', 'value' => '1'];
    $final_meta_query['photo_clause'] = ['key' => 'user_photos', 'compare' => 'EXISTS'];
    $final_meta_query['photo_clause_not_empty'] = ['key' => 'user_photos', 'value' => 'a:0:{}', 'compare' => '!='];
    $final_meta_query['photo_clause_not_empty_str'] = ['key' => 'user_photos', 'value' => '', 'compare' => '!='];

    $query_args = [
        'fields' => 'ID',
        'exclude' => $exclude_ids,
        'meta_query' => $final_meta_query,
        'count_total' => true,
        'number' => 1
    ];

    $user_query = new WP_User_Query($query_args);
    return (int) $user_query->get_total();
}

function get_total_user_count_nearby($current_user_id, $meta_query_args = []) {
    global $wpdb;

    $current_user_id = intval($current_user_id);
    $exclude_ids = get_excluded_user_ids($current_user_id);
    $nearby_user_ids = [];

    $user_lat = get_user_meta($current_user_id, 'latitude', true);
    $user_lng = get_user_meta($current_user_id, 'longitude', true);
    $user_city = get_user_meta($current_user_id, 'city', true);

    $fetch_limit_sql = 500;
    $exclude_placeholders = !empty($exclude_ids) ? implode(',', array_fill(0, count($exclude_ids), '%d')) : '0';
    $exclude_params = $exclude_ids;

    if (!empty($user_lat) && !empty($user_lng)) {
         $user_lat = floatval($user_lat); $user_lng = floatval($user_lng);
         $distance_km = 150;
         $lat_range = $distance_km / 111.32; $lng_range = $distance_km / (111.32 * cos(deg2rad($user_lat)) + 0.00001);
         $min_lat = $user_lat - $lat_range; $max_lat = $user_lat + $lat_range;
         $min_lng = $user_lng - $lng_range; $max_lng = $user_lng + $lng_range;

        $coord_query = $wpdb->prepare(
            "SELECT DISTINCT u.ID
                FROM {$wpdb->users} AS u
                INNER JOIN {$wpdb->usermeta} AS lat_meta ON u.ID = lat_meta.user_id AND lat_meta.meta_key = 'latitude'
                INNER JOIN {$wpdb->usermeta} AS lng_meta ON u.ID = lng_meta.user_id AND lng_meta.meta_key = 'longitude'
                WHERE lat_meta.meta_value BETWEEN %f AND %f
                AND lng_meta.meta_value BETWEEN %f AND %f
                AND u.ID NOT IN ($exclude_placeholders)
                LIMIT %d",
            array_merge([$min_lat, $max_lat, $min_lng, $max_lng], $exclude_params, [$fetch_limit_sql])
        );
        $nearby_user_ids = $wpdb->get_col($coord_query);

    } elseif (!empty($user_city)) {
        // City-based ID fetching (same SQL as get_nearby_members)
        $city_query = $wpdb->prepare(
            "SELECT DISTINCT u.ID
            FROM {$wpdb->users} AS u
            INNER JOIN {$wpdb->usermeta} AS city_meta ON u.ID = city_meta.user_id AND city_meta.meta_key = 'city'
            WHERE city_meta.meta_value LIKE %s
            AND u.ID NOT IN ($exclude_placeholders)
            LIMIT %d",
            array_merge([$user_city], $exclude_params, [$fetch_limit_sql])
        );
        $nearby_user_ids = $wpdb->get_col($city_query);
    }

    if (empty($nearby_user_ids)) {
        return 0;
    }

    $query_args = [
        'include' => $nearby_user_ids,
        'fields' => 'ID',
        'meta_query' => $meta_query_args,
        'count_total' => true,
        'number' => 1
    ];

    if (empty($query_args['meta_query']) || !is_array($query_args['meta_query'])) {
        $query_args['meta_query'] = ['relation' => 'AND'];
    } elseif (!isset($query_args['meta_query']['relation'])) {
        $query_args['meta_query']['relation'] = 'AND';
    }
    $query_args['meta_query']['completion_clause'] = ['key' => 'profile_completed', 'value' => '1'];
    $query_args['meta_query']['photo_clause'] = ['key' => 'user_photos', 'compare' => 'EXISTS'];
    $query_args['meta_query']['photo_clause_not_empty'] = ['key' => 'user_photos', 'value' => 'a:0:{}', 'compare' => '!='];
    $query_args['meta_query']['photo_clause_not_empty_str'] = ['key' => 'user_photos', 'value' => '', 'compare' => '!='];

    $user_query = new WP_User_Query($query_args);
    return (int) $user_query->get_total();
}

function filter_quality_profiles($users) {
    if (empty($users) || !is_array($users)) {
        return [];
    }

    $filtered_users = [];
    foreach ($users as $user) {
        if (!is_array($user) || empty($user['id'])) {
            continue;
        }

        $user_id = $user['id'];
        
        // Use centralized profile visibility validation
        if (sud_is_user_profile_visible($user_id)) {
            $filtered_users[] = $user;
        }
    }
    
    // Apply priority sorting (SUD/SUD users first)
    return sud_sort_users_with_priority($filtered_users, 'last_active');
}

/**
 * Centralized profile visibility validation
 * Checks if a user profile is complete enough to be shown to others
 * 
 * @param int $user_id The user ID to check
 * @return bool True if profile meets visibility requirements (80% + photo)
 */
function sud_is_user_profile_visible($user_id) {
    if (!$user_id || !is_numeric($user_id)) {
        return false;
    }
    
    // Check compulsory Step 0 (interests/relationship terms)
    $is_step0_completed = get_user_meta($user_id, 'completed_step_0', true);
    $relationship_terms = get_user_meta($user_id, 'relationship_terms', true);
    $has_valid_terms = is_array($relationship_terms) && count($relationship_terms) >= 1; // Relaxed from 3 to 1 for visibility
    
    if (!$is_step0_completed || !$has_valid_terms) {
        return false;
    }
    
    // Check compulsory photos
    $user_photos = get_user_meta($user_id, 'user_photos', true);
    $has_photos = !empty($user_photos) && is_array($user_photos) && count($user_photos) > 0;
    
    if (!$has_photos) {
        // Also check for profile picture as backup
        $profile_picture_id = get_user_meta($user_id, 'profile_picture', true);
        $has_profile_pic = !empty($profile_picture_id) && is_numeric($profile_picture_id);
        if (!$has_profile_pic) {
            return false;
        }
    }
    
    // Check profile completion percentage (must be 80% or higher)
    $completion_percentage = function_exists('get_profile_completion_percentage') 
        ? get_profile_completion_percentage($user_id) 
        : 0;
    
    if ($completion_percentage < 80) {
        return false;
    }
    
    return true;
}

/**
 * Check if a user is a SUD/SUD team member (priority user)
 * 
 * @param int $user_id The user ID to check
 * @return bool True if user is SUD/SUD team member
 */
function sud_is_priority_user($user_id) {
    if (!$user_id || !is_numeric($user_id)) {
        return false;
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $email = $user->user_email;
    
    // Check if email ends with @sud.com (SUD team domain)
    $is_priority = str_ends_with($email, '@sud.com');
    
    return $is_priority;
}

/**
 * Sort users with priority users first, then by other criteria
 * 
 * @param array $users Array of user IDs or user objects
 * @param string $fallback_sort Optional fallback sorting criteria ('last_active', 'random', etc.)
 * @return array Sorted array with priority users first
 */
function sud_sort_users_with_priority($users, $fallback_sort = 'last_active') {
    if (empty($users) || !is_array($users)) {
        return $users;
    }
    
    $priority_users = [];
    $regular_users = [];
    
    foreach ($users as $user) {
        $user_id = is_array($user) ? ($user['id'] ?? $user['ID'] ?? 0) : (is_object($user) ? $user->ID : $user);
        
        if (sud_is_priority_user($user_id)) {
            $priority_users[] = $user;
        } else {
            $regular_users[] = $user;
        }
    }
    
    // Sort each group based on fallback criteria
    if ($fallback_sort === 'last_active') {
        usort($priority_users, function($a, $b) {
            $user_id_a = is_array($a) ? ($a['id'] ?? $a['ID'] ?? 0) : (is_object($a) ? $a->ID : $a);
            $user_id_b = is_array($b) ? ($b['id'] ?? $b['ID'] ?? 0) : (is_object($b) ? $b->ID : $b);
            
            $last_active_a = get_user_meta($user_id_a, 'last_active', true) ?: 0;
            $last_active_b = get_user_meta($user_id_b, 'last_active', true) ?: 0;
            
            return $last_active_b - $last_active_a; // Most recent first
        });
        
        usort($regular_users, function($a, $b) {
            $user_id_a = is_array($a) ? ($a['id'] ?? $a['ID'] ?? 0) : (is_object($a) ? $a->ID : $a);
            $user_id_b = is_array($b) ? ($b['id'] ?? $b['ID'] ?? 0) : (is_object($b) ? $b->ID : $b);
            
            $last_active_a = get_user_meta($user_id_a, 'last_active', true) ?: 0;
            $last_active_b = get_user_meta($user_id_b, 'last_active', true) ?: 0;
            
            return $last_active_b - $last_active_a; // Most recent first
        });
    } else if ($fallback_sort === 'random') {
        shuffle($priority_users);
        shuffle($regular_users);
    }
    
    // Combine priority users first, then regular users
    return array_merge($priority_users, $regular_users);
}

function sud_custom_avatar_data( $args, $id_or_email ) {
    $user_id = false;

    if ( is_numeric( $id_or_email ) ) {
        $user_id = (int) $id_or_email;
    } elseif ( is_object( $id_or_email ) ) {
        if ( $id_or_email instanceof WP_User ) {
            $user_id = $id_or_email->ID;
        }
        elseif ( $id_or_email instanceof WP_Post ) {
            $user_id = (int) $id_or_email->post_author;
        }
        elseif ( $id_or_email instanceof WP_Comment ) {
            if ( ! empty( $id_or_email->user_id ) ) {
                $user_id = (int) $id_or_email->user_id;
            }
        }
        elseif ( ! empty( $id_or_email->user_id ) ) {
            $user_id = (int) $id_or_email->user_id;
        }
    } elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
        $user = get_user_by( 'email', $id_or_email );
        if ( $user ) {
            $user_id = $user->ID;
        }
    }

    if ( $user_id ) {
        $profile_picture_id = get_user_meta( $user_id, 'profile_picture', true );
        if ( ! empty( $profile_picture_id ) && is_numeric( $profile_picture_id ) ) {
            $image_url = wp_get_attachment_image_url( (int) $profile_picture_id, 'thumbnail' ); 
            
            if ( $image_url ) {
                $args['url'] = $image_url;
                $args['found_avatar'] = true; 
            }
        }
    }
    return $args;
}
add_filter( 'get_avatar_data', 'sud_custom_avatar_data', 20, 2 );

/**
 * Grant initial subscription bonuses when user subscribes to a tier
 * 
 * @param int $user_id The user ID
 * @param string $plan_id The plan ID (gold, diamond)
 */
function sud_grant_initial_subscription_bonuses($user_id, $plan_id) {
    if (!$user_id || !$plan_id) {
        return;
    }
    
    // Get plan details to know what bonuses to grant
    $plans = sud_get_all_plans_with_pricing();
    $plan_details = $plans[$plan_id] ?? null;
    
    if (!$plan_details) {
        error_log("Unable to grant subscription bonuses for user $user_id: Invalid plan $plan_id");
        return;
    }
    
    // Grant free swipe-ups (reset daily allowance)
    $daily_swipe_ups = $plan_details['free_swipe_ups_daily'] ?? 0;
    if ($daily_swipe_ups > 0) {
        // Reset the user's daily swipe-up usage so they get immediate access
        update_user_meta($user_id, 'sud_free_swipe_ups_used_today', 0);
        update_user_meta($user_id, 'sud_free_swipe_ups_last_used_date', current_time('Y-m-d'));
    }
    
    // Grant weekly mini-boosts (if applicable)
    $weekly_mini_boosts = $plan_details['weekly_mini_boosts'] ?? 0;
    if ($weekly_mini_boosts > 0) {
        // Set up weekly mini-boost balance
        $current_mini_boosts = (int) get_user_meta($user_id, 'sud_weekly_mini_boosts_balance', true);
        $new_balance = $current_mini_boosts + $weekly_mini_boosts;
        update_user_meta($user_id, 'sud_weekly_mini_boosts_balance', $new_balance);
        update_user_meta($user_id, 'sud_last_weekly_boost_grant', current_time('Y-m-d'));
    }
    
    // Log bonus granting
}

/**
 * Schedule recurring boost grants for premium users
 */
function sud_schedule_recurring_boost_grants() {
    // Schedule weekly mini-boost reset (every Monday at 00:00)
    if (!wp_next_scheduled('sud_weekly_boost_reset')) {
        wp_schedule_event(strtotime('next Monday 00:00:00'), 'weekly', 'sud_weekly_boost_reset');
    }
    
    // Schedule monthly boost reset (1st of each month at 00:00)
    if (!wp_next_scheduled('sud_monthly_boost_reset')) {
        $next_month = strtotime('first day of next month 00:00:00');
        wp_schedule_event($next_month, 'monthly', 'sud_monthly_boost_reset');
    }
}

/**
 * Grant weekly mini-boosts to all premium users
 */
function sud_grant_weekly_boosts() {
    $plans = sud_get_all_plans_with_pricing();
    
    foreach ($plans as $plan_id => $plan_details) {
        if ($plan_id === 'free') continue;
        
        $weekly_boosts = $plan_details['weekly_mini_boosts'] ?? 0;
        if ($weekly_boosts <= 0) continue;
        
        // Get all users with this plan
        $users = get_users([
            'meta_query' => [
                [
                    'key' => 'premium_plan',
                    'value' => $plan_id,
                    'compare' => '='
                ]
            ],
            'fields' => 'ID'
        ]);
        
        foreach ($users as $user_id) {
            // Grant weekly mini-boosts
            $current_balance = (int) get_user_meta($user_id, 'sud_weekly_mini_boosts_balance', true);
            $new_balance = $current_balance + $weekly_boosts;
            update_user_meta($user_id, 'sud_weekly_mini_boosts_balance', $new_balance);
            update_user_meta($user_id, 'sud_last_weekly_boost_grant', current_time('Y-m-d'));
        }
        
    }
}

/**
 * Grant monthly profile boosts to all premium users
 */
function sud_grant_monthly_boosts() {
    $plans = sud_get_all_plans_with_pricing();
    
    foreach ($plans as $plan_id => $plan_details) {
        if ($plan_id === 'free') continue;
        
        $monthly_boosts = $plan_details['weekly_mini_boosts'] ?? 0; // Using weekly_mini_boosts as monthly allocation
        if ($monthly_boosts <= 0) continue;
        
        // Get all users with this plan
        $users = get_users([
            'meta_query' => [
                [
                    'key' => 'premium_plan',
                    'value' => $plan_id,
                    'compare' => '='
                ]
            ],
            'fields' => 'ID'
        ]);
        
        foreach ($users as $user_id) {
            // Grant monthly profile boosts
            $current_balance = (int) get_user_meta($user_id, 'sud_monthly_boost_balance', true);
            $new_balance = $current_balance + $monthly_boosts;
            update_user_meta($user_id, 'sud_monthly_boost_balance', $new_balance);
            update_user_meta($user_id, 'sud_last_monthly_boost_grant', current_time('Y-m-d'));
        }
        
    }
}

// Hook the recurring grant functions
add_action('sud_weekly_boost_reset', 'sud_grant_weekly_boosts');
add_action('sud_monthly_boost_reset', 'sud_grant_monthly_boosts');

// Schedule the events on init
add_action('init', 'sud_schedule_recurring_boost_grants');