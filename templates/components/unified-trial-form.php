<?php
/**
 * Unified Trial Form Component
 * Follows industry standards (minimal data collection like Netflix, Shopify)
 * Integrates with secure SetupIntent system
 */

// Get current user info for prefilling
$current_user = wp_get_current_user();

// Get payment settings if not already set
if (!isset($payment_settings)) {
    $payment_settings = get_option('sud_payment_settings', []);
}
?>

<!-- Free Trial Modal -->
<div id="trial-modal" class="payment-modal">
    <div class="sud-modal-content">
        <span class="close-modal">&times;</span>

        <div class="trial-info">
            <h3>Start Your 3-Day FREE Trial</h3> 
            <p>$<span id="trial-price">33.33</span>/month • <span id="trial-plan-name">Gold Tier</span></p>
        </div>
        
        <?php if (isset($payment_settings['test_mode']) && $payment_settings['test_mode']): ?>
            <div class="test-mode-warning">
                <i class="fas fa-exclamation-triangle"></i> Test mode is active. No real charges will be made.
            </div>
        <?php endif; ?>

        <form id="trial-form" novalidate>
            <div class="payment-tabs">
                <div class="payment-tab active" data-tab="card">Credit Card</div>
                <div class="payment-tab" data-tab="paypal">PayPal</div>
            </div>

            <!-- Credit Card Section - Simplified to industry standards -->
            <div class="payment-section active" id="trial-card-section">
                <div class="form-group">
                    <label for="trial-email">Email Address</label>
                    <input type="email" id="trial-email" name="email" required 
                           value="<?php echo esc_attr($current_user->user_email); ?>" readonly>
                    <small>Pre-filled from your account</small>
                </div>

                <div class="form-group">
                    <label for="trial-cardholder-name">Name on Card</label>
                    <input type="text" id="trial-cardholder-name" name="cardholder_name" required 
                           value="<?php echo esc_attr($current_user->display_name); ?>">
                    <small>Pre-filled from your account</small>
                </div>

                <!-- Secure Stripe Elements Card Input -->
                <div class="form-group">
                    <label>Card Information</label>
                    <div id="trial-card-element" class="stripe-card-element">
                        <!-- Stripe Card Element will be mounted here -->
                    </div>
                    <div id="trial-card-errors" class="card-errors" role="alert"></div>
                </div>
            </div>

            <!-- PayPal Section -->
            <div class="payment-section" id="trial-paypal-section" style="display: none;">
                <div class="form-group">
                    <label for="trial-paypal-email">PayPal Email</label>
                    <input type="email" id="trial-paypal-email" name="paypal_email" required 
                           value="<?php echo esc_attr($current_user->user_email); ?>">
                </div>
                <p style="color: #666; font-size: 14px; margin: 15px 0;">
                    You'll be redirected to PayPal to complete your trial setup.
                </p>
            </div>

            <button type="submit" id="start-trial-btn">
                Start My FREE Trial
            </button>
            
            <p class="trial-footer-text">
                We'll send you a reminder 1 day before it ends. 
                <a href="<?php echo site_url('/terms-of-service'); ?>" target="_blank">View cancellation policy</a>.
            </p>
            
            <!-- Hidden fields for secure processing -->
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('sud_start_trial_secure'); ?>">
            <input type="hidden" name="action" value="start_trial_secure">
        </form>
    </div>
</div>