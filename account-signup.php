<?php
require_once('includes/config.php');
require_once('includes/database.php');

if (is_user_logged_in()) {
    wp_safe_redirect(SUD_URL . '/profile-setup?from_acct_signup_logged_in=1');
    exit;
}

if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}

if (!isset($_SESSION['join_session_id'])) {
    header('Location: ' . SUD_URL . '/?error=session_expired_as');
    exit;
}

$progress = join_get_progress($_SESSION['join_session_id']);
if (empty($progress) || empty($progress['gender']) || empty($progress['looking_for'])) {
    header('Location: ' . SUD_URL . '/looking-for?error=sequence_error_as_missingdata');
    exit;
}

if (isset($_GET['action']) && in_array($_GET['action'], ['switch_gender', 'switch_role', 'switch_looking_for'])) {
    header('Location: ' . SUD_URL . '/account-signup'); 
    exit;
}

$error = '';
$email_from_post = '';
$agree_terms = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) { 
    $nonce = isset($_POST['_sud_signup_nonce']) ? $_POST['_sud_signup_nonce'] : '';
    if (!wp_verify_nonce($nonce, 'sud_signup_account_action')) {
        header('Location: ' . SUD_URL . '/account-signup?error=security_check_failed_submit');
        exit;
    }

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $retype_password = isset($_POST['retype_password']) ? $_POST['retype_password'] : '';
    $agree_terms = isset($_POST['agree_terms']) ? true : false;

    $final_gender = isset($_POST['final_gender']) ? sanitize_text_field($_POST['final_gender']) : '';
    $final_role = isset($_POST['final_role']) ? sanitize_text_field($_POST['final_role']) : '';
    $final_looking_for = isset($_POST['final_looking_for']) ? sanitize_text_field($_POST['final_looking_for']) : '';

    $email_from_post = $email; 

    if (empty($final_gender) || !in_array($final_gender, ['Man', 'Woman', 'LGBTQ+'])) {
        $error = 'Invalid final gender state.';
    } else if ($final_gender === 'LGBTQ+' && (empty($final_role) || !in_array($final_role, ['Sugar Daddy/Mommy', 'Sugar Baby']))) {
        $error = 'Invalid final role state for LGBTQ+.';
    } else if (empty($final_looking_for)) {
         $error = 'Invalid final looking for state.';
    } else if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else if (empty($password)) {
        $error = 'Please enter a password';
    } else if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else if (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must include at least one uppercase letter';
    } else if ($password !== $retype_password) {
        $error = 'Passwords do not match';
    } else if (!$agree_terms) {
        $error = 'You must agree to the terms';
    } else {
        if (email_exists($email)) {
            $error = 'This email is already registered';
        } else {
            join_save_progress($_SESSION['join_session_id'], [
                'email' => $email,
                'password' => $password, 
                'gender' => $final_gender, 
                'role' => $final_role,     
                'looking_for' => $final_looking_for, 
                'last_step' => 'account-signup'
            ]);

            set_transient('user_password_' . $_SESSION['join_session_id'], $password, 3600);
            header('Location: ' . SUD_URL . '/introduction-signup');
            exit;
        }
    }
}

$gender = $progress['gender'] ?? '';
$looking_for = $progress['looking_for'] ?? '';
$role = $progress['role'] ?? '';
$email = $error ? $email_from_post : ($progress['email'] ?? ''); 

if (empty($gender) || empty($looking_for) || ($gender === 'LGBTQ+' && empty($role))) {
    header('Location: ' . SUD_URL . '/looking-for?error=missing_progress_as');
    exit;
}

include('templates/header.php');
?>

<div class="sud-join-content">
    <div class="sud-join-image">
    </div>
    <div class="sud-join-form-container">
        <a href="<?php echo SUD_URL; ?>/looking-for" class="sud-back-button">
            <i class="fas fa-arrow-left"></i>
        </a>
        <form class="sud-join-form" method="post" id="account-signup-form">
            <div class="cont-form">
                <h3 class="sud-welcome-content">Create new account</h3>
                <?php
                if ( shortcode_exists( 'nextend_social_login' ) ) {
                    echo '<div class="sud-social-login-buttons sud-social-signup-buttons">';
                    echo do_shortcode('[nextend_social_login]');
                    echo '</div>';
                    echo '<div class="sud-login-divider" style="margin-top: 20px;"><span>or sign up with email</span></div>';
                }
                ?>
                <div class="sud-form-group">
                    <label class="sud-form-label">
                        I am a
                        <a href="#" class="sud-gender-selection">
                            <strong><u id="selected-gender"><?php echo htmlspecialchars($gender); ?></u></strong>
                        </a>
                        <a href="#" class="sud-switch-link" id="switch-gender-link">
                            <i class="fas fa-sync-alt"></i> Switch
                        </a>
                        <span id="role-container" <?php echo $gender !== 'LGBTQ+' ? 'style="display:none;"' : ''; ?>>
                            , a
                            <a href="#" class="sud-role-selection">
                                <strong><u id="selected-role"><?php echo htmlspecialchars($role); ?></u></strong>
                            </a>
                            <a href="#" class="sud-switch-link" id="switch-role-link">
                                <i class="fas fa-sync-alt"></i> Switch
                            </a>
                        </span>
                        <br>
                        looking for
                        <a href="#" class="sud-looking-for-selection">
                            <strong><u id="selected-looking-for"><?php echo htmlspecialchars($looking_for); ?></u></strong>
                        </a>
                        <a href="#" class="sud-switch-link" id="switch-looking-for-link">
                            <i class="fas fa-sync-alt"></i> Switch
                        </a>
                    </label>
                    <input type="hidden" name="final_gender" id="hidden-final-gender" value="<?php echo htmlspecialchars($gender); ?>">
                    <input type="hidden" name="final_role" id="hidden-final-role" value="<?php echo htmlspecialchars($role); ?>">
                    <input type="hidden" name="final_looking_for" id="hidden-final-looking-for" value="<?php echo htmlspecialchars($looking_for); ?>">
                </div>

                <?php if (!empty($error)): ?>
                    <div class="sud-error-alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="sud-form-group">
                    <input type="email" id="email" name="email" class="sud-form-input" placeholder="Email Address" value="<?php echo htmlspecialchars($email); ?>" required>
                    <div class="sud-error-message" style="display: none;">Email Address is required</div>
                </div>

                <div class="sud-form-group sud-password-field">
                    <input type="password" id="password" name="password" class="sud-form-input" placeholder="Password" required>
                    <span class="sud-password-toggle"><i class="fas fa-eye"></i></span>
                    <div class="sud-error-message" style="display: none;">Password is required</div>
                </div>

                <div class="sud-form-group sud-password-field">
                    <input type="password" id="retype_password" name="retype_password" class="sud-form-input" placeholder="Retype Password" required>
                    <span class="sud-password-toggle"><i class="fas fa-eye"></i></span>
                    <div class="sud-error-message" style="display: none;">Please retype your password</div>
                </div>

                <div class="sud-form-group sud-checkbox">
                    <input type="checkbox" id="agree_terms" name="agree_terms" class="sud-checkbox-input" <?php echo $agree_terms ? 'checked' : ''; ?> required>
                    <label for="agree_terms" class="sud-checkbox-label">I'm over 18 years old</label>
                    <div class="sud-error-message" style="display:none;">You must agree to the terms</div>
                </div>

                <button type="submit" class="sud-join-btn">Continue</button>
                <p class="sud-terms-text">
                    By continuing you agree to <?php echo get_bloginfo('name'); ?>'s
                    <a href="/privacy-policy/" target="_blank" class="sud-terms-link">Terms</a> and
                    <a href="/privacy-policy" target="_blank" class="sud-terms-link">Privacy Policy</a>.
                    Promoting illegal commercial activities (such as prostitution) is prohibited.
                    Users must be at least 18 years old.
                </p>
            </div>
            <?php wp_nonce_field('sud_signup_account_action', '_sud_signup_nonce'); ?>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const genderSwitchLink = document.getElementById('switch-gender-link');
    const roleSwitchLink = document.getElementById('switch-role-link');
    const lookingForSwitchLink = document.getElementById('switch-looking-for-link');

    const selectedGenderEl = document.getElementById('selected-gender');
    const roleContainer = document.getElementById('role-container');
    const selectedRoleEl = document.getElementById('selected-role');
    const selectedLookingForEl = document.getElementById('selected-looking-for');

    const hiddenGenderInput = document.getElementById('hidden-final-gender');
    const hiddenRoleInput = document.getElementById('hidden-final-role');
    const hiddenLookingForInput = document.getElementById('hidden-final-looking-for');

    let currentGender = hiddenGenderInput.value;
    let currentRole = hiddenRoleInput.value;
    let currentLookingFor = hiddenLookingForInput.value;

    function updateUIAndState(newGender, newRole, newLookingFor) {
        currentGender = newGender;
        currentRole = newRole;
        currentLookingFor = newLookingFor;

        selectedGenderEl.textContent = currentGender;
        selectedRoleEl.textContent = currentRole;
        selectedLookingForEl.textContent = currentLookingFor;

        hiddenGenderInput.value = currentGender;
        hiddenRoleInput.value = currentRole;
        hiddenLookingForInput.value = currentLookingFor;

        if (currentGender === 'LGBTQ+') {
            roleContainer.style.display = 'inline';
        } else {
            roleContainer.style.display = 'none';
            hiddenRoleInput.value = ''; 
        }
    }

    if (genderSwitchLink && selectedGenderEl) {
        genderSwitchLink.addEventListener('click', function(e) {
            e.preventDefault();

            let nextGender = '';
            let nextRole = '';
            let nextLookingFor = '';

            if (currentGender === 'Man') {
                nextGender = 'Woman';
                nextRole = '';
                nextLookingFor = 'Sugar Daddy/Mommy'; 
            } else if (currentGender === 'Woman') {
                nextGender = 'LGBTQ+';
                nextRole = 'Sugar Daddy/Mommy'; 
                nextLookingFor = 'Gay';         
            } else { 
                nextGender = 'Man';
                nextRole = '';
                nextLookingFor = 'Sugar Baby'; 
            }
            updateUIAndState(nextGender, nextRole, nextLookingFor);
        });
    }

    if (roleSwitchLink && selectedRoleEl) {
        roleSwitchLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentGender !== 'LGBTQ+') return; 

            const nextRole = (currentRole === 'Sugar Daddy/Mommy') ? 'Sugar Baby' : 'Sugar Daddy/Mommy';
            updateUIAndState(currentGender, nextRole, currentLookingFor); 
        });
    }

    if (lookingForSwitchLink && selectedLookingForEl) {
        lookingForSwitchLink.addEventListener('click', function(e) {
            e.preventDefault();
            let nextLookingFor = '';

            if (currentGender === 'LGBTQ+') {
                nextLookingFor = (currentLookingFor === 'Gay') ? 'Lesbian' : 'Gay';
            } else if (currentGender === 'Man') {
                nextLookingFor = (currentLookingFor === 'Sugar Baby') ? 'Sugar Daddy/Mommy' : 'Sugar Baby';
            } else if (currentGender === 'Woman') {
                nextLookingFor = (currentLookingFor === 'Sugar Daddy/Mommy') ? 'Sugar Baby' : 'Sugar Daddy/Mommy';
            }
            updateUIAndState(currentGender, currentRole, nextLookingFor); 
        });
    }

    const passwordToggles = document.querySelectorAll('.sud-password-toggle');
    if (passwordToggles.length > 0) {
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.innerHTML = (type === 'text') ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            });
        });
    }

    // Add has-value class functionality for primary color border
    const allInputs = document.querySelectorAll('.sud-form-input');
    allInputs.forEach(input => {
        const checkValue = () => {
            const hasValue = input.value.trim() !== '';
            input.classList.toggle('has-value', hasValue);
        };
        checkValue(); // Initial check
        input.addEventListener('input', checkValue);
        input.addEventListener('blur', checkValue);
    });

    const form = document.getElementById('account-signup-form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const retypePasswordInput = document.getElementById('retype_password');
    const agreeTermsCheckbox = document.getElementById('agree_terms');
    const errorAlert = document.querySelector('.sud-error-alert'); 

    function validateField(input, errorCondition, errorMessage) {
        const parentGroup = input.closest('.sud-form-group');
        const errorMsgDiv = parentGroup ? parentGroup.querySelector('.sud-error-message') : null;
        let isValid = true;

        if (errorCondition) {
            if(errorMsgDiv) {
                errorMsgDiv.textContent = errorMessage;
                errorMsgDiv.style.display = 'block';
            } else if (errorAlert && !errorAlert.textContent) { 
                errorAlert.textContent = errorMessage;
                errorAlert.style.display = 'block';
            }
            isValid = false;
        } else if (errorMsgDiv) {
            errorMsgDiv.style.display = 'none';
        }
        return isValid;
    }

    if (form) {
        if (emailInput) {
            emailInput.addEventListener('input', () => validateField(emailInput, false)); 
        }
         if (passwordInput) {
            passwordInput.addEventListener('input', () => validateField(passwordInput, false)); 
        }
        if (retypePasswordInput) {
            retypePasswordInput.addEventListener('input', () => validateField(retypePasswordInput, false)); 
        }
        if (agreeTermsCheckbox) {
            agreeTermsCheckbox.addEventListener('change', () => validateField(agreeTermsCheckbox, false)); 
        }

        form.addEventListener('submit', function(e) {
            let isFormValid = true;
            if (errorAlert) errorAlert.style.display = 'none'; 

            const emailVal = emailInput ? emailInput.value.trim() : '';
            const passwordVal = passwordInput ? passwordInput.value : '';
            const retypePasswordVal = retypePasswordInput ? retypePasswordInput.value : '';
            const termsChecked = agreeTermsCheckbox ? agreeTermsCheckbox.checked : false;

            isFormValid = validateField(emailInput, !emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal), 'Please enter a valid email address') && isFormValid;
            isFormValid = validateField(passwordInput, !passwordVal, 'Please enter a password') && isFormValid;
            isFormValid = validateField(passwordInput, passwordVal && passwordVal.length < 6, 'Password must be at least 6 characters long') && isFormValid;
            isFormValid = validateField(passwordInput, passwordVal && !/[A-Z]/.test(passwordVal), 'Password must include at least one uppercase letter') && isFormValid;
            isFormValid = validateField(retypePasswordInput, passwordVal && passwordVal !== retypePasswordVal, 'Passwords do not match') && isFormValid;
            isFormValid = validateField(agreeTermsCheckbox, !termsChecked, 'You must agree to the terms') && isFormValid;

            if (!isFormValid) {
                e.preventDefault();
                if(errorAlert && !errorAlert.textContent){ 
                    errorAlert.textContent = "Please correct the errors above.";
                    errorAlert.style.display = 'block';
                }
            } else {
                showLoader();
            }
        });
    }
});
</script>


<?php include('templates/footer.php'); ?>