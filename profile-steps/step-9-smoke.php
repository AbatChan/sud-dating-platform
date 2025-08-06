<?php
$user_smoke = get_user_meta($current_user->ID, 'smoke', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['smoke_submit'])) {
    $smoke = isset($_POST['smoke']) ? $_POST['smoke'] : '';
    
    if (!empty($smoke)) {
        update_user_meta($current_user->ID, 'smoke', $smoke);
        update_user_meta($current_user->ID, 'completed_step_9', true);

        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>

<div class="sud-step-content" id="step-9">
    <form method="post" id="smoke-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">Do you smoke?</h2>
            
            <div class="sud-smoke-options">
                <?php
                $smoke_options = array(
                    'non_smoker' => 'Non-Smoker',
                    'light_smoker' => 'Light Smoker',
                    'heavy_smoker' => 'Heavy Smoker',
                );
                
                foreach ($smoke_options as $value => $label) {
                    $selected = ($user_smoke == $value) ? 'selected' : '';
                    echo '<div class="sud-option-button smoke-option ' . $selected . '" data-value="' . $value . '">' . $label . '</div>';
                }
                ?>
            </div>
            <input type="hidden" name="smoke" id="selected-smoke" value="<?php echo esc_attr($user_smoke); ?>">
            <input type="hidden" name="smoke_submit" value="1">
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
        const form = document.getElementById('smoke-form');
        const options = document.querySelectorAll('.smoke-option');
        const hiddenInput = document.getElementById('selected-smoke');

        if (form && options.length > 0 && hiddenInput) {
            options.forEach(option => {
                option.addEventListener('click', function() {
                    options.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    hiddenInput.value = this.getAttribute('data-value');

                    validateAndToggleButton(form);
                });
            });
            validateAndToggleButton(form);
        }
    });
</script>