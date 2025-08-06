<?php
$user_about_me = get_user_meta($current_user->ID, 'about_me', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['about_me_submit'])) {
    $about_me = isset($_POST['about_me']) ? trim($_POST['about_me']) : '';
    
    if (!empty($about_me)) {
        update_user_meta($current_user->ID, 'about_me', $about_me);
        update_user_meta($current_user->ID, 'completed_step_11', true);

        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>

<div class="sud-step-content" id="step-11">
    <form method="post" id="about-me-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">Tell us about yourself</h2>
            
            <div class="sud-form-group">
                <textarea id="about_me" name="about_me" class="sud-form-textarea" rows="6" placeholder="Write something interesting about yourself..."><?php echo esc_textarea($user_about_me); ?></textarea>
                <div class="sud-error-message" style="display: none;">Please write something about yourself</div>
            </div>   
            <input type="hidden" name="about_me_submit" value="1">
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
        const form = document.getElementById('about-me-form');
        const textarea = form.querySelector('#about_me');
        const errorMsg = form.querySelector('.sud-error-message');

        if (form && textarea) {
            textarea.addEventListener('input', function() {
                if (errorMsg) {
                    errorMsg.style.display = 'none';
                }
                validateAndToggleButton(form);
            });

            textarea.addEventListener('blur', function() {
                validateAndToggleButton(form);
                if (this.value.trim() === '' && errorMsg) {
                    errorMsg.style.display = 'block';
                }
            });
            
            validateAndToggleButton(form);
        }
    });
</script>