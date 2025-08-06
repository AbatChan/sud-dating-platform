<?php

require_once(dirname(__FILE__, 3) . '/wp-load.php'); 
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');

require_login();

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$error = '';
$success = false; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['sud_delete_account_nonce']) || !wp_verify_nonce($_POST['sud_delete_account_nonce'], 'sud_delete_account_' . $user_id)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        if (empty($password)) {
            $error = 'Please enter your current password to confirm account deletion.';
        } elseif (!wp_check_password($password, $current_user->user_pass, $user_id)) {
            $error = 'Incorrect password. Account deletion cancelled.';

        } else {
            require_once(ABSPATH . 'wp-admin/includes/user.php'); 
            do_action('sud_before_delete_user', $user_id);
            $deleted = wp_delete_user($user_id, null); 
            if ($deleted) {
                wp_logout(); 
                $success = true;
            } else {
                $error = 'There was an error deleting your account. Please contact support.';
                error_log("SUD Account Deletion Error: Failed to delete user ID $user_id");
            }
        }
    }
}

$page_title = "Delete Account"; 
include(dirname(__FILE__, 2) . '/templates/header.php'); 

?>

<div class="sud-join-content">
    <div class="sud-join-image">
        <!-- Background image applied via CSS -->
    </div>
    <div class="sud-join-form-container sud-delete-form-container">
        <?php if ($success): ?>
            <div class="cont-form" style="text-align: center;">
                 <div class="sud-login-logo" style="margin-bottom: 20px;">
                    <a href="<?php echo site_url(); ?>">
                         <?php 
                         $site_icon_url = get_site_icon_url(150);
                         echo '<img src="' . esc_url($site_icon_url ?: SUD_IMG_URL . '/logo.png') . '" alt="Logo">';
                         ?>
                    </a>
                 </div>
                <h3 class="sud-welcome-content">Account Deleted</h3>
                <div class="sud-success-alert" style="margin-top: 20px;">
                    <p>Your account and all associated data have been permanently deleted.</p>
                    <p>We're sorry to see you go.</p>
                    <p>You will be redirected to the homepage shortly.</p>
                </div>
                <meta http-equiv="refresh" content="7;url=<?php echo esc_url(home_url('/')); ?>">
                 <div style="margin-top: 20px;">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="sud-button sud-button-secondary" style="color:#fff !important;">Go to Homepage Now</a>
                 </div>
            </div>
        <?php else: ?>
            <form class="sud-login-form sud-join-form" method="post">
                <div class="cont-form">
                    <div class="sud-login-logo">
                        <a href="<?php echo esc_url(site_url());?>">
                            <?php
                                $custom_logo_id = get_theme_mod('custom_logo');
                                $logo_image_url = '';
                                if ($custom_logo_id) {
                                    $logo_image_url = wp_get_attachment_image_url($custom_logo_id, 'full');
                                }
                                if (!empty($logo_image_url)) {
                                    echo '<img src="' . esc_url($logo_image_url) . '" alt="' . esc_attr(get_bloginfo('name')) . ' - Logo">';
                                } else {
                                    echo '<img src="' . esc_url(SUD_IMG_URL . '/logo.png') . '" alt="' . esc_attr(BRAND_NAME) . '" onerror="this.src=\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjUwIiB2aWV3Qm94PSIwIDAgMTAwIDUwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iNTAiIGZpbGw9IiMxMzBmNDAiLz48dGV4dCB4PSIxMCIgeT0iMzAiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNiIgZmlsbD0id2hpdGUiPlNVRDwvdGV4dD48L3N2Zz4=\'"></a>';
                                }
                            ?>
                        </a>
                    </div>
                    <h3 class="sud-welcome-content">Delete Your Account</h3>

                    <div class="alert alert-danger" style="text-align: left; margin: 20px 0; padding: 15px; border: 1px solid #dc3545; background-color: rgba(220,53,69,0.1); color: #dc3545;">
                        <strong>Warning:</strong> This action is permanent and cannot be undone. Deleting your account will remove all your profile information, messages, favorites, photos, and any other associated data.
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="sud-error-alert"><?php echo esc_html($error); ?></div>
                    <?php endif; ?>

                    <p style="text-align: left; margin-bottom: 20px; color: rgba(255,255,255,0.9);">To confirm permanent deletion of your account, please enter your current password.</p>
                    <div class="sud-form-group sud-password-field sud-input-floating">
                        <input type="password" id="password" name="password" class="sud-form-input" required>
                        <label for="password" class="sud-floating-label">Current Password <span class="sud-required">*</span></label>
                        <span class="sud-password-toggle"><i class="fas fa-eye"></i></span>
                        <div class="sud-error-message" style="display: none;">Password is required</div>
                    </div>
                    <?php wp_nonce_field('sud_delete_account_' . $user_id, 'sud_delete_account_nonce'); ?>
                    <button type="submit" class="sud-login-btn btn-danger" style="background-color: #dc3545 !important;">Permanently Delete Account</button>
                    <div class="sud-login-divider">
                        <span>or</span>
                    </div>
                    <div class="sud-back-to-login" style="text-align:center;">
                        <a href="<?php echo SUD_URL; ?>/pages/settings" class="sud-back-login-link">Cancel and Go Back to Settings</a>
                    </div>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.sud-form-input');
        inputs.forEach(input => {
            if (input.value.trim() !== '') input.classList.add('has-value');
            input.addEventListener('focus', function() { this.classList.add('has-value'); });
            input.addEventListener('blur', function() { if (this.value.trim() === '') this.classList.remove('has-value'); });
        });
        const passwordToggles = document.querySelectorAll('.sud-password-toggle');
        passwordToggles.forEach(toggle => {
            toggle?.addEventListener('click', function() {
                const input = this.previousElementSibling.previousElementSibling; 
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.innerHTML = type === 'text' ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            });
        });
        const deleteForm = document.querySelector('.sud-login-form'); 
        if(deleteForm && !<?php echo json_encode($success); ?>) { 
            deleteForm.addEventListener('submit', function(e) {
                const passwordInput = document.getElementById('password');
                if (!passwordInput || passwordInput.value.trim() === '') {
                    return;
                }
                if (!confirm('ARE YOU ABSOLUTELY SURE?\n\nThis action will permanently delete your account and all associated data. This cannot be undone.')) {
                    e.preventDefault(); 
                }
            });
        }
    });
</script>
<?php include(dirname(__FILE__, 2) . '/templates/footer.php'); ?>
</body>
</html>