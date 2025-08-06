<?php
$user_occupation = get_user_meta($current_user->ID, 'occupation', true);
$prev_step = $active_step - 1;
$prev_step = max(0, $prev_step);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['occupation_submit'])) {
    $occupation = isset($_POST['occupation']) ? trim($_POST['occupation']) : '';
    
    if (!empty($occupation)) {
        update_user_meta($current_user->ID, 'occupation', $occupation);
        update_user_meta($current_user->ID, 'completed_step_6', true);

        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>
<div class="sud-step-content" id="step-6">
    <form method="post" id="occupation-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">What's your occupation?</h2>
            <div class="sud-form-group sud-input-floating">
                <input type="text" id="occupation" name="occupation" class="sud-form-input" value="<?php echo esc_attr($user_occupation); ?>" required>
                <label for="occupation" class="sud-floating-label">Your Occupation <span class="sud-required">*</span></label>
                <div class="sud-error-message" style="display: none;">Occupation is required</div>
            </div>
            <input type="hidden" name="occupation_submit" value="1">
            <?php
                if (isset($active_step)) {
                    wp_nonce_field( 'sud_profile_step_' . $active_step . '_action', '_sud_step_nonce' );
                }
            ?>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('occupation-form');
        const input = form.querySelector('#occupation');
        const errorMsg = form.querySelector('.sud-error-message');

        if (form && input) {
            input.addEventListener('input', function() {
                if (errorMsg) {
                    errorMsg.style.display = 'none';
                }
                validateAndToggleButton(form);
            });
            validateAndToggleButton(form);
            if (input.classList.contains('has-error') && errorMsg) {
                errorMsg.style.display = 'block';
            }
        }
    });
</script>