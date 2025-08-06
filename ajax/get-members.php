<?php

$wp_load_path = dirname(__FILE__, 3) . '/wp-load.php';
if ( file_exists( $wp_load_path ) ) {
    require_once( $wp_load_path );
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([ 'success' => false, 'message' => 'Server configuration error: Cannot load WordPress.' ]);
    error_log( "AJAX get-members Error: Failed to load wp-load.php from path: " . $wp_load_path );
    exit;
}

require_once( dirname(__FILE__, 2) . '/includes/config.php' );
require_once( dirname(__FILE__, 2) . '/includes/ajax-security.php' );

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . site_url());
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
    exit(0);
}

try {
    // Use centralized security verification for GET requests (read-only, no nonce needed)
    $current_user_id = sud_verify_ajax([
        'methods' => ['GET'],
        'require_auth' => true,
        'require_nonce' => false, // GET requests for viewing members don't need nonces
        'rate_limit' => ['requests' => 30, 'window' => 60, 'action' => 'get_members'], // Reasonable rate limit
        'check_blocked' => false
    ]);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode([ 'success' => false, 'message' => 'security_check_failed', 'error' => $e->getMessage() ]);
    exit;
}

$is_admin_view = current_user_can('manage_options');
$target_functional_role = '';
$base_meta_query = [ 'relation' => 'AND' ];

if ( ! $is_admin_view ) {
    $current_user_functional_role = get_user_meta( $current_user_id, 'functional_role', true );

    if ( $current_user_functional_role === 'provider' ) {
        $target_functional_role = 'receiver';
    } elseif ( $current_user_functional_role === 'receiver' ) {
        $target_functional_role = 'provider';
    } else {
        echo json_encode([
            'success'        => true,
            'users'          => [],
            'pagination'     => [ 'total_items' => 0, 'current_page' => 1, 'per_page' => 12, 'total_pages' => 0, 'has_more' => false ],
            'filter_applied' => sanitize_key($_GET['filter'] ?? 'active'),
            'message'        => 'User role not properly defined.'
        ]);
        exit;
    }

    $base_meta_query['role_clause'] = [
        'key'     => 'functional_role',
        'value'   => $target_functional_role,
        'compare' => '='
    ];
}

$filter = isset($_GET['filter']) ? sanitize_key( strtolower( $_GET['filter'] ) ) : 'active';
$limit  = isset($_GET['limit'])  ? absint( $_GET['limit'] )    : 12;
$page   = isset($_GET['page'])   ? absint( $_GET['page'] )     : 1;
$offset = ( $page - 1 ) * $limit;

$current_gender = get_user_meta( $current_user_id, 'gender', true );

$search_criteria = [];
$perform_search  = false;

// opposite-gender by default
if ( $filter !== 'search' && $current_gender !== 'LGBTQ+' ) {
    if ( $current_gender === 'Man' ) {
        $base_meta_query['gender_clause'] = [
            'key'     => 'gender',
            'value'   => 'Woman',
            'compare' => '=',
        ];
    } elseif ( $current_gender === 'Woman' ) {
        $base_meta_query['gender_clause'] = [
            'key'     => 'gender',
            'value'   => 'Man',
            'compare' => '=',
        ];
    }
}

if ( $filter === 'search' ) {
    $allowed = [ 'gender','looking_for','min_age','max_age','location','ethnicity','verified_only','online_only','body_type' ];
    foreach ( $allowed as $key ) {
        if ( isset($_GET[$key]) && $_GET[$key] !== '' ) {
            if ( in_array($key, ['min_age','max_age'], true) ) {
                $search_criteria[$key] = absint($_GET[$key]);
            } elseif ( in_array($key, ['verified_only','online_only'], true) ) {
                $search_criteria[$key] = filter_var($_GET[$key], FILTER_VALIDATE_BOOLEAN);
            } else {
                $val = is_array($_GET[$key]) ? array_map('sanitize_text_field', $_GET[$key]) : sanitize_text_field($_GET[$key]);
                $search_criteria[$key] = $val;
            }
        }
    }

    $search_criteria = array_filter($search_criteria);
    if ( ! empty($search_criteria) ) {
        $perform_search = true;
    }
}

$users       = [];
$total_count = 0;

try {
    if ( function_exists('update_user_last_active') ) {
        update_user_last_active($current_user_id);
    }

    $fetch_args = [
        'offset'     => $offset,
        'number'     => $limit,
        'meta_query' => $base_meta_query
    ];

    $args_for_query = [
        'offset'     => $offset,
        'number'     => $limit,
        'meta_query' => $base_meta_query,
    ];

    switch ($filter) {
        case 'active':
            $fetch_args['meta_key'] = 'last_active';
            $fetch_args['orderby']  = 'meta_value_num';
            $fetch_args['order']    = 'DESC';
            $users = function_exists('custom_get_users') ? custom_get_users($current_user_id, $limit, $offset, $fetch_args) : [];
            $total_count = function_exists('get_total_user_count_filtered') ? get_total_user_count_filtered($current_user_id, $base_meta_query) : 0;
            break;

        case 'nearby':
            $nearby_base_args = $fetch_args;
            $initial_fetch_limit = $limit * 5;
            $nearby_base_args['number'] = $initial_fetch_limit;
            $nearby_base_args['offset'] = 0;
            $nearby_base_args['orderby'] = 'last_active';
            $nearby_base_args['order'] = 'DESC';

            $users_pool = function_exists('custom_get_users') ? custom_get_users($current_user_id, $initial_fetch_limit, 0, $nearby_base_args) : [];

            if (!empty($users_pool)) {
                $current_user_location = get_user_profile_data($current_user_id);
                $user_lat = $current_user_location['latitude'] ?? null;
                $user_lng = $current_user_location['longitude'] ?? null;
                $user_city = $current_user_location['city'] ?? null;
                $user_region = $current_user_location['region'] ?? null;
                $user_country = $current_user_location['country'] ?? null;

                if ($user_lat && $user_lng && function_exists('calculate_distance')) {
                    foreach ($users_pool as &$user) {
                        $target_lat = $user['latitude'] ?? null;
                        $target_lng = $user['longitude'] ?? null;
                        if ($target_lat && $target_lng) {
                            $distance = calculate_distance($user_lat, $user_lng, $target_lat, $target_lng);
                            $user['distance'] = ($distance !== false) ? round($distance, 2) : PHP_INT_MAX;
                        } else {
                            $user['distance'] = PHP_INT_MAX;
                        }
                    }
                    unset($user);
                } else {
                    foreach ($users_pool as &$user) { $user['distance'] = PHP_INT_MAX; } unset($user);
                }

                usort($users_pool, function($a, $b) use ($user_lat, $user_lng, $user_city, $user_region, $user_country) {

                    $dist_a = $a['distance'] ?? PHP_INT_MAX;
                    $dist_b = $b['distance'] ?? PHP_INT_MAX;
                    if ($dist_a !== PHP_INT_MAX || $dist_b !== PHP_INT_MAX) {
                        if ($dist_a != $dist_b) {
                            return $dist_a <=> $dist_b;
                        }
                    }

                    if ($user_city) {
                        $city_a = $a['city'] ?? null;
                        $city_b = $b['city'] ?? null;
                        $a_is_same_city = ($city_a && strcasecmp($city_a, $user_city) === 0);
                        $b_is_same_city = ($city_b && strcasecmp($city_b, $user_city) === 0);
                        if ($a_is_same_city !== $b_is_same_city) {
                            return $a_is_same_city ? -1 : 1;
                        }
                    }

                    if ($user_region) {
                        $region_a = $a['region'] ?? null;
                        $region_b = $b['region'] ?? null;
                        if($region_a && $region_b){
                             $a_is_same_region = (strcasecmp($region_a, $user_region) === 0);
                             $b_is_same_region = (strcasecmp($region_b, $user_region) === 0);
                             if ($a_is_same_region !== $b_is_same_region) {
                                 return $a_is_same_region ? -1 : 1;
                             }
                         }
                    }

                    if ($user_country) {
                        $country_a = $a['country'] ?? null;
                        $country_b = $b['country'] ?? null;
                        $a_is_same_country = ($country_a && strcasecmp($country_a, $user_country) === 0);
                        $b_is_same_country = ($country_b && strcasecmp($country_b, $user_country) === 0);
                        if ($a_is_same_country !== $b_is_same_country) {
                            return $a_is_same_country ? -1 : 1;
                        }
                    }

                    $last_active_a = $a['last_active_timestamp'] ?? 0;
                    $last_active_b = $b['last_active_timestamp'] ?? 0;
                    return $last_active_b <=> $last_active_a;
                });
            }

            $total_count = count($users_pool);
            $users = array_slice($users_pool, $offset, $limit);
            break;

        case 'newest':
            $fetch_args['orderby'] = 'user_registered';
            $fetch_args['order'] = 'DESC';
            $users = function_exists('custom_get_newest_members') ? custom_get_newest_members($current_user_id, $limit, $offset, $fetch_args) : [];
            $total_count = function_exists('get_total_user_count_filtered') ? get_total_user_count_filtered($current_user_id, $base_meta_query) : 0;
            break;

        case 'favorites':
            if (function_exists('get_user_favorites')) {
                $all_favorites_profiles = get_user_favorites($current_user_id);
                if ($is_admin_view) {
                    $users = $all_favorites_profiles;
                } else {
                    $users = array_filter($all_favorites_profiles, function($u) use ($target_functional_role){
                        $user_role = get_user_meta($u['id'], 'functional_role', true);
                        return ($user_role === $target_functional_role);
                    });
                }
                $total_count = count($users);
                $users = array_values(array_slice($users, $offset, $limit)); // Re-index after slice
            }
            break;

        case 'favorited_me':
            if (function_exists('get_who_favorited_me')) {
                $all_favoriters_profiles = get_who_favorited_me($current_user_id);
                if ($is_admin_view) {
                    $users = $all_favoriters_profiles;
                } else {
                    $users = array_filter($all_favoriters_profiles, function($u) use ($target_functional_role){
                        $user_role = get_user_meta($u['id'], 'functional_role', true);
                        return ($user_role === $target_functional_role);
                    });
                }
                $total_count = count($users);
                $users = array_values(array_slice($users, $offset, $limit));
            }
            break;

        case 'search':
            if ( $perform_search && function_exists('search_users') ) {
                $res = search_users( $search_criteria, $page, $limit, $base_meta_query );
                $users       = $res['users'];
                $total_count = $res['total'];
            } else {
                // fallback, same logging
                $users       = function_exists('custom_get_users') ? custom_get_users( $current_user_id, $limit, $offset, $args_for_query ) : [];
                $total_count = function_exists('get_total_user_count_filtered') ? get_total_user_count_filtered( $current_user_id, $base_meta_query ) : 0;
            }
            break;

        default:
            $fetch_args['orderby'] = 'last_active';
            $fetch_args['order'] = 'DESC';
            $users = function_exists('custom_get_users') ? custom_get_users($current_user_id, $limit, $offset, $fetch_args) : [];
            $total_count = function_exists('get_total_user_count_filtered') ? get_total_user_count_filtered($current_user_id, $base_meta_query) : 0;
            break;
    }

    // Apply profile boost and priority sorting for better visibility
    // Only apply to 'active' filter to preserve distance-based and date-based sorting for other filters
    if (!empty($users) && $filter === 'active') {
        if (function_exists('sud_apply_profile_boost')) {
            $users = sud_apply_profile_boost($users, $current_user_id);
        } else if (function_exists('sud_sort_users_with_priority')) {
            $users = sud_sort_users_with_priority($users, 'last_active');
        }
    } else if (!empty($users) && $filter !== 'active') {
        // For non-active filters, only apply priority user sorting without disrupting the primary sort
        if (function_exists('sud_sort_users_with_priority')) {
            $users = sud_sort_users_with_priority($users, $filter === 'newest' ? 'user_registered' : 'distance');
        }
    }

    $total_pages = ($limit > 0 && $total_count > 0) ? ceil($total_count / $limit) : 0;
    $has_more = ($page < $total_pages);

    echo json_encode([
        'success' => true,
        'users' => $users,
        'pagination' => [
            'total_items' => (int) $total_count,
            'current_page' => (int) $page,
            'per_page' => (int) $limit,
            'total_pages' => (int) $total_pages,
            'has_more' => $has_more
        ],
        'filter_applied' => $filter
    ]);

} catch (Exception $e) {
    error_log("AJAX Get Members Exception ({$filter}): " . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([ 'success' => false, 'message' => 'An error occurred while fetching members. Please try again later.']);
}
exit;