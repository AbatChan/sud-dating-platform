<?php
$user_relationship_status = get_user_meta($current_user->ID, 'relationship_status', true);
$prev_step = $active_step - 1;
$prev_step = max(0, $prev_step);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['relationship_status_submit'])) {
    $relationship_status = isset($_POST['relationship_status']) ? $_POST['relationship_status'] : '';
    if (!empty($relationship_status)) {
        update_user_meta($current_user->ID, 'relationship_status', $relationship_status);
        update_user_meta($current_user->ID, 'completed_step_4', true);

        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>

<div class="sud-step-content" id="step-4">
    <form method="post" id="relationship-status-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">What's your relationship status?</h2>
            <div class="sud-relationship-status-options">
                <?php
                $status_options = array(
                    'single' => 'Single',
                    'in_relationship' => 'In a Relationship',
                    'married_looking' => 'Married but Looking',
                    'separated' => 'Separated',
                    'divorced' => 'Divorced',
                    'widowed' => 'Widowed',
                );

                foreach ($status_options as $value => $label) {
                    $selected = ($user_relationship_status == $value) ? 'selected' : '';
                    echo '<div class="sud-option-button relationship-status-option ' . $selected . '" data-value="' . $value . '">' . $label . '</div>';
                }
                ?>
            </div>
            <input type="hidden" name="relationship_status" id="selected-relationship-status" value="<?php echo esc_attr($user_relationship_status); ?>">
            <input type="hidden" name="relationship_status_submit" value="1">
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
        const form = document.getElementById('relationship-status-form');
        const options = document.querySelectorAll('.relationship-status-option');
        const hiddenInput = document.getElementById('selected-relationship-status');
        
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