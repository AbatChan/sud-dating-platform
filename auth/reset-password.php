<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/auth.php');
require_once(dirname(__FILE__, 2) . '/includes/mailer.php');

// Check if the necessary parameters are present
if (!isset($_GET['key']) || !isset($_GET['login'])) {
    wp_redirect(SUD_URL . '/auth/login');
    exit;
}

// Sanitize GET parameters
$reset_key = sanitize_text_field($_GET['key']);
$login = sanitize_user($_GET['login']);
$user = check_password_reset_key($reset_key, $login);

$message = '';
$error = '';
$success = false;

// Check if the reset key is valid
if (is_wp_error($user)) {
    $error = 'Invalid or expired password reset link. Please request a new one.';
} else {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF Protection
        if (!wp_verify_nonce($_POST['reset_password_nonce'], 'sud_reset_password_action')) {
            $error = 'Security verification failed. Please try again.';
        } else {
            // Don't sanitize passwords - preserve original input for validation
            $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
            
            if (empty($new_password)) {
                $error = 'Please enter a new password';
            } elseif (is_password_recently_used($user->ID, $new_password)) {
                $error = 'Please use a password you haven\'t used recently.';
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters long';
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $error = 'Password must include at least one uppercase letter';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match';
            } else {
                // Update password history before resetting
                update_password_history($user->ID, $new_password);
            
                // Reset the password
                reset_password($user, $new_password);
                
                // Mark as success
                $success = true;

                if ($success) {
                    // Send notification email about password change
                    send_password_changed_notification($user->user_email);
                }
            }
        }
    }
}

include(dirname(__FILE__, 2) . '/templates/header.php');
?>

<div class="sud-join-content">
    <div class="sud-join-image">
        <!-- Background image will be applied via CSS -->
    </div>
    <div class="sud-join-form-container sud-login-form-container">
        <form class="sud-login-form sud-join-form" method="post">
            <?php wp_nonce_field('sud_reset_password_action', 'reset_password_nonce'); ?>
            <div class="cont-form">
                <div class="sud-login-logo">
                    <a href="<?php echo site_url(); ?>">
                        <?php
                        $site_icon_url = get_site_icon_url();
                        if (!empty($site_icon_url)) {
                            // Use WordPress site icon if available
                            echo '<img src="' . esc_url($site_icon_url) . '" alt="Logo">';
                        } else {
                            // Fallback to local logo.png if site icon not set
                            echo '<img src="' . SUD_IMG_URL . '/logo.png" alt="Logo">';
                        }
                        ?>
                    </a>
                </div>
                
                <h3 class="sud-welcome-content">Reset Your Password</h3>
                
                <?php if (!empty($error)): ?>
                    <div class="sud-error-alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="sud-success-alert">
                        <p>Your password has been reset successfully!</p>
                        <p>You can now <a href="<?php echo SUD_URL; ?>/auth/login">login</a> with your new password.</p>
                    </div>
                <?php elseif (!is_wp_error($user)): ?>
                    <div class="sud-form-group sud-password-field sud-input-floating">
                        <input type="password" id="new_password" name="new_password" class="sud-form-input" required>
                        <label for="new_password" class="sud-floating-label">New Password <span class="sud-required">*</span></label>
                        <span class="sud-password-toggle"><i class="fas fa-eye"></i></span>
                        <div class="sud-error-message" style="display: none;">Password is required</div>
                    </div>
                    
                    <div class="sud-form-group sud-password-field sud-input-floating">
                        <input type="password" id="confirm_password" name="confirm_password" class="sud-form-input" required>
                        <label for="confirm_password" class="sud-floating-label">Confirm Password <span class="sud-required">*</span></label>
                        <span class="sud-password-toggle"><i class="fas fa-eye"></i></span>
                        <div class="sud-error-message" style="display: none;">Please confirm your password</div>
                    </div>
                    
                    <div class="sud-password-requirements">
                        <p>Password must:</p>
                        <ul>
                            <li>Be at least 6 characters long</li>
                            <li>Contain at least one uppercase letter</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="sud-login-btn">Reset Password</button>
                <?php endif; ?>
                
                <div class="sud-login-divider">
                    <span>or</span>
                </div>
                
                <div class="sud-back-to-login">
                    <a href="<?php echo SUD_URL; ?>/auth/login" class="sud-back-login-link">Back to Login</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Floating labels animation setup
        const inputs = document.querySelectorAll('.sud-form-input');
        
        inputs.forEach(input => {
            // Add active class if input has value on page load
            if (input.value.trim() !== '') {
                input.classList.add('has-value');
            }
            
            // Handle input events
            input.addEventListener('focus', function() {
                this.classList.add('has-value');
            });
            
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.remove('has-value');
                }
            });
        });
        
        // Password visibility toggle
        const passwordToggles = document.querySelectorAll('.sud-password-toggle');
        if (passwordToggles.length > 0) {
            passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const input = this.previousElementSibling.previousElementSibling;
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    
                    // Toggle icon
                    if (type === 'text') {
                        this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                    } else {
                        this.innerHTML = '<i class="fas fa-eye"></i>';
                    }
                });
            });
        }
        
        // Form validation
        const form = document.querySelector('.sud-login-form');
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        if (form && newPasswordInput && confirmPasswordInput) {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Validate new password
                if (newPasswordInput.value.trim() === '') {
                    const errorMsg = newPasswordInput.nextElementSibling.nextElementSibling.nextElementSibling;
                    errorMsg.textContent = 'Password is required';
                    errorMsg.style.display = 'block';
                    isValid = false;
                } else if (newPasswordInput.value.length < 6) {
                    const errorMsg = newPasswordInput.nextElementSibling.nextElementSibling.nextElementSibling;
                    errorMsg.textContent = 'Password must be at least 6 characters long';
                    errorMsg.style.display = 'block';
                    isValid = false;
                } else if (!/[A-Z]/.test(newPasswordInput.value)) {
                    const errorMsg = newPasswordInput.nextElementSibling.nextElementSibling.nextElementSibling;
                    errorMsg.textContent = 'Password must include at least one uppercase letter';
                    errorMsg.style.display = 'block';
                    isValid = false;
                }
                
                // Validate confirm password
                if (confirmPasswordInput.value.trim() === '') {
                    const errorMsg = confirmPasswordInput.nextElementSibling.nextElementSibling.nextElementSibling;
                    errorMsg.textContent = 'Please confirm your password';
                    errorMsg.style.display = 'block';
                    isValid = false;
                } else if (newPasswordInput.value !== confirmPasswordInput.value) {
                    const errorMsg = confirmPasswordInput.nextElementSibling.nextElementSibling.nextElementSibling;
                    errorMsg.textContent = 'Passwords do not match';
                    errorMsg.style.display = 'block';
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
            
            // Clear error messages on input
            newPasswordInput.addEventListener('input', function() {
                const errorMsg = this.nextElementSibling.nextElementSibling.nextElementSibling;
                if (errorMsg) {
                    errorMsg.style.display = 'none';
                }
            });
            
            confirmPasswordInput.addEventListener('input', function() {
                const errorMsg = this.nextElementSibling.nextElementSibling.nextElementSibling;
                if (errorMsg) {
                    errorMsg.style.display = 'none';
                }
            });
        }
    });
</script>

<?php include(dirname(__FILE__, 2) . '/templates/footer.php'); ?>