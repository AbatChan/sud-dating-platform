<?php

function sud_get_google_api_key() {
    $options = get_option('sud_payment_settings');
    return isset($options['google_api_key']) ? trim($options['google_api_key']) : '';
}

if (!function_exists('sud_get_paypal_client_id')) {
    function sud_get_paypal_client_id() {
        $options = get_option('sud_payment_settings');
        $test_mode = isset($options['test_mode']) ? (bool)$options['test_mode'] : false;
        $live_id = isset($options['paypal_live_client_id']) ? trim($options['paypal_live_client_id']) : '';
        $test_id = isset($options['paypal_test_client_id']) ? trim($options['paypal_test_client_id']) : '';

        if ($test_mode) {
            return $test_id;
        } else {
            return $live_id;
        }
    }
}

if (!function_exists('sud_get_paypal_secret_key')) {
    function sud_get_paypal_secret_key() {
        $options = get_option('sud_payment_settings');
        $test_mode = isset($options['test_mode']) ? (bool)$options['test_mode'] : false;
        $live_secret = isset($options['paypal_live_secret_key']) ? trim($options['paypal_live_secret_key']) : '';
        $test_secret = isset($options['paypal_test_secret_key']) ? trim($options['paypal_test_secret_key']) : '';

        if ($test_mode) {
            return $test_secret;
        } else {
            return $live_secret;
        }
    }
}

if (!function_exists('sud_get_stripe_publishable_key')) {
    function sud_get_stripe_publishable_key() {
        $options = get_option('sud_payment_settings');
        $test_mode = isset($options['test_mode']) ? (bool)$options['test_mode'] : false;
        $live_key = isset($options['stripe_live_publishable_key']) ? trim($options['stripe_live_publishable_key']) : '';
        $test_key = isset($options['stripe_test_publishable_key']) ? trim($options['stripe_test_publishable_key']) : '';
        
        if ($test_mode) {
            return $test_key;
        } else {
            return $live_key;
        }
    }
}

if (!function_exists('sud_get_stripe_secret_key')) {
    function sud_get_stripe_secret_key() {
        $options = get_option('sud_payment_settings');
        $test_mode = isset($options['test_mode']) ? (bool)$options['test_mode'] : false;
        $live_key = isset($options['stripe_live_secret_key']) ? trim($options['stripe_live_secret_key']) : '';
        $test_key = isset($options['stripe_test_secret_key']) ? trim($options['stripe_test_secret_key']) : '';
        
        if ($test_mode) {
            return $test_key;
        } else {
            return $live_key;
        }
    }
}

if (!function_exists('sud_is_test_mode')) {
    function sud_is_test_mode() {
        $options = get_option('sud_payment_settings');
        return isset($options['test_mode']) ? (bool)$options['test_mode'] : false;
    }
}

if (!function_exists('sud_init_stripe_ssl_fix')) {
    function sud_init_stripe_ssl_fix() {
        // Fix for XAMPP/macOS SSL certificate verification issues
        if (class_exists('\\Stripe\\Stripe')) {
            // Ensure we have the updated CA bundle
            $stripe_ca_path = dirname(__FILE__, 2) . '/vendor/stripe/stripe-php/data/ca-certificates.crt';
            
            if (file_exists($stripe_ca_path)) {
                \Stripe\Stripe::setCABundlePath($stripe_ca_path);
                \Stripe\Stripe::setVerifySslCerts(true);
                
                // Additional cURL options for better SSL handling on macOS/XAMPP
                if (function_exists('curl_version')) {
                    $curl_info = curl_version();
                    // If using SecureTransport (macOS default), set additional options
                    if (strpos($curl_info['ssl_version'], 'SecureTransport') !== false) {
                        add_filter('http_api_curl', function($curl_handle) {
                            curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, true);
                            curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 2);
                            curl_setopt($curl_handle, CURLOPT_CAINFO, dirname(__FILE__, 2) . '/vendor/stripe/stripe-php/data/ca-certificates.crt');
                            return $curl_handle;
                        });
                    }
                }
                
                error_log("Stripe SSL Fix: Using CA bundle at " . $stripe_ca_path);
            } else {
                error_log("Stripe SSL Fix: CA bundle not found at " . $stripe_ca_path);
            }
        }
    }
}

if (!function_exists('sud_cancel_paypal_subscription')) {
    function sud_cancel_paypal_subscription($subscription_id) {
        $client_id = sud_get_paypal_client_id();
        $secret = sud_get_paypal_secret_key();
        $test_mode = sud_is_test_mode();
        
        if (empty($client_id) || empty($secret)) {
            throw new Exception('PayPal API credentials not configured');
        }
        
        // PayPal API endpoint
        $base_url = $test_mode ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        
        try {
            // Get access token
            $token_response = wp_remote_post($base_url . '/v1/oauth2/token', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                    'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $secret)
                ],
                'body' => 'grant_type=client_credentials'
            ]);
            
            if (is_wp_error($token_response)) {
                throw new Exception('PayPal token request failed: ' . $token_response->get_error_message());
            }
            
            $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
            $access_token = $token_data['access_token'] ?? null;
            
            if (!$access_token) {
                throw new Exception('Failed to get PayPal access token');
            }
            
            // Cancel subscription
            $cancel_response = wp_remote_post($base_url . '/v1/billing/subscriptions/' . $subscription_id . '/cancel', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json',
                    'PayPal-Request-Id' => uniqid()
                ],
                'body' => json_encode([
                    'reason' => 'User requested cancellation'
                ])
            ]);
            
            if (is_wp_error($cancel_response)) {
                throw new Exception('PayPal cancellation request failed: ' . $cancel_response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($cancel_response);
            if ($response_code !== 204) {
                $error_body = wp_remote_retrieve_body($cancel_response);
                throw new Exception('PayPal cancellation failed with code ' . $response_code . ': ' . $error_body);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('PayPal cancellation error: ' . $e->getMessage());
            throw $e;
        }
    }
}

/**
 * Convert Stripe error codes to user-friendly messages
 * 
 * @param \Exception $e The Stripe exception
 * @return string User-friendly error message
 */
function sud_get_user_friendly_stripe_error($e) {
    // Map of Stripe error codes to user-friendly messages
    $error_map = [
        // Card issues
        'insufficient_funds'        => 'Your card doesn\'t have enough funds. Try a different card or contact your bank.',
        'card_declined'             => 'Your bank declined this card. Please try another card.',
        'incorrect_cvc'             => 'The CVC code is incorrect. Double-check and try again.',
        'expired_card'              => 'This card is expired. Use a different one.',
        'processing_error'          => 'The bank couldn\'t process the charge. Try again or use another card.',
        'authentication_required'   => 'This card requires extra authentication. Please complete the verification step.',
        'do_not_honor'              => 'Your bank didn\'t approve the charge. Call them or try another card.',
        'pickup_card'               => 'This card cannot be used. Please contact your bank.',
        'lost_card'                 => 'This card has been reported lost. Use a different one.',
        'stolen_card'               => 'This card has been reported stolen. Use a different one.',
        'transaction_not_allowed'   => 'This transaction type isn\'t allowed on your card.',
        'currency_not_supported'    => 'This card doesn\'t support the transaction currency.',
        'duplicate_transaction'     => 'This appears to be a duplicate transaction. Please wait before trying again.',
        
        // Card details issues
        'incorrect_number'          => 'The card number looks wrong. Check it and try again.',
        'invalid_number'            => 'The card number is invalid. Please check and try again.',
        'invalid_expiry_month'      => 'The expiration month is invalid.',
        'invalid_expiry_year'       => 'The expiration year is invalid.',
        'invalid_cvc'               => 'The CVC code is invalid.',
        'postal_code_invalid'       => 'The ZIP/Postal code didn\'t match your card. Fix it and try again.',
        'address_verification_failed' => 'Your billing address doesn\'t match your card. Please check and try again.',
        
        // Amount issues
        'amount_too_large'          => 'The amount is too high for your card. Try a smaller amount or different card.',
        'amount_too_small'          => 'The amount is too small for this card. Try another card.',
        'balance_insufficient'      => 'Your account balance is insufficient for this transaction.',
        
        // Rate limiting and fraud
        'rate_limit'                => 'Too many attempts. Please wait a moment and try again.',
        'testmode_charges_only'     => 'This card can only be used in test mode.',
        'live_mode_test_card'       => 'This appears to be a test card. Please use a real card.',
        'card_velocity_exceeded'    => 'You\'ve exceeded the number of allowed attempts. Please try again later.',
        
        // Account and setup issues
        'account_already_exists'    => 'An account with this information already exists.',
        'account_country_invalid_address' => 'The address provided is not valid for this country.',
        'instant_payouts_unsupported' => 'Instant payouts are not supported for this account.',
        'bank_account_restricted'   => 'This bank account has restrictions. Please contact your bank.',
        'platform_account_required' => 'A platform account is required for this transaction.',
        
        // Subscription specific
        'subscription_pending_invoice_item_interval' => 'Your subscription has pending changes that prevent this action.',
        'invoice_no_customer_line_items' => 'Unable to create invoice without customer information.',
        'invoice_no_subscription_line_items' => 'Unable to create invoice without subscription details.',
        
        // Generic API errors
        'api_key_expired'           => 'Payment system configuration issue. Please contact support.',
        'invalid_request_error'     => 'There was an issue with your payment request. Please try again.',
        'idempotency_error'         => 'This request conflicts with a previous one. Please try again.',
        'resource_missing'          => 'The requested payment resource was not found.',
        
        // Network issues
        'connection_error'          => 'Connection issue occurred. Please check your internet and try again.',
        'invalid_grant'             => 'Authentication failed. Please refresh the page and try again.',
        
        // Webhooks and events
        'webhook_endpoint_unreachable' => 'Unable to deliver payment confirmation. Your payment may still have succeeded.',
        
        // Generic fallbacks
        'generic_decline'           => 'Your card was declined. Please try another card or contact your bank.',
        'unknown_error'             => 'Something went wrong with the payment. Try again or contact support.',
        'try_again_later'           => 'Payment processing is temporarily unavailable. Please try again in a few minutes.',
    ];
    
    $error_code = null;
    $error_message = $e->getMessage();
    
    // Extract Stripe error code
    if ($e instanceof \Stripe\Exception\CardException) {
        $error_code = $e->getStripeCode();
    } elseif ($e instanceof \Stripe\Exception\InvalidRequestException) {
        $error_code = $e->getStripeCode();
    } elseif ($e instanceof \Stripe\Exception\AuthenticationException) {
        return 'Payment system authentication issue. Please contact support.';
    } elseif ($e instanceof \Stripe\Exception\ApiConnectionException) {
        return 'Unable to connect to payment processor. Please check your internet connection and try again.';
    } elseif ($e instanceof \Stripe\Exception\ApiErrorException) {
        $error_code = $e->getStripeCode();
    } elseif ($e instanceof \Stripe\Exception\RateLimitException) {
        return $error_map['rate_limit'];
    }
    
    // Check for specific error codes in the message if no Stripe code
    if (!$error_code) {
        foreach ($error_map as $code => $message) {
            if (stripos($error_message, $code) !== false) {
                $error_code = $code;
                break;
            }
        }
    }
    
    // Return mapped message or fallback
    if ($error_code && isset($error_map[$error_code])) {
        return $error_map[$error_code];
    }
    
    // Special handling for common phrases in error messages
    $error_lower = strtolower($error_message);
    
    if (strpos($error_lower, 'insufficient') !== false || strpos($error_lower, 'funds') !== false) {
        return $error_map['insufficient_funds'];
    } elseif (strpos($error_lower, 'declined') !== false || strpos($error_lower, 'decline') !== false) {
        return $error_map['card_declined'];
    } elseif (strpos($error_lower, 'expired') !== false) {
        return $error_map['expired_card'];
    } elseif (strpos($error_lower, 'cvc') !== false || strpos($error_lower, 'security code') !== false || strpos($error_lower, 'incorrect') !== false) {
        return $error_map['incorrect_cvc'];
    } elseif (strpos($error_lower, 'postal') !== false || strpos($error_lower, 'zip') !== false) {
        return $error_map['postal_code_invalid'];
    } elseif (strpos($error_lower, 'authentication') !== false) {
        return $error_map['authentication_required'];
    } elseif (strpos($error_lower, 'rate limit') !== false || strpos($error_lower, 'too many') !== false) {
        return $error_map['rate_limit'];
    }
    
    // Log for debugging
    error_log("SUD Error Mapping Debug - Original: " . $error_message . " | Code: " . ($error_code ?? 'none') . " | Class: " . get_class($e));
    
    // Final fallback
    return $error_map['unknown_error'];
}