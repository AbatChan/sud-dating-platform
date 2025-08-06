<?php

$wp_load_path = dirname(__FILE__, 3) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration error: Cannot load WordPress environment.']);
    error_log("AJAX update-profile Error: Failed to load wp-load.php from path: " . $wp_load_path);
    exit;
}

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');

header('Content-Type: application/json');

try {
    if (!function_exists('is_user_logged_in') || !function_exists('get_profile_completion_percentage') || !function_exists('update_user_location')) {
        throw new Exception('Core functions not loaded correctly');
    }

    // Use centralized security verification
    $user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_ajax_action',
        'rate_limit' => ['requests' => 20, 'window' => 60, 'action' => 'update_profile']
    ]);

    $action = isset($_POST['action']) ? sanitize_key($_POST['action']) : ''; 
    $response_data = ['success' => false, 'message' => 'Invalid request.']; 

    if ($action === 'delete_photo') {
        $photo_id = isset($_POST['photo_id']) ? intval($_POST['photo_id']) : 0;
        if ($photo_id > 0) {
            $user_photos = get_user_meta($user_id, 'user_photos', true);
            if (!is_array($user_photos)) $user_photos = [];
            if (in_array($photo_id, $user_photos)) {
                $user_photos = array_values(array_diff($user_photos, [$photo_id]));
                $deleted = wp_delete_attachment($photo_id, true);

            if ($deleted !== false) {
                update_user_meta($user_id, 'user_photos', $user_photos);
                $current_profile_pic = get_user_meta($user_id, 'profile_picture', true);
                if ($current_profile_pic == $photo_id) {
                    $new_profile_pic = !empty($user_photos) ? $user_photos[0] : '';
                    update_user_meta($user_id, 'profile_picture', $new_profile_pic);
                }
                if (empty($user_photos)) { update_user_meta($user_id, 'completed_step_13', false); }
                else { update_user_meta($user_id, 'completed_step_13', true); }

                $completion_percentage = get_profile_completion_percentage($user_id);
                wp_send_json_success(['message' => 'Photo deleted.', 'completion_percentage' => $completion_percentage]);
            } else {
                error_log("Failed to delete attachment ID $photo_id for user $user_id.");
                wp_send_json_error(['message' => 'Failed to delete photo file. Please try again.']);
            }
        } else {
            wp_send_json_error(['message' => 'Photo does not belong to user or already deleted.']);
        }
    } else {
        wp_send_json_error(['message' => 'Invalid photo ID.'], 400);
    }
}

if ($action === 'set_profile_picture') {
    $photo_id = isset($_POST['photo_id']) ? intval($_POST['photo_id']) : 0;
    $user_photos = get_user_meta($user_id, 'user_photos', true);
    if (!is_array($user_photos)) $user_photos = [];

    if ($photo_id > 0 && in_array($photo_id, $user_photos)) {
        update_user_meta($user_id, 'profile_picture', $photo_id);
        $completion_percentage = get_profile_completion_percentage($user_id);
        wp_send_json_success(['message' => 'Profile picture updated.', 'completion_percentage' => $completion_percentage]);
    } else {
        wp_send_json_error(['message' => 'Invalid photo ID or photo does not belong to user.'], 400);
    }
}

if ($action === 'reorder_photos') {
    $photo_order = isset($_POST['photo_order']) && is_array($_POST['photo_order']) ? $_POST['photo_order'] : null;

    if ($photo_order !== null) {
        $sanitized_order = array_map('intval', $photo_order);
        $current_photos = get_user_meta($user_id, 'user_photos', true) ?: [];
        $valid_order = true;

        if (count(array_diff($current_photos, $sanitized_order)) !== 0 || count(array_diff($sanitized_order, $current_photos)) !== 0) {
            $valid_order = false;
            error_log("Photo reorder validation failed for user $user_id: Mismatch between submitted order and current photos.");
        }

        if ($valid_order) {
            update_user_meta($user_id, 'user_photos', $sanitized_order);
            $new_profile_pic_id = !empty($sanitized_order) ? $sanitized_order[0] : '';
            update_user_meta($user_id, 'profile_picture', $new_profile_pic_id);
            $completion_percentage = get_profile_completion_percentage($user_id);
            wp_send_json_success(['message' => 'Photo order saved.', 'new_profile_pic_id' => $new_profile_pic_id, 'completion_percentage' => $completion_percentage]);
        } else {
            wp_send_json_error(['message' => 'Invalid photo order data received. Please refresh and try again.']);
        }
    } else {
        wp_send_json_error(['message' => 'Missing photo order data.'], 400);
    }
}

if ($action === 'update_profile') {
    $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
    $update_successful = false; 
    $error_message = ''; 
    $completion_step_keys = []; 

    function validate_required($value, $field_name, &$error_msg) {
        if (empty(trim((string)$value))) { 
            $error_msg = ucfirst(str_replace('_', ' ', $field_name)) . " cannot be empty.";
            return false;
        }
        return true;
    }

    switch ($field) {
        case 'basic':
            $display_name = isset($_POST['display_name']) ? sanitize_text_field(trim($_POST['display_name'])) : '';
            if (validate_required($display_name, 'display name', $error_message)) {
                $update_result = wp_update_user(['ID' => $user_id, 'display_name' => $display_name]);
                if (is_wp_error($update_result)) {
                    $error_message = "Error updating display name: " . $update_result->get_error_message();
                } else {
                    update_user_meta($user_id, 'display_name', $display_name); 
                    $completion_step_keys[] = 0; 
                    $update_successful = true;
                }
            }
            break;

        case 'terms':
            $terms_str = isset($_POST['relationship_terms']) ? sanitize_text_field($_POST['relationship_terms']) : '';
            $terms_array = array_filter(array_map('sanitize_text_field', explode(',', $terms_str)));
            if (count($terms_array) >= 3) {
                update_user_meta($user_id, 'relationship_terms', $terms_array);
                $completion_step_keys[] = 0; 
                $update_successful = true;
            } else {
                $error_message = 'Please select at least 3 terms.';
            }
            break;

        case 'dating':
            $styles_str = isset($_POST['dating_styles']) ? sanitize_text_field($_POST['dating_styles']) : '';
            $styles_array = array_filter(array_map('sanitize_text_field', explode(',', $styles_str)));
            update_user_meta($user_id, 'dating_styles', $styles_array);

            if (!empty($styles_array)) {
                $completion_step_keys[] = 5; 
            } else {
                update_user_meta($user_id, 'completed_step_5', false);
            }
            $update_successful = true; 
            break;

        case 'financial':
            // Use functional_role to decide whether to collect financial data
            $functional_role = get_user_meta($user_id, 'functional_role', true);
            if ($functional_role !== 'receiver') {
                $income = isset($_POST['annual_income']) ? sanitize_text_field($_POST['annual_income']) : '';
                $networth = isset($_POST['net_worth']) ? sanitize_text_field($_POST['net_worth']) : '';
                $budget = isset($_POST['dating_budget']) ? sanitize_text_field($_POST['dating_budget']) : '';
                update_user_meta($user_id, 'annual_income', $income);
                update_user_meta($user_id, 'net_worth', $networth);
                update_user_meta($user_id, 'dating_budget', $budget);

                if (!empty($income) && !empty($networth) && !empty($budget)) {
                    $completion_step_keys = array_merge($completion_step_keys, [1, 2, 3]);
                } else {
                    update_user_meta($user_id, 'completed_step_1', false);
                    update_user_meta($user_id, 'completed_step_2', false);
                    update_user_meta($user_id, 'completed_step_3', false);
                }
            }
            // If receiver, skip financial steps entirely
            $update_successful = true;
            break;

        case 'location':
            $latitude = isset($_POST['latitude']) ? filter_var($_POST['latitude'], FILTER_VALIDATE_FLOAT) : null;
            $longitude = isset($_POST['longitude']) ? filter_var($_POST['longitude'], FILTER_VALIDATE_FLOAT) : null;
            $accuracy = isset($_POST['accuracy']) ? sanitize_text_field($_POST['accuracy']) : 'google_places_edit';
            $city = isset($_POST['city_google']) ? sanitize_text_field(trim($_POST['city_google'])) : '';
            $region = isset($_POST['region_google']) ? sanitize_text_field(trim($_POST['region_google'])) : '';
            $country = isset($_POST['country_google']) ? sanitize_text_field(trim($_POST['country_google'])) : '';

            if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                $error_message = 'Invalid location coordinates provided. Please select a valid location.';
                $update_successful = false; 
            } elseif (empty($city) || empty($country)) {
                $error_message = 'Location selection incomplete (missing city or country). Please select a valid location.';
                $update_successful = false; 
            } else {
                update_user_location($user_id, $latitude, $longitude, $accuracy);
                update_user_meta($user_id, 'latitude', $latitude);
                update_user_meta($user_id, 'longitude', $longitude);
                update_user_meta($user_id, 'city', $city);
                update_user_meta($user_id, 'region', $region);
                update_user_meta($user_id, 'country', $country);
                $loc_parts = array_filter([$city, $region, $country]);
                update_user_meta($user_id, 'location_string', implode(', ', $loc_parts));
                update_user_meta($user_id, 'location_updated', current_time('mysql', true));
                delete_user_meta($user_id, 'location_needs_update');
                delete_user_meta($user_id, 'location');

                $update_successful = true;
                $completion_step_keys[] = 0;
            }
            break;

        case 'appearance':
            $fields_to_update = ['height', 'body_type', 'ethnicity', 'race', 'eye_color', 'hair_color'];
            $all_filled = true;
            foreach ($fields_to_update as $appearance_field) {
                $value = isset($_POST[$appearance_field]) ? sanitize_text_field($_POST[$appearance_field]) : '';
                update_user_meta($user_id, $appearance_field, $value);
                if (in_array($appearance_field, ['height','body_type','ethnicity','race']) && empty($value)) {
                    $all_filled = false;
                }
            }
            if ($all_filled) {
                $completion_step_keys = array_merge($completion_step_keys, [7, 8]);
            } else {
                update_user_meta($user_id, 'completed_step_7', false);
                update_user_meta($user_id, 'completed_step_8', false);
            }
            $update_successful = true;
            break;

        case 'personal':
            $fields_to_update = ['occupation', 'industry', 'education', 'relationship_status', 'smoke', 'drink'];
            $all_filled = true;
            foreach ($fields_to_update as $personal_field) {
                $value = isset($_POST[$personal_field]) ? sanitize_text_field(trim($_POST[$personal_field])) : '';
                update_user_meta($user_id, $personal_field, $value);
                if (in_array($personal_field, ['occupation','relationship_status','smoke','drink']) && empty($value)) {
                    $all_filled = false;
                }
            }
            if ($all_filled) {
                $completion_step_keys = array_merge($completion_step_keys, [4, 6, 9, 10]);
            } else {
                update_user_meta($user_id, 'completed_step_4', false);
                update_user_meta($user_id, 'completed_step_6', false);
                update_user_meta($user_id, 'completed_step_9', false);
                update_user_meta($user_id, 'completed_step_10', false);
            }
            $update_successful = true;
            break;

        case 'about':
            $about_me = isset($_POST['about_me']) ? sanitize_textarea_field(trim($_POST['about_me'])) : '';
            if (validate_required($about_me, 'about me', $error_message)) {
                update_user_meta($user_id, 'about_me', $about_me);
                $completion_step_keys[] = 11;
                $update_successful = true;
            } else {
                update_user_meta($user_id, 'completed_step_11', false);
            }
            break;

        case 'looking':
            // Age and ethnicity
            $age_min = isset($_POST['looking_for_age_min']) ? intval($_POST['looking_for_age_min']) : 18;
            $age_max = isset($_POST['looking_for_age_max']) ? intval($_POST['looking_for_age_max']) : 70;
            $ethnicities_str = isset($_POST['looking_for_ethnicities']) ? sanitize_text_field($_POST['looking_for_ethnicities']) : '';
            $ethnicities_array = array_filter(array_map('sanitize_text_field', explode(',', $ethnicities_str)));

            if ($age_min < 18 || $age_max > 70 || $age_min >= $age_max) {
                $error_message = 'Invalid age range selected.';
                $update_successful = false;
            } else {
                update_user_meta($user_id, 'looking_for_age_min', $age_min);
                update_user_meta($user_id, 'looking_for_age_max', $age_max);
                update_user_meta($user_id, 'looking_for_ethnicities', $ethnicities_array);

                // Who they're looking for
                if (isset($_POST['looking_for'])) {
                    $lf = sanitize_text_field($_POST['looking_for']);
                    if (in_array($lf, ['Sugar Daddy/Mommy','Sugar Baby','Gay','Lesbian'], true)) {
                        update_user_meta($user_id, 'looking_for', $lf);
                    }
                }
                // Role for LGBTQ+
                $gender = get_user_meta($user_id, 'gender', true);
                if ($gender === 'LGBTQ+' && isset($_POST['role'])) {
                    $r = sanitize_text_field($_POST['role']);
                    if (in_array($r, ['Sugar Daddy/Mommy','Sugar Baby'], true)) {
                        update_user_meta($user_id, 'role', $r);
                    }
                }
                // Recompute functional_role
                $role_current = get_user_meta($user_id, 'role', true);
                $lf_current = get_user_meta($user_id, 'looking_for', true);
                if ($gender === 'Man') {
                    $functional_role = ($lf_current === 'Sugar Daddy/Mommy') ? 'receiver' : 'provider';
                } elseif ($gender === 'Woman') {
                    $functional_role = ($lf_current === 'Sugar Baby') ? 'provider' : 'receiver';
                } elseif ($gender === 'LGBTQ+') {
                    $functional_role = ($role_current === 'Sugar Daddy/Mommy') ? 'provider' : 'receiver';
                } else {
                    $functional_role = '';
                }
                update_user_meta($user_id, 'functional_role', $functional_role);

                if (!empty($ethnicities_array) && isset($lf_current)) {
                    $completion_step_keys[] = 12;
                } else {
                    update_user_meta($user_id, 'completed_step_12', false);
                }
                $update_successful = true;
            }
            break;

        default:
            $error_message = 'Invalid field specified for update.';
            $update_successful = false;
            break;
    }

    if ($update_successful) {
        foreach(array_unique($completion_step_keys) as $step_key) {
            if (in_array($step_key, [1,2,3], true)) {
                $func = get_user_meta($user_id,'functional_role',true);
                if ($func === 'receiver') {
                    continue;
                }
            }
            update_user_meta($user_id, 'completed_step_' . $step_key, true);
        }
        $completion_percentage = get_profile_completion_percentage($user_id);
        wp_send_json_success([
            'message' => ucfirst($field) . ' updated successfully.',
            'completion_percentage' => $completion_percentage
        ]);
    } else {
        wp_send_json_error(['message' => $error_message ?: 'Update failed for ' . $field . '.'], 400);
    }
} else {
    wp_send_json_error(['message' => 'Invalid action specified.'], 400);
}

} catch (Exception $e) {
    sud_handle_ajax_error($e);
}
?>
