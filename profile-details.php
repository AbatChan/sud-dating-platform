<?php

require_once('includes/config.php');
require_login();

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

$display_page_message = '';
$message_type_is_error = false;

if (isset($_GET['step_error'])) {
    $display_page_message = sanitize_text_field(urldecode($_GET['step_error']));
    $message_type_is_error = true;
} elseif (isset($_GET['error_message'])) {
    $display_page_message = sanitize_text_field(urldecode($_GET['error_message']));
    $message_type_is_error = true;
} elseif (isset($_GET['setup_reason'])) {
    $reason = sanitize_text_field($_GET['setup_reason']);
    if ($reason === 'step0_incomplete_from_dashboard' && (!isset($_GET['active']) || intval($_GET['active']) === 0) ) {
        $display_page_message = "Welcome! Please define your 'Terms of Relationship' to proceed.";
    } elseif ($reason === 'profile_setup_complete' && (!isset($_GET['active']) || intval($_GET['active']) === 0) ) {
        $display_page_message = "Great! Now let's define your relationship interests and terms. This is the first essential step.";
    } elseif (($reason === 'step13_incomplete_from_dashboard' || $reason === 'detail_step_13_incomplete_from_dashboard_check') && (!isset($_GET['active']) || intval($_GET['active']) === 13) ) {
        $display_page_message = "A profile picture is required to access all features. Please upload at least one photo.";
        $message_type_is_error = true;
    } elseif (strpos($reason, '_incomplete_from_dashboard_check') !== false) {
        $display_page_message = "Please complete this section to continue.";
    }
}

if (isset($_GET['from_skip_all']) && $_GET['from_skip_all'] == '1' && isset($_GET['active']) && intval($_GET['active']) == 13) {
    $profile_picture_id_check = get_user_meta($user_id, 'profile_picture', true);
    $is_photo_step_completed_check = get_user_meta($user_id, 'completed_step_13', true);
    if (!empty($profile_picture_id_check) && $is_photo_step_completed_check) {
        wp_safe_redirect(SUD_URL . '/pages/swipe?skipped_setup=1');
        exit;
    }
}

function sud_compress_image($source_path, $quality = 75) {
    $image_info = getimagesize($source_path);
    if ($image_info === false) {
        return false; 
    }

    $mime = $image_info['mime'];
    $editor = wp_get_image_editor($source_path);

    if (is_wp_error($editor)) {
        error_log("SUD Image Compression: Could not get WP_Image_Editor for " . $source_path);
        return false;
    }

    $editor->set_quality($quality);

    $size = $editor->get_size();
    if (isset($size['width']) && $size['width'] > 1920) {
        $editor->resize(1920, null, false);
    }

    $saved = $editor->save($source_path);

    if (is_wp_error($saved)) {
        error_log("SUD Image Compression: Failed to save compressed image. Error: " . $saved->get_error_message());
        return false;
    }

    clearstatcache();
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_step = -1;
    $nonce_action = '';
    $nonce_value = isset($_POST['_sud_step_nonce']) ? sanitize_text_field($_POST['_sud_step_nonce']) : ''; 

    if (isset($_POST['terms_submit']))           { $submitted_step = 0; }
    elseif (isset($_POST['annual_income_submit'])) { $submitted_step = 1; }
    elseif (isset($_POST['net_worth_submit']))     { $submitted_step = 2; }
    elseif (isset($_POST['dating_budget_submit'])) { $submitted_step = 3; }
    elseif (isset($_POST['relationship_status_submit'])) { $submitted_step = 4; }
    elseif (isset($_POST['dating_style_submit']))  { $submitted_step = 5; }
    elseif (isset($_POST['occupation_submit']))    { $submitted_step = 6; }
    elseif (isset($_POST['ethnicity_submit']))     { $submitted_step = 7; }
    elseif (isset($_POST['race_submit']))          { $submitted_step = 8; }
    elseif (isset($_POST['smoke_submit']))         { $submitted_step = 9; }
    elseif (isset($_POST['drink_submit']))         { $submitted_step = 10; }
    elseif (isset($_POST['about_me_submit']))      { $submitted_step = 11; }
    elseif (isset($_POST['looking_for_submit']))   { $submitted_step = 12; }
    elseif (isset($_POST['photos_submit']))        { $submitted_step = 13; }

    if ($submitted_step !== -1) {
        $nonce_action = 'sud_profile_step_' . $submitted_step . '_action';

        if (!wp_verify_nonce($nonce_value, $nonce_action)) {
            wp_die('Security check failed. Please go back and try again.', 'CSRF Error', ['response' => 403]);
        } else {
            if ($submitted_step == 13) {
                if (!function_exists('media_handle_sideload')) {
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/media.php');
                }
            }
            $completed_flag_key = 'completed_step_' . $submitted_step;
            $redirect_to_dashboard = false;
            $processing_error = null; 

            switch ($submitted_step) {
                case 0:
                    $selected_terms = isset($_POST['terms']) ? $_POST['terms'] : array();
                    if (is_array($selected_terms) && !empty($selected_terms)) {
                        update_user_meta($user_id, 'relationship_terms', $selected_terms);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "No terms were selected."; }
                    break;

                case 1:
                    $annual_income = isset($_POST['annual_income']) ? sanitize_text_field($_POST['annual_income']) : '';
                    if (!empty($annual_income)) {
                        update_user_meta($user_id, 'annual_income', $annual_income);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "Annual income was not selected."; }
                    break;

                case 2:
                    $net_worth = isset($_POST['net_worth']) ? sanitize_text_field($_POST['net_worth']) : '';
                    if (!empty($net_worth)) {
                        update_user_meta($user_id, 'net_worth', $net_worth);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "Net worth was not selected."; }
                    break;

                case 3:
                    $dating_budget = isset($_POST['dating_budget']) ? sanitize_text_field($_POST['dating_budget']) : '';
                    if (!empty($dating_budget)) {
                        update_user_meta($user_id, 'dating_budget', $dating_budget);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "Dating budget was not selected."; }
                    break;

                case 4:
                    $relationship_status = isset($_POST['relationship_status']) ? sanitize_text_field($_POST['relationship_status']) : '';
                    if (!empty($relationship_status)) {
                        update_user_meta($user_id, 'relationship_status', $relationship_status);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "Relationship status was not selected."; }
                    break;

                case 5:
                    $dating_styles = isset($_POST['dating_styles']) ? $_POST['dating_styles'] : array();
                    if (is_array($dating_styles) && !empty($dating_styles)) {
                        update_user_meta($user_id, 'dating_styles', $dating_styles);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "No dating styles were selected."; }
                    break;

                case 6:
                    $occupation = isset($_POST['occupation']) ? sanitize_text_field(trim($_POST['occupation'])) : '';
                    if (!empty($occupation)) {
                        update_user_meta($user_id, 'occupation', $occupation);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "Occupation cannot be empty."; }
                    break;

                case 7:
                    $ethnicity = isset($_POST['ethnicity']) ? sanitize_text_field($_POST['ethnicity']) : '';
                    if (!empty($ethnicity)) {
                        update_user_meta($user_id, 'ethnicity', $ethnicity);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "Ethnicity was not selected."; }
                    break;

                case 8:
                    $race = isset($_POST['race']) ? sanitize_text_field($_POST['race']) : '';
                    if (!empty($race)) {
                        update_user_meta($user_id, 'race', $race);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "Race was not selected."; }
                    break;

                case 9:
                    $smoke = isset($_POST['smoke']) ? sanitize_text_field($_POST['smoke']) : '';
                    if (!empty($smoke)) {
                        update_user_meta($user_id, 'smoke', $smoke);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "Smoking preference was not selected."; }
                    break;

                case 10:
                    $drink = isset($_POST['drink']) ? sanitize_text_field($_POST['drink']) : '';
                    if (!empty($drink)) {
                        update_user_meta($user_id, 'drink', $drink);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "Drinking preference was not selected."; }
                    break;

                case 11:
                    $about_me = isset($_POST['about_me']) ? sanitize_textarea_field(trim($_POST['about_me'])) : '';
                    if (!empty($about_me)) {
                        update_user_meta($user_id, 'about_me', $about_me);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else { $processing_error = "About Me cannot be empty."; }
                    break;

                case 12:
                    $age_min = isset($_POST['age_min']) ? intval($_POST['age_min']) : 18;
                    $age_max = isset($_POST['age_max']) ? intval($_POST['age_max']) : 70;
                    $ethnicities_to_save = [];
    
                    if (isset($_POST['any_ethnicity']) && $_POST['any_ethnicity'] === '1') {
                        $ethnicities_to_save = [SUD_ANY_ETHNICITY_KEY];
                    } else {
                        $ethnicities_raw = isset($_POST['ethnicities']) ? $_POST['ethnicities'] : array();
                        if (is_array($ethnicities_raw)) {
                            $ethnicities_to_save = array_map('sanitize_text_field', $ethnicities_raw);
                        }
                    }

                    if ($age_min <= $age_max && $age_min >= 18 && $age_max <= 70) {
                        update_user_meta($user_id, 'looking_for_age_min', $age_min);
                        update_user_meta($user_id, 'looking_for_age_max', $age_max);
                        update_user_meta($user_id, 'looking_for_ethnicities', $ethnicities_to_save);
                        update_user_meta($user_id, $completed_flag_key, true);
                    } else {
                        $processing_error = "Invalid age range provided.";
                    }
                    break;

                case 13:
                    $upload_count = 0;
                    $photos_error_message = '';
                    $new_upload_attempted = isset($_FILES['user_photos']) && !empty($_FILES['user_photos']['name'][0]);

                    $profile_picture_id = get_user_meta($user_id, 'profile_picture', true);
                    $user_photos = get_user_meta($user_id, 'user_photos', true);
                    if (!is_array($user_photos)) {
                        $user_photos = array();
                    }
                    $initial_photo_count = count($user_photos);
                    $initial_profile_picture_id = $profile_picture_id;

                    if ($new_upload_attempted) {
                        foreach ($_FILES['user_photos']['name'] as $key => $value) {
                            if ($_FILES['user_photos']['error'][$key] === UPLOAD_ERR_NO_FILE) {
                                continue;
                            }
                            if ($_FILES['user_photos']['error'][$key] !== UPLOAD_ERR_OK) {
                                $photos_error_message .= "Error uploading file: " . htmlspecialchars($_FILES['user_photos']['name'][$key]) . " (Code: " . $_FILES['user_photos']['error'][$key] . ")<br>";
                                continue;
                            }
                            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                            if (!in_array($_FILES['user_photos']['type'][$key], $allowed_types)) {
                                $photos_error_message .= "Invalid file type: " . htmlspecialchars($_FILES['user_photos']['name'][$key]) . ". Please upload JPG, PNG, GIF, or WEBP.<br>";
                                continue;
                            }
                            if ($_FILES['user_photos']['size'][$key] > 5 * 1024 * 1024) {
                                $photos_error_message .= "File too large: " . htmlspecialchars($_FILES['user_photos']['name'][$key]) . ". Max 5MB allowed.<br>";
                                continue;
                            }

                            sud_compress_image($_FILES['user_photos']['tmp_name'][$key], 75);

                            $file_for_sideload = array(
                                'name'     => $_FILES['user_photos']['name'][$key],
                                'type'     => $_FILES['user_photos']['type'][$key],
                                'tmp_name' => $_FILES['user_photos']['tmp_name'][$key],
                                'error'    => $_FILES['user_photos']['error'][$key],
                                'size'     => $_FILES['user_photos']['size'][$key]
                            );

                            $attachment_id = media_handle_sideload($file_for_sideload, 0);

                            if (is_wp_error($attachment_id)) {
                                $photos_error_message .= "Error processing '{$file_for_sideload['name']}': " . $attachment_id->get_error_message() . "<br>";
                            } else {
                                if (!in_array($attachment_id, $user_photos)) {
                                    $user_photos[] = $attachment_id;
                                }
                                $upload_count++;

                                if (empty($profile_picture_id)) {
                                    update_user_meta($user_id, 'profile_picture', $attachment_id);
                                    $profile_picture_id = $attachment_id;
                                }
                            }
                        }
                        if ($upload_count > 0) {
                            update_user_meta($user_id, 'user_photos', array_unique($user_photos));
                        }
                    }

                    $current_profile_picture_id = get_user_meta($user_id, 'profile_picture', true);
                    $final_user_photos = get_user_meta($user_id, 'user_photos', true);
                    if(!is_array($final_user_photos)) $final_user_photos = [];


                    if (empty($current_profile_picture_id) || empty($final_user_photos)) {
                        if (!empty($photos_error_message)) {
                             $processing_error = $photos_error_message . "Additionally, a profile picture is required.";
                        } else {
                             $processing_error = 'A profile picture is required. Please upload at least one photo.';
                        }
                    } else {
                        update_user_meta($user_id, $completed_flag_key, true);
                        update_user_meta($user_id, 'profile_completed', true);
                        update_user_meta($user_id, 'basic_details_complete', true);
                        update_user_meta($user_id, 'profile_completion_date', current_time('mysql'));
                        update_user_meta($user_id, 'just_completed_profile', true);
                        $redirect_to_dashboard = true;
                    }
                    break;

                default:
                    error_log("Unknown profile step submitted: " . $submitted_step);
                    break;
            }

            if ($processing_error) {
                wp_safe_redirect(add_query_arg(['active' => $submitted_step, 'step_error' => urlencode($processing_error)], SUD_URL . '/profile-details'));
                exit;
            } elseif ($redirect_to_dashboard) {
                wp_safe_redirect(SUD_URL . '/pages/swipe?first_time=1');
                exit;
            } else {
                $next_active_step = $submitted_step + 1;

                $profile_pic_check_next = get_user_meta($user_id, 'profile_picture', true);
                $is_photo_step_done_next = get_user_meta($user_id, 'completed_step_13', true);

                if ($next_active_step > 13 && (empty($profile_pic_check_next) || !$is_photo_step_done_next)) {
                    wp_safe_redirect(SUD_URL . '/profile-details?active=13&step_error=' . urlencode('Please complete the photo upload step.'));
                    exit;
                }

                $current_functional_role = get_user_meta($user_id, 'functional_role', true);
                $is_receiver_role_nav = ($current_functional_role === 'receiver');
                $skipped_steps_nav = $is_receiver_role_nav ? [1, 2, 3] : [];
                $total_steps_nav = 14; 

                while (in_array($next_active_step, $skipped_steps_nav) && $next_active_step < $total_steps_nav) {
                    $next_active_step++;
                }

                if ($next_active_step >= $total_steps_nav) {
                    if (empty($profile_pic_check_next) || !$is_photo_step_done_next) {
                       wp_safe_redirect(SUD_URL . '/profile-details?active=13&step_error=' . urlencode('A profile picture is required before finishing.'));
                       exit;
                    } else {
                       update_user_meta($user_id, 'just_completed_profile', true);
                       wp_safe_redirect(SUD_URL . '/pages/swipe?first_time=1');
                       exit;
                    }
                }

                wp_safe_redirect(SUD_URL . '/profile-details?active=' . $next_active_step);
                exit;
            }
        }
    }
}

$user_terms = get_user_meta($user_id, 'relationship_terms', true);
$user_dating_styles = get_user_meta($user_id, 'dating_styles', true);

$initial_terms_count = is_array($user_terms) ? count($user_terms) : 0;
$initial_styles_count = is_array($user_dating_styles) ? count($user_dating_styles) : 0;

$requested_step = isset($_GET['active']) ? intval($_GET['active']) : 0;
$total_steps = 14;

$user_gender = get_user_meta($user_id, 'gender', true);
$user_functional_role = get_user_meta($user_id, 'functional_role', true);
$is_receiver_role = ($user_functional_role === 'receiver');
$skipped_receiver_steps = [1, 2, 3];
$first_valid_receiver_step_after_skip = 4;

if ($is_receiver_role && in_array($requested_step, $skipped_receiver_steps)) {
    wp_safe_redirect(SUD_URL . '/profile-details?active=' . $first_valid_receiver_step_after_skip);
    exit;
}
if ($requested_step < 0 || $requested_step >= $total_steps) {
    $active_step = 0;
} else {
    $active_step = $requested_step;
}

$completed_steps = array();
for ($i = 0; $i < $total_steps; $i++) {
    if ($is_receiver_role && in_array($i, $skipped_receiver_steps)) {
        $completed_steps[$i] = false;
        continue;
    }
    $completed_steps[$i] = get_user_meta($user_id, 'completed_step_' . $i, true);
}

$step_labels = [
    'Interests', 'Annual Income', 'Net Worth', 'Dating Budget',
    'Relationship Status', 'Dating Style', 'Occupation', 'Ethnicity', 'Race',
    'Smoke', 'Drink', 'About Me', 'Looking For', 'Photos'
];
$step_icons = [
    'fa-search', 'fa-money-bill-wave', 'fa-university', 'fa-hand-holding-usd',
    'fa-heart', 'fa-heartbeat', 'fa-briefcase', 'fa-users', 'fa-users',
    'fa-smoking', 'fa-glass-martini', 'fa-file-alt', 'fa-user-check', 'fa-camera'
];
$step_slugs = [
    'interests', 'annual-income', 'net-worth', 'dating-budget',
    'relationship-status', 'dating-style', 'occupation', 'ethnicity', 'race',
    'smoke', 'drink', 'about-me', 'looking-for', 'photos'
];

if (!isset($step_slugs[$active_step])) {
    wp_safe_redirect(SUD_URL . '/profile-details?active=0');
    exit;
}

$step_slug = $step_slugs[$active_step];
$step_file = 'profile-steps/step-' . $active_step . '-' . $step_slug . '.php';
$full_path = dirname(__FILE__) . '/' . $step_file;

$site_name = get_bloginfo('name');
if (isset($step_labels[$active_step])) {
    $current_step_label = $step_labels[$active_step];
    $page_title = $current_step_label . ' | ' . $site_name;
} else {
    $page_title = 'Complete Your Profile | ' . $site_name;
}

include('templates/header.php');
?>
<div class="sud-join-content">
    <div class="sud-join-image">
        <!-- Background image will be applied via CSS -->
    </div>

    <div class="sud-multi-step-container">
        <?php include('profile-steps/step-nav.php'); ?>
        <div class="sud-steps-content">
            <?php
            if (!empty($display_page_message)) {
                $alert_class = $message_type_is_error ? 'sud-error-alert' : 'sud-notice-info';
                $style_bg = $message_type_is_error ? '#f8d7da' : '#d1ecf1';
                $style_color = $message_type_is_error ? '#721c24' : '#0c5460';
                $style_border = $message_type_is_error ? '#f5c6cb' : '#bee5eb';
                echo '<div class="' . esc_attr($alert_class) . '" style="padding: 10px 15px; margin: 0 20px 20px 20px; border-radius: 4px; text-align: center; background-color: ' . $style_bg . '; color: ' . $style_color . '; border: 1px solid ' . $style_border . ';">';
                echo htmlspecialchars($display_page_message);
                echo '</div>';
            }
            if (file_exists($full_path)) {
                include($full_path);
            } else {
                echo '<div class="sud-error-message" style="display:block;text-align:center;margin:20px;color:white;">
                    Error: Step content could not be loaded. (' . esc_html($step_file) . ')<br> Path Checked: '.esc_html($full_path).'
                </div>';
                error_log("Step file not found: " . $full_path);
            }
            ?>
        </div>
        <?php include('profile-steps/step-footer-nav.php'); ?>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/compressorjs/1.2.1/compressor.min.js" integrity="sha512-MgYeYFj8R3S6rvZHiJ1xA9cM/VDGcT4eRRFQwGA7qDP7NHbnWKNmAm28z0LVjOuUqjD0T9JxpDMdVqsZOSHaSA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const stepDots = document.querySelectorAll('.sud-step-dot');
        if (stepDots.length > 0) {
            const isCurrentUserReceiver = <?php echo json_encode($is_receiver_role); ?>;
            const skippedStepsForReceiver = <?php echo json_encode($skipped_receiver_steps); ?>;

            stepDots.forEach(dot => {
                const stepIndex = parseInt(dot.dataset.step, 10);
                const isSkipped = isCurrentUserReceiver && skippedStepsForReceiver.includes(stepIndex);

                if (!isSkipped && (dot.classList.contains('completed') || dot.classList.contains('active'))) {
                    dot.style.cursor = 'pointer';
                } else {
                    dot.removeAttribute('onclick');
                    dot.style.cursor = 'default';
                }
            });
        }

        const floatingInputs = document.querySelectorAll('.sud-input-floating input');
        if (floatingInputs.length > 0) {
            floatingInputs.forEach(input => {
                if (input.value.trim() !== '') {
                    input.classList.add('has-value');
                }

                input.addEventListener('focus', function() {
                    this.classList.add('has-value');
                });

                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.classList.remove('has-value');
                    }
                });
            });
        }

        const desktopBtn = document.getElementById('desktop-action-btn');
        const mobileBtn = document.querySelector('.sud-next-btn:not(.desktop-btn)');

        if (desktopBtn && mobileBtn) {
            desktopBtn.addEventListener('click', function() {
                mobileBtn.click();
            });
        }
        const stepForm = document.querySelector('.sud-step-content form');
        if (stepForm) {
            const actionBtn = document.querySelector('#step-action-btn, [id$="-action-btn"]');
            if (actionBtn) {
                actionBtn.addEventListener('click', function() {
                    if (!this.disabled && !this.classList.contains('disabled')) {
                        stepForm.submit();
                    }
                });
            }
        }
    });
</script>
<script>
    const sudProfileData = {
        isReceiverRole: <?php echo json_encode($is_receiver_role); ?>,
        skippedReceiverSteps: <?php echo json_encode($skipped_receiver_steps); ?>,
        totalSteps: <?php echo json_encode($total_steps); ?>,
        initialTermsCount: <?php echo json_encode($initial_terms_count); ?>,
        initialStylesCount: <?php echo json_encode($initial_styles_count); ?>
    };
</script>
<?php include('templates/footer.php'); ?>