<?php
require_once('includes/config.php');
require_once('includes/database.php');

if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}
if (!isset($_SESSION['join_session_id'])) {
    header('Location: ' . SUD_URL . '/?error=session_expired_lf');
    exit;
}

$progress = join_get_progress($_SESSION['join_session_id']);

if (empty($progress) || !isset($progress['gender']) || empty($progress['gender'])) {
    header('Location: ' . SUD_URL . '/?error=sequence_error_lf_nogender');
    exit;
}

$gender = $progress['gender'] ?? '';
$role = $progress['role'] ?? '';
$looking_for = $progress['looking_for'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $nonce = isset($_POST['_sud_signup_nonce']) ? $_POST['_sud_signup_nonce'] : '';
    if (!wp_verify_nonce($nonce, 'sud_signup_looking_action')) {
        header('Location: ' . SUD_URL . '/looking-for?error=security_check_failed_submit');
        exit;
    }

    $submitted_gender = isset($_POST['gender']) ? sanitize_text_field($_POST['gender']) : '';
    $submitted_role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
    $submitted_looking_for = isset($_POST['looking_for']) ? sanitize_text_field($_POST['looking_for']) : '';

    if (empty($submitted_gender) || !in_array($submitted_gender, ['Man', 'Woman', 'LGBTQ+'])) {
        header('Location: ' . SUD_URL . '/looking-for?error=invalid_gender_submitted');
        exit;
    }

    if ($submitted_gender === 'LGBTQ+' && (empty($submitted_role) || !in_array($submitted_role, ['Sugar Daddy/Mommy', 'Sugar Baby']))) {
        header('Location: ' . SUD_URL . '/looking-for?error=invalid_role_submitted');
        exit;
    }

    if ($submitted_gender !== 'LGBTQ+') {
        $submitted_role = '';
    }

    $valid_options = [];
    if ($submitted_gender === 'Man' || $submitted_gender === 'Woman') {
        $valid_options = ['Sugar Daddy/Mommy', 'Sugar Baby'];
    } elseif ($submitted_gender === 'LGBTQ+') {
        $valid_options = ['Gay', 'Lesbian'];
    }

    if (empty($submitted_looking_for) || !in_array($submitted_looking_for, $valid_options)) {
        header('Location: ' . SUD_URL . '/looking-for?error=selection_required');
        exit;
    }

    $functional_role = '';
    if ($submitted_gender === 'Man') {
        $functional_role = ($submitted_looking_for === 'Sugar Daddy/Mommy') ? 'receiver' : 'provider';
    } elseif ($submitted_gender === 'Woman') {
        $functional_role = ($submitted_looking_for === 'Sugar Baby') ? 'provider' : 'receiver';
    } elseif ($submitted_gender === 'LGBTQ+') {
        $functional_role = ($submitted_role === 'Sugar Daddy/Mommy') ? 'provider' : 'receiver';
    }

    if (empty($functional_role)) {
        error_log("Could not determine functional_role for submitted gender: $submitted_gender, role: $submitted_role, looking_for: $submitted_looking_for");
        header('Location: ' . SUD_URL . '/looking-for?error=role_determination_failed');
        exit;
    }

    join_save_progress($_SESSION['join_session_id'], [
        'gender' => $submitted_gender,
        'role' => $submitted_role,
        'looking_for' => $submitted_looking_for,
        'functional_role' => $functional_role,
        'last_step' => 'looking-for'
    ]);

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'gender', $submitted_gender);
        update_user_meta($user_id, 'role', $submitted_role);
        update_user_meta($user_id, 'looking_for', $submitted_looking_for);
        update_user_meta($user_id, 'functional_role', $functional_role);
    }

    header('Location: ' . SUD_URL . '/account-signup');
    exit;
}

$options_to_display = [];
if ($gender === 'Man' || $gender === 'Woman') {
    $options_to_display = [
        'Sugar Daddy/Mommy' => 'Sugar Daddy/Mommy',
        'Sugar Baby' => 'Sugar Baby'
    ];
} elseif ($gender === 'LGBTQ+') {
    $options_to_display = [
        'Gay' => 'Gay Partner',
        'Lesbian' => 'Lesbian Partner'
    ];
    if (empty($role)) { $role = 'Sugar Daddy/Mommy'; }
} else {
    header('Location: ' . SUD_URL . '/?error=invalid_gender_state');
    exit;
}

include('templates/header.php');
?>

<div class="sud-join-content">
    <div class="sud-join-image"></div>
    <div class="sud-join-form-container">
        <a href="<?php echo SUD_URL; ?>/" class="sud-back-button"><i class="fas fa-arrow-left"></i></a>
        <form class="sud-join-form" method="post" id="looking-for-form">
            <div class="cont-form">
                <h3 class="sud-welcome-content">Who are you looking for?</h3>
                <div class="sud-form-group sud-gender-option">
                    <label class="sud-form-label">
                        I am a
                        <a href="#" id="display-gender" class="sud-gender-selection">
                            <strong><u><?php echo htmlspecialchars($gender); ?></u></strong>
                        </a>
                        <a href="#" id="switch-gender-link" class="sud-switch-link"><i class="fas fa-sync-alt"></i> Switch</a>
                        <span class="sud-role-separator" style="<?php echo ($gender !== 'LGBTQ+') ? 'display: none;' : ''; ?>">, a </span>
                        <a href="#" id="display-role" class="sud-role-selection" style="<?php echo ($gender !== 'LGBTQ+') ? 'display: none;' : ''; ?>">
                            <strong><u><?php echo htmlspecialchars($role); ?></u></strong>
                        </a>
                        <a href="#" id="switch-role-link" class="sud-switch-link" style="<?php echo ($gender !== 'LGBTQ+') ? 'display: none;' : ''; ?>"><i class="fas fa-sync-alt"></i> Switch</a>
                    </label>
                    <input type="hidden" name="gender" id="hidden-gender-input" value="<?php echo htmlspecialchars($gender); ?>">
                    <input type="hidden" name="role" id="hidden-role-input" value="<?php echo htmlspecialchars($role); ?>">
                </div>

                <div class="sud-form-group sud-gender-preference">
                    <label class="sud-form-label">
                        I am looking for...
                        <a href="#" class="sud-info-icon <?php echo empty($looking_for) ? 'inactive-info-icon' : ''; ?>" id="looking-for-info" title="View Role Definitions"><i class="fas fa-question-circle"></i></a>
                    </label>
                    <input type="hidden" name="looking_for" id="hidden-looking-for-input" value="<?php echo htmlspecialchars($looking_for); ?>" required>
                    <div class="sud-option-buttons">
                        <?php foreach ($options_to_display as $value => $label): ?>
                            <div class="sud-option-button <?php echo $looking_for === $value ? 'sud-active' : ''; ?>" data-value="<?php echo htmlspecialchars($value); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="sud-error-message" style="display: none;">Please select who you're looking for</div>
                </div>
            </div>
            <button type="submit" class="sud-join-btn" id="continue-btn" style="<?php echo empty($looking_for) ? 'display:none;' : ''; ?>">Continue</button>
            <?php wp_nonce_field('sud_signup_looking_action', '_sud_signup_nonce'); ?>
        </form>
    </div>

    <div class="sud-modal" id="definition-modal" style="display: none;">
        <div class="sud-modal-backdrop"></div>
        <div class="sud-modal-content">
            <button class="sud-modal-close">×</button>
            <div id="sugar-daddy-definition" class="sud-definition" style="display: none;">
                <h4>Sugar Daddy/Mommy</h4>
                <p>A generous provider role where you support your sugar baby with financial guidance and mentorship.</p>
            </div>
            <div id="sugar-baby-definition" class="sud-definition" style="display: none;">
                <h4>Sugar Baby</h4>
                <p>A role where you receive financial support and mentorship in return for companionship and shared experiences.</p>
            </div>
             <div id="gay-definition" class="sud-definition" style="display: none;">
                <h4>Gay Partner</h4>
                <p>Seeking a relationship with another man within the LGBTQ+ community context.</p>
            </div>
            <div id="lesbian-definition" class="sud-definition" style="display: none;">
                <h4>Lesbian Partner</h4>
                <p>Seeking a relationship with another woman within the LGBTQ+ community context.</p>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const hiddenLookingForInput = document.getElementById('hidden-looking-for-input');
    const hiddenGenderInput = document.getElementById('hidden-gender-input');
    const hiddenRoleInput = document.getElementById('hidden-role-input');
    const continueBtnLF = document.getElementById('continue-btn');
    const optionsContainer = document.querySelector('#looking-for-form .sud-option-buttons');
    const definitionModal = document.getElementById('definition-modal');
    const infoIcon = document.getElementById('looking-for-info');

    const genderSwitchLink = document.getElementById('switch-gender-link');
    const roleSwitchLink = document.getElementById('switch-role-link');
    const displayGenderElement = document.getElementById('display-gender')?.querySelector('u');
    const displayRoleElement = document.getElementById('display-role')?.querySelector('u');
    const roleSeparatorElement = document.querySelector('.sud-role-separator');
    const roleSelectionElement = document.getElementById('display-role');
    const lookingForErrorMsg = document.querySelector('#looking-for-form .sud-error-message');
    const form = document.getElementById('looking-for-form');

    let currentGender = hiddenGenderInput.value;
    let currentRole = hiddenRoleInput.value;

    function updateLookingForOptionsUI(gender) {
        if (!optionsContainer) return;
        optionsContainer.innerHTML = '';
        let newOptions = {};
        let optionLabels = {};

        if (gender === 'Man' || gender === 'Woman') {
            newOptions = ['Sugar Daddy/Mommy', 'Sugar Baby'];
            optionLabels = { 'Sugar Daddy/Mommy': 'Sugar Daddy/Mommy', 'Sugar Baby': 'Sugar Baby' };
        } else if (gender === 'LGBTQ+') {
            newOptions = ['Gay', 'Lesbian'];
            optionLabels = { 'Gay': 'Gay Partner', 'Lesbian': 'Lesbian Partner' };
        }

        newOptions.forEach(value => {
            const button = document.createElement('div');
            button.className = 'sud-option-button';
            button.setAttribute('data-value', value);
            button.textContent = optionLabels[value] || value; // Use label if available
            optionsContainer.appendChild(button);
        });
        selectLookingFor('');
    }

    function selectLookingFor(value) {
        const currentButtons = optionsContainer.querySelectorAll('.sud-option-button');
        currentButtons.forEach(btn => btn.classList.remove('sud-active'));
        const selectedButton = optionsContainer.querySelector(`.sud-option-button[data-value="${value}"]`);

        if (selectedButton) {
            selectedButton.classList.add('sud-active');
        }
        if (hiddenLookingForInput) {
            hiddenLookingForInput.value = value;
        }
        if (continueBtnLF) {
            continueBtnLF.style.display = value ? 'block' : 'none';
        }
        if (infoIcon) {
            if (value) {
                infoIcon.classList.remove('inactive-info-icon');
                infoIcon.setAttribute('title', 'View Role Definitions');
            } else {
                infoIcon.classList.add('inactive-info-icon');
                infoIcon.removeAttribute('title');
            }
        }
        if (lookingForErrorMsg) {
            lookingForErrorMsg.style.display = 'none';
        }
    }

    function updateDisplay(gender, role = null) {
        if (displayGenderElement) {
            displayGenderElement.textContent = gender;
        }
        if (hiddenGenderInput) {
            hiddenGenderInput.value = gender;
        }
        const effectiveRole = (gender === 'LGBTQ+' && role) ? role : '';
        if (hiddenRoleInput) {
            hiddenRoleInput.value = effectiveRole;
        }

        const showRole = (gender === 'LGBTQ+');
        if (roleSeparatorElement) roleSeparatorElement.style.display = showRole ? 'inline' : 'none';
        if (roleSelectionElement) roleSelectionElement.style.display = showRole ? 'inline' : 'none';
        if (roleSwitchLink) roleSwitchLink.style.display = showRole ? 'inline' : 'none';

        if (showRole && displayRoleElement && role) {
            displayRoleElement.textContent = role;
        }
    }

    if (optionsContainer) {
        optionsContainer.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('sud-option-button')) {
                const value = e.target.getAttribute('data-value');
                selectLookingFor(value);
            }
        });
    }

    if (genderSwitchLink && displayGenderElement) {
        genderSwitchLink.addEventListener('click', function(e) {
            e.preventDefault();
            let newGender = '';
            if (currentGender === 'Man') newGender = 'Woman';
            else if (currentGender === 'Woman') newGender = 'LGBTQ+';
            else newGender = 'Man';

            currentGender = newGender;

            if (currentGender === 'LGBTQ+') {
                currentRole = 'Sugar Daddy/Mommy';
            } else {
                currentRole = '';
            }

            updateDisplay(currentGender, currentRole);
            updateLookingForOptionsUI(currentGender);
        });
    }

    if (roleSwitchLink && displayRoleElement) {
        roleSwitchLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentGender !== 'LGBTQ+') return;

            const newRole = (currentRole === 'Sugar Daddy/Mommy') ? 'Sugar Baby' : 'Sugar Daddy/Mommy';
            currentRole = newRole;
            updateDisplay(currentGender, currentRole);
        });
    }

    if (infoIcon && definitionModal) {
        infoIcon.addEventListener('click', function(e) {
            e.preventDefault();

            if (this.classList.contains('inactive-info-icon')) {
                return;
            }
            
            const currentLookingForValue = hiddenLookingForInput ? hiddenLookingForInput.value : '';

            const definitions = definitionModal.querySelectorAll('.sud-definition');
            definitions.forEach(def => def.style.display = 'none');

            let definitionId = '';
            if (currentLookingForValue === 'Sugar Daddy/Mommy') definitionId = 'sugar-daddy-definition';
            else if (currentLookingForValue === 'Sugar Baby') definitionId = 'sugar-baby-definition';
            else if (currentLookingForValue === 'Gay') definitionId = 'gay-definition';
            else if (currentLookingForValue === 'Lesbian') definitionId = 'lesbian-definition';

            const relevantDefinition = document.getElementById(definitionId);
            
            if (relevantDefinition) {
                relevantDefinition.style.display = 'block';
                definitionModal.style.display = 'flex';
                definitionModal.classList.add('show');
            } else {
                definitionModal.style.display = 'none';
                definitionModal.classList.remove('show');
            }
        });

        const defCloseBtn = definitionModal.querySelector('.sud-modal-close');
        if (defCloseBtn) {
            defCloseBtn.addEventListener('click', function() {
                definitionModal.style.display = 'none';
                definitionModal.classList.remove('show');
            });
        }
        const defBackdrop = definitionModal.querySelector('.sud-modal-backdrop');
        if (defBackdrop) {
            defBackdrop.addEventListener('click', function() {
                definitionModal.style.display = 'none';
                definitionModal.classList.remove('show');
            });
        }
    }

    if(form && continueBtnLF){
        form.addEventListener('submit', function(e){
            let isValid = true;
            if (!hiddenLookingForInput || !hiddenLookingForInput.value) {
                e.preventDefault();
                isValid = false;
                if (lookingForErrorMsg) {
                    lookingForErrorMsg.textContent = "Please select who you're looking for";
                    lookingForErrorMsg.style.display = 'block';
                }
            }
            if (hiddenGenderInput.value === 'LGBTQ+' && !hiddenRoleInput.value) {
                e.preventDefault();
                isValid = false;
            }
            if (isValid) {
                showLoader();
            }
        });
    }

    updateDisplay(currentGender, currentRole);
    if (hiddenLookingForInput) {
        selectLookingFor(hiddenLookingForInput.value || '');
    }
    if (continueBtnLF && hiddenLookingForInput) {
        continueBtnLF.style.display = hiddenLookingForInput.value ? 'block' : 'none';
    }
});
</script>

<?php include('templates/footer.php'); ?>