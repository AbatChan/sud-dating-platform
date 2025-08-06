<?php
$user_dating_budget = get_user_meta($current_user->ID, 'dating_budget', true);
$prev_step = $active_step - 1;
$prev_step = max(0, $prev_step);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dating_budget_submit'])) {
    $dating_budget = isset($_POST['dating_budget']) ? $_POST['dating_budget'] : '';
    
    if (!empty($dating_budget)) {
        update_user_meta($current_user->ID, 'dating_budget', $dating_budget);
        update_user_meta($current_user->ID, 'completed_step_3', true);

        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>

<div class="sud-step-content" id="step-3">
    <form method="post" id="dating-budget-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">What's your<br>dating budget?</h2>
            <p class="sud-step-description">Tell us how much you are willing to spend on a date.</p>
            <div class="sud-dating-budget-options">
                <?php
                $budget_options = array(
                    '300-1000' => '$300-$1,000',
                    '1000-3000' => '$1,000-$3,000',
                    '3000-5000' => '$3,000-$5,000',
                    '5000-9000' => '$5,000-$9,000',
                    '9000-20000' => '$9,000-$20,000',
                    '20000plus' => '$20,000+',
                );
                
                foreach ($budget_options as $value => $label) {
                    $selected = ($user_dating_budget == $value) ? 'selected' : '';
                    echo '<div class="sud-option-button dating-budget-option ' . $selected . '" data-value="' . $value . '">' . $label . '</div>';
                }
                ?>
            </div>
            
            <input type="hidden" name="dating_budget" id="selected-dating-budget" value="<?php echo esc_attr($user_dating_budget); ?>">
            <input type="hidden" name="dating_budget_submit" value="1">
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
        const form = document.getElementById('dating-budget-form');
        const options = document.querySelectorAll('.dating-budget-option');
        const hiddenInput = document.getElementById('selected-dating-budget');

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