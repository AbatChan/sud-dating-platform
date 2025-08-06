<?php

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/user-functions.php');

function geocode_coordinates($latitude, $longitude) {
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        return false;
    }

    $lat_rounded = round($latitude, 3);
    $lng_rounded = round($longitude, 3);
    $cache_key = 'sud_geocode_struct_' . $lat_rounded . '_' . $lng_rounded; 

    $cached_result = get_transient($cache_key);
    if ($cached_result !== false) {
        return (is_array($cached_result) && isset($cached_result['display_string'])) ? $cached_result : false;
    }

    $location_data = nominatim_geocode($latitude, $longitude);

    if ($location_data === false) {
        $location_data = ipapi_geocode(); 
    }

    if ($location_data !== false) {
        set_transient($cache_key, $location_data, 30 * DAY_IN_SECONDS);
    } else {
        set_transient($cache_key, ['display_string' => ''], DAY_IN_SECONDS); 
        return false;
    }
    return $location_data;
}

function nominatim_geocode($latitude, $longitude) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=18&addressdetails=1";
    usleep(rand(150000, 350000)); 

    $args = [
        'timeout' => 10, 
        'user-agent' => 'SUD Location Service/' . SUD_URL, 
        'headers' => [
            'Accept-Language' => 'en-US,en;q=0.9' 
        ]
    ];
    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        error_log("Nominatim Geocode Error (WP Error): " . $response->get_error_message());
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
         error_log("Nominatim Geocode Error (HTTP Status): " . $status_code);
         return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() === JSON_ERROR_NONE && isset($data['address'])) {
        $a = $data['address'];

        $city = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['hamlet'] ?? null;
        $region = $a['state'] ?? $a['state_district'] ?? $a['county'] ?? null; 
        $country = $a['country'] ?? null;

        $parts = array_filter([$city, $region, $country]); 
        $display_string = implode(', ', $parts);

        if (empty($display_string) && isset($data['display_name'])) {
             $display_string = $data['display_name']; 
        }

        if (!empty($country) || !empty($display_string)) {
            return [
                'city' => $city,
                'region' => $region,
                'country' => $country,
                'display_string' => $display_string ?: ''
            ];
        }
    } else {
        error_log("Nominatim Geocode Error (JSON Decode/No Address): " . json_last_error_msg());
    }
    return false;
}

function ipapi_geocode() {
    $url = 'https://ipapi.co/json/';
    $response = wp_remote_get($url, ['timeout' => 5]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        error_log("IP API Geocode Error: " . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response)));
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() === JSON_ERROR_NONE && !empty($data['country_name'])) {
        $city = $data['city'] ?? null;
        $region = $data['region'] ?? null;
        $country = $data['country_name'] ?? null;

        $parts = array_filter([$city, $region, $country]);
        $display_string = implode(', ', $parts);

        return [
            'city' => $city,
            'region' => $region,
            'country' => $country,
            'display_string' => $display_string ?: ''
        ];
    }
    return false;
}

function update_user_location($user_id, $latitude, $longitude, $accuracy = 'medium') {
    if (!$user_id || !is_numeric($user_id) || !is_numeric($latitude) || !is_numeric($longitude)) {
        return false;
    }

    $latitude = floatval($latitude);
    $longitude = floatval($longitude);

    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        return false;
    }

    $accuracy_str = is_numeric($accuracy) ? 'approx ' . round($accuracy) . 'm' : sanitize_text_field($accuracy);

    update_user_meta($user_id, 'latitude', $latitude);
    update_user_meta($user_id, 'longitude', $longitude);
    update_user_meta($user_id, 'location_updated', current_time('timestamp', true)); 
    update_user_meta($user_id, 'location_accuracy', $accuracy_str);

    $geocoded_data = geocode_coordinates($latitude, $longitude);

    if ($geocoded_data !== false && is_array($geocoded_data)) {
        update_user_meta($user_id, 'city', $geocoded_data['city'] ?? '');
        update_user_meta($user_id, 'region', $geocoded_data['region'] ?? '');
        update_user_meta($user_id, 'country', $geocoded_data['country'] ?? '');
        update_user_meta($user_id, 'location_string', $geocoded_data['display_string'] ?? ''); 
        delete_user_meta($user_id, 'location');
    } else {
        update_user_meta($user_id, 'city', '');
        update_user_meta($user_id, 'region', '');
        update_user_meta($user_id, 'country', '');
        update_user_meta($user_id, 'location_string', ''); 
        delete_user_meta($user_id, 'location');
        error_log("update_user_location: Geocoding failed for UserID $user_id");
    }

    delete_user_meta($user_id, 'location_needs_update');
    return true;
}

function calculate_distance($lat1, $lon1, $lat2, $lon2) {

    if (!is_numeric($lat1) || !is_numeric($lon1) || !is_numeric($lat2) || !is_numeric($lon2)) {
        return false;
    }
     if (abs($lat1) > 90 || abs($lon1) > 180 || abs($lat2) > 90 || abs($lon2) > 180) {
         return false;
     }

    $radius = 6371; 
    $lat1_rad = deg2rad(floatval($lat1));
    $lon1_rad = deg2rad(floatval($lon1));
    $lat2_rad = deg2rad(floatval($lat2));
    $lon2_rad = deg2rad(floatval($lon2));

    $dlat = $lat2_rad - $lat1_rad;
    $dlon = $lon2_rad - $lon1_rad;

    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1_rad) * cos($lat2_rad) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $radius * $c;
}

function prioritize_nearby_users($users, $current_user_id) {
    if (empty($users) || !is_array($users)) {
        return [];
    }

    $current_user_profile = get_user_profile_data($current_user_id); 
    $user_lat = $current_user_profile['latitude'] ?? null;
    $user_lng = $current_user_profile['longitude'] ?? null;

    if (empty($user_lat) || empty($user_lng)) {
        return $users;
    }

    foreach ($users as $key => &$user) {
         if (!is_array($user) || !isset($user['id'])) {
            unset($users[$key]); 
            continue;
        }

        $lat = $user['latitude'] ?? null;
        $lng = $user['longitude'] ?? null;

        if (!empty($lat) && !empty($lng)) {
            $distance = calculate_distance($user_lat, $user_lng, $lat, $lng);
             if ($distance !== false) {
                 $user['distance'] = $distance;
                 $user['has_location'] = true;
             } else {
                 $user['distance'] = PHP_INT_MAX; 
                 $user['has_location'] = false;
             }
        } else {
            $user['distance'] = PHP_INT_MAX;
            $user['has_location'] = false;
        }
    }
    unset($user); 

    usort($users, function($a, $b) {
        $a_has_loc = $a['has_location'] ?? false;
        $b_has_loc = $b['has_location'] ?? false;

        if ($a_has_loc && !$b_has_loc) return -1;
        if (!$a_has_loc && $b_has_loc) return 1;

        $a_dist = $a['distance'] ?? PHP_INT_MAX;
        $b_dist = $b['distance'] ?? PHP_INT_MAX;
        return $a_dist <=> $b_dist;
    });

    return $users;
}

function get_nearby_members($current_user_id, $limit = 12, $offset = 0, $meta_query_args = []) {
    global $wpdb;

    $current_user_id = intval($current_user_id);
    $limit = max(1, intval($limit));
    $offset = max(0, intval($offset));

    $exclude_ids = get_excluded_user_ids($current_user_id);
    $nearby_user_ids = [];

    $user_lat = get_user_meta($current_user_id, 'latitude', true);
    $user_lng = get_user_meta($current_user_id, 'longitude', true);
    $user_city = get_user_meta($current_user_id, 'city', true);

    $fetch_limit_sql = $limit * 10;

    if (!empty($user_lat) && !empty($user_lng)) {
        $user_lat = floatval($user_lat);
        $user_lng = floatval($user_lng);
        $distance_km = 150;
        $lat_range = $distance_km / 111.32;
        $lng_range = $distance_km / (111.32 * cos(deg2rad($user_lat)) + 0.00001);
        $min_lat = $user_lat - $lat_range; $max_lat = $user_lat + $lat_range;
        $min_lng = $user_lng - $lng_range; $max_lng = $user_lng + $lng_range;

        $exclude_placeholders = !empty($exclude_ids) ? implode(',', array_fill(0, count($exclude_ids), '%d')) : '0';
        $exclude_params = $exclude_ids;

        $coord_query = $wpdb->prepare(
            "SELECT DISTINCT u.ID
            FROM {$wpdb->users} AS u
            INNER JOIN {$wpdb->usermeta} AS lat_meta ON u.ID = lat_meta.user_id AND lat_meta.meta_key = 'latitude'
            INNER JOIN {$wpdb->usermeta} AS lng_meta ON u.ID = lng_meta.user_id AND lng_meta.meta_key = 'longitude'
            WHERE lat_meta.meta_value BETWEEN %f AND %f
              AND lng_meta.meta_value BETWEEN %f AND %f
              AND u.ID NOT IN ($exclude_placeholders)
            LIMIT %d",
            array_merge(
                [$min_lat, $max_lat, $min_lng, $max_lng],
                $exclude_params,
                [$fetch_limit_sql]
             )
        );
        $nearby_user_ids = $wpdb->get_col($coord_query);

    } elseif (!empty($user_city)) {
        $exclude_placeholders = !empty($exclude_ids) ? implode(',', array_fill(0, count($exclude_ids), '%d')) : '0';
        $exclude_params = $exclude_ids;
        $city_like = '%' . $wpdb->esc_like( $user_city ) . '%';

        $city_query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} AS u
            INNER JOIN {$wpdb->usermeta} AS city_meta
            ON u.ID = city_meta.user_id
            AND city_meta.meta_key = 'city'
            WHERE city_meta.meta_value LIKE %s
            AND $base_where",
            array_merge( [ $city_like ], $exclude_params )
        );
        $nearby_user_ids = $wpdb->get_col($city_query);
    }

    if (empty($nearby_user_ids)) {
        return [];
    }

    $query_args = [
        'include' => $nearby_user_ids,
        'number' => -1,
        'fields' => 'ID',
        'meta_query' => $meta_query_args
    ];

    $query_args['meta_query']['completion_clause'] = ['key' => 'profile_completed', 'value' => '1'];
    $query_args['meta_query']['photo_clause'] = ['key' => 'user_photos', 'compare' => 'EXISTS'];
    $query_args['meta_query']['photo_clause_not_empty'] = ['key' => 'user_photos', 'value' => 'a:0:{}', 'compare' => '!='];
    $query_args['meta_query']['photo_clause_not_empty_str'] = ['key' => 'user_photos', 'value' => '', 'compare' => '!='];
    if (!isset($query_args['meta_query']['relation'])) {
        $query_args['meta_query']['relation'] = 'AND';
    }

    $user_query = new WP_User_Query($query_args);
    $filtered_nearby_ids = $user_query->get_results();

    if (empty($filtered_nearby_ids)) {
        return [];
    }

    $users_data = custom_get_user_data($filtered_nearby_ids);

    if (!empty($user_lat) && !empty($user_lng)) {
        foreach ($users_data as &$user) {
            $lat = $user['latitude'] ?? null;
            $lng = $user['longitude'] ?? null;
            if ($lat && $lng) {
                $distance = calculate_distance($user_lat, $user_lng, $lat, $lng);
                $user['distance'] = ($distance !== false) ? $distance : PHP_INT_MAX;
            } else {
                $user['distance'] = PHP_INT_MAX;
            }
        }
        unset($user);

        usort($users_data, function($a, $b) {
            $a_dist = $a['distance'] ?? PHP_INT_MAX;
            $b_dist = $b['distance'] ?? PHP_INT_MAX;
            return $a_dist <=> $b_dist;
        });
    }

    $final_users = array_slice($users_data, $offset, $limit);
    return $final_users;
}

function get_excluded_user_ids($current_user_id) {
    // Exclude administrators, editors, and chat moderators from public areas
    $admin_users = get_users(['role__in' => ['administrator', 'editor', 'sud_chat_moderator'], 'fields' => 'ID']); 
    $exclude_ids = array_map('intval', $admin_users);

    if ($current_user_id > 0 && !in_array($current_user_id, $exclude_ids)) {
        $exclude_ids[] = intval($current_user_id);
    }
    return array_unique($exclude_ids); 
}

function custom_get_users($current_user_id = 0, $limit = 6, $offset = 0, $passed_args = []) {
    $limit = max(1, intval($limit));
    $offset = max(0, intval($offset));
    $fetch_limit = $limit;

    $default_args = [
        'number' => $fetch_limit,
        'offset' => $offset,
        'fields' => 'ID',
        'orderby' => 'user_registered', 
        'order' => 'DESC',              
        'exclude' => get_excluded_user_ids($current_user_id),
    ];

    $query_args = wp_parse_args($passed_args, $default_args);

    $final_meta_query = ['relation' => 'AND'];
    $final_meta_query['completion_clause'] = ['key' => 'profile_completed', 'value' => '1'];
    $final_meta_query['photo_clause'] = ['key' => 'user_photos', 'compare' => 'EXISTS'];
    $final_meta_query['photo_clause_not_empty'] = ['key' => 'user_photos', 'value' => 'a:0:{}', 'compare' => '!='];
    $final_meta_query['photo_clause_not_empty_str'] = ['key' => 'user_photos', 'value' => '', 'compare' => '!='];

    if (isset($passed_args['meta_query']) && is_array($passed_args['meta_query'])) {
        foreach ($passed_args['meta_query'] as $key => $clause) {
            if ($key === 'relation') {
                $final_meta_query['relation'] = $clause;
            } elseif (!isset($final_meta_query[$key])) {
                $final_meta_query[$key] = $clause;
            }
        }
    }

    $query_args['meta_query'] = $final_meta_query;

    if (!is_array($query_args['exclude'])) {
        $query_args['exclude'] = empty($query_args['exclude']) ? [] : [$query_args['exclude']];
    }

    $current_orderby_setting = $query_args['orderby'] ?? 'user_registered';
    $current_order_direction = $query_args['order'] ?? 'DESC';

    $new_orderby_array = [];

    if (is_array($current_orderby_setting)) {
        $new_orderby_array = $current_orderby_setting;
        $has_id_key = false;
        foreach (array_keys($new_orderby_array) as $key) {
            if (in_array(strtolower($key), ['id', 'user_id'])) {
                $has_id_key = true;
                break;
            }
        }
        if (!$has_id_key) {
            $new_orderby_array['ID'] = 'DESC'; 
        }
    } else {
        if (strtolower($current_orderby_setting) !== 'none' && strtolower($current_orderby_setting) !== 'include') {
            $new_orderby_array[$current_orderby_setting] = $current_order_direction;
            if (strtolower($current_orderby_setting) !== 'id') {
                $new_orderby_array['ID'] = 'DESC'; 
            }
        } else {
            $new_orderby_array = $current_orderby_setting;
        }
    }

    $query_args['orderby'] = $new_orderby_array;

    if (is_array($query_args['orderby'])) {
        unset($query_args['order']);
    }

    $user_query = new WP_User_Query($query_args);
    $user_ids = $user_query->get_results();

    if (empty($user_ids)) {
        return [];
    }

    $users_data = custom_get_user_data($user_ids);
    
    // Apply centralized profile visibility validation as additional filter
    if (!empty($users_data) && function_exists('sud_is_user_profile_visible')) {
        $original_requested_limit = $limit;
        $users_data = array_filter($users_data, function($user_profile) {
            return sud_is_user_profile_visible($user_profile['id'] ?? 0);
        });
        // Re-index array after filtering
        $users_data = array_values($users_data);
        
        // If we have fewer visible users than requested, try to fetch more
        $visible_count = count($users_data);
        if ($visible_count < $original_requested_limit && $visible_count > 0) {
            // We need more users - fetch additional batch
            $additional_needed = $original_requested_limit - $visible_count;
            $additional_fetch_limit = min($additional_needed * 3, 50); // Fetch up to 3x needed or 50 max
            $additional_offset = $offset + ($fetch_limit * 2); // Start from where we left off (estimating)
            
            $additional_query_args = $query_args;
            $additional_query_args['number'] = $additional_fetch_limit;
            $additional_query_args['offset'] = $additional_offset;
            
            $additional_user_query = new WP_User_Query($additional_query_args);
            $additional_user_ids = $additional_user_query->get_results();
            
            if (!empty($additional_user_ids)) {
                $additional_users_data = custom_get_user_data($additional_user_ids);
                
                // Apply visibility filtering to additional users
                if (!empty($additional_users_data)) {
                    $additional_filtered = array_filter($additional_users_data, function($user_profile) {
                        return sud_is_user_profile_visible($user_profile['id'] ?? 0);
                    });
                    
                    // Merge and limit to requested amount
                    $users_data = array_merge($users_data, array_values($additional_filtered));
                    $users_data = array_slice($users_data, 0, $original_requested_limit);
                }
            }
        }
    }
    
    $needs_distance_sort = isset($passed_args['orderby']) && $passed_args['orderby'] === 'distance';
    $original_passed_orderby = $passed_args['orderby'] ?? ($default_args['orderby'] ?? 'user_registered');
    $is_active_sort_intended = ($original_passed_orderby === 'last_active' || ($original_passed_orderby === 'meta_value_num' && ($passed_args['meta_key'] ?? '') === 'last_active'));

    if (($is_active_sort_intended || $needs_distance_sort) && !empty($users_data)) {
        $current_user_profile = get_user_profile_data($current_user_id);
        $current_user_lat = $current_user_profile['latitude'] ?? null;
        $current_user_lng = $current_user_profile['longitude'] ?? null;

        if ($current_user_lat && $current_user_lng && function_exists('calculate_distance')) {
            foreach ($users_data as &$user) {
                $lat = $user['latitude'] ?? null;
                $lng = $user['longitude'] ?? null;
                if ($lat && $lng) {
                    $distance = calculate_distance($current_user_lat, $current_user_lng, $lat, $lng);
                    $user['distance'] = ($distance !== false) ? $distance : PHP_INT_MAX;
                } else {
                    $user['distance'] = PHP_INT_MAX;
                }
            }
            unset($user);
            usort($users_data, function ($a, $b) use ($original_passed_orderby, $passed_args) {
                // Priority users always come first
                $is_priority_a = function_exists('sud_is_priority_user') ? sud_is_priority_user($a['id'] ?? 0) : false;
                $is_priority_b = function_exists('sud_is_priority_user') ? sud_is_priority_user($b['id'] ?? 0) : false;
                
                if ($is_priority_a !== $is_priority_b) {
                    return $is_priority_a ? -1 : 1; // Priority users first
                }
                
                // If both are priority or both are regular, sort by requested criteria
                if ($original_passed_orderby === 'last_active' || ($original_passed_orderby === 'meta_value_num' && ($passed_args['meta_key'] ?? '') === 'last_active')) {
                    $last_active_a = $a['last_active_timestamp'] ?? 0;
                    $last_active_b = $b['last_active_timestamp'] ?? 0;
                    if ($last_active_a === $last_active_b) {
                        $distance_a = $a['distance'] ?? PHP_INT_MAX;
                        $distance_b = $b['distance'] ?? PHP_INT_MAX;
                        return $distance_a <=> $distance_b;
                    }
                    return $last_active_b <=> $last_active_a; 
                } elseif ($original_passed_orderby === 'distance') {
                    $distance_a = $a['distance'] ?? PHP_INT_MAX;
                    $distance_b = $b['distance'] ?? PHP_INT_MAX;
                    return $distance_a <=> $distance_b;
                }
                return 0;
            });
        }
    } else {
        // Apply priority sorting even when no distance/activity sorting is needed
        if (!empty($users_data) && function_exists('sud_sort_users_with_priority')) {
            $users_data = sud_sort_users_with_priority($users_data, 'last_active');
        }
    }
    return $users_data;
}

function custom_get_newest_members($current_user_id, $limit = 6, $offset = 0, $passed_args = []) {
    $newest_args = [
        'orderby' => 'user_registered',
        'order' => 'DESC'
    ];
    $final_args = wp_parse_args($newest_args, $passed_args);
    $users = custom_get_users($current_user_id, $limit, $offset, $final_args);

    return $users;
}

function get_total_user_count($current_user_id, $tab) {
    global $wpdb;

    $current_user_id = intval($current_user_id);
    $exclude_ids = get_excluded_user_ids($current_user_id);
    $exclude_placeholders = !empty($exclude_ids) ? implode(',', array_fill(0, count($exclude_ids), '%d')) : '0';
    $exclude_params = $exclude_ids;

    $base_where = "u.ID NOT IN ($exclude_placeholders)";

    switch (strtolower($tab)) {
        case 'nearby':
            $user_lat = get_user_meta($current_user_id, 'latitude', true);
            $user_lng = get_user_meta($current_user_id, 'longitude', true);
            $user_city = get_user_meta($current_user_id, 'city', true);

            if (!empty($user_lat) && !empty($user_lng)) {
                $user_lat = floatval($user_lat);
                $distance_km = 100; 
                $lat_range = $distance_km / 111.32;
                $lng_range = $distance_km / (111.32 * cos(deg2rad($user_lat)) + 0.00001);
                $min_lat = $user_lat - $lat_range;
                $max_lat = $user_lat + $lat_range;
                $min_lng = $user_lng - $lng_range;
                $max_lng = $user_lng + $lng_range;

                $count_query = $wpdb->prepare(
                    "SELECT COUNT(DISTINCT u.ID)
                    FROM {$wpdb->users} AS u
                    INNER JOIN {$wpdb->usermeta} AS lat_meta ON u.ID = lat_meta.user_id AND lat_meta.meta_key = 'latitude'
                    INNER JOIN {$wpdb->usermeta} AS lng_meta ON u.ID = lng_meta.user_id AND lng_meta.meta_key = 'longitude'
                    WHERE lat_meta.meta_value BETWEEN %f AND %f
                      AND lng_meta.meta_value BETWEEN %f AND %f
                      AND $base_where",
                    array_merge([$min_lat, $max_lat, $min_lng, $max_lng], $exclude_params)
                );
                return (int) $wpdb->get_var($count_query);

            } elseif (!empty($user_city)) {
                $count_query = $wpdb->prepare(
                    "SELECT COUNT(DISTINCT u.ID)
                    FROM {$wpdb->users} AS u
                    INNER JOIN {$wpdb->usermeta} AS city_meta ON u.ID = city_meta.user_id AND city_meta.meta_key = 'city'
                    WHERE city_meta.meta_value LIKE %s
                      AND $base_where",
                    array_merge([$user_city], $exclude_params)
                );
                 return (int) $wpdb->get_var($count_query);
            } else {
                $count_query = $wpdb->prepare("SELECT COUNT(ID) FROM {$wpdb->users} u WHERE $base_where", $exclude_params);
                return (int) $wpdb->get_var($count_query);
            }

        case 'newest': 
        case 'recently active': 
        default:
            $count_query = $wpdb->prepare("SELECT COUNT(ID) FROM {$wpdb->users} u WHERE $base_where", $exclude_params);
            return (int) $wpdb->get_var($count_query);
    }
}