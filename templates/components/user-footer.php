<?php
// Get current year if not set
if (!isset($current_year)) {
    $current_year = date('Y');
}
?>
<div id="upgrade-prompt-modal" class="modal upgrade-modal">
    <div class="sud-modal-content">
        <span class="close-modal">×</span>
        <div class="modal-icon"><i class="fas fa-crown"></i></div>
        <h3 id="upgrade-prompt-title">Upgrade for Full Access</h3>
        <p id="upgrade-prompt-message">This feature is available for premium members. Upgrade your plan to enjoy this and other exclusive benefits!</p>
        <div class="modal-actions">
            <a href="#" class="btn btn-primary btn-upgrade-action" id="footer-upgrade-link">Upgrade Now</a>
            <button class="btn btn-secondary close-modal-btn">Maybe Later</button>
        </div>
    </div>
</div>
<footer>
    <div class="container">
        <div class="footer-content">
            <div class="footer-links">
                <a href="<?php echo site_url('/privacy-policy'); ?>">Privacy Policy</a>
                <a href="<?php echo site_url('/terms-of-service'); ?>">Terms of Service</a>
                <a href="<?php echo site_url('/help'); ?>">Help Center</a>
                <a href="<?php echo site_url('/contact'); ?>">Contact Us</a>
            </div>
            <div class="copyright">
                &copy; <?php echo $current_year; ?> <?php echo esc_html(SUD_SITE_NAME); ?>. All Rights Reserved.
            </div>
        </div>
    </div>
</footer>