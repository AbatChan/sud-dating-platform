/**
 * Stripe Error Mapping Utilities
 * Centralized error handling for all payment flows
 */

(function() {
    'use strict';

    // Shared decline code to user-friendly message mapping
    const DECLINE_CODE_MAP = {
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

    // Generic error code fallbacks
    const ERROR_CODE_MAP = {
        'card_declined': 'Your bank declined this card. Please try another card.',
        'expired_card': 'Your card has expired. Please use a different card.',
        'incorrect_cvc': 'Your card\'s security code (CVC) is incorrect.',
        'processing_error': 'We encountered an error processing your card. Please try again.',
        'incorrect_number': 'Your card number is incorrect. Please check and try again.',
        'incomplete_number': 'Please complete all card details and try again.',
        'incomplete_cvc': 'Please complete all card details and try again.',
        'incomplete_expiry': 'Please complete all card details and try again.',
        'authentication_required': 'Additional authentication is required. Please follow the prompts from your bank.',
        'rate_limit': 'Too many requests. Please wait a moment and try again.',
        'postal_code_invalid': 'The ZIP/Postal code didn\'t match your card. Fix it and try again.',
    };

    // Trial-specific overrides for decline codes
    const TRIAL_DECLINE_OVERRIDES = {
        'insufficient_funds': 'To verify your card for this free trial, we place a temporary $5 hold (refunded immediately). Your card doesn\'t have enough funds for this verification. Please add funds or try a different card.',
        'do_not_honor': 'Your bank declined the $5 verification charge needed to start your free trial. Please contact your bank or try another card. The $5 is refunded immediately after verification.',
        'lost_card': 'This card has been reported lost and cannot be used for the $5 trial verification. Please try a different card.',
        'stolen_card': 'This card has been reported stolen and cannot be used for the $5 trial verification. Please try a different card.',
        'pickup_card': 'This card cannot be used for the $5 trial verification. Please contact your bank or try a different card.',
        'restricted_card': 'This card has spending restrictions that prevent the $5 trial verification. Please contact your bank or try a different card.',
        'fraudulent': 'The $5 trial verification was flagged for security. Please contact your bank or try a different card.',
        'transaction_not_allowed': 'This card doesn\'t allow the $5 verification charge needed for the free trial. Please try a different card.',
        'generic_decline': 'Your bank declined the $5 trial verification. Please contact your bank or try another card. The $5 is refunded immediately after verification.',
    };

    // Trial-specific overrides for error codes  
    const TRIAL_ERROR_CODE_OVERRIDES = {
        'card_declined': 'Your card was declined for the $5 trial verification. Please try another card. The $5 is refunded immediately after verification.',
        'expired_card': 'Your card has expired and cannot be used for the trial verification. Please use a different card.',
        'processing_error': 'We encountered an error processing the $5 trial verification. Please try again.',
        'authentication_required': 'Additional authentication is required for the $5 trial verification. Please follow the prompts from your bank.',
        'rate_limit': 'Too many verification attempts. Please wait a moment and try again.',
    };

    function getStripeErrorMessage(stripeError, { isTrialFlow = false, enableLogging = true } = {}) {
        // Guard against null/undefined/invalid stripeError
        if (!stripeError || typeof stripeError !== 'object') {
            return isTrialFlow 
                ? 'We couldn\'t verify your card for the free trial. Please try again.'
                : 'Payment failed. Please try again.';
        }
        
        if (enableLogging && !window.SUD_SUPPRESS_STRIPE_LOGS) {
            console.log('[SUD-Stripe] details:', {
                code: stripeError.code,
                decline_code: stripeError.decline_code,
                message: stripeError.message,
                type: stripeError.type,
                flow: isTrialFlow ? 'trial' : 'payment'
            });
        }
    
        const declineCode = stripeError.decline_code || stripeError.code;
    
        if (isTrialFlow) {
            if (TRIAL_DECLINE_OVERRIDES[declineCode])      return TRIAL_DECLINE_OVERRIDES[declineCode];
            if (TRIAL_ERROR_CODE_OVERRIDES[stripeError.code]) return TRIAL_ERROR_CODE_OVERRIDES[stripeError.code];
        }
    
        if (DECLINE_CODE_MAP[declineCode])   return DECLINE_CODE_MAP[declineCode];
    
        if (ERROR_CODE_MAP[stripeError.code]) return ERROR_CODE_MAP[stripeError.code];
    
        return stripeError.message ||
            (isTrialFlow
                ? 'We couldn’t verify your card for the free trial. Check your details and try again (a $5 temporary hold is refunded immediately).'
                : 'There was an issue with your payment. Please check your card details and try again.');
    }
    
    function suppressStripeNetworkErrors() {
        if (window.SUD_SUPPRESS_STRIPE_LOGS ||
            (!window.SUD_DEBUG && window.location.hostname !== 'localhost')) {
    
            // Comprehensive console patching for all console methods
            if (!console.__SUD_STRIPE_PATCHED__) {
                console.__SUD_STRIPE_PATCHED__ = true;
                
                // Patch multiple console methods that Stripe might use
                ['error', 'warn', 'log'].forEach(method => {
                    const original = console[method];
                    console[method] = (...args) => {
                        const txt = args.join(' ');
                        
                        // Filter Stripe API network errors
                        if (txt.includes('api.stripe.com') && 
                            (txt.includes('402') || txt.includes('Payment Required') ||
                             txt.includes('/payment_intents/') || txt.includes('/confirm'))) {
                            return; // Suppress these expected payment failure logs
                        }
                        
                        // Also suppress generic Stripe network errors
                        if (txt.includes('stripe.com') && 
                            (txt.includes('POST') || txt.includes('GET')) &&
                            (txt.includes('402') || txt.includes('4'))) {
                            return;
                        }
                        
                        original.apply(console, args);
                    };
                });
                
                // Additional: Override XMLHttpRequest if needed for deeper suppression
                if (window.XMLHttpRequest && !window.XMLHttpRequest.__SUD_PATCHED__) {
                    const OriginalXHR = window.XMLHttpRequest;
                    window.XMLHttpRequest.__SUD_PATCHED__ = true;
                    
                    function PatchedXHR() {
                        const xhr = new OriginalXHR();
                        
                        // Override addEventListener to suppress error events for Stripe URLs
                        const originalAddEventListener = xhr.addEventListener;
                        xhr.addEventListener = function(event, handler) {
                            if (event === 'error' || event === 'loadend') {
                                const wrappedHandler = function(e) {
                                    // Check if this is a Stripe API call with expected error
                                    if (xhr.responseURL && xhr.responseURL.includes('api.stripe.com') && 
                                        (xhr.status === 402 || xhr.status === 400)) {
                                        // Don't call the handler for expected payment errors
                                        return;
                                    }
                                    return handler.call(this, e);
                                };
                                return originalAddEventListener.call(this, event, wrappedHandler);
                            }
                            return originalAddEventListener.call(this, event, handler);
                        };
                        
                        return xhr;
                    }
                    
                    // Copy static properties
                    Object.setPrototypeOf(PatchedXHR, OriginalXHR);
                    Object.setPrototypeOf(PatchedXHR.prototype, OriginalXHR.prototype);
                    
                    // Only apply in non-debug environments
                    if (!window.SUD_DEBUG && window.location.hostname !== 'localhost') {
                        // window.XMLHttpRequest = PatchedXHR; // Commented out as this might be too aggressive
                    }
                }
            }
        }
    }
    
    window.SUDStripeErrors = Object.freeze({
        getStripeErrorMessage,
        suppressNetworkErrors: suppressStripeNetworkErrors,
        // Convenience alias for backward compatibility
        get: getStripeErrorMessage,
        maps: {
            decline: DECLINE_CODE_MAP,
            error:   ERROR_CODE_MAP,
            trialDecline: TRIAL_DECLINE_OVERRIDES,
            trialError:   TRIAL_ERROR_CODE_OVERRIDES
        }
    });
    
    suppressStripeNetworkErrors();
    
    // Additional early suppression - run as soon as possible
    if (typeof window !== 'undefined' && !window.SUD_DEBUG && window.location.hostname !== 'localhost') {
        // Immediate console patching before Stripe SDK loads
        (function() {
            const originalError = console.error;
            const originalWarn = console.warn;
            
            console.error = function(...args) {
                const message = args.join(' ');
                if (message.includes('api.stripe.com') && 
                    (message.includes('402') || message.includes('Payment Required') ||
                     message.includes('payment_intents') || message.includes('/confirm'))) {
                    return; // Suppress Stripe 402 errors
                }
                originalError.apply(this, args);
            };
            
            console.warn = function(...args) {
                const message = args.join(' ');
                if (message.includes('stripe.com') && message.includes('402')) {
                    return; // Suppress Stripe warnings
                }
                originalWarn.apply(this, args);
            };
        })();
    }
})();