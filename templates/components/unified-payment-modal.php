<?php
/**
 * Unified Payment Modal Component
 * Used for both trials and subscriptions to reduce code duplication
 * Follows industry standards with minimal data collection
 */

// Get current user info for prefilling
$current_user = wp_get_current_user();

// Get payment settings if not already set
if (!isset($payment_settings)) {
    $payment_settings = get_option('sud_payment_settings', []);
}
?>

<!-- Unified Payment Modal (used for both trials and subscriptions) -->
<div id="unified-payment-modal" class="payment-modal">
    <div class="sud-modal-content">
        <span class="close-modal">&times;</span>

        <div id="payment-info" class="payment-info">
            <h3 id="payment-title">Complete Your Purchase</h3> 
            <p id="payment-description">$<span id="payment-price">33.33</span>/month • <span id="payment-plan-name">Gold Plan</span></p>
        </div>
        
        <?php if (isset($payment_settings['test_mode']) && $payment_settings['test_mode']): ?>
            <div class="test-mode-warning">
                <i class="fas fa-exclamation-triangle"></i> Test mode is active. No real charges will be made.
            </div>
        <?php endif; ?>

        <form id="unified-payment-form" novalidate>
            <div class="payment-tabs">
                <div class="payment-tab active" data-tab="card">Credit Card</div>
                <div class="payment-tab" data-tab="paypal">PayPal</div>
            </div>

            <!-- Credit Card Section - Unified minimal approach -->
            <div class="payment-section active" id="card-section-unified">
                <div class="form-group">
                    <label for="payment-email" class="form-label">Email Address</label>
                    <input type="email" id="payment-email" name="email" class="form-input" required 
                           value="<?php echo esc_attr($current_user->user_email); ?>" readonly>
                    <small>Pre-filled from your account</small>
                </div>

                <div class="form-group">
                    <label for="payment-cardholder-name" class="form-label">Name on Card</label>
                    <input type="text" id="payment-cardholder-name" name="cardholder_name" class="form-input" required 
                           value="<?php echo esc_attr($current_user->display_name); ?>">
                    <small>Pre-filled from your account</small>
                </div>

                <!-- Stripe Elements Card Input -->
                <div class="form-group">
                    <label class="form-label">Card Information</label>
                    <div id="card-element-unified" class="stripe-card-element">
                        <!-- Stripe Card Element will be mounted here -->
                    </div>
                    <div id="card-errors-unified" class="card-errors" role="alert"></div>
                </div>
            </div>

            <!-- PayPal Section -->
            <div class="payment-section" id="paypal-section-unified" style="display: none;">
                <div class="form-group">
                    <label for="payment-paypal-email" class="form-label">PayPal Email</label>
                    <input type="email" id="payment-paypal-email" name="paypal_email" class="form-input" required 
                           value="<?php echo esc_attr($current_user->user_email); ?>">
                </div>
                <div id="paypal-container-unified" style="margin-top: 15px;">
                    <!-- PayPal Buttons will be rendered here -->
                </div>
                <p style="color: #666; font-size: 14px; margin: 15px 0;">
                    You'll be redirected to PayPal to complete your purchase.
                </p>
            </div>

            <button type="submit" id="unified-payment-btn" class="btn-confirm">
                Complete Purchase
            </button>
            
            <p id="payment-footer-text" class="payment-footer-text">
                Cancel anytime. <a href="<?php echo site_url('/terms-of-service'); ?>" target="_blank">View terms</a>.
            </p>
            
            <!-- Hidden fields for processing -->
            <input type="hidden" name="payment_type" id="payment-type" value="">
            <input type="hidden" name="plan_id" id="payment-plan-id" value="">
            <input type="hidden" name="billing_cycle" id="payment-billing-cycle" value="">
            <input type="hidden" name="nonce" id="payment-nonce" value="">
            <input type="hidden" name="action" id="payment-action" value="">
        </form>
    </div>
</div>