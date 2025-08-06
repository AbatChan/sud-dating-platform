<?php
require_once('includes/config.php');

if (!is_user_logged_in()) {
    header('Location: ' . SUD_URL . '/auth/login');
    exit;
}
$current_user = wp_get_current_user();
$display_name = $current_user->display_name;
$first_name = $display_name;
if (strpos($display_name, ' ') !== false) {
    $name_parts = explode(' ', $display_name);
    $first_name = $name_parts[0];
}

include('templates/header.php');
?>

<div class="sud-join-content">
    <div class="sud-join-image">
        <!-- Background image will be applied via CSS -->
    </div>
    <div class="sud-join-form-container sud-welcome-container">
        <div class="sud-welcome-content">
            <h2 class="sud-welcome-heading">Hi <?php echo htmlspecialchars($first_name); ?>!</h2>
            <p class="sud-welcome-text">Traditional dating sucks. Not anymore - we fixed it.</p>
            <p class="sud-welcome-description">
                <?php echo esc_html(SUD_SITE_NAME); ?>'s modern approach to sugar dating transforms how you get
                to meet people of similar interests, and not just another dating platform.
            </p>
            <div class="sud-welcome-actions">
                <a href="<?php echo SUD_URL; ?>/profile-details" class="sud-join-btn" id="welcome-continue-link">Continue</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const welcomeContinueLink = document.getElementById('welcome-continue-link');
    if (welcomeContinueLink) {
        welcomeContinueLink.addEventListener('click', function(event) {
            event.preventDefault();
            if (typeof showLoader === 'function') {
                showLoader();
            }
            setTimeout(() => {
                window.location.href = this.href;
            }, 100);
        });
    }
});
</script>

<?php include('templates/footer.php'); ?>