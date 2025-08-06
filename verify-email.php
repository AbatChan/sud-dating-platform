<?php
require_once(dirname(__FILE__) . '/includes/config.php');
require_once(dirname(__FILE__) . '/includes/database.php');

$progress = join_get_progress($_SESSION['join_session_id']);
if (empty($progress) || $progress['last_step'] !== 'introduction') {
    header('Location: ' . SUD_URL . '/introduction-signup');
    exit;
}

$debug_mode = false;
$error = '';
$success = false;

function transfer_registration_data_to_user_meta($user_id, $session_id) {
    $wpdb = join_connect_to_wp_db();
    $registration_data = join_get_progress($session_id);

    if (!$registration_data) return false;

    $fields_to_transfer = [
        'gender', 'looking_for', 'role', 'functional_role', 'display_name', 'date_of_birth',
        'relationship_terms', 'annual_income', 'net_worth', 'dating_budget',
        'relationship_status', 'dating_styles', 'occupation', 'ethnicity',
        'race', 'smoke', 'drink', 'about_me', 'profile_looking_for'
    ];

    foreach ($fields_to_transfer as $field) {
        if (isset($registration_data[$field]) && $registration_data[$field] !== ''){
            $is_array_field = in_array($field, ['relationship_terms', 'user_photos', 'dating_styles', 'looking_for_ethnicities']);
            $data = ($is_array_field && is_serialized($registration_data[$field]))
                    ? maybe_unserialize($registration_data[$field])
                    : $registration_data[$field];
            update_user_meta($user_id, $field, $data);
        }
    }

    if ($wpdb) {
        $wpdb->update(
            $wpdb->prefix . 'join_registration_progress',
            ['user_id' => $user_id],
            ['session_id' => $session_id]
        );
    } else {
        error_log("SUD verify-email: wpdb connection failed in transfer_registration_data_to_user_meta");
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = isset($_POST['_sud_signup_nonce']) ? $_POST['_sud_signup_nonce'] : '';
    $action = 'sud_signup_verify_action';
    if (!wp_verify_nonce($nonce, $action)) {
        header('Location: ' . SUD_URL . '/verify-email?error=security_check_failed');
        exit;
    }

    $code = trim($_POST['verification_code'] ?? '');

    if (empty($code)) {
        $error = 'Please enter the verification code';
    } else if ($code !== $progress['verification_code']) {
        $error = 'Invalid verification code';
    } else {
        join_save_progress($_SESSION['join_session_id'], [
            'verified' => 1,
            'last_step' => 'verified'
        ]);

        $email = $progress['email'];
        $gender = $progress['gender'];
        $looking_for = $progress['looking_for'];
        $role = $progress['role'] ?? '';
        $password = get_transient('user_password_' . $_SESSION['join_session_id']);

        if (!$password) {
            $wpdb = join_connect_to_wp_db();
            $table = $wpdb->prefix . 'join_registration_progress';
            $stored_password = $wpdb->get_var($wpdb->prepare(
                "SELECT password FROM $table WHERE session_id = %s",
                $_SESSION['join_session_id']
            ));
            $password = $stored_password ?: wp_generate_password(12, true, true);
        }

        // Check for multiple account attempts
        require_once(dirname(__FILE__) . '/includes/auth.php');
        $multiple_account_check = check_multiple_account_prevention($email);
        $user_id = null; // Initialize variable to prevent undefined variable warning
        
        if ($multiple_account_check !== true) {
            $error = $multiple_account_check;
        } else {
            $username = preg_replace('/[^a-z0-9]/', '', strtolower(substr($email, 0, strpos($email, '@')))) . rand(100, 999);
            $user_id = wp_create_user($username, $password, $email);
        }

        if (is_wp_error($user_id)) {
            $error = $user_id->get_error_message();
        } elseif ($user_id) {
            delete_transient('user_password_' . $_SESSION['join_session_id']);
            $user = new WP_User($user_id);
            $user->set_role('subscriber');

            // Store registration metadata for multiple account prevention
            store_registration_metadata($user_id);

            transfer_registration_data_to_user_meta($user_id, $_SESSION['join_session_id']);
            update_user_meta($user_id, 'completed_step_0', true);

            wp_clear_auth_cookie();
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            do_action('wp_login', $username, $user);

            // Track signup conversion with TrafficJunky
            if (function_exists('track_signup_conversion')) {
                track_signup_conversion($user_id, [
                    'email' => $email,
                    'registration_source' => 'email_verification'
                ]);
            }

            $success = true;
        }
    }
}

if (isset($_GET['resend'])) {
    $verification_code = rand(100000, 999999);
    join_save_progress($_SESSION['join_session_id'], [
        'verification_code' => $verification_code
    ]);
    require_once('includes/mailer.php');
    send_verification_code($progress['email'], $verification_code);
}

$email = $progress['email'] ?? '';

include('templates/header.php');
?>

<div class="sud-join-content">
    <div class="sud-join-image">
        <!-- Background image will be applied via CSS -->
    </div>
    <div class="sud-join-form-container">
        <a href="<?php echo SUD_URL; ?>/introduction-signup" class="sud-back-button"><i class="fas fa-arrow-left"></i></a>
        
        <form class="sud-join-form" method="post">
            <div class="cont-form" style="text-align: center;">
                <h3 class="sud-welcome-content">Great! Check your email</h3>
                <p class="sent-code-text">Your code has been sent to <span class="email-label"><?php echo htmlspecialchars($email); ?></span></p>
                <div class="welcome-input-verify-check">
                    <?php if (!empty($error)): ?>
                        <div class="sud-error-alert"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="sud-success-alert">
                            <span class='verified-success-text'>Your email has been verified successfully!</span>
                            <p>You'll be redirected to set up your profile shortly...</p>
                        </div>
                    <?php else: ?>
                        <div class="sud-form-group">
                            <input type="text" id="verification_code" name="verification_code" class="sud-form-input sud-verification-code" required>
                            <div class="sud-error-message" style="display: none;">Verification code is required</div>
                        </div>
                        
                        <div class="sud-resend-code">
                            <span class='verify-code-text'>Did not receive the verification code?</span><br/><br/>
                            <a href="?resend=1" class="sud-resend-link">Resend code</a><span class="in-codedown"> in </span><span id="countdown">52s</span>
                        </div>
                </div>
                <button type="submit" class="sud-join-btn sud-verify-check-btn">Submit</button>
                
                <div class="sud-restart-link">
                    <span class='access-email-text'>Don't have access to this email?</span><br/><br/>
                    <a href="<?php echo SUD_URL; ?>/" class="sud-restart-reg-link">Restart registration</a>
                </div>
            </div>
            <?php wp_nonce_field('sud_signup_verify_action', '_sud_signup_nonce'); ?>
        </form>
        <?php endif;?>

        <?php if (isset($debug_mode) && $debug_mode): ?>
            <div style="background: #ffecb3; padding: 10px; margin: 10px 0; border: 1px solid #dca">
                <strong>Debug:</strong> Verification code: <?php echo htmlspecialchars($progress['verification_code']); ?>
            </div>
        <?php endif; ?>
    </div>
    <div id="success-popup" style="display: none;" class="sud-success-popup">
        <div class="sud-success-popup-content">
            <div class="sud-success-icon">
                <div id="lottie-container"></div>
            </div>
            <h3>Success!</h3>
            <p>Your email has been verified successfully!</p>
            <p>Redirecting to profile setup...</p>
        </div>
    </div>
</div>

<?php if ($success): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof lottie !== 'undefined') {
        const animContainer = document.getElementById('lottie-container');
        if (animContainer) {
            const anim = lottie.loadAnimation({
                container: animContainer,
                renderer: 'svg',
                loop: false,
                autoplay: false,
                path: '<?php echo SUD_ASSETS_URL; ?>/animations/success-animation.json'
            });

            const popup = document.getElementById('success-popup');
            if(popup) {
                popup.style.display = 'flex';
                setTimeout(() => {
                    popup.classList.add('show');
                    anim.play();
                }, 10);

                function redirectToProfile() {
                    if(popup.classList.contains('show')){
                       window.location.href = '<?php echo SUD_URL; ?>/profile-setup';
                    }
                }
                anim.addEventListener('complete', redirectToProfile);
                setTimeout(redirectToProfile, 5000);
            }
        } else {
            console.error("Lottie container not found.");
            setTimeout(() => { window.location.href = '<?php echo SUD_URL; ?>/profile-setup'; }, 1500);
        }
    } else {
        console.warn("Lottie library not loaded. Redirecting without animation.");
        const popup = document.getElementById('success-popup');
        if(popup) {
            popup.style.display = 'flex';
            setTimeout(() => { popup.classList.add('show'); }, 10);
        }
        setTimeout(() => { window.location.href = '<?php echo SUD_URL; ?>/profile-setup'; }, 3000);
    }
});
</script>
<?php endif; ?>

<?php if (!$success): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const countdownElement = document.getElementById('countdown');
    const resendLink = document.querySelector('.sud-resend-link');
    const inTextElement = document.querySelector('.in-codedown');

    if (!countdownElement || !resendLink || !inTextElement) {
        console.error('Countdown timer elements not found!');
        return;
    }

    let timeLeft = 60;

    function updateTimer() {
        if (timeLeft > 0) {
            resendLink.style.pointerEvents = 'none';
            resendLink.style.opacity = '0.6';
            resendLink.style.cursor = 'default';
            countdownElement.textContent = timeLeft + 's';
            inTextElement.style.display = 'inline';
            countdownElement.style.display = 'inline';
        } else {
            countdownElement.style.display = 'none';
            inTextElement.style.display = 'none';
            resendLink.style.pointerEvents = 'auto';
            resendLink.style.opacity = '1';
            resendLink.style.cursor = 'pointer';
            resendLink.textContent = 'Resend Code Now';
            clearInterval(timerInterval);
        }
    }
    updateTimer();
    const timerInterval = setInterval(function() {
        timeLeft--;
        updateTimer();
    }, 1000);
});
</script>
<?php endif; ?>

<?php include('templates/footer.php'); ?>
</body>
</html>