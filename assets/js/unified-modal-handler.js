/**
 * Unified Modal Payment Handler
 * Handles both trial and subscription payments from a single modal
 * Reduces code duplication and ensures consistent behavior
 */

(function() {
    'use strict';

    window.SUDUnifiedModal = {
        stripe: null,
        cardElement: null,
        currentPaymentType: null,
        currentConfig: null,
        
        init: function() {
            if (typeof Stripe === 'undefined') {
                console.error('Stripe.js not loaded');
                return;
            }
            
            const config = window.sud_payment_config || {};
            if (!config.stripe_key) {
                console.error('Stripe key not configured');
                return;
            }
            
            // Suppress Stripe HTTPS warnings in development/test mode
            if (config.test_mode && location.protocol === 'http:') {
                const originalWarn = console.warn;
                console.warn = function(...args) {
                    const message = args.join(' ');
                    if (message.includes('Stripe.js integration') && message.includes('HTTPS')) {
                        return; // Suppress this warning in test mode
                    }
                    originalWarn.apply(console, args);
                };
            }
            
            this.stripe = Stripe(config.stripe_key);
            
            // Check if modal exists before setting up
            const modal = document.getElementById('unified-payment-modal');
            if (!modal) {
                console.error('Unified payment modal not found in DOM');
                return;
            }
            
            this.setupModal();
            this.bindEvents();
        },
        
        setupModal: function() {
            const modal = document.getElementById('unified-payment-modal');
            if (!modal) return;
            
            // Initialize Stripe Elements
            this.createCardElement();
            
            // Handle form submission
            const form = document.getElementById('unified-payment-form');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handlePaymentSubmission();
                });
            }
        },
        
        bindEvents: function() {
            // Bind trial/subscription buttons
            document.addEventListener('click', (e) => {
                // Handle trial buttons (btn-choice now directly opens trial, no more choice modal)
                if (e.target.classList.contains('btn-choice') || 
                    e.target.classList.contains('choice-trial-btn') || 
                    e.target.classList.contains('btn-trial')) {
                    e.preventDefault();
                    
                    const modal = document.getElementById('unified-payment-modal');
                    if (!modal) return;
                    
                    this.openTrialModal(e.target);
                    
                // Handle subscription buttons  
                } else if (e.target.classList.contains('btn-subscribe') || 
                          e.target.classList.contains('choice-payment-btn') ||
                          e.target.classList.contains('auto-open-payment')) {
                    e.preventDefault();
                    
                    const modal = document.getElementById('unified-payment-modal');
                    if (!modal) return;
                    
                    this.openSubscriptionModal(e.target);
                }
            });
            
            // Payment tab switching
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('payment-tab')) {
                    // Only switch tabs if we're in the unified modal
                    const modal = document.getElementById('unified-payment-modal');
                    if (modal && modal.classList.contains('show')) {
                        this.switchPaymentTab(e.target.dataset.tab);
                    }
                }
            });
            
            // Modal close
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('close-modal')) {
                    this.closeModal();
                }
                
                // Close modal when clicking outside
                if (e.target.classList.contains('payment-modal')) {
                    this.closeModal();
                }
            });
            
            // Listen for billing toggle changes to update modal content
            const billingToggle = document.getElementById('billing-toggle');
            if (billingToggle) {
                billingToggle.addEventListener('change', () => {
                    if (this.currentPaymentType === 'subscription' && this.currentConfig) {
                        this.updateSubscriptionModalContent();
                    } else if (this.currentPaymentType === 'trial' && this.currentConfig) {
                        this.updateTrialModalContent();
                    }
                });
            }
        },
        
        openTrialModal: function(button) {
            const plan = button.dataset.plan || 'gold';
            const priceMonthly = parseFloat(button.dataset.priceMonthly || button.dataset.pricemonthly || 33.33);
            const priceAnnually = parseFloat(button.dataset.priceAnnually || button.dataset.priceannually || 400);
            
            this.currentPaymentType = 'trial';
            this.currentConfig = {
                plan: plan,
                priceMonthly: priceMonthly,
                priceAnnually: priceAnnually,
                planName: plan.charAt(0).toUpperCase() + plan.slice(1) + ' Plan'
            };
            
            this.updateTrialModalContent();
            this.showModal();
        },
        
        updateTrialModalContent: function() {
            if (!this.currentConfig) return;
            
            const billingCycle = document.getElementById('billing-toggle')?.checked ? 'annual' : 'monthly';
            const price = billingCycle === 'annual' ? this.currentConfig.priceAnnually : this.currentConfig.priceMonthly;
            const periodText = billingCycle === 'annual' ? '/year' : '/month';
            
            // Update current config with new billing cycle
            this.currentConfig.billingCycle = billingCycle;
            this.currentConfig.price = price;
            
            // Update modal content
            const titleEl = document.getElementById('payment-title');
            const descEl = document.getElementById('payment-description');
            const buttonEl = document.getElementById('unified-payment-btn');
            const footerEl = document.getElementById('payment-footer-text');
            
            // Get dynamic trial duration from config
            const trialDays = this.currentConfig.trial_duration_days || 3;
            if (titleEl) titleEl.textContent = `Start Your ${trialDays}-Day FREE Trial`;
            
            // Update description while preserving the spans
            if (descEl) {
                descEl.innerHTML = `$<span id="payment-price">${price}</span>${periodText} • <span id="payment-plan-name">${this.currentConfig.planName}</span>`;
            }
            
            if (buttonEl) buttonEl.textContent = 'Start My FREE Trial';
            if (footerEl) footerEl.innerHTML = 'We\'ll send you a reminder 1 day before it ends. <a href="/terms-of-service" target="_blank">View cancellation policy</a>.';
            
            this.setFormData('trial', this.currentConfig.plan, billingCycle);
        },
        
        openSubscriptionModal: function(button) {
            const plan = button.dataset.plan || 'gold';
            const priceMonthly = parseFloat(button.dataset.priceMonthly || button.dataset.pricemonthly || 33.33);
            const priceAnnually = parseFloat(button.dataset.priceAnnually || button.dataset.priceannually || 400);
            
            this.currentPaymentType = 'subscription';
            this.currentConfig = {
                plan: plan,
                priceMonthly: priceMonthly,
                priceAnnually: priceAnnually,
                planName: plan.charAt(0).toUpperCase() + plan.slice(1) + ' Plan',
                button: button // Store button reference for data attributes
            };
            
            this.updateSubscriptionModalContent();
            this.showModal();
        },
        
        updateSubscriptionModalContent: function() {
            if (!this.currentConfig) return;
            
            const billingCycle = document.getElementById('billing-toggle')?.checked ? 'annual' : 'monthly';
            const price = billingCycle === 'annual' ? this.currentConfig.priceAnnually : this.currentConfig.priceMonthly;
            const periodText = billingCycle === 'annual' ? '/year' : '/month';
            
            // Update current config with new billing cycle
            this.currentConfig.billingCycle = billingCycle;
            this.currentConfig.price = price;
            
            // Update modal content
            const titleEl = document.getElementById('payment-title');
            const descEl = document.getElementById('payment-description');
            const buttonEl = document.getElementById('unified-payment-btn');
            const footerEl = document.getElementById('payment-footer-text');
            
            if (titleEl) titleEl.textContent = 'Complete Your Subscription';
            
            // Update description while preserving the spans
            if (descEl) {
                descEl.innerHTML = `$<span id="payment-price">${price}</span>${periodText} • <span id="payment-plan-name">${this.currentConfig.planName}</span>`;
            }
            
            if (buttonEl) buttonEl.textContent = billingCycle === 'annual' ? 'Subscribe Annually' : 'Start Subscription';
            if (footerEl) footerEl.innerHTML = 'Cancel anytime. <a href="/terms-of-service" target="_blank">View terms</a>.';
            
            this.setFormData('subscription', this.currentConfig.plan, billingCycle);
        },
        
        
        setFormData: function(paymentType, plan, billingCycle) {
            const typeEl = document.getElementById('payment-type');
            const planEl = document.getElementById('payment-plan-id');
            const cycleEl = document.getElementById('payment-billing-cycle');
            const nonceEl = document.getElementById('payment-nonce');
            const actionEl = document.getElementById('payment-action');
            
            if (!typeEl || !planEl) return;
            
            typeEl.value = paymentType;
            planEl.value = plan;
            if (cycleEl) cycleEl.value = billingCycle;
            
            const config = window.sud_payment_config || {};
            
            if (paymentType === 'trial') {
                if (nonceEl) nonceEl.value = config.complete_trial_nonce || '';
                if (actionEl) actionEl.value = 'start_trial_secure';
            } else {
                if (nonceEl) nonceEl.value = window.sud_premium_config?.subscription_nonce || '';
                if (actionEl) actionEl.value = 'process_subscription';
            }
        },
        
        createCardElement: function() {
            const cardElementContainer = document.getElementById('card-element-unified');
            if (!cardElementContainer || this.cardElement) return;
            
            const elements = this.stripe.elements();
            
            this.cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#FFFFFF',
                        fontFamily: 'Lato, Inter, sans-serif',
                        backgroundColor: '#161616',
                        '::placeholder': {
                            color: '#AAAAAA'
                        }
                    },
                    invalid: {
                        color: '#EF4444',
                        iconColor: '#EF4444'
                    }
                },
                hidePostalCode: false
            });
            
            this.cardElement.mount('#card-element-unified');
            
            // Handle validation errors
            this.cardElement.on('change', (event) => {
                const displayError = document.getElementById('card-errors-unified');
                if (event.error) {
                    displayError.textContent = event.error.message;
                    displayError.style.display = 'block';
                } else {
                    displayError.textContent = '';
                    displayError.style.display = 'none';
                }
            });
        },
        
        switchPaymentTab: function(tabType) {
            // Only operate within the unified modal context
            const modal = document.getElementById('unified-payment-modal');
            if (!modal) {
                return; // No unified modal present
            }
            
            // Update tab appearance within the modal
            modal.querySelectorAll('.payment-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.tab === tabType);
            });
            
            // Show/hide sections within the modal
            const cardSection = modal.querySelector('#card-section-unified');
            const paypalSection = modal.querySelector('#paypal-section-unified');
            
            if (!cardSection || !paypalSection) {
                // Elements not found in unified modal, this is expected if using other modals
                return;
            }
            
            if (tabType === 'card') {
                cardSection.style.display = 'block';
                paypalSection.style.display = 'none';
            } else {
                cardSection.style.display = 'none';
                paypalSection.style.display = 'block';
                this.initializePayPal();
            }
        },
        
        initializePayPal: function() {
            if (!window.paypal) return;
            
            const container = document.getElementById('paypal-container-unified');
            if (!container || container.hasAttribute('data-paypal-initialized')) return;
            
            const config = this.currentConfig;
            const isSubscription = this.currentPaymentType === 'subscription';
            
            // Use the working PayPal system from unified-payment.js
            if (isSubscription) {
                window.SUDUnifiedPayment.setupPayPalForSubscription('#unified-payment-modal', {
                    type: 'subscription',
                    plan_id: config.plan,
                    billing_cycle: config.billingCycle,
                    amount: config.price,
                    paypal_plan_id: config.paypal_plan_id || null
                });
            } else {
                window.SUDUnifiedPayment.setupPayPalForAmount('#unified-payment-modal', {
                    type: 'trial',
                    amount: config.price,
                    description: `${config.planName} Trial`,
                    name: `${config.planName} Trial`
                });
            }
        },
        
        async handlePaymentSubmission() {
            const submitButton = document.getElementById('unified-payment-btn');
            const originalText = submitButton.textContent;
            
            try {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
                
                if (!this.validateForm()) {
                    throw new Error('Please fill in all required fields');
                }
                
                if (this.currentPaymentType === 'trial') {
                    await this.processTrialPayment();
                } else {
                    await this.processSubscriptionPayment();
                }
                
            } catch (error) {
                console.error('Payment processing error:', error);
                
                if (typeof SUD !== 'undefined' && SUD.showToast) {
                    SUD.showToast('error', 'Payment Failed', error.message || 'Please try again');
                } else {
                    alert('Payment Failed: ' + (error.message || 'Please try again'));
                }
                
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        },
        
        validateForm: function() {
            const requiredFields = ['payment-email', 'payment-cardholder-name'];
            let isValid = true;
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field || !field.value.trim()) {
                    isValid = false;
                    if (field) field.style.borderColor = '#EF4444';
                } else if (field) {
                    field.style.borderColor = '';
                }
            });
            
            return isValid;
        },
        
        async processTrialPayment() {
            // Use the existing secure trial system but with unified form data
            const formData = new FormData();
            formData.append('plan', this.currentConfig.plan);
            formData.append('email', document.getElementById('payment-email').value);
            formData.append('cardholder_name', document.getElementById('payment-cardholder-name').value);
            formData.append('billing_cycle', 'monthly');
            formData.append('nonce', document.getElementById('payment-nonce').value);
            formData.append('action', 'start_trial_secure');
            
            // Step 1: Create SetupIntent
            const baseUrl = window.sud_payment_config?.base_url || '/wordpress/sud';
            const setupResponse = await fetch(`${baseUrl}/ajax/start-trial-secure.php`, {
                method: 'POST',
                body: formData
            });
            
            const setupResult = await setupResponse.json();
            if (!setupResult.success) {
                throw new Error(setupResult.message || 'Failed to initialize payment verification');
            }
            
            // Step 2: Confirm PaymentIntent with Stripe (charges $5)
            const confirmResult = await this.stripe.confirmCardPayment(
                setupResult.payment_intent_client_secret,
                {
                    payment_method: {
                        card: this.cardElement,
                        billing_details: {
                            name: document.getElementById('payment-cardholder-name').value,
                            email: document.getElementById('payment-email').value
                        }
                    }
                }
            );
            
            if (confirmResult.error) {
                throw new Error(this.getStripeErrorMessage(confirmResult.error));
            }
            
            // Step 3: Complete trial activation (triggers immediate refund)
            const completeData = new FormData();
            completeData.append('payment_intent_id', confirmResult.paymentIntent.id);
            completeData.append('billing_cycle', 'monthly');
            completeData.append('nonce', window.sud_payment_config.complete_trial_nonce);
            completeData.append('action', 'complete_trial_setup');
            
            const completeResponse = await fetch(`${baseUrl}/ajax/complete-trial-setup.php`, {
                method: 'POST',
                body: completeData
            });
            
            const completeResult = await completeResponse.json();
            if (!completeResult.success) {
                throw new Error(completeResult.message || 'Failed to activate trial');
            }
            
            // Success!
            if (typeof SUD !== 'undefined' && SUD.showToast) {
                SUD.showToast('success', 'Trial Activated!', 'Your trial is now active. Redirecting...');
            }
            
            setTimeout(() => {
                window.location.href = `${baseUrl}/pages/dashboard`;
            }, 2000);
        },
        
        async processSubscriptionPayment() {
            // Use existing unified payment system for subscriptions
            const config = {
                plan_id: this.currentConfig.plan,
                billing_cycle: this.currentConfig.billingCycle,
                amount: this.currentConfig.price,
                name: this.currentConfig.planName
            };
            
            // Let unified-payment.js handle the subscription processing
            window.SUDUnifiedPayment.processStripePayment('#unified-payment-modal', config);
        },
        
        getStripeErrorMessage: function(stripeError) {
            switch (stripeError.code) {
                case 'card_declined':
                    return 'Your card was declined. Please try a different payment method.';
                case 'expired_card':
                    return 'Your card has expired. Please use a different card.';
                case 'incorrect_cvc':
                    return 'Your card\'s security code (CVC) is incorrect.';
                case 'processing_error':
                    return 'We encountered an error processing your card. Please try again.';
                case 'incorrect_number':
                    return 'Your card number is incorrect. Please check and try again.';
                case 'incomplete_number':
                case 'incomplete_cvc':
                case 'incomplete_expiry':
                    return 'Please complete all card details and try again.';
                case 'authentication_required':
                    return 'Additional authentication is required. Please follow the prompts from your bank.';
                case 'rate_limit':
                    return 'Too many requests. Please wait a moment and try again.';
                default:
                    return 'There was an issue with your payment information. Please check your details and try again.';
            }
        },
        
        showModal: function() {
            const modal = document.getElementById('unified-payment-modal');
            if (!modal) return;
            
            // Reset form and button states
            const form = document.getElementById('unified-payment-form');
            const button = document.getElementById('unified-payment-btn');
            
            if (form) form.reset();
            if (button) {
                button.disabled = false;
                button.textContent = this.currentPaymentType === 'trial' ? 'Start My FREE Trial' : 'Start Subscription';
            }
            
            // Clear any previous card errors
            const errorEl = document.getElementById('card-errors-unified');
            if (errorEl) {
                errorEl.textContent = '';
                errorEl.style.display = 'none';
            }
            
            // Show modal
            modal.classList.add('show');
            
            // Initialize Stripe elements fresh each time
            this.createCardElement();
        },
        
        closeModal: function() {
            const modal = document.getElementById('unified-payment-modal');
            if (!modal) return;
            
            modal.classList.remove('show');
            
            // Clean up Stripe elements
            if (this.cardElement) {
                this.cardElement.unmount();
                const cardContainer = document.getElementById('card-element-unified');
                if (cardContainer) {
                    cardContainer.innerHTML = ''; // Clear ghost styling after unmount
                }
                this.cardElement = null;
            }
            
            // Reset form and states
            const form = document.getElementById('unified-payment-form');
            const button = document.getElementById('unified-payment-btn');
            
            if (form) form.reset();
            if (button) {
                button.disabled = false;
                button.textContent = 'Complete Purchase';
            }
            
            // Clear errors
            const errorEl = document.getElementById('card-errors-unified');
            if (errorEl) {
                errorEl.textContent = '';
                errorEl.style.display = 'none';
            }
            
            // Reset current config
            this.currentPaymentType = null;
            this.currentConfig = null;
        }
    };
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        window.SUDUnifiedModal.init();
    });
    
    // Also initialize when Stripe is loaded
    if (typeof Stripe !== 'undefined') {
        window.SUDUnifiedModal.init();
    }
})();