<?php
$user_drink = get_user_meta($current_user->ID, 'drink', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drink_submit'])) {
    $drink = isset($_POST['drink']) ? $_POST['drink'] : '';
    
    if (!empty($drink)) {
        update_user_meta($current_user->ID, 'drink', $drink);
        update_user_meta($current_user->ID, 'completed_step_10', true);

        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>

<div class="sud-step-content" id="step-10">
    <form method="post" id="drink-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">Do you drink?</h2>
            
            <div class="sud-drink-options">
                <?php
                $drink_options = array(
                    'non_drinker' => 'Non-Drinker',
                    'social_drinker' => 'Social Drinker',
                    'heavy_drinker' => 'Heavy Drinker',
                );
                
                foreach ($drink_options as $value => $label) {
                    $selected = ($user_drink == $value) ? 'selected' : '';
                    echo '<div class="sud-option-button drink-option ' . $selected . '" data-value="' . $value . '">' . $label . '</div>';
                }
                ?>
            </div>
            <input type="hidden" name="drink" id="selected-drink" value="<?php echo esc_attr($user_drink); ?>">
            <input type="hidden" name="drink_submit" value="1">
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
        const form = document.getElementById('drink-form');
        const options = document.querySelectorAll('.drink-option');
        const hiddenInput = document.getElementById('selected-drink');

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