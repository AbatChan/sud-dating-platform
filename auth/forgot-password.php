<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/auth.php');

if (is_user_logged_in()) {
    wp_redirect(SUD_URL . '/pages/swipe');
    exit;
}

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $ip_address = $_SERVER['REMOTE_ADDR'];

    if (is_rate_limited('password_reset', $ip_address, 5, 3600)) {
        $error = 'Too many password reset attempts. Please try again later.';
    } else {
        if (empty($email)) {
            $error = 'Please enter your email address';
        } elseif (!is_email($email)) {
            $error = 'Please enter a valid email address';
        } else {
            $result = create_password_reset_link($email);
            
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                $success = true;
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
                
                <h3 class="sud-welcome-content">Forgot Password</h3>
                
                <?php if ($success): ?>
                    <div class="sud-success-alert">
                        <p>Password reset email has been sent. Please check your inbox for instructions to reset your password.</p>
                        <p>If you don't receive an email within a few minutes, please check your spam folder.</p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($error)): ?>
                        <div class="sud-error-alert"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <p class="sud-forgot-password-text">Enter your email address below and we'll send you a link to reset your password.</p>
                    
                    <div class="sud-form-group sud-input-floating">
                        <input type="text" id="email" name="email" class="sud-form-input" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        <label for="email" class="sud-floating-label">Email Address <span class="sud-required">*</span></label>
                        <div class="sud-error-message" style="display: none;">Email Address is required</div>
                    </div>
                    
                    <button type="submit" class="sud-login-btn">Reset Password</button>
                    
                    <div class="sud-login-divider">
                        <span>or</span>
                    </div>
                    
                    <div class="sud-back-to-login">
                        <a href="<?php echo SUD_URL; ?>/auth/login" class="sud-back-login-link">Back to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.sud-form-input');
        
        inputs.forEach(input => {
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

        const form = document.querySelector('.sud-login-form');
        const emailInput = document.getElementById('email');
        
        if (form && emailInput) {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                if (emailInput.value.trim() === '') {
                    const errorMsg = emailInput.nextElementSibling.nextElementSibling;
                    errorMsg.style.display = 'block';
                    isValid = false;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
                    const errorMsg = emailInput.nextElementSibling.nextElementSibling;
                    errorMsg.textContent = 'Please enter a valid email address';
                    errorMsg.style.display = 'block';
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });

            emailInput.addEventListener('input', function() {
                const errorMsg = this.nextElementSibling.nextElementSibling;
                if (errorMsg) {
                    errorMsg.style.display = 'none';
                }
            });
        }
    });
</script>

<?php include(dirname(__FILE__, 2) . '/templates/footer.php'); ?>