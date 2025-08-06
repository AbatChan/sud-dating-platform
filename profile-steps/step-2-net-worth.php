<?php
$user_net_worth = get_user_meta($current_user->ID, 'net_worth', true);
$prev_step = $active_step - 1;
$prev_step = max(0, $prev_step);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['net_worth_submit'])) {
    $net_worth = isset($_POST['net_worth']) ? $_POST['net_worth'] : '';
    
    if (!empty($net_worth)) {
        update_user_meta($current_user->ID, 'net_worth', $net_worth);
        update_user_meta($current_user->ID, 'completed_step_2', true);

        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>

<div class="sud-step-content" id="step-2">
    <form method="post" id="net-worth-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">What's your<br>net worth?</h2>
            <div class="sud-net-worth-options">
                <?php
                $networth_options = array(
                    '100000' => '$100,000',
                    '250000' => '$250,000',
                    '500000' => '$500,000',
                    '750000' => '$750,000',
                    '1000000' => '$1,000,000',
                    '2000000' => '$2,000,000',
                    '5000000' => '$5,000,000',
                    '10000000' => '$10,000,000',
                    '50000000' => '$50,000,000',
                    '100000000' => '$100,000,000',
                    '100000000plus' => '$100,000,000+',
                );
                
                foreach ($networth_options as $value => $label) {
                    $selected = ($user_net_worth == $value) ? 'selected' : '';
                    echo '<div class="sud-option-button net-worth-option ' . $selected . '" data-value="' . $value . '">' . $label . '</div>';
                }
                ?>
            </div>
            <input type="hidden" name="net_worth" id="selected-net-worth" value="<?php echo esc_attr($user_net_worth); ?>">
            <input type="hidden" name="net_worth_submit" value="1">
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
        const form = document.getElementById('net-worth-form');
        const options = document.querySelectorAll('.net-worth-option');
        const hiddenInput = document.getElementById('selected-net-worth');

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