<?php
require_once(dirname(__FILE__, 3) . '/wp-load.php');
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');

require_login();

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Use centralized validation for all profile requirements (no photo requirement)
validate_core_profile_requirements($user_id, 'settings');

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['sud_settings_nonce']) || !wp_verify_nonce($_POST['sud_settings_nonce'], 'sud_update_settings_action')) {
        $error_message = 'Security check failed. Please try again.';
    } else {
        $new_settings = [];
        $allowed_settings = ['email_notifications', 'message_notifications', 'favorite_notifications', 'view_notifications', 'hide_online_status']; 

        foreach ($allowed_settings as $key) {
            $new_settings[$key] = isset($_POST[$key]);
        }

        if (function_exists('update_user_settings')) {
            $updated = update_user_settings($user_id, $new_settings);
            if ($updated) {
                $success_message = 'Settings saved successfully!';
            } else {
                global $wpdb;
                if (!empty($wpdb->last_error)) {
                    $error_message = 'Database error saving settings. Please contact support.';
                    error_log("SUD Settings Save Error: " . $wpdb->last_error);
                } else {
                    $success_message = 'Settings saved (or no changes detected).'; 
                }
            }
        } else {
            error_log("SUD Error: update_user_settings function not found.");
            $error_message = 'Error: Settings function unavailable.';
        }
    }
}

$current_settings = get_user_settings($user_id);
$page_title = "Account Settings";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (function_exists('get_site_icon_url') && ($icon_url = get_site_icon_url())) {
        echo '<link rel="icon" href="' . esc_url($icon_url) . '" />';
    } ?>
    <title><?php echo function_exists('sud_get_formatted_page_title') ? sud_get_formatted_page_title($page_title) : esc_html($page_title . ' - ' . SUD_SITE_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/settings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
</head>
<body>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>
    <div id="toast-container" class="toast-container"></div>
    <main class="main-content">
        <div class="container settings-container">
            <h1>Account Settings</h1>
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo esc_html($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo esc_html($error_message); ?></div>
            <?php endif; ?>
            <form method="POST" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" id="sud-settings-form">
                <?php wp_nonce_field('sud_update_settings_action', 'sud_settings_nonce'); ?>
                <fieldset class="settings-group">
                    <legend>Notification Preferences</legend>
                    <p class="settings-group-description">Choose which email notifications you want to receive.</p>
                    <div class="setting-item">
                        <label class="switch">
                            <input type="checkbox" name="email_notifications" id="email_notifications" value="1" <?php checked($current_settings['email_notifications'], true); ?>>
                            <span class="slider round"></span>
                        </label>
                        <label for="email_notifications" class="setting-label">General Email Notifications</label>
                        <small class="setting-description">Receive general updates and announcements via email.</small>
                    </div>
                    <div class="setting-item">
                        <label class="switch">
                             <input type="checkbox" name="message_notifications" id="message_notifications" value="1" <?php checked($current_settings['message_notifications'], true); ?>>
                             <span class="slider round"></span>
                         </label>
                         <label for="message_notifications" class="setting-label">New Message Alerts</label>
                         <small class="setting-description">Get notified by email when you receive a new message.</small>
                    </div>
                    <div class="setting-item">
                        <label class="switch">
                             <input type="checkbox" name="favorite_notifications" id="favorite_notifications" value="1" <?php checked($current_settings['favorite_notifications'], true); ?>>
                             <span class="slider round"></span>
                        </label>
                         <label for="favorite_notifications" class="setting-label">New Favorite Alerts</label>
                         <small class="setting-description">Get notified by email when someone adds you to their favorites.</small>
                    </div>
                    <div class="setting-item">
                         <label class="switch">
                             <input type="checkbox" name="view_notifications" id="view_notifications" value="1" <?php checked($current_settings['view_notifications'], true); ?>>
                             <span class="slider round"></span>
                         </label>
                         <label for="view_notifications" class="setting-label">Profile View Alerts</label>
                          <small class="setting-description">Get notified by email when someone views your profile.</small>
                    </div>
                    <div class="setting-item">
                         <label class="switch">
                             <input type="checkbox" name="match_notifications" id="match_notifications" value="1" <?php checked($current_settings['match_notifications'], true); ?>>
                             <span class="slider round"></span>
                         </label>
                         <label for="match_notifications" class="setting-label">Match Alerts</label>
                          <small class="setting-description">Get notified by email when you get a new match.</small>
                    </div>
                </fieldset>
                <fieldset class="settings-group">
                    <legend>Privacy Settings</legend>
                    <div class="setting-item">
                         <label class="switch">
                             <input type="checkbox" name="hide_online_status" id="hide_online_status" value="1" <?php checked($current_settings['hide_online_status'], true); ?>>
                              <span class="slider round"></span>
                         </label>
                         <label for="hide_online_status" class="setting-label">Hide Online Status</label>
                          <small class="setting-description">Prevent others from seeing when you are currently online.</small>
                    </div>
                </fieldset>
                 <fieldset class="settings-group">
                    <legend>Account Management</legend>
                     <div class="setting-item">
                        <a href="<?php echo SUD_URL; ?>/auth/reset-password" class="btn btn-secondary">Change Password</a>
                    </div>
                     <div class="setting-item">
                        <a href="<?php echo SUD_URL; ?>/auth/delete-account" class="btn btn-danger">Delete Account</a>
                         <small class="setting-description">Warning: This action is permanent and cannot be undone.</small>
                    </div>
                 </fieldset>

                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </main>

    <?php include(dirname(__FILE__, 2) . '/templates/components/user-footer.php'); ?>
    
    <script>
   jQuery(document).ready(function($) {
        $('#sud-settings-form').on('submit', function(e) {
            e.preventDefault(); 

            const $form = $(this);
            const $submitButton = $form.find('button[type="submit"]');
            const originalButtonText = $submitButton.html();

            $submitButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            $('.alert').remove(); 

            const formData = $form.serialize(); 

            $.ajax({
                url: '<?php echo esc_url(SUD_AJAX_URL . '/save-settings.php'); ?>',
                type: 'POST',
                data: formData, 
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (typeof SUD !== 'undefined' && SUD.showToast) {
                            SUD.showToast('success', 'Success', response.message || 'Settings saved successfully!');
                        } else {
                            $form.before('<div class="alert alert-success">' + (response.message || 'Settings saved successfully!') + '</div>');
                        }
                    } else {
                        if (typeof SUD !== 'undefined' && SUD.showToast) {
                            SUD.showToast('error', 'Error', response.message || 'Failed to save settings.');
                        } else {
                            $form.before('<div class="alert alert-danger">' + (response.message || 'Failed to save settings.') + '</div>');
                        }
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, jqXHR.responseText); 
                    let errorMsg = 'An unexpected error occurred. Please try again.';

                    try {
                        const errorResponse = JSON.parse(jqXHR.responseText);
                        if (errorResponse && errorResponse.message) {
                            errorMsg = errorResponse.message;
                        }
                    } catch (e) {  }

                    if (typeof SUD !== 'undefined' && SUD.showToast) {
                        SUD.showToast('error', 'Error', errorMsg);
                    } else {
                        $form.before('<div class="alert alert-danger">' + errorMsg + '</div>');
                    }
                },
                complete: function() {
                    $submitButton.prop('disabled', false).html(originalButtonText);
                }
            });
        });
    });
    </script>
</body>
</html>