<?php

defined( 'ABSPATH' ) or die( 'Cannot access this file directly.' );

/**
 * Helper function to check if an instant match already exists between two users.
 *
 * @param int $user_id1
 * @param int $user_id2
 * @return bool
 */
function sud_is_instant_match_active($user_id1, $user_id2) {
    global $wpdb;
    $likes_table = $wpdb->prefix . 'sud_user_likes';

    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $likes_table 
        WHERE ((liker_user_id = %d AND liked_user_id = %d) OR 
               (liker_user_id = %d AND liked_user_id = %d))
        AND like_type IN ('swipe_up', 'instant_match_auto')
    ", $user_id1, $user_id2, $user_id2, $user_id1));

    return $count > 0;
}


/**
 * Get the daily swipe limit for a user based on their premium plan.
 *
 * @param int $user_id
 * @return int
 */
function sud_get_daily_swipe_limit($user_id) {
    if (empty($user_id) || !is_numeric($user_id)) {
        return 10;
    }

    // Ensure premium functions are available
    if (!function_exists('sud_get_user_current_plan_details')) {
        $premium_functions_path = dirname(__FILE__) . '/premium-functions.php';
        if (file_exists($premium_functions_path)) {
            require_once($premium_functions_path);
        } else {
            error_log("SUD Swipe Error: premium-functions.php not found for swipe limit calculation.");
            return 10;
        }
    }
    
    $plan_details = sud_get_user_current_plan_details($user_id);

    if (isset($plan_details['swipe_limit']) && is_numeric($plan_details['swipe_limit'])) {
        return (int) $plan_details['swipe_limit'];
    }

    // Fallback if swipe_limit is not defined for the plan (shouldn't happen with new definitions)
    error_log("SUD Swipe Warning: swipe_limit capability not found for user_id {$user_id}, plan_id '{$plan_details['id']}'. Defaulting to 10.");
    return 10; 
}

/**
 * Get the number of swipes a user has made today.
 *
 * @param int $user_id
 * @return int
 */
function sud_get_user_swipe_count_today($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_daily_swipe_counts';
    $today_date = current_time('Y-m-d');

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT swipe_count FROM $table_name WHERE user_id = %d AND swipe_date = %s",
        $user_id,
        $today_date
    ));

    return $count ? (int)$count : 0;
}

/**
 * Increment the swipe count for a user for today.
 *
 * @param int $user_id
 * @return bool True on success, false on failure.
 */
function sud_increment_user_swipe_count($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_daily_swipe_counts';
    $today_date = current_time('Y-m-d');

    // Try to increment if exists, or insert if not
    $result = $wpdb->query($wpdb->prepare(
        "INSERT INTO $table_name (user_id, swipe_date, swipe_count) VALUES (%d, %s, 1)
         ON DUPLICATE KEY UPDATE swipe_count = swipe_count + 1",
        $user_id,
        $today_date
    ));

    return $result !== false;
}

/**
 * Check if a user has remaining swipes for today.
 *
 * @param int $user_id
 * @return bool
 */
function sud_has_remaining_swipes($user_id) {
    $limit = sud_get_daily_swipe_limit($user_id);
    $count_today = sud_get_user_swipe_count_today($user_id);
    return $count_today < $limit;
}

/**
 * Get a list of user IDs that the current user has already swiped on (liked or passed).
 *
 * @param int $swiper_user_id
 * @return array Array of user IDs.
 */
function sud_get_already_swiped_user_ids($swiper_user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_swipes';

    $swiped_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT swiped_user_id FROM $table_name WHERE swiper_user_id = %d",
        $swiper_user_id
    ));
    return array_map('intval', $swiped_ids);
}


/**
 * Get potential swipe candidates for a user, respecting their preferences with fallbacks.
 *
 * @param int $user_id The ID of the user who is swiping.
 * @param int $count Number of candidates to retrieve.
 * @return array Array of user profile data, or an empty array if no candidates are found.
 */
function sud_get_swipe_candidates($user_id, $count = 10) {
    global $wpdb;
    $current_user_id = (int)$user_id;
    if ($current_user_id <= 0) {
        return [];
    }

    $excluded_ids = get_excluded_user_ids($current_user_id);
    $excluded_ids[] = $current_user_id;
    $already_swiped_ids = sud_get_already_swiped_user_ids($current_user_id);
    $excluded_ids = array_unique(array_merge($excluded_ids, $already_swiped_ids));

    $current_user_functional_role = get_user_meta($current_user_id, 'functional_role', true);
    $current_user_gender = get_user_meta($current_user_id, 'gender', true);

    $target_functional_role = get_user_meta($current_user_id, 'looking_for_role', true);
    if (empty($target_functional_role)) {
        $target_functional_role = ($current_user_functional_role === 'provider') ? 'receiver' : 'provider';
    }

    $target_genders_input = get_user_meta($current_user_id, 'looking_for_gender', true);
    $target_genders = [];
    if (!empty($target_genders_input) && $target_genders_input !== 'Any') {
        $target_genders = (array) $target_genders_input;
    } else {
        if ($current_user_gender === 'Man') {
            $target_genders = ['Woman'];
        } elseif ($current_user_gender === 'Woman') {
            $target_genders = ['Man'];
        } else {
            $target_genders = ['Man', 'Woman', 'LGBTQ+'];
        }
    }

    $base_meta_query = ['relation' => 'AND'];

    $base_meta_query[] = ['key' => 'functional_role', 'value' => $target_functional_role, 'compare' => '='];
    
    if (!empty($target_genders)) {
        $base_meta_query[] = [
            'key'     => 'gender',
            'value'   => $target_genders,
            'compare' => (count($target_genders) > 1 ? 'IN' : '=')
        ];
    }

    $base_meta_query[] = ['key' => 'profile_completed', 'value' => '1', 'compare' => '='];
    $base_meta_query[] = ['key' => 'user_photos', 'compare' => 'EXISTS'];
    $base_meta_query[] = ['key' => 'user_photos', 'value' => 'a:0:{}', 'compare' => '!='];
    $base_meta_query[] = ['key' => 'user_photos', 'value' => '', 'compare' => '!='];

    $query_args = [
        'fields'       => 'ID',
        'number'       => $count * 3,
        'exclude'      => $excluded_ids,
        'meta_query'   => $base_meta_query,
        'meta_key'     => 'last_active',
        'orderby'      => 'meta_value_num',
        'order'        => 'DESC',
    ];

    $user_query = new WP_User_Query($query_args);
    $candidate_ids = $user_query->get_results();

    if (empty($candidate_ids)) {
        return [];
    }

    $users_data = custom_get_user_data($candidate_ids);
    
    // Apply centralized profile visibility validation as additional filter
    if (!empty($users_data) && function_exists('sud_is_user_profile_visible')) {
        $users_data = array_filter($users_data, function($user_profile) {
            return sud_is_user_profile_visible($user_profile['id'] ?? 0);
        });
    }
    
    $current_user_profile_data = get_user_profile_data($current_user_id);
    $current_user_lat = $current_user_profile_data['latitude'] ?? null;
    $current_user_lng = $current_user_profile_data['longitude'] ?? null;
    $current_user_city = strtolower($current_user_profile_data['city'] ?? '');
    $current_user_country = strtolower($current_user_profile_data['country'] ?? '');

    if (!empty($users_data)) {
        foreach ($users_data as &$user_profile) {
            $user_profile['sort_score'] = 0;
            
            // Priority boost for SUD/SUD team members (highest priority)
            if (function_exists('sud_is_priority_user') && sud_is_priority_user($user_profile['id'])) {
                $user_profile['sort_score'] += 1000000; // Very high boost to ensure they appear first
            }
            
            $last_active_timestamp = $user_profile['last_active_timestamp'] ?? 0;
            $user_profile['sort_score'] += $last_active_timestamp / 100000;

            if ($current_user_lat && $current_user_lng && !empty($user_profile['latitude']) && !empty($user_profile['longitude'])) {
                $distance = calculate_distance($current_user_lat, $current_user_lng, $user_profile['latitude'], $user_profile['longitude']);
                if ($distance !== false) {
                    $user_profile['sort_score'] += max(0, (500 - $distance)) * 10;
                }
            } elseif ($current_user_city && !empty($user_profile['city']) && strcasecmp($current_user_city, strtolower($user_profile['city'] ?? '')) === 0) {
                $user_profile['sort_score'] += 5000;
                if ($current_user_country && !empty($user_profile['country']) && strcasecmp($current_user_country, strtolower($user_profile['country'] ?? '')) === 0) {
                     $user_profile['sort_score'] += 2500;
                }
            } elseif ($current_user_country && !empty($user_profile['country']) && strcasecmp($current_user_country, strtolower($user_profile['country'] ?? '')) === 0) {
                 $user_profile['sort_score'] += 1000;
            }
        }
        unset($user_profile);

        usort($users_data, function ($a, $b) {
            return ($b['sort_score'] ?? 0) <=> ($a['sort_score'] ?? 0);
        });
    }

    $final_candidates_data = [];
    $has_more = count($users_data) > $count;
    
    foreach (array_slice($users_data, 0, $count) as $profile_data) {
        if ($profile_data) {
            $final_candidates_data[] = [
                'id' => $profile_data['id'],
                'name' => $profile_data['name'],
                'age' => $profile_data['age'],
                'profile_pic' => $profile_data['profile_pic'],
                'location_formatted' => $profile_data['location_formatted'],
                'is_online' => $profile_data['is_online'],
                'is_verified' => $profile_data['is_verified'],
                'premium_badge_html_small' => $profile_data['premium_badge_html_small'] ?? '',
                'has_active_boost' => $profile_data['has_active_boost'] ?? false,
                'boost_type' => $profile_data['boost_type'] ?? '',
                'boost_name' => $profile_data['boost_name'] ?? '',
            ];
        }
    }
    
    return [
        'candidates' => $final_candidates_data,
        'has_more' => $has_more
    ];
}

/**
 * Record a swipe action.
 *
 * @param int $swiper_user_id
 * @param int $swiped_user_id
 * @param string $swipe_type ('like' or 'pass')
 * @return bool True on success, false on failure.
 */
function sud_record_swipe($swiper_user_id, $swiped_user_id, $swipe_type) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_swipes';

    if (!in_array($swipe_type, ['like', 'pass'])) {
        return false;
    }

    // Use REPLACE to handle unique key gracefully.
    $result = $wpdb->replace(
        $table_name,
        [
            'swiper_user_id' => $swiper_user_id,
            'swiped_user_id' => $swiped_user_id,
            'swipe_type' => $swipe_type,
            'swipe_timestamp' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%s']
    );
    return $result !== false;
}

/**
 * Check for a mutual like (match) and update records.
 *
 * @param int $user_id1 User performing the action
 * @param int $user_id2 User being acted upon
 * @param bool $is_instant_match True if this is a Swipe Up action
 * @return bool True if a new match was made, false otherwise.
 */
function sud_check_and_process_match($user_id1, $user_id2, $is_instant_match = false) {
    global $wpdb;
    $swipes_table = $wpdb->prefix . 'sud_user_swipes';

    // If an instant match already exists, do nothing further.
    if (sud_is_instant_match_active($user_id1, $user_id2)) {
        return false; 
    }

    if ($is_instant_match) {
        // Record the instant match in the dedicated table. This is the primary record.
        sud_record_like($user_id1, $user_id2, 'swipe_up');
        
        // Create a reciprocal "like" in the swipes table so the other user doesn't see them in their deck.
        // DO NOT set is_match=1 here, as this is not a regular match.
        $wpdb->replace(
            $swipes_table,
            [
                'swiper_user_id' => $user_id2,
                'swiped_user_id' => $user_id1,
                'swipe_type'     => 'like',
                'is_match'       => 0, // IMPORTANT: Not a regular match
                'swipe_timestamp'=> current_time('mysql')
            ],
            ['%d', '%d', '%s', '%d', '%s']
        );

        // Send notification for the instant match.
        sud_send_match_notification($user_id1, $user_id2, true);
        return true; // A new connection was made.
    }

    // --- Logic for a REGULAR match ---
    
    // Check if user1 liked user2
    $swipe1_liked_2 = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $swipes_table WHERE swiper_user_id = %d AND swiped_user_id = %d AND swipe_type = 'like'",
        $user_id1, $user_id2
    ));

    // Check if user2 liked user1
    $swipe2_liked_1 = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $swipes_table WHERE swiper_user_id = %d AND swiped_user_id = %d AND swipe_type = 'like'",
        $user_id2, $user_id1
    ));

    // If both have liked each other, it's a regular match.
    if ($swipe1_liked_2 && $swipe2_liked_1) {
        // Update both swipe records to flag them as a regular match.
        $wpdb->update(
            $swipes_table,
            ['is_match' => 1],
            ['swiper_user_id' => $user_id1, 'swiped_user_id' => $user_id2],
            ['%d'], ['%d', '%d']
        );
        $wpdb->update(
            $swipes_table,
            ['is_match' => 1],
            ['swiper_user_id' => $user_id2, 'swiped_user_id' => $user_id1],
            ['%d'], ['%d', '%d']
        );
        
        // Send notification for the regular match.
        sud_send_match_notification($user_id1, $user_id2, false);
        
        return true; // A new connection was made.
    }

    return false; // No new connection was made.
}


/**
 * Get the total number of regular matches for a user.
 *
 * @param int $user_id
 * @return int
 */
function sud_get_user_match_count($user_id) {
    // This function can now simply count the results of the definitive get_user_matches function
    $regular_matches = sud_get_user_matches($user_id);
    return count($regular_matches);
}

/**
 * Get the total combined count of regular matches and instant matches for a user.
 *
 * @param int $user_id
 * @return int
 */
function sud_get_user_total_match_count($user_id) {
    $regular_matches_count = sud_get_user_match_count($user_id);
    $instant_matches_count = sud_get_user_instant_match_count($user_id);
    
    return $regular_matches_count + $instant_matches_count;
}

/**
 * Get the total number of likes a user has received from valid users.
 *
 * @param int $user_id
 * @return int
 */
function sud_get_user_likes_received_count($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_swipes';
    $users_table = $wpdb->prefix . 'users';

    // Count likes only from users who still exist in the system
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT s.swiper_user_id) FROM $table_name s 
         INNER JOIN $users_table u ON s.swiper_user_id = u.ID 
         WHERE s.swiped_user_id = %d AND s.swipe_type = 'like'",
        $user_id
    ));

    return (int)$count;
}

/**
 * Get users who have liked the given user.
 *
 * @param int $user_id
 * @return array Array of user profile data with 'liked_at' timestamp.
 */
function sud_get_users_who_liked($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_swipes';
    $user_id_int = intval($user_id);

    if ($user_id_int <= 0) return [];

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT swiper_user_id, MAX(swipe_timestamp) as swipe_timestamp 
         FROM $table_name 
         WHERE swiped_user_id = %d AND swipe_type = 'like' 
         GROUP BY swiper_user_id 
         ORDER BY swipe_timestamp DESC",
        $user_id_int
    ), ARRAY_A);

    if (empty($results)) return [];

    $liker_ids = [];
    $timestamps = [];
    foreach ($results as $row) {
        $liker_id = intval($row['swiper_user_id']);
        if ($liker_id > 0) {
            $liker_ids[] = $liker_id;
            $timestamps[$liker_id] = $row['swipe_timestamp'];
        }
    }
    
    if (empty($liker_ids)) return [];

    $liker_profiles = function_exists('custom_get_user_data') ? custom_get_user_data($liker_ids) : [];

    foreach ($liker_profiles as &$profile) {
        if (isset($timestamps[$profile['id']])) {
            $profile['liked_at'] = $timestamps[$profile['id']];
        }
    }
    unset($profile);
    return $liker_profiles;
}

/**
 * Get users with whom the given user has a REGULAR match.
 * This function is now modified to EXCLUDE anyone they have an instant match with.
 *
 * @param int $user_id
 * @return array Array of user profile data with 'matched_at' timestamp.
 */
function sud_get_user_matches($user_id) {
    global $wpdb;
    $swipes_table = $wpdb->prefix . 'sud_user_swipes';
    $likes_table = $wpdb->prefix . 'sud_user_likes';
    $user_id_int = intval($user_id);

    if ($user_id_int <= 0) return [];
    
    // Subquery to get all user IDs involved in an instant match with the current user.
    $instant_match_subquery = $wpdb->prepare(
        "SELECT DISTINCT CASE WHEN liker_user_id = %d THEN liked_user_id ELSE liker_user_id END
         FROM {$likes_table}
         WHERE (liker_user_id = %d OR liked_user_id = %d) AND like_type IN ('swipe_up', 'instant_match_auto')",
        $user_id_int, $user_id_int, $user_id_int
    );

    // Main query to get regular matches, excluding any from the instant match subquery.
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            t.matched_user_id,
            t.matched_at
         FROM (
            SELECT 
                CASE 
                    WHEN swiper_user_id = %d THEN swiped_user_id 
                    ELSE swiper_user_id 
                END as matched_user_id, 
                MAX(swipe_timestamp) as matched_at
            FROM {$swipes_table}
            WHERE (swiper_user_id = %d OR swiped_user_id = %d) 
              AND is_match = 1
            GROUP BY matched_user_id
         ) as t
         WHERE t.matched_user_id NOT IN ({$instant_match_subquery})
         ORDER BY t.matched_at DESC",
        $user_id_int, $user_id_int, $user_id_int
    ), ARRAY_A);

    if (empty($results)) return [];

    $matched_user_ids = array_column($results, 'matched_user_id');
    $timestamps = array_column($results, 'matched_at', 'matched_user_id');
    
    $matched_profiles = function_exists('custom_get_user_data') ? custom_get_user_data($matched_user_ids) : [];

    foreach ($matched_profiles as &$profile) {
        if (isset($timestamps[$profile['id']])) {
            $profile['matched_at'] = $timestamps[$profile['id']];
        }
    }
    unset($profile);
    
    return $matched_profiles;
}


/**
 * Get users with whom the given user has an instant match (via Swipe Up).
 *
 * @param int $user_id
 * @return array Array of user profile data with 'matched_at' timestamp.
 */
function sud_get_user_instant_matches($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_likes';
    $user_id_int = intval($user_id);

    if ($user_id_int <= 0) return [];

    // Combined query to get both sent and received instant matches
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            CASE 
                WHEN liker_user_id = %d THEN liked_user_id 
                ELSE liker_user_id 
            END as matched_user_id,
            MAX(like_timestamp) as matched_at
         FROM {$table_name}
         WHERE (liker_user_id = %d OR liked_user_id = %d)
           AND like_type IN ('swipe_up', 'instant_match_auto')
         GROUP BY matched_user_id
         ORDER BY matched_at DESC",
        $user_id_int, $user_id_int, $user_id_int
    ), ARRAY_A);

    if (empty($results)) return [];

    $instant_match_user_ids = [];
    $timestamps = [];

    foreach ($results as $row) {
        $matched_user_id = intval($row['matched_user_id']);
        if ($matched_user_id > 0) {
            $instant_match_user_ids[] = $matched_user_id;
            $timestamps[$matched_user_id] = $row['matched_at'];
        }
    }

    if (empty($instant_match_user_ids)) return [];

    $matched_profiles = function_exists('custom_get_user_data') ? custom_get_user_data($instant_match_user_ids) : [];

    foreach ($matched_profiles as &$profile) {
        if (isset($timestamps[$profile['id']])) {
            $profile['matched_at'] = $timestamps[$profile['id']];
        }
    }
    unset($profile);
    return $matched_profiles;
}


/**
 * Get the count of instant matches for a user.
 *
 * @param int $user_id
 * @return int
 */
function sud_get_user_instant_match_count($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_likes';
    $user_id_int = intval($user_id);

    if ($user_id_int <= 0) return 0;
    
    // This counts the number of unique people the user has an instant match with.
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT CASE WHEN liker_user_id = %d THEN liked_user_id ELSE liker_user_id END)
         FROM {$table_name}
         WHERE (liker_user_id = %d OR liked_user_id = %d)
           AND like_type IN ('swipe_up', 'instant_match_auto')",
        $user_id_int, $user_id_int, $user_id_int
    ));

    return (int)$count;
}

/**
 * Get the total number of Swipe Ups a user has available.
 *
 * @param int $user_id
 * @return int Total available Swipe Ups.
 */
function sud_get_user_swipe_up_balance($user_id) {
    if (empty($user_id)) return 0;

    $purchased_balance = (int) get_user_meta($user_id, 'sud_purchased_swipe_ups_balance', true);
    $plan_details = sud_get_user_current_plan_details($user_id);
    $daily_allowance = $plan_details['free_swipe_ups_daily'] ?? 0;
    $used_today = (int) get_user_meta($user_id, 'sud_free_swipe_ups_used_today', true);
    $last_used_date = get_user_meta($user_id, 'sud_free_swipe_ups_last_used_date', true);
    $today_date = current_time('Y-m-d');

    if ($last_used_date !== $today_date) {
        $used_today = 0;
        delete_user_meta($user_id, 'sud_free_swipe_ups_used_today');
    }
    
    $remaining_free_today = max(0, $daily_allowance - $used_today);
    return $purchased_balance + $remaining_free_today;
}

/**
 * Deduct one Swipe Up from a user's balance.
 *
 * @param int $user_id
 * @return bool True on successful deduction, false if no balance.
 */
function sud_deduct_swipe_up($user_id) {
    if (empty($user_id)) return false;

    if (sud_get_user_swipe_up_balance($user_id) <= 0) {
        return false;
    }

    $plan_details = sud_get_user_current_plan_details($user_id);
    $daily_allowance = $plan_details['free_swipe_ups_daily'] ?? 0;
    $used_today = (int) get_user_meta($user_id, 'sud_free_swipe_ups_used_today', true);
    $last_used_date = get_user_meta($user_id, 'sud_free_swipe_ups_last_used_date', true);
    $today_date = current_time('Y-m-d');

    if ($last_used_date !== $today_date) {
        $used_today = 0;
    }
    
    if ($used_today < $daily_allowance) {
        update_user_meta($user_id, 'sud_free_swipe_ups_used_today', $used_today + 1);
        update_user_meta($user_id, 'sud_free_swipe_ups_last_used_date', $today_date);
        return true;
    }

    $purchased_balance = (int) get_user_meta($user_id, 'sud_purchased_swipe_ups_balance', true);
    if ($purchased_balance > 0) {
        update_user_meta($user_id, 'sud_purchased_swipe_ups_balance', $purchased_balance - 1);
        return true;
    }
    
    return false;
}

/**
 * Check if two users are matched (either regularly or instantly)
 */
function sud_are_users_matched($user_id1, $user_id2) {
    global $wpdb;
    $swipes_table = $wpdb->prefix . 'sud_user_swipes';
    $user_id1 = intval($user_id1);
    $user_id2 = intval($user_id2);

    if ($user_id1 <= 0 || $user_id2 <= 0 || $user_id1 === $user_id2) {
        return false;
    }

    // First, check for an instant match, as it's definitive.
    if (sud_is_instant_match_active($user_id1, $user_id2)) {
        return true;
    }

    // If no instant match, check for a regular match.
    $regular_match_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $swipes_table 
        WHERE ((swiper_user_id = %d AND swiped_user_id = %d) OR 
               (swiper_user_id = %d AND swiped_user_id = %d))
        AND is_match = 1
    ", $user_id1, $user_id2, $user_id2, $user_id1));

    // A regular match requires two 'is_match=1' rows.
    return ($regular_match_count >= 2);
}

/**
 * Record a like action in the likes table (used for instant matches and favorites)
 *
 * @param int $liker_user_id The user who is liking
 * @param int $liked_user_id The user being liked
 * @param string $like_type Type of like ('swipe_up', 'instant_match_auto', 'favorite', etc.)
 * @return bool True on success, false on failure
 */
function sud_record_like($liker_user_id, $liked_user_id, $like_type = 'swipe_up') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_likes';
    
    $liker_user_id = intval($liker_user_id);
    $liked_user_id = intval($liked_user_id);
    
    if ($liker_user_id <= 0 || $liked_user_id <= 0 || $liker_user_id === $liked_user_id) {
        return false;
    }

    // Check if this specific like type already exists to avoid duplicates
    $existing_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE liker_user_id = %d AND liked_user_id = %d AND like_type = %s",
        $liker_user_id, $liked_user_id, $like_type
    ));

    if ($existing_count > 0) {
        return true; // Already exists, consider it successful
    }

    $result = $wpdb->insert(
        $table_name,
        [
            'liker_user_id' => $liker_user_id,
            'liked_user_id' => $liked_user_id,
            'like_type' => $like_type,
            'like_timestamp' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%s']
    );

    return $result !== false;
}

/**
 * Admin function to manually create a match between two users
 * This bypasses normal swipe validation for admin purposes
 *
 * @param int $user_id1 First user ID
 * @param int $user_id2 Second user ID
 * @return bool True if match was created successfully, false otherwise
 */
function sud_admin_create_manual_match($user_id1, $user_id2) {
    global $wpdb;
    
    // Validate input
    if (!$user_id1 || !$user_id2 || $user_id1 === $user_id2) {
        return false;
    }
    
    // Verify both users exist
    $user1_exists = get_userdata($user_id1);
    $user2_exists = get_userdata($user_id2);
    if (!$user1_exists || !$user2_exists) {
        return false;
    }
    
    $swipes_table = $wpdb->prefix . 'sud_user_swipes';
    
    // Check if they're already matched (either regular or instant)
    if (sud_are_users_matched($user_id1, $user_id2)) {
        return false; // Already matched
    }
    
    // Check if an instant match already exists
    if (sud_is_instant_match_active($user_id1, $user_id2)) {
        return false; // Already have instant match
    }
    
    // Create mutual swipes if they don't exist, then mark as matched
    // This creates a regular match between the users
    
    // Insert/update swipe from user1 to user2
    $wpdb->replace(
        $swipes_table,
        [
            'swiper_user_id' => $user_id1,
            'swiped_user_id' => $user_id2,
            'swipe_type' => 'like',
            'is_match' => 1,
            'swipe_timestamp' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%d', '%s']
    );
    
    // Insert/update swipe from user2 to user1
    $wpdb->replace(
        $swipes_table,
        [
            'swiper_user_id' => $user_id2,
            'swiped_user_id' => $user_id1,
            'swipe_type' => 'like',
            'is_match' => 1,
            'swipe_timestamp' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%d', '%s']
    );
    
    // Send match notifications to both users
    if (function_exists('sud_send_match_notification')) {
        sud_send_match_notification($user_id1, $user_id2, false); // Regular match, not instant
    }
    
    // Trigger match monitoring action for admin-created matches
    do_action('sud_match_created', $user_id1, $user_id2, 'admin_manual', true);
    
    return true;
}