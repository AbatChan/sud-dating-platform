/**
 * Unified Payment System
 * Handles all Stripe and PayPal payments across the application
 */

(function() {
    'use strict';

    // Global payment handler
    window.SUDUnifiedPayment = {
        stripe: null,
        elements: {},
        cardElements: {},
        config: {},
        
        // Initialize the payment system
        init: function() {
            this.config = window.sud_payment_config || {};
            
            if (this.config.stripe_key) {
                this.stripe = Stripe(this.config.stripe_key);
                this.initializeAllPaymentModals();
            }
            
            this.bindEvents();
        },
        
        // Initialize all payment modals on the page
        initializeAllPaymentModals: function() {
            // We are moving Stripe initialization to be "lazy" (on-demand).
            // This function can remain for other initializations if needed, like pre-binding events.
            // For now, it doesn't need to do anything with Stripe.
        },
        
        // Initialize a specific payment modal
        initializeStripeElement: function(modalId) {
            const modalElement = document.querySelector(modalId);
            if (!modalElement || !this.stripe) return;

            // Construct the unique ID for the card element div inside the modal
            const cardElementSelector = modalId + ' [id^="card-element-"]';
            const cardElement = document.querySelector(cardElementSelector);

            if (!cardElement || this.cardElements[cardElement.id]) {
                return;
            }
            

            // Create and mount the Stripe Card Element
            const elements = this.stripe.elements();
            const card = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#ffffff',
                        '::placeholder': {
                            color: '#a9a9a9',
                        },
                        fontFamily: 'Lato, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    },
                    invalid: {
                        color: '#ff7b7b',
                        iconColor: '#ff7b7b'
                    }
                }
            });
            
            card.mount(cardElement);
            this.cardElements[cardElement.id] = card;
            
            // Handle real-time validation errors from the card Element
            const errorsElementSelector = modalId + ' [id^="card-errors-"]';
            const errorsElement = document.querySelector(errorsElementSelector);
            if (errorsElement) {
                card.addEventListener('change', ({error}) => {
                    if (error) {
                        errorsElement.textContent = error.message;
                    } else {
                        errorsElement.textContent = '';
                    }
                });
            }
        },
        
        // Initialize PayPal for a specific modal
        initializePayPal: function(modalId) {
            if (!window.paypal || !this.config.paypal_client_id) return;
            
            const paypalContainerId = modalId.replace('payment-modal-', 'paypal-container-').replace('#one-off-purchase-modal', '#paypal-container-purchase');
            const paypalContainer = document.querySelector(paypalContainerId);
            
            if (paypalContainer && !paypalContainer.hasChildNodes()) {
                // PayPal will be initialized when modal opens with specific amount
            }
        },
        
        // Show payment modal with specific configuration
        showPaymentModal: function(modalId, config) {
            const modal = document.querySelector(modalId);
            if (!modal) return;
            
            // Generate unique order ID for this payment session
            config.order_uid = crypto.randomUUID();
            
            // Clear any previous data
            this.clearModalData(modal);
            
            this.initializeStripeElement(modalId);
            this.updateModalContent(modalId, config);
            
            // Show modal
            modal.classList.add('show');
        },

        // Update modal content (price, description, etc.)
        updateModalContent: function(modalId, config) {
            const modal = document.querySelector(modalId);
            if (!modal) return;
        
            // --- Handle Different Modal Structures ---
        
            // For the Premium Subscription Modal
            if (modalId === '#payment-modal-premium') {
                const planNameEl = modal.querySelector('#plan-name');
                const billingPeriodEl = modal.querySelector('#billing-period');
                const planPriceEl = modal.querySelector('#plan-price');
                const annualNoteEl = modal.querySelector('#annual-price-note');
                const annualTotalEl = modal.querySelector('.total-price');
        
                if (planNameEl) planNameEl.textContent = config.plan_name || 'Premium';
                if (billingPeriodEl) billingPeriodEl.textContent = config.billing_cycle === 'annual' ? 'Annual' : 'Monthly';
                
                if (config.billing_cycle === 'annual') {
                    const monthlyEquivalent = parseFloat(config.amount) / 12;
                    if (planPriceEl) planPriceEl.textContent = `$${monthlyEquivalent.toFixed(2)}`;
                    if (annualTotalEl) annualTotalEl.textContent = `$${parseFloat(config.amount).toFixed(2)}`;
                    if (annualNoteEl) annualNoteEl.style.display = 'block';
                } else {
                    if (planPriceEl) planPriceEl.textContent = `$${parseFloat(config.amount).toFixed(2)}`;
                    if (annualNoteEl) annualNoteEl.style.display = 'none';
                }
            }
            // For the Wallet (Coins) Modal
            else if (modalId === '#payment-modal-wallet') {
                const amountEl = modal.querySelector('#purchase-amount-wallet');
                const priceEl = modal.querySelector('#purchase-price-wallet');
                if (amountEl) amountEl.textContent = config.coin_amount ? parseInt(config.coin_amount).toLocaleString() : '0';
                if (priceEl) priceEl.textContent = parseFloat(config.amount).toFixed(2);
            }
            // For the One-Off Purchase Modal (Swipe/Boosts)
            else {
                const itemNameEl = modal.querySelector('#purchase-item-name');
                const priceEl = modal.querySelector('#purchase-total-price');
                const descriptionEl = modal.querySelector('#purchase-item-description');
                
                if (itemNameEl) itemNameEl.textContent = config.name;
                if (priceEl) priceEl.textContent = `$${parseFloat(config.amount).toFixed(2)}`;
                if (descriptionEl) descriptionEl.textContent = config.description;
            }
        
            // Store config for payment processing
            modal.setAttribute('data-payment-config', JSON.stringify(config));
        },
        
        // Setup PayPal for specific amount
        setupPayPalForAmount: function(modalId, config) {
            const paypalContainerId = modalId.replace('payment-modal-', 'paypal-container-').replace('#one-off-purchase-modal', '#paypal-container-purchase');
            const paypalContainer = document.querySelector(paypalContainerId);

            if (!paypalContainer || !window.paypal || !this.config.paypal_client_id) {
                if (paypalContainer) {
                    paypalContainer.innerHTML = '<p style="text-align: center; color: #666;">PayPal is currently unavailable.</p>';
                }
                console.warn('PayPal prerequisites not met. Container, SDK, or Client ID missing.');
                return;
            }

            setTimeout(() => {
                const modal = paypalContainer.closest('.payment-modal');

                // Final check to ensure the modal is still open before we render.
                if (!modal || !modal.classList.contains('show') || !document.contains(paypalContainer)) {
                    console.warn('PayPal rendering aborted: modal was closed or container was removed.');
                    return;
                }

                paypalContainer.innerHTML = '';

                try {
                    window.paypal.Buttons({
                        createOrder: (data, actions) => {
                            return actions.order.create({
                                purchase_units: [{
                                    amount: {
                                        value: parseFloat(config.amount).toFixed(2),
                                        currency_code: 'USD'
                                    },
                                    description: config.description || config.name
                                }]
                            });
                        },
                        onApprove: (data, actions) => {
                            return actions.order.capture().then((details) => {
                                // Find the modalId again within this scope
                                const currentModalId = '#' + modal.id;
                                
                                // Process PayPal payment on server
                                this.processPayPalPayment(currentModalId, {
                                    transaction_id: details.id,
                                    payer_id: details.payer.payer_id,
                                    amount: config.amount,
                                    config: config
                                });
                            });
                        },
                        onError: (err) => {
                            this.showError('An error occurred with the PayPal transaction. Please try again or use a credit card.');
                            if (document.contains(paypalContainer)) {
                                paypalContainer.innerHTML = '<p style="text-align: center; color: #dc3545;">PayPal payment failed. Please try again.</p>';
                            }
                        }
                    }).render(paypalContainerId).then(() => {
                        // Mark container as initialized to prevent DOM clearing
                        if (paypalContainer) {
                            paypalContainer.setAttribute('data-paypal-initialized', 'true');
                        }
                    }).catch(renderError => {
                        this.showError('Could not display PayPal buttons. Please use the credit card option.');
                         if (document.contains(paypalContainer)) {
                            paypalContainer.innerHTML = '<p style="text-align: center; color: #dc3545;">Error loading PayPal. Please use credit card.</p>';
                        }
                    });
                } catch (initError) {
                    this.showError('A critical error occurred with PayPal. Please use the credit card option.');
                     if (document.contains(paypalContainer)) {
                        paypalContainer.innerHTML = '<p style="text-align: center; color: #dc3545;">Critical PayPal error. Please use credit card.</p>';
                    }
                }
            }, 250);
        },

        setupPayPalForSubscription: function(modalId, config) {
            const paypalContainerId = modalId.replace('payment-modal-', 'paypal-container-');
            const paypalContainer = document.querySelector(paypalContainerId);

            // DEBUG LOGGING
            console.log('PayPal Subscription Setup:', {
                modalId: modalId,
                paypalContainerId: paypalContainerId,
                config: config,
                paypal_plan_id: config.paypal_plan_id,
                plan_id: config.plan_id,
                billing_cycle: config.billing_cycle
            });

            if (!paypalContainer || !window.paypal || !config.paypal_plan_id) {
                const errorMsg = !config.paypal_plan_id 
                    ? 'PayPal plan is not configured for this subscription.' 
                    : 'PayPal is currently unavailable.';
                console.error('❌ PayPal Setup Failed:', {
                    paypalContainer: !!paypalContainer,
                    window_paypal: !!window.paypal,
                    paypal_plan_id: config.paypal_plan_id,
                    errorMsg: errorMsg
                });
                if (paypalContainer) {
                    paypalContainer.innerHTML = `<p style="text-align: center; color: #666;">${errorMsg}</p>`;
                }
                return;
            }

            paypalContainer.innerHTML = '';

            try {
                window.paypal.Buttons({
                    createSubscription: function(data, actions) {
                        return actions.subscription.create({
                            'plan_id': config.paypal_plan_id
                        });
                    },
                    onApprove: (data, actions) => {
                        const subscriptionID = data.subscriptionID;
                        
                        fetch(this.getPaymentEndpoint('paypal_subscription'), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                subscription_id: subscriptionID,
                                plan_id: config.plan_id
                            })
                        })
                        .then(res => res.json())
                        .then(serverData => {
                            if (serverData.success) {
                                this.handlePaymentSuccess(modalId, { config: { type: 'subscription' } });
                            } else {
                                this.showError(serverData.message || 'Failed to activate PayPal subscription.');
                            }
                        });
                    },
                    onError: (err) => {
                        console.error('💥 PayPal Subscription Error:', {
                            error: err,
                            config_used: config,
                            plan_id_sent: config.paypal_plan_id,
                            error_details: JSON.stringify(err, null, 2)
                        });
                        this.showError('An error occurred with PayPal. Please try again.');
                    }
                }).render(paypalContainerId).then(() => {
                    // Mark container as initialized to prevent DOM clearing
                    if (paypalContainer) {
                        paypalContainer.setAttribute('data-paypal-initialized', 'true');
                    }
                });

            } catch (initError) {
                this.showError('A critical error occurred with PayPal.');
            }
        },
        
        // Process Stripe payment
        processStripePayment: async function(modalId, config) {
            const modal = document.querySelector(modalId);
            if (!modal) return;
            
            // Prevent duplicate submissions
            if (modal.dataset.processing && modal.dataset.processing !== 'false') {
                return;
            }
            modal.dataset.processing = 'true';
            
            // Find the unique card element for this modal
            const cardElementSelector = modalId + ' [id^="card-element-"]';
            const cardElement = this.cardElements[document.querySelector(cardElementSelector).id];
            
            // Find the unique name input for this modal
            const nameInputSelector = modalId + ' [id^="card-name-"]';
            const nameInput = document.querySelector(nameInputSelector);
            
            if (!cardElement || !nameInput) {
                this.showError('Payment form not properly initialized');
                return;
            }
            
            const submitButton = modal.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
            }
        
            try {
                // Step 1: Create a PaymentMethod
                const { paymentMethod, error } = await this.stripe.createPaymentMethod({
                    type: 'card',
                    card: cardElement,
                    billing_details: {
                        name: nameInput.value,
                    },
                });
        
                if (error) {
                    this.showError(error.message);
                    this.showButtonError(modalId);
                    return;
                }
        
                const paymentData = {
                    payment_method_id: paymentMethod.id,
                    plan_id: config.plan_id,
                    billing_cycle: config.billing_cycle,
                    nonce: config.nonce || window.sud_premium_config?.subscription_nonce || '',
                };
        
                if (config.type !== 'subscription') {
                    paymentData.amount = config.amount;
                    paymentData.description = config.description;
                    paymentData.item_type = config.type;
                    paymentData.order_uid = config.order_uid;
                    paymentData.item_key = config.boost_type || config.package_type;
                    
                    // Get nonce from config first, then fallback to page-specific nonces
                    paymentData.nonce = config.nonce || 
                                       window.sud_swipe_page_config?.payment_nonce || 
                                       window.sud_wallet_config?.payment_nonce || '';
                    
                    // Add coin-specific data
                    if (config.type === 'coins') {
                        paymentData.coin_amount = config.coin_amount;
                        paymentData.order_uid = config.order_uid;
                    }

                    this.sendOneOffPaymentToServer(modalId, paymentData, config);
                } else {
                    const serverResponse = await this.sendSubscriptionToServer(paymentData);
                    this.handleServerResponse(serverResponse, modalId);
                }
        
            } catch (e) {
                this.showError('An unexpected error occurred. Please try again.');
                this.showButtonError(modalId);
            }
        },

        sendSubscriptionToServer: async function(paymentData) {
            const endpoint = this.getPaymentEndpoint('subscription');
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(paymentData)
            });
        
            return await response.json();
        },
        
        handleServerResponse: async function(response, modalId) {
            if (response.requires_action) {
                // 3D Secure is required, prompt user for authentication
                const { error } = await this.stripe.confirmCardPayment(response.payment_intent_client_secret);
                if (error) {
                    this.showError(error.message);
                    this.showButtonError(modalId);
                } else {
                    // Authentication successful
                    this.handlePaymentSuccess(modalId, { config: { type: 'subscription' } });
                }
            } else if (response.success) {
                // Subscription created successfully without 3D Secure
                this.handlePaymentSuccess(modalId, { config: { type: 'subscription' } });
            } else {
                // Generic error - prioritize server error message
                let errorMessage = 'Payment failed on the server.'; // fallback
                
                if (response.message) {
                    errorMessage = response.message;
                } else if (response.data && response.data.message) {
                    errorMessage = response.data.message;
                } else if (response.error) {
                    errorMessage = response.error;
                }
                
                this.showError(errorMessage);
                this.showButtonError(modalId);
            }
        },
        
        // Send payment data to server
        sendOneOffPaymentToServer: function(modalId, paymentData, config) {
            const endpoint = this.getPaymentEndpoint(config.type);
            
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(paymentData)
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // Server returned HTML (probably an error page)
                    throw new Error('Server returned an unexpected response format. Please check server configuration.');
                }
            })
            .then(data => {
                if (data.success) {
                    this.handlePaymentSuccess(modalId, { config, serverResponse: data });
                } else {
                    // Prioritize server error message
                    let errorMessage = 'Payment failed';
                    if (data.message) {
                        errorMessage = data.message;
                    } else if (data.data && data.data.message) {
                        errorMessage = data.data.message;
                    } else if (data.error) {
                        errorMessage = data.error;
                    }
                    
                    this.showError(errorMessage);
                    this.showButtonError(modalId);
                }
            })
            .catch(error => {
                console.error('Payment error:', error);
                this.showError('Payment processing failed. Please try again.');
                this.showButtonError(modalId);
            });
        },
        
        // Process PayPal payment on server
        processPayPalPayment: function(modalId, paymentData) {
            const endpoint = this.getPaymentEndpoint(paymentData.config.type);
            
            const serverData = {
                payment_method: 'paypal',
                transaction_id: paymentData.transaction_id,
                payer_id: paymentData.payer_id,
                amount: paymentData.amount,
                item_type: paymentData.config.type,
                item_key: paymentData.config.boost_type || paymentData.config.package_type,
                description: paymentData.config.description,
                order_uid: paymentData.config.order_uid,
                nonce: paymentData.config.nonce || window.sud_wallet_config?.payment_nonce || window.sud_swipe_page_config?.payment_nonce || ''
            };
            
            // Add coin-specific data
            if (paymentData.config.type === 'coins') {
                serverData.coin_amount = paymentData.config.coin_amount;
            }
            
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(serverData)
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    throw new Error('Server returned an unexpected response format.');
                }
            })
            .then(data => {
                if (data.success) {
                    this.handlePaymentSuccess(modalId, { config: paymentData.config, serverResponse: data });
                } else {
                    // Prioritize server error message for PayPal too
                    let errorMessage = 'PayPal payment processing failed';
                    if (data.message) {
                        errorMessage = data.message;
                    } else if (data.data && data.data.message) {
                        errorMessage = data.data.message;
                    } else if (data.error) {
                        errorMessage = data.error;
                    }
                    
                    this.showError(errorMessage);
                    this.showButtonError(modalId);
                }
            })
            .catch(error => {
                this.showError('PayPal payment processing failed. Please try again.');
                this.showButtonError(modalId);
            });
        },
        
        // Get appropriate endpoint based on payment type
        getPaymentEndpoint: function(type) {
            const baseUrl = window.sud_config?.ajax_url || window.sud_config?.sud_url + '/ajax' || '/wordpress/sud/ajax';
            
            switch(type) {
                case 'subscription':
                    return `${baseUrl}/process-subscription.php`; // For Stripe
                case 'paypal_subscription':
                    return `${baseUrl}/record-paypal-subscription.php`; // For PayPal
                case 'coins':
                    return `${baseUrl}/process-coin-purchase.php`;
                case 'boost':
                case 'swipe-up':
                default:
                    return `${baseUrl}/process-payment.php`;
            }
        },
        
        // Handle successful payment
        handlePaymentSuccess: function(modalId, paymentData) {
            const modal = document.querySelector(modalId);
            if (modal) {
                modal.dataset.processing = 'done'; // Mark as done to prevent any retries until reload
            }
            
            // Show success state on button
            const submitButton = modal?.querySelector('.btn-confirm');
            if (submitButton) {
                submitButton.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> Done';
                submitButton.classList.add('btn-success');
                submitButton.disabled = true;
            }
            
            // Show success toast with specific notification type
            let successMessage = 'Purchase successful!';
            let notificationType = 'success';
            
            if (paymentData.config.type === 'boost') {
                successMessage = `${paymentData.config.name} boost activated! Your profile is now more visible.`;
                notificationType = 'boost_purchased';
            } else if (paymentData.config.type === 'swipe-up') {
                successMessage = `${paymentData.config.name} purchased! Check your balance.`;
                notificationType = 'super_swipe_purchased';
            } else if (paymentData.config.type === 'coins') {
                successMessage = 'Coins purchased successfully!';
                notificationType = 'success'; // Use generic success instead of coins_purchased to avoid duplicate
            }
            
            this.showSuccess(successMessage, notificationType);
            
            // Show success animation
            this.showSuccessAnimation();
            
            // Update UI immediately, then close modal after delay
            if (paymentData.config.type === 'subscription') {
                // For subscriptions, just reload after delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                // Update balance or other UI elements immediately
                this.updateUIAfterPayment(paymentData.config, paymentData.serverResponse);
                
                // Close modal after delay and refresh page to ensure all updates
                setTimeout(() => {
                    if (modal) {
                        this.clearModalData(modal);
                        modal.classList.remove('show');
                    }
                    // Refresh page to ensure all balances are updated
                    window.location.reload();
                }, 2000);
            }
        },
        
        // Show success animation
        showSuccessAnimation: function() {
            const successPopup = document.querySelector('#sud-payment-success-popup');
            if (successPopup) {
                successPopup.style.display = 'flex';
                successPopup.classList.add('show');
                setTimeout(() => {
                    successPopup.classList.remove('show');
                    setTimeout(() => {
                        successPopup.style.display = 'none';
                    }, 300); // Wait for transition to complete
                }, 3000);
            }
        },
        
        // Update UI after payment
        updateUIAfterPayment: function(config, serverResponse) {
            if (config.type === 'coins') {
                // Update coin balance if displayed
                if (serverResponse && serverResponse.new_coin_balance) {
                    const balanceElements = document.querySelectorAll('.balance-amount, [data-coin-balance]');
                    balanceElements.forEach(el => {
                        el.textContent = parseInt(serverResponse.new_coin_balance).toLocaleString();
                    });
                    // Also trigger a custom event for other systems to listen to
                    window.dispatchEvent(new CustomEvent('coinBalanceUpdated', {
                        detail: {
                            newBalance: serverResponse.new_coin_balance,
                            amountAdded: serverResponse.amount_added
                        }
                    }));
                }
            } else if (config.type === 'swipe-up') {
                // Update swipe-up balance in real-time
                if (serverResponse && serverResponse.new_swipe_up_balance !== null) {
                    const balanceElements = document.querySelectorAll('[data-swipe-up-balance], .swipe-up-balance');
                    balanceElements.forEach(el => {
                        el.textContent = serverResponse.new_swipe_up_balance;
                    });
                    
                    // Update global config if available
                    if (window.sud_swipe_page_config) {
                        window.sud_swipe_page_config.swipe_up_balance = serverResponse.new_swipe_up_balance;
                    }
                    
                    // Trigger a custom event for other systems to listen to
                    window.dispatchEvent(new CustomEvent('swipeUpBalanceUpdated', {
                        detail: {
                            newBalance: serverResponse.new_swipe_up_balance
                        }
                    }));
                }
            } else if (config.type === 'boost') {
                // Show boost status
                if (serverResponse && serverResponse.boost_active) {
                    // You could add UI elements to show active boost status
                    const boostIndicator = document.querySelector('.boost-indicator');
                    if (boostIndicator) {
                        boostIndicator.innerHTML = `<i class="fas fa-rocket"></i> ${serverResponse.boost_name} Active`;
                        boostIndicator.style.display = 'block';
                    }
                }
            }
        },
        
        // Reset submit button
        resetSubmitButton: function(modalId, type) {
            const modal = document.querySelector(modalId);
            const submitButton = modal?.querySelector('.btn-confirm');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = type === 'subscription' ? 'Start Subscription' : 'Complete Purchase';
                submitButton.classList.remove('btn-success', 'btn-error');
            }
        },
        
        // Show error state on button
        showButtonError: function(modalId) {
            const modal = document.querySelector(modalId);
            if (modal) {
                modal.dataset.processing = 'false'; // Clear processing flag on error
            }
            const submitButton = modal?.querySelector('.btn-confirm');
            if (submitButton) {
                submitButton.innerHTML = '<i class="fas fa-times-circle" style="color: #dc3545;"></i> Failed';
                submitButton.classList.add('btn-error');
                submitButton.disabled = true;
                
                // Reset button after 3 seconds
                setTimeout(() => {
                    if (submitButton.classList.contains('btn-error')) {
                        this.resetSubmitButton(modalId, modal.id.includes('premium') ? 'subscription' : 'purchase');
                    }
                }, 3000);
            }
        },
        
        // Show error message
        showError: function(message) {
            if (window.SUD && window.SUD.showToast) {
                window.SUD.showToast('error', 'Payment Error', message);
            } else {
                alert(message);
            }
        },
        
        // Show success message
        showSuccess: function(message, type = 'success') {
            if (window.SUD && window.SUD.showToast) {
                window.SUD.showToast(type, 'Payment Success', message);
            } else {
                alert(message);
            }
        },
        
        // Bind events
        bindEvents: function() {
            // Payment tab switching
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('payment-tab')) {
                    this.handleTabSwitch(e.target);
                }
            });
            
            // Form submissions
            document.addEventListener('submit', (e) => {
                if (e.target.id.includes('card-payment-form')) {
                    e.preventDefault();
                    const modalId = '#' + e.target.closest('.payment-modal').id;
                    const configData = e.target.closest('.payment-modal').getAttribute('data-payment-config');
                    const config = configData ? JSON.parse(configData) : {};
                    this.processStripePayment(modalId, config);
                }
            });
            
            // Modal close events
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('close-modal')) {
                    const modal = e.target.closest('.payment-modal');
                    if (modal) {
                        this.clearModalData(modal);
                        modal.classList.remove('show');
                    }
                }
            });
        },
        
        // Clear modal data when closed
        clearModalData: function(modal) {
            // Clear processing flag
            modal.dataset.processing = 'false';
            
            // Clear form data
            const forms = modal.querySelectorAll('form');
            forms.forEach(form => form.reset());
            
            // Clear card errors
            const errorElements = modal.querySelectorAll('[id*="card-errors"]');
            errorElements.forEach(el => el.textContent = '');
            
            // Reset submit buttons
            const submitButtons = modal.querySelectorAll('.btn-confirm');
            submitButtons.forEach(button => {
                button.disabled = false;
                button.innerHTML = button.closest('#card-section-premium') ? 'Start Subscription' : 'Complete Purchase';
                button.classList.remove('btn-success', 'btn-error');
            });
            
            // Clear PayPal containers safely
            const paypalContainers = modal.querySelectorAll('[id*="paypal-container"]');
            paypalContainers.forEach(container => {
                // Don't clear innerHTML if PayPal buttons are active - let PayPal SDK handle cleanup
                if (container.children.length > 0 && !container.hasAttribute('data-paypal-initialized')) {
                    container.innerHTML = '';
                } else if (container.hasAttribute('data-paypal-initialized')) {
                    // Mark for re-initialization next time
                    container.removeAttribute('data-paypal-initialized');
                }
            });
            
            // Reset to card tab
            const cardTab = modal.querySelector('.payment-tab[data-tab="card"]');
            const paypalTab = modal.querySelector('.payment-tab[data-tab="paypal"]');
            const cardSection = modal.querySelector('[id*="card-section"]');
            const paypalSection = modal.querySelector('[id*="paypal-section"]');
            
            if (cardTab && paypalTab && cardSection && paypalSection) {
                cardTab.classList.add('active');
                paypalTab.classList.remove('active');
                cardSection.classList.add('active');
                paypalSection.classList.remove('active');
            }
            
            // Remove payment config
            modal.removeAttribute('data-payment-config');
            
            // Destroy existing Stripe elements for this modal
            const modalId = '#' + modal.id;
            const cardElementSelector = modalId + ' [id^="card-element-"]';
            const cardElementDiv = document.querySelector(cardElementSelector);
            if (cardElementDiv && this.cardElements[cardElementDiv.id]) {
                this.cardElements[cardElementDiv.id].destroy();
                delete this.cardElements[cardElementDiv.id];
            }
        },
        
        // Handle payment tab switching
        handleTabSwitch: function(tabElement) {
            const modal = tabElement.closest('.payment-modal');
            if (!modal) return;
            
            const tabType = tabElement.getAttribute('data-tab');
            if (tabElement.classList.contains('active')) return;
        
            modal.querySelectorAll('.payment-tab').forEach(tab => tab.classList.remove('active'));
            modal.querySelectorAll('.payment-section').forEach(section => section.classList.remove('active'));
            
            tabElement.classList.add('active');
        
            const section = modal.querySelector(`[id^="${tabType}-section-"]`);
            if (section) {
                section.classList.add('active');
            }
        
            if (tabType === 'paypal') {
                const configData = modal.getAttribute('data-payment-config');
                const config = configData ? JSON.parse(configData) : {};
                
                if (config.type === 'subscription') {
                    // Call the new subscription function
                    this.setupPayPalForSubscription('#' + modal.id, config);
                } else if (config.amount && window.paypal) {
                    // Call the existing one-off payment function
                    this.setupPayPalForAmount('#' + modal.id, config);
                }
            }
        },
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            SUDUnifiedPayment.init();
        });
    } else {
        SUDUnifiedPayment.init();
    }
    
    // Helper functions for easy integration
    window.showPaymentModal = function(type, config) {
        let modalId;
        
        switch(type) {
            case 'subscription':
                modalId = '#payment-modal-premium';
                break;
            case 'coins':
                modalId = '#payment-modal-wallet';
                break;
            case 'boost':
            case 'swipe-up':
            default:
                modalId = '#one-off-purchase-modal';
                break;
        }
        
        SUDUnifiedPayment.showPaymentModal(modalId, config);
    };
    
})();