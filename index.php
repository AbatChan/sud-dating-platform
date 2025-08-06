<?php
define('WP_USE_THEMES', false);
require_once('includes/config.php');
require_once('includes/database.php');

// Check maintenance mode before continuing
sud_maintenance_gate();

if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}

// For logged-in users, populate session with existing profile data to prevent sequence errors
if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    $user_gender = get_user_meta($current_user->ID, 'gender', true);
    $user_looking_for = get_user_meta($current_user->ID, 'looking_for', true);
    $functional_role = get_user_meta($current_user->ID, 'functional_role', true);
    
    // Populate session with existing data to allow preference updates
    if (!empty($user_gender)) {
        if (!isset($_SESSION['join_session_id'])) {
            $_SESSION['join_session_id'] = uniqid('join_', true);
        }
        $existing_data = [
            'gender' => $user_gender,
            'last_step' => 'gender-selection'
        ];
        if (!empty($user_looking_for)) {
            $existing_data['looking_for'] = $user_looking_for;
            $existing_data['last_step'] = 'looking-for';
        }
        if (!empty($functional_role)) {
            $existing_data['functional_role'] = $functional_role;
        }
        join_save_progress($_SESSION['join_session_id'], $existing_data);
    }
}

$setup_message = '';
$message_type = 'notice';

// Handle error messages from URL parameters
if (isset($_GET['error'])) {
    $error = sanitize_text_field($_GET['error']);
    $setup_message = htmlspecialchars(urldecode($error));
    $message_type = 'error';
} elseif (isset($_GET['setup_reason'])) {
    $reason = sanitize_text_field($_GET['setup_reason']);
    
    if ($reason === 'core_data_missing_gender' || $reason === 'nsl_new_user_core_data_missing') {
        $setup_message = "Welcome! To continue, please select your gender and role.";
    } elseif ($reason === 'core_data_missing_lookingfor' || $reason === 'nsl_new_user_lookingfor_missing') {
        $setup_message = "Great! Now, tell us who you are looking for.";
    } else if (strpos($reason, 'error') !== false) {
        $setup_message = "There was an issue: " . htmlspecialchars(urldecode($reason));
        $message_type = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gender'])) {
    if (!isset($_SESSION['join_session_id'])) {
        header('Location: ' . SUD_URL . '/?error=session_missing_on_post');
        exit;
    }

    $nonce = isset($_POST['_sud_signup_nonce']) ? $_POST['_sud_signup_nonce'] : '';
    $action = 'sud_signup_gender_action';
    if (!wp_verify_nonce($nonce, $action)) {
        // Provide more specific error message for better user experience
        $error_msg = 'Your session has expired. Please refresh the page and try again.';
        header('Location: ' . SUD_URL . '/?error=' . urlencode($error_msg));
        exit;
    }

    $gender = sanitize_text_field($_POST['gender']);
    $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
    $looking_for = isset($_POST['looking_for']) ? sanitize_text_field($_POST['looking_for']) : '';

    if (!empty($gender) && in_array($gender, ['Man', 'Woman', 'LGBTQ+'])) {
        $data = [
            'gender' => $gender,
            'last_step' => 'gender-selection'
        ];

        if ($gender === 'LGBTQ+') {
            if ($role === 'Sugar Daddy/Mommy' || $role === 'Sugar Baby') {
                $data['role'] = $role;
            } else {
                header('Location: ' . SUD_URL . '/?error=role_required');
                exit;
            }
        }

        // If looking_for is provided, save it and go to account signup
        if (!empty($looking_for) && in_array($looking_for, ['Sugar Daddy/Mommy', 'Sugar Baby'])) {
            // Calculate functional_role same as looking-for.php
            $functional_role = '';
            if ($gender === 'Man') {
                $functional_role = ($looking_for === 'Sugar Daddy/Mommy') ? 'receiver' : 'provider';
            } elseif ($gender === 'Woman') {
                $functional_role = ($looking_for === 'Sugar Baby') ? 'provider' : 'receiver';
            } elseif ($gender === 'LGBTQ+') {
                $functional_role = ($role === 'Sugar Daddy/Mommy') ? 'provider' : 'receiver';
            }

            if (empty($functional_role)) {
                error_log("Could not determine functional_role for gender: $gender, role: $role, looking_for: $looking_for");
                header('Location: ' . SUD_URL . '/?error=role_determination_failed');
                exit;
            }

            $data['looking_for'] = $looking_for;
            $data['functional_role'] = $functional_role;
            $data['last_step'] = 'looking-for';
            
            join_save_progress($_SESSION['join_session_id'], $data);

            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                update_user_meta($user_id, 'gender', $gender);
                update_user_meta($user_id, 'looking_for', $looking_for);
                update_user_meta($user_id, 'functional_role', $functional_role);
                if (isset($data['role'])) {
                    update_user_meta($user_id, 'role', $data['role']);
                } else {
                    delete_user_meta($user_id, 'role');
                }
            }
            header('Location: ' . SUD_URL . '/account-signup');
            exit;
        } else {
            // No looking_for provided, go to looking-for page
            join_save_progress($_SESSION['join_session_id'], $data);

            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                update_user_meta($user_id, 'gender', $gender);
                if (isset($data['role'])) {
                    update_user_meta($user_id, 'role', $data['role']);
                } else {
                    delete_user_meta($user_id, 'role');
                }
            }
            header('Location: ' . SUD_URL . '/looking-for');
            exit;
        }
    } else {
        header('Location: ' . SUD_URL . '/?error=invalid_gender');
        exit;
    }
}

$progress = (isset($_SESSION['join_session_id']) && function_exists('join_get_progress')) ? join_get_progress($_SESSION['join_session_id']) : [];
$selected_gender = $progress['gender'] ?? '';
$selected_role = $progress['role'] ?? '';
$selected_looking_for = $progress['looking_for'] ?? '';

include('templates/header.php');
?>

<div class="sud-join-content">
    <div class="sud-join-image">
    </div>
    <div class="sud-join-form-container">
        <?php if (!empty($setup_message)): ?>
            <div class="sud-page-notice sud-notice-<?php echo $message_type; ?>" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; text-align: center; <?php echo $message_type === 'error' ? 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' : 'background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb;'; ?>">
                <?php echo htmlspecialchars($setup_message); ?>
            </div>
        <?php endif; ?>
        <div class="sud-background-banner">
            <div class="sud-tagline">
                Swipe & Get Paid by <br/><span class="pink-side">Verified</span> Daddy’s & Mommy’s
            </div>
        </div>

        <form class="sud-join-form" method="post" id="gender-form">
            <div class="cont-form">
                <h3 class="sud-welcome-content">Welcome!<br/>Sign up for free.</h3>
                <div class="sud-form-group">
                    <label class="sud-form-label">I am a...</label>
                    <input type="hidden" name="gender" value="<?php echo htmlspecialchars($selected_gender); ?>" required>
                    <div class="sud-option-buttons">
                        <div class="sud-option-button <?php echo $selected_gender === 'Man' ? 'sud-active' : ''; ?>" data-value="Man">Man</div>
                        <div class="sud-option-button <?php echo $selected_gender === 'Woman' ? 'sud-active' : ''; ?>" data-value="Woman">Woman</div>
                        <div class="sud-option-button <?php echo $selected_gender === 'LGBTQ+' ? 'sud-active' : ''; ?>" data-value="LGBTQ+">LGBTQ+</div>
                    </div>
                    <div class="sud-error-message" style="display: none;">Please select your gender</div>
                </div>
            </div>
            <?php wp_nonce_field('sud_signup_gender_action', '_sud_signup_nonce'); ?>
        </form>
    </div>
</div>

<div class="sud-modal" id="role-modal">
    <div class="sud-modal-backdrop"></div>
    <div class="sud-modal-content">
        <button class="sud-modal-close">×</button>
        <div class="sud-modal-options-container">
            <h3>Choose your role</h3>
            <div class="sud-modal-option <?php echo ($selected_role === 'Sugar Daddy/Mommy') ? 'sud-active' : ''; ?>" data-value="Sugar Daddy/Mommy">
                <div class="sud-modal-option-content">
                    <div class="sud-modal-option-title">Sugar Daddy/Mommy</div>
                    <div class="sud-modal-option-subtitle">The Generous Provider</div>
                    <div class="sud-modal-option-description">
                        A successful individual looking to support and mentor a partner in return for companionship.
                    </div>
                </div>
            </div>
            <div class="sud-modal-option <?php echo ($selected_role === 'Sugar Baby') ? 'sud-active' : ''; ?>" data-value="Sugar Baby">
                <div class="sud-modal-option-content">
                    <div class="sud-modal-option-title">Sugar Baby</div>
                    <div class="sud-modal-option-subtitle">The Charming Receiver</div>
                    <div class="sud-modal-option-description">
                        An attractive individual seeking financial support, mentorship, and experiences in return for companionship.
                    </div>
                </div>
            </div>
        </div>
        <div class="sud-modal-error-message" style="display: none; color: red; margin: 10px 0; text-align: center;">
            Please select a role.
        </div>
        <button class="sud-join-btn sud-modal-continue" disabled>Continue</button>
    </div>
</div>

<!-- Looking For Modal -->
<div class="sud-modal" id="looking-for-modal">
    <div class="sud-modal-backdrop"></div>
    <div class="sud-modal-content">
        <button class="sud-modal-close">×</button>
        <div class="sud-modal-options-container">
            <h3>Who are you looking for?</h3>
            <div class="sud-modal-option <?php echo ($selected_looking_for === 'Sugar Daddy/Mommy') ? 'sud-active' : ''; ?>" data-value="Sugar Daddy/Mommy">
                <div class="sud-modal-option-content">
                    <div class="sud-modal-option-title">Sugar Daddy/Mommy</div>
                    <div class="sud-modal-option-subtitle">The Generous Provider</div>
                    <div class="sud-modal-option-description">
                        Looking for a successful individual who provides financial support and mentorship.
                    </div>
                </div>
            </div>
            <div class="sud-modal-option <?php echo ($selected_looking_for === 'Sugar Baby') ? 'sud-active' : ''; ?>" data-value="Sugar Baby">
                <div class="sud-modal-option-content">
                    <div class="sud-modal-option-title">Sugar Baby</div>
                    <div class="sud-modal-option-subtitle">The Charming Receiver</div>
                    <div class="sud-modal-option-description">
                        Looking for someone who seeks financial support and experiences in return for companionship.
                    </div>
                </div>
            </div>
        </div>
        <div class="sud-modal-error-message" style="display: none; color: red; margin: 10px 0; text-align: center;">
            Please select who you're looking for.
        </div>
        <button class="sud-join-btn sud-modal-continue" disabled>Continue</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Nonce management for long-lived pages
    let cachedNonce = '<?php echo wp_create_nonce('sud_signup_gender_action'); ?>';
    let nonceCreatedTime = Date.now();
    
    function getNonce() {
        // Refresh nonce if older than 10 hours (nonces expire after 12-24 hours)
        const hoursOld = (Date.now() - nonceCreatedTime) / (1000 * 60 * 60);
        if (hoursOld > 10) {
            // Show a brief message before refreshing
            const existingLoader = document.querySelector('.sud-loader');
            if (!existingLoader) {
                showLoader();
            }
            
            // For simplicity, just refresh the page to get a new nonce
            setTimeout(() => {
                window.location.reload();
            }, 500);
            return cachedNonce;
        }
        return cachedNonce;
    }
    
    // Override the existing submitGenderSelection function to prevent auto-submission
    window.submitGenderSelection = function(gender, role) {
        // Do nothing - we handle this with modals now
        return false;
    };
    const genderButtons = document.querySelectorAll('.sud-option-button');
    const genderForm = document.getElementById('gender-form');
    const genderInput = document.querySelector('input[name="gender"]');
    const roleModal = document.getElementById('role-modal');
    const lookingForModal = document.getElementById('looking-for-modal');
    
    // Role modal elements
    const roleOptions = roleModal.querySelectorAll('.sud-modal-option');
    const roleCloseBtn = roleModal.querySelector('.sud-modal-close');
    const roleBackdrop = roleModal.querySelector('.sud-modal-backdrop');
    const roleContinueBtn = roleModal.querySelector('.sud-modal-continue');
    
    // Looking for modal elements
    const lookingForOptions = lookingForModal.querySelectorAll('.sud-modal-option');
    const lookingForCloseBtn = lookingForModal.querySelector('.sud-modal-close');
    const lookingForBackdrop = lookingForModal.querySelector('.sud-modal-backdrop');
    const lookingForContinueBtn = lookingForModal.querySelector('.sud-modal-continue');
    
    let selectedGender = '<?php echo esc_js($selected_gender); ?>';
    let selectedRole = '<?php echo esc_js($selected_role); ?>';
    let selectedLookingFor = '<?php echo esc_js($selected_looking_for); ?>';
    
    // Initialize continue buttons based on existing selections
    if (selectedRole) {
        roleContinueBtn.disabled = false;
    }
    if (selectedLookingFor) {
        lookingForContinueBtn.disabled = false;
    }

    // Note: Gender button clicks handled at bottom after cloning to remove existing listeners

    // Role modal functionality
    roleOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove active class from all options
            roleOptions.forEach(opt => opt.classList.remove('sud-active'));
            // Add active class to clicked option
            this.classList.add('sud-active');
            
            selectedRole = this.getAttribute('data-value');
            roleContinueBtn.disabled = false;
        });
    });

    roleContinueBtn.addEventListener('click', function() {
        // For LGBTQ+, submit form and go to looking-for page (not modal)
        roleModal.style.display = 'none';
        roleModal.classList.remove('show');
        
        // Show loader
        showLoader();
        
        // Submit the form with gender and role for LGBTQ+
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo SUD_URL; ?>/';
        
        // Add nonce
        const nonceInput = document.createElement('input');
        nonceInput.type = 'hidden';
        nonceInput.name = '_sud_signup_nonce';
        nonceInput.value = getNonce();
        form.appendChild(nonceInput);
        
        // Add gender
        const genderInput = document.createElement('input');
        genderInput.type = 'hidden';
        genderInput.name = 'gender';
        genderInput.value = selectedGender;
        form.appendChild(genderInput);
        
        // Add role for LGBTQ+
        const roleInput = document.createElement('input');
        roleInput.type = 'hidden';
        roleInput.name = 'role';
        roleInput.value = selectedRole;
        form.appendChild(roleInput);
        
        document.body.appendChild(form);
        form.submit();
    });

    // Looking for modal functionality
    lookingForOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove active class from all options
            lookingForOptions.forEach(opt => opt.classList.remove('sud-active'));
            // Add active class to clicked option
            this.classList.add('sud-active');
            
            selectedLookingFor = this.getAttribute('data-value');
            lookingForContinueBtn.disabled = false;
        });
    });

    lookingForContinueBtn.addEventListener('click', function() {
        // Show loader
        showLoader();
        
        // Submit the form with all selections
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo SUD_URL; ?>/';
        
        // Add nonce
        const nonceInput = document.createElement('input');
        nonceInput.type = 'hidden';
        nonceInput.name = '_sud_signup_nonce';
        nonceInput.value = getNonce();
        form.appendChild(nonceInput);
        
        // Add gender
        const genderInput = document.createElement('input');
        genderInput.type = 'hidden';
        genderInput.name = 'gender';
        genderInput.value = selectedGender;
        form.appendChild(genderInput);
        
        // Add role if LGBTQ+
        if (selectedRole) {
            const roleInput = document.createElement('input');
            roleInput.type = 'hidden';
            roleInput.name = 'role';
            roleInput.value = selectedRole;
            form.appendChild(roleInput);
        }
        
        // Add looking for
        const lookingForInput = document.createElement('input');
        lookingForInput.type = 'hidden';
        lookingForInput.name = 'looking_for';
        lookingForInput.value = selectedLookingFor;
        form.appendChild(lookingForInput);
        
        document.body.appendChild(form);
        form.submit();
    });

    // Modal close functionality
    function closeModal(modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        // Reset selections if closing
        selectedRole = '';
        selectedLookingFor = '';
        roleContinueBtn.disabled = true;
        lookingForContinueBtn.disabled = true;
        roleOptions.forEach(option => option.classList.remove('sud-active'));
        lookingForOptions.forEach(option => option.classList.remove('sud-active'));
    }

    roleCloseBtn.addEventListener('click', () => closeModal(roleModal));
    roleBackdrop.addEventListener('click', () => closeModal(roleModal));
    lookingForCloseBtn.addEventListener('click', () => closeModal(lookingForModal));
    lookingForBackdrop.addEventListener('click', () => closeModal(lookingForModal));
    
    // Remove any existing event listeners from the existing script.js by cloning and replacing elements
    genderButtons.forEach(button => {
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
    });
    
    // Re-select the new buttons and add our event listeners
    const newGenderButtons = document.querySelectorAll('.sud-option-button');
    newGenderButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            
            selectedGender = this.getAttribute('data-value');
            genderInput.value = selectedGender;
            
            // Remove active class from all buttons
            newGenderButtons.forEach(btn => btn.classList.remove('sud-active'));
            // Add active class to clicked button
            this.classList.add('sud-active');
            
            if (selectedGender === 'LGBTQ+') {
                // Show role modal for LGBTQ+
                roleModal.style.display = 'flex';
                roleModal.classList.add('show');
            } else if (selectedGender === 'Man' || selectedGender === 'Woman') {
                // Show looking-for modal for Man/Woman
                lookingForModal.style.display = 'flex';
                lookingForModal.classList.add('show');
            }
        });
    });
});
</script>

<?php
include('templates/footer.php');
?>