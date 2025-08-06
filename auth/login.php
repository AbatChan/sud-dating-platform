<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');

if (is_user_logged_in()) {
    wp_redirect(SUD_URL . '/pages/swipe');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!wp_verify_nonce($_POST['login_nonce'], 'sud_login_action')) {
        $error = 'Security verification failed. Please try again.';
    } else {
        // Sanitize inputs
        $email = isset($_POST['email']) ? sanitize_email(trim($_POST['email'])) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : ''; // Don't sanitize passwords
        $remember = isset($_POST['remember']) ? (bool) $_POST['remember'] : false;
        
        if (empty($email)) {
            $error = 'Please enter your email address';
        } elseif (empty($password)) {
            $error = 'Please enter your password';
        } else {
            require_once(dirname(__FILE__, 2) . '/includes/auth.php');
            // Sanitize IP address
            $ip_address = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ?: '127.0.0.1';
            
            if (is_rate_limited('login_attempt', $ip_address, 1000, 900)) {
                $error = 'Too many login attempts. Please try again in 15 minutes.';
            } else {
                $user_by_email = get_user_by('email', $email);
                
                if ($user_by_email) {
                    $creds = array(
                        'user_login'    => $user_by_email->user_login,
                        'user_password' => $password,
                        'remember'      => $remember
                    );
                    
                    $user = wp_signon($creds, false);
                    
                    if (is_wp_error($user)) {
                        $error = 'The password you entered is incorrect.';
                        is_rate_limited('login_attempt', $ip_address, 5, 900);
                    } else {
                        update_user_meta($user->ID, 'last_active', time());
                        wp_redirect(SUD_URL . '/pages/swipe');
                        exit;
                    }
                } else {
                    $error = 'Unknown email address. Please check your email or register for an account.';
                    is_rate_limited('login_attempt', $ip_address, 5, 900);
                }
            }
        }
    }
}

$site_name = get_bloginfo('name');
$page_title = 'Sign in to your account | ' . $site_name;

include(dirname(__FILE__, 2) . '/templates/header.php');
?>

<div class="sud-join-content">
    <div class="sud-join-image">
        <!-- Background image applied via CSS -->
    </div>
    <div class="sud-join-form-container sud-login-form-container">
        <form class="sud-login-form sud-join-form" method="post" id="login-form">
            <?php wp_nonce_field('sud_login_action', 'login_nonce'); ?>
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
                                echo '<img src="' . esc_url(SUD_IMG_URL . '/logo.png') . '" alt="' . esc_attr(SUD_SITE_NAME) . '" onerror="this.src=\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjUwIiB2aWV3Qm94PSIwIDAgMTAwIDUwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iNTAiIGZpbGw9IiMxMzBmNDAiLz48dGV4dCB4PSIxMCIgeT0iMzAiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNiIgZmlsbD0id2hpdGUiPkxNUjwvdGV4dD48L3N2Zz4=\'"></a>';
                            }
                        ?>
                    </a>
                </div>
                <?php if (!empty($error)): ?>
                    <div class="sud-error-alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <div class="sud-form-group sud-input-floating">
                    <input type="text" id="email" name="email" class="sud-form-input" value="<?php echo isset($_POST['email']) ? esc_attr(sanitize_email($_POST['email'])) : ''; ?>" required>
                    <label for="email" class="sud-floating-label">Email Address <span class="sud-required">*</span></label>
                    <div class="sud-error-message" style="display: none;">Email Address is required</div>
                </div>
                <div class="sud-form-group sud-password-field sud-input-floating">
                    <input type="password" id="password" name="password" class="sud-form-input" required>
                    <label for="password" class="sud-floating-label">Password <span class="sud-required">*</span></label>
                    <span class="sud-password-toggle"><i class="fas fa-eye"></i></span>
                    <div class="sud-error-message" style="display: none;">Password is required</div>
                </div>
                <div class="sud-forgot-password">
                    <a href="<?php echo SUD_URL; ?>/auth/forgot-password">Forgot Password?</a>
                </div>
                <button type="submit" class="sud-login-btn">Login</button>
                <div class="sud-login-divider">
                    <span>or continue with</span>
                </div>

                <?php
                if ( shortcode_exists( 'nextend_social_login' ) ) {
                    echo '<div class="sud-social-login-buttons">';
                    echo do_shortcode('[nextend_social_login]');
                    echo '</div>';
                } else {
                    echo '<p style="color:red;">Social Login not active.</p>';
                }
                ?>
                <div class="sud-no-account">
                    Don't have an account?
                </div>
                <a href="<?php echo SUD_URL; ?>" class="sud-join-free-btn">Join Free Today</a>
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
        const passwordToggle = document.querySelector('.sud-password-toggle');
        if (passwordToggle) {
            passwordToggle.addEventListener('click', function() {
                const input = document.getElementById('password');
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                if (type === 'text') {
                    this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    this.innerHTML = '<i class="fas fa-eye"></i>';
                }
            });
        }

        const form = document.getElementById('login-form');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                if (emailInput.value.trim() === '') {
                    const errorMsg = emailInput.nextElementSibling.nextElementSibling;
                    errorMsg.style.display = 'block';
                    isValid = false;
                }
                if (passwordInput.value.trim() === '') {
                    const errorMsg = passwordInput.nextElementSibling.nextElementSibling.nextElementSibling;
                    errorMsg.style.display = 'block';
                    isValid = false;
                }
                if (!isValid) {
                    e.preventDefault();
                }
            });
            emailInput.addEventListener('input', function() {
                const errorMsg = this.nextElementSibling.nextElementSibling;
                errorMsg.style.display = 'none';
            });
            passwordInput.addEventListener('input', function() {
                const errorMsg = this.nextElementSibling.nextElementSibling.nextElementSibling;
                errorMsg.style.display = 'none';
            });
        }
    });
</script>

<?php include(dirname(__FILE__, 2) . '/templates/footer.php'); ?>