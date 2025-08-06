<?php
require_once('includes/config.php');

$progress = join_get_progress($_SESSION['join_session_id']);
if (empty($progress) || empty($progress['gender']) || empty($progress['looking_for']) || empty($progress['email']) ) {
    header('Location: ' . SUD_URL . '/account-signup?error=sequence_error_intro_missingdata');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = isset($_POST['_sud_signup_nonce']) ? $_POST['_sud_signup_nonce'] : '';
    $action = 'sud_signup_introduction_action';
    if (!wp_verify_nonce($nonce, $action)) {
        header('Location: ' . SUD_URL . '/profile-setup?error=security_check_failed_intro');
        exit;
    }

    if (is_user_logged_in()) {
        header('Location: ' . SUD_URL . '/profile-setup');
        exit;
    } else {
        require_once('includes/database.php');
        require_once('includes/mailer.php');

        if (session_status() == PHP_SESSION_NONE) { @session_start(); }
        if (!isset($_SESSION['join_session_id'])) {
            header('Location: ' . SUD_URL . '/account-signup?error=session_expired_intro');
            exit;
        }
        $progress = join_get_progress($_SESSION['join_session_id']);
        if (empty($progress) || empty($progress['email'])) {
            header('Location: ' . SUD_URL . '/account-signup?error=sequence_error_intro_missingdata');
            exit;
        }

        $verification_code = rand(100000, 999999);
        join_save_progress($_SESSION['join_session_id'], [
            'verification_code' => $verification_code,
            'last_step' => 'introduction'
        ]);
        $email = $progress['email'];
        $mail_sent = send_verification_code($email, $verification_code);
        header('Location: ' . SUD_URL . '/verify-email');
        exit;
    }
}

include('templates/header.php');
?>

<div class="sud-join-content">
    <div class="sud-join-image">
        <!-- Background image will be applied via CSS -->
    </div>
    <div class="sud-join-form-container">
        <a href="<?php echo SUD_URL; ?>/account-signup" class="sud-back-button"><i class="fas fa-arrow-left"></i></a>

        <form class="sud-join-form" method="post" id="introduction-form">
            <div class="cont-form sud-intro-content">
                <h3 class="sud-welcome-content">Be honest and transparent</h3>
                <p>Being upfront about your feelings saves everyone's time.</p>
                <p><?php echo esc_html(SUD_SITE_NAME); ?> is a community where everyone can be real about who they are, and what they look for in a relationship without being judged.</p>
                <p>By being part of our community, you agree to be honest and transparent about your terms prior to meeting any fellow members.</p>
                <button type="submit" class="sud-join-btn sud-intro-btn">Let's get started!</button>
            </div>
            <?php wp_nonce_field('sud_signup_introduction_action', '_sud_signup_nonce'); ?>
        </form>
    </div>
</div>

<?php include('templates/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const introductionForm = document.getElementById('introduction-form');

    if (introductionForm) {
        introductionForm.addEventListener('submit', function(e) {
            if (typeof showLoader === 'function') {
                showLoader();
            } else {
                console.error('showLoader function not found!');
            }
        });
    }
});
</script>