<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');

wp_logout();

include(dirname(__FILE__, 2) . '/templates/header.php');
?>

<div class="sud-join-content">
    <div class="sud-join-image">
        <!-- Background image will be applied via CSS -->
    </div>
    <div class="sud-join-form-container sud-login-form-container">
        <div class="cont-form">
            <div class="sud-login-logo">
                <a href="<?php echo esc_url(site_url());?>">
                    <?php
                        $custom_logo_id = get_theme_mod('custom_logo');
                        $logo_image_url = '';
                        if ($custom_logo_id) {
                            $logo_image_url = wp_get_attachment_image_url($custom_logo_id, 'full');
                        }
                        if (!empty($logo_image_url)) {
                            echo '<img src="' . esc_url($logo_image_url) . '" alt="' . esc_attr(get_bloginfo('name')) . ' - Logo">';
                        } else {
                            echo '<img src="' . esc_url(SUD_IMG_URL . '/logo.png') . '" alt="' . esc_attr(BRAND_NAME) . '" onerror="this.src=\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjUwIiB2aWV3Qm94PSIwIDAgMTAwIDUwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iNTAiIGZpbGw9IiMxMzBmNDAiLz48dGV4dCB4PSIxMCIgeT0iMzAiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNiIgZmlsbD0id2hpdGUiPlNVRDwvdGV4dD48L3N2Zz4=\'"></a>';
                        }
                    ?>
                </a>
            </div>
            
            <h3 class="sud-welcome-content">You have been logged out</h3>
            
            <div class="sud-logout-message">
                <p>Thank you for using <?php echo esc_html(SUD_SITE_NAME); ?>!</p>
                <p>We hope to see you again soon.</p>
            </div>
            
            <div class="sud-login-divider">
                <span>or</span>
            </div>
            
            <div class="sud-login-options">
                <a href="<?php echo SUD_URL; ?>/auth/login" class="sud-login-btn">Log In Again</a>
                <a href="<?php echo site_url(); ?>" class="sud-home-btn">Return to Home</a>
            </div>
        </div>
    </div>
</div>

<?php include(dirname(__FILE__, 2) . '/templates/footer.php'); ?>