<?php
$user_annual_income = get_user_meta($current_user->ID, 'annual_income', true);
$prev_step = $active_step - 1;
$prev_step = max(0, $prev_step);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annual_income_submit'])) {
    $annual_income = isset($_POST['annual_income']) ? $_POST['annual_income'] : '';
    if (!empty($annual_income)) {
        update_user_meta($current_user->ID, 'annual_income', $annual_income);
        update_user_meta($current_user->ID, 'completed_step_1', true);
        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>

<div class="sud-step-content" id="step-1">
    <form method="post" id="annual-income-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">What's your<br>annual income?</h2>
            <div class="sud-annual-income-options">
                <?php
                    $income_options = array(
                        '50000' => '$50,000',
                        '75000' => '$75,000',
                        '100000' => '$100,000',
                        '125000' => '$125,000',
                        '150000' => '$150,000',
                        '200000' => '$200,000',
                        '350000' => '$350,000',
                        '400000' => '$400,000',
                        '500000' => '$500,000',
                        '1000000' => '$1,000,000',
                        '1000000plus' => '$1,000,000+'
                    );
                    foreach ($income_options as $value => $label) {
                        $selected = ($user_annual_income == $value) ? 'selected' : '';
                        echo '<div class="sud-option-button annual-income-option ' . $selected . '" data-value="' . $value . '">' . $label . '</div>';
                    }
                ?>
            </div>
            <input type="hidden" name="annual_income" id="selected-annual-income" value="<?php echo esc_attr($user_annual_income); ?>">
            <input type="hidden" name="annual_income_submit" value="1">
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
        const form = document.getElementById('annual-income-form');
        const options = form.querySelectorAll('.annual-income-option');
        const hiddenInput = form.querySelector('#selected-annual-income');

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