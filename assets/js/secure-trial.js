/**
 * Secure Trial Payment System with Stripe PaymentIntents + Refund
 * Validates payment method with $5 charge that is immediately refunded
 */

(function() {
    'use strict';

    window.SUDSecureTrial = {
        stripe: null,
        cardElement: null,
        paymentIntent: null,
        
        init: function() {
            // Initialize when premium page is loaded
            if (typeof Stripe === 'undefined') {
                console.error('Stripe.js not loaded');
                return;
            }
            
            const config = window.sud_payment_config || {};
            if (!config.stripe_key) {
                console.error('Stripe key not configured');
                return;
            }
            
            this.stripe = Stripe(config.stripe_key);
            this.setupTrialForm();
        },
        
        setupTrialForm: function() {
            const trialForm = document.getElementById('trial-form');
            if (!trialForm) return;
            
            // Remove any existing submit listeners to prevent duplicates
            trialForm.replaceWith(trialForm.cloneNode(true));
            const newTrialForm = document.getElementById('trial-form');
            
            // Create card element after cloning
            this.cardElement = null; // Reset
            this.createCardElement();
            
            // Handle form submission
            newTrialForm.addEventListener('submit', (e) => {
                e.preventDefault();
                e.stopImmediatePropagation();
                this.handleTrialSubmission();
            });
        },
        
        createCardElement: function() {
            const cardElementContainer = document.getElementById('trial-card-element');
            if (!cardElementContainer || this.cardElement) return;
            
            const elements = this.stripe.elements();
            
            // Create card element with SUD styling
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
            
            this.cardElement.mount('#trial-card-element');
            
            // Handle real-time validation errors
            this.cardElement.on('change', (event) => {
                const displayError = document.getElementById('trial-card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                    displayError.style.display = 'block';
                } else {
                    displayError.textContent = '';
                    displayError.style.display = 'none';
                }
            });
        },
        
        async handleTrialSubmission() {
            const submitButton = document.getElementById('start-trial-btn');
            const originalText = submitButton ? submitButton.textContent : 'Start My FREE Trial';
            
            try {
                // Disable submit button and show loading
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Processing...';
                }
                
                // Validate form
                if (!this.validateTrialForm()) {
                    // Re-enable button immediately for validation errors
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                    }
                    
                    // Show validation error toast
                    if (typeof SUD !== 'undefined' && SUD.showToast) {
                        SUD.showToast('error', 'Validation Error', 'Please fill in all required fields');
                    } else {
                        alert('Please fill in all required fields');
                    }
                    return; // Don't throw error, just return
                }
                
                // Get form data
                const formData = this.getTrialFormData();
                
                // Step 1: Create PaymentIntent on server
                const baseUrl = window.sud_payment_config?.base_url || '/wordpress/sud';
                const setupResponse = await fetch(`${baseUrl}/ajax/start-trial-secure.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(formData)
                });
                
                if (!setupResponse.ok) {
                    const errorText = await setupResponse.text();
                    console.error('Setup response error:', errorText);
                    throw new Error(`Server error (${setupResponse.status}): ${setupResponse.statusText}`);
                }
                
                const setupResult = await setupResponse.json();
                
                if (!setupResult.success) {
                    // Show user-friendly error message
                    const errorMsg = this.getFriendlyErrorMessage(setupResult.message || 'Failed to initialize payment verification');
                    throw new Error(errorMsg);
                }
                
                // Validate we have the client secret
                if (!setupResult.payment_intent_client_secret) {
                    throw new Error('Payment system configuration error. Please try again or contact support.');
                }
                
                // Step 2: Confirm PaymentIntent with Stripe (charges $5)
                const confirmResult = await this.stripe.confirmCardPayment(
                    setupResult.payment_intent_client_secret,
                    {
                        payment_method: {
                            card: this.cardElement,
                            billing_details: {
                                name: formData.get('cardholder_name'),
                                email: formData.get('email')
                            }
                        }
                    }
                );
                
                if (confirmResult.error) {
                    // Handle Stripe-specific errors with user-friendly messages
                    const friendlyError = this.getStripeErrorMessage(confirmResult.error);
                    throw new Error(friendlyError);
                }
                
                // Handle 3D Secure authentication if required
                if (confirmResult.paymentIntent.status === 'requires_action') {
                    // Show user that additional authentication is needed
                    if (submitButton) {
                        submitButton.textContent = 'Authenticating...';
                    }
                    
                    const {error: confirmError} = await this.stripe.confirmCardPayment(confirmResult.paymentIntent.client_secret);
                    if (confirmError) {
                        throw new Error(this.getStripeErrorMessage(confirmError));
                    }
                    
                    // Payment succeeded after 3DS
                    if (submitButton) {
                        submitButton.textContent = 'Completing setup...';
                    }
                } else if (confirmResult.paymentIntent.status === 'processing') {
                    // Handle processing status - some issuers take a few seconds
                    if (submitButton) {
                        submitButton.textContent = 'Processing payment...';
                    }
                    
                    // Poll for status change with timeout
                    const processedPayment = await this.pollPaymentIntentStatus(confirmResult.paymentIntent.id, 30000); // 30 second timeout
                    
                    if (processedPayment.status !== 'succeeded') {
                        throw new Error('Payment processing timed out. Please try again or contact support.');
                    }
                    
                    // Update confirmResult with processed payment
                    confirmResult.paymentIntent = processedPayment;
                    
                    if (submitButton) {
                        submitButton.textContent = 'Completing setup...';
                    }
                }
                
                // Step 3: Complete trial activation on server (triggers immediate refund)
                const completeData = new FormData();
                completeData.append('payment_intent_id', confirmResult.paymentIntent.id);
                completeData.append('billing_cycle', formData.get('billing_cycle'));
                completeData.append('plan', formData.get('plan'));
                
                // Use completion nonce
                const config = window.sud_payment_config || {};
                console.log('🔍 Complete Trial Debug:', {
                    config: config,
                    complete_trial_nonce: config.complete_trial_nonce,
                    plan: formData.get('plan'),
                    payment_intent_id: confirmResult.paymentIntent.id,
                    window_sud_payment_config: window.sud_payment_config
                });
                
                if (config.complete_trial_nonce) {
                    completeData.append('nonce', config.complete_trial_nonce);
                } else {
                    console.error('❌ Missing complete_trial_nonce in config');
                }
                completeData.append('action', 'complete_trial_setup');
                
                const completeResponse = await fetch(`${baseUrl}/ajax/complete-trial-setup.php`, {
                    method: 'POST',
                    body: completeData
                });
                
                const completeResult = await completeResponse.json();
                
                if (!completeResult.success) {
                    throw new Error(completeResult.message || 'Failed to activate trial');
                }
                
                // Success! Show success toast with dynamic duration
                const trialDays = setupResult.trial_duration_days || 3;
                const message = `Your ${trialDays}-day trial is now active. The $5 verification charge was immediately refunded. Most banks drop the hold within a few minutes; a few take 3-7 days. Redirecting to dashboard...`;
                
                if (typeof SUD !== 'undefined' && SUD.showToast) {
                    SUD.showToast('success', 'Trial Activated!', message);
                } else {
                    alert(message);
                }
                
                setTimeout(() => {
                    window.location.href = window.sud_payment_config?.base_url + '/pages/dashboard' || '/wordpress/sud/pages/dashboard';
                }, 2000);
                
            } catch (error) {
                console.error('Trial setup error:', error);
                
                // Show error toast instead of custom modal
                let errorMessage = 'Please try again or contact support.';
                if (error.message) {
                    errorMessage = error.message;
                } else if (error.data && error.data.message) {
                    errorMessage = error.data.message;
                } else if (error.error) {
                    errorMessage = error.error;
                }
                
                if (typeof SUD !== 'undefined' && SUD.showToast) {
                    SUD.showToast('error', 'Trial Setup Failed', errorMessage);
                } else {
                    alert('Trial Setup Failed: ' + errorMessage);
                }
                
                // Re-enable submit button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            }
        },
        
        validateTrialForm: function() {
            const requiredFields = [
                'trial-email', 
                'trial-cardholder-name'
            ];
            let isValid = true;
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field || !field.value.trim()) {
                    isValid = false;
                    if (field) {
                        field.style.borderColor = '#EF4444';
                    }
                } else if (field) {
                    field.style.borderColor = '';
                }
            });
            
            // Validate card element if it exists
            if (this.cardElement) {
                // Stripe Elements validation is handled by the change event
                // We just need to ensure the element is ready
            }
            
            return isValid;
        },
        
        getTrialFormData: function() {
            const formData = new FormData();
            
            // Get selected plan from form data-plan attribute
            const form = document.getElementById('trial-form');
            const planId = form ? form.getAttribute('data-plan') : '';
            
            formData.append('plan', planId);
            formData.append('email', document.getElementById('trial-email').value);
            formData.append('cardholder_name', document.getElementById('trial-cardholder-name').value);
            formData.append('billing_cycle', 'monthly'); // Default for trials
            const nonceField = document.querySelector('input[name="nonce"]');
            if (nonceField) {
                formData.append('nonce', nonceField.value);
            }
            formData.append('action', 'start_trial_secure');
            
            return formData;
        },
        
        // Poll PaymentIntent status for 'processing' payments
        async pollPaymentIntentStatus(paymentIntentId, timeoutMs = 30000) {
            const startTime = Date.now();
            const pollInterval = 2000; // Poll every 2 seconds
            
            while (Date.now() - startTime < timeoutMs) {
                try {
                    const result = await this.stripe.retrievePaymentIntent(paymentIntentId);
                    
                    if (result.paymentIntent.status === 'succeeded') {
                        return result.paymentIntent;
                    }
                    
                    if (result.paymentIntent.status === 'canceled' || 
                        result.paymentIntent.status === 'payment_failed') {
                        throw new Error('Payment was declined or failed');
                    }
                    
                    // Still processing, wait before next poll
                    await new Promise(resolve => setTimeout(resolve, pollInterval));
                    
                } catch (error) {
                    console.error('Error polling payment status:', error);
                    throw error;
                }
            }
            
            // Timeout reached
            throw new Error('Payment processing timed out');
        },
        
        // User-friendly error message helpers
        getFriendlyErrorMessage: function(serverMessage) {
            // Convert technical server errors to user-friendly messages
            const lowerMsg = serverMessage.toLowerCase();
            
            if (lowerMsg.includes('stripe') && lowerMsg.includes('key')) {
                return 'Payment system temporarily unavailable. Please try again later.';
            }
            
            if (lowerMsg.includes('plan') && lowerMsg.includes('invalid')) {
                return 'Please select a valid subscription plan and try again.';
            }
            
            if (lowerMsg.includes('trial') && lowerMsg.includes('already')) {
                return 'You already have an active trial for this plan.';
            }
            
            if (lowerMsg.includes('login') || lowerMsg.includes('authentication')) {
                return 'Please refresh the page and log in again.';
            }
            
            // Return original message if no specific match
            return serverMessage;
        },
        
        getStripeErrorMessage: function(stripeError) {
            // Log for debugging
            console.log('Stripe error details:', {
                code: stripeError.code,
                decline_code: stripeError.decline_code,
                message: stripeError.message,
                type: stripeError.type
            });
            
            // Map of decline codes to user-friendly messages (prioritize these)
            const declineCodeMap = {
                'insufficient_funds': 'Your card doesn\'t have enough funds. Try a different card or contact your bank.',
                'lost_card': 'This card has been reported lost. Use a different one.',
                'stolen_card': 'This card has been reported stolen. Use a different one.',
                'do_not_honor': 'Your bank didn\'t approve the charge. Call them or try another card.',
                'pickup_card': 'This card cannot be used. Please contact your bank.',
                'transaction_not_allowed': 'This transaction type isn\'t allowed on your card.',
                'currency_not_supported': 'This card doesn\'t support the transaction currency.',
                'duplicate_transaction': 'This appears to be a duplicate transaction. Please wait before trying again.',
                'fraudulent': 'This transaction was flagged as potentially fraudulent. Contact your bank.',
                'generic_decline': 'Your card was declined. Please try another card or contact your bank.',
                'issuer_not_available': 'Your bank is currently unavailable. Please try again later.',
                'merchant_blacklist': 'This card cannot be used for this transaction.',
                'new_account_information_available': 'Your card information may have changed. Please try again.',
                'no_action_taken': 'The bank declined the transaction without explanation. Try another card.',
                'not_permitted': 'This payment is not permitted on your card.',
                'offline_pin_required': 'This card requires PIN verification which isn\'t supported online.',
                'online_or_offline_pin_required': 'This card requires PIN verification which isn\'t supported online.',
                'pin_try_exceeded': 'Too many incorrect PIN attempts. Contact your bank.',
                'restricted_card': 'This card has spending restrictions. Contact your bank.',
                'security_violation': 'Security violation detected. Contact your bank.',
                'service_not_allowed': 'This service is not allowed on your card.',
                'stop_payment_order': 'A stop payment order has been placed on this card.',
                'testmode_decline': 'This is a test transaction decline.',
                'withdrawal_count_limit_exceeded': 'You\'ve exceeded your card\'s transaction limit.',
            };
            
            // First, check for specific decline code (more precise than error code)
            if (stripeError.decline_code && declineCodeMap[stripeError.decline_code]) {
                return declineCodeMap[stripeError.decline_code];
            }
            
            // Fall back to generic error code mapping
            switch (stripeError.code) {
                case 'card_declined':
                    return 'Your bank declined this card. Please try another card.';
                    
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
                    
                case 'postal_code_invalid':
                    return 'The ZIP/Postal code didn\'t match your card. Fix it and try again.';
                    
                default:
                    // If all else fails, show the original Stripe message with context
                    return stripeError.message || 'There was an issue with your payment information. Please check your details and try again.';
            }
        }
    };
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        window.SUDSecureTrial.init();
    });
    
    // Also initialize when Stripe is loaded (fallback)
    if (typeof Stripe !== 'undefined') {
        window.SUDSecureTrial.init();
    }
})();