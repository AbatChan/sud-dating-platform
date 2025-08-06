<?php
$user_ethnicity = get_user_meta($current_user->ID, 'ethnicity', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ethnicity_submit'])) {
    $ethnicity = isset($_POST['ethnicity']) ? $_POST['ethnicity'] : '';
    
    if (!empty($ethnicity)) {
        update_user_meta($current_user->ID, 'ethnicity', $ethnicity);
        update_user_meta($current_user->ID, 'completed_step_7', true);

        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>

<div class="sud-step-content" id="step-7">
    <form method="post" id="ethnicity-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">What's your ethnicity?</h2>
            <div class="sud-ethnicity-options">
                <?php
                $ethnicity_options = array(
                    'african' => 'African',
                    'asian' => 'Asian',
                    'caucasian' => 'Caucasian',
                    'hispanic' => 'Hispanic',
                    'middle_eastern' => 'Middle Eastern',
                    'latino' => 'Latino',
                    'native_american' => 'Native American',
                    'pacific_islander' => 'Pacific Islander',
                    'multiracial' => 'Multiracial',
                    'other' => 'Other',
                );
                
                foreach ($ethnicity_options as $value => $label) {
                    $selected = ($user_ethnicity == $value) ? 'selected' : '';
                    echo '<div class="sud-option-button ethnicity-option ' . $selected . '" data-value="' . $value . '">' . $label . '</div>';
                }
                ?>
            </div>
            <input type="hidden" name="ethnicity" id="selected-ethnicity" value="<?php echo esc_attr($user_ethnicity); ?>">
            <input type="hidden" name="ethnicity_submit" value="1">
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
        const form = document.getElementById('ethnicity-form');
        const options = document.querySelectorAll('.ethnicity-option');
        const hiddenInput = document.getElementById('selected-ethnicity');

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