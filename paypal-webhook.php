<?php

require_once(dirname(__FILE__, 2) . '/wp-load.php');
require_once(dirname(__FILE__) . '/includes/mailer.php');

$payload = file_get_contents('php://input');
$data = json_decode($payload);

if (!$data || !isset($data->event_type)) {
    http_response_code(400);
    exit('Invalid payload.');
}

$headers = getallheaders();
$payment_settings = get_option('sud_payment_settings');

$paypal_webhook_id = sud_is_test_mode()
    ? ($payment_settings['paypal_test_webhook_id'] ?? '')
    : ($payment_settings['paypal_live_webhook_id'] ?? '');

$transmission_id = $headers['Paypal-Transmission-Id'] ?? '';
$transmission_time = $headers['Paypal-Transmission-Time'] ?? '';
$cert_url = $headers['Paypal-Cert-Url'] ?? '';
$auth_algo = $headers['Paypal-Auth-Algo'] ?? '';
$transmission_sig = $headers['Paypal-Transmission-Sig'] ?? '';

$is_verified = false;
if ($paypal_webhook_id && $transmission_id && $transmission_time && $cert_url && $auth_algo && $transmission_sig) {
    
    $webhook_verify_url = sud_is_test_mode() 
        ? 'https://api.sandbox.paypal.com/v1/notifications/verify-webhook-signature'
        : 'https://api.paypal.com/v1/notifications/verify-webhook-signature';
    
    $verify_data = [
        'transmission_id' => $transmission_id,
        'transmission_time' => $transmission_time,
        'cert_url' => $cert_url,
        'auth_algo' => $auth_algo,
        'transmission_sig' => $transmission_sig,
        'webhook_id' => $paypal_webhook_id,
        'webhook_event' => json_decode($payload, true)
    ];
    
    $client_id = sud_get_paypal_client_id();
    $secret = sud_get_paypal_secret_key();
    
    if ($client_id && $secret) {
        // Get access token using client credentials
        $token_url = sud_is_test_mode()
            ? 'https://api.sandbox.paypal.com/v1/oauth2/token'
            : 'https://api.paypal.com/v1/oauth2/token';
        
        $token_ch = curl_init();
        curl_setopt($token_ch, CURLOPT_URL, $token_url);
        curl_setopt($token_ch, CURLOPT_POST, true);
        curl_setopt($token_ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($token_ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US',
            'Authorization: Basic ' . base64_encode($client_id . ':' . $secret)
        ]);
        curl_setopt($token_ch, CURLOPT_RETURNTRANSFER, true);
        
        $token_response = curl_exec($token_ch);
        $token_http_code = curl_getinfo($token_ch, CURLINFO_HTTP_CODE);
        curl_close($token_ch);
        
        if ($token_http_code === 200) {
            $token_data = json_decode($token_response, true);
            $access_token = $token_data['access_token'] ?? '';
        } else {
            $access_token = '';
        }
        
        if ($access_token) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $webhook_verify_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verify_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $response_data = json_decode($response, true);
                $is_verified = ($response_data['verification_status'] ?? '') === 'SUCCESS';
            }
        }
    }
}

if (!$is_verified) {
    http_response_code(401);
    error_log("PayPal Webhook: Verification failed. Webhook ID or headers missing/invalid.");
    exit('Verification failed.');
}

$event_type = $data->event_type;
$resource = $data->resource;

switch ($event_type) {

    case 'BILLING.SUBSCRIPTION.PAYMENT.COMPLETED':
        $subscription_id = $resource->billing_agreement_id ?? null;
        if ($subscription_id) {

            $users = get_users([
                'meta_key' => 'subscription_id',
                'meta_value' => $subscription_id,
                'number' => 1,
                'fields' => 'ID',
            ]);
            $user_id = !empty($users) ? $users[0] : 0;

            if ($user_id > 0) {

                $current_expiry_str = get_user_meta($user_id, 'subscription_expires', true);
                $billing_cycle = get_user_meta($user_id, 'subscription_billing_type', true) ?: 'monthly';

                $new_expiry = new DateTime($current_expiry_str ?: 'now');

                if ($billing_cycle === 'annual') {
                    $new_expiry->modify('+1 year');
                } else {
                    $new_expiry->modify('+1 month');
                }

                update_user_meta($user_id, 'subscription_expires', $new_expiry->format('Y-m-d H:i:s'));
                
                // Handle auto-verification for Diamond tier renewals
                if (function_exists('sud_update_verification_based_on_plan')) {
                    sud_update_verification_based_on_plan($user_id);
                }
                
                // Send admin notification for PayPal subscription renewal
                $user = get_userdata($user_id);
                if ($user) {
                    $plan_id = get_user_meta($user_id, 'premium_plan', true) ?: 'premium';
                    $amount = ($resource->amount->value ?? '0.00');
                    
                    $transaction_data = [
                        'type' => 'subscription_renewal',
                        'user_id' => $user_id,
                        'amount' => $amount,
                        'item_name' => ucfirst($plan_id) . ' Plan (PayPal Renewal)',
                        'payment_method' => 'PayPal',
                        'transaction_id' => $subscription_id
                    ];
                    
                    send_admin_payment_notification($transaction_data);
                    
                    // Track PayPal plan renewal conversion with TrafficJunky
                    if (function_exists('track_plan_purchase_conversion')) {
                        track_plan_purchase_conversion($user_id, $plan_id, $subscription_id, [
                            'plan_name' => ucfirst($plan_id) . ' Plan',
                            'billing_cycle' => 'paypal_renewal',
                            'amount' => $amount,
                            'payment_method' => 'paypal_webhook'
                        ]);
                    }
                }
            }
        }
        break;

    case 'BILLING.SUBSCRIPTION.CANCELLED':
        $subscription_id = $resource->id ?? null;
        if ($subscription_id) {
            $users = get_users([
                'meta_key' => 'subscription_id',
                'meta_value' => $subscription_id,
                'number' => 1,
                'fields' => 'ID',
            ]);
            $user_id = !empty($users) ? $users[0] : 0;

            if ($user_id > 0) {
                // Get the old plan before changing it for verification handling
                $old_plan_id = get_user_meta($user_id, 'premium_plan', true);
                
                update_user_meta($user_id, 'premium_plan', 'free');
                delete_user_meta($user_id, 'subscription_id');
                delete_user_meta($user_id, 'subscription_expires');
                update_user_meta($user_id, 'subscription_auto_renew', '0');
                
                // Handle verification removal when downgrading from Diamond
                if (function_exists('sud_handle_plan_verification_update')) {
                    sud_handle_plan_verification_update($user_id, 'free', $old_plan_id);
                }
                
                // Send admin notification for PayPal subscription cancellation
                $user = get_userdata($user_id);
                if ($user && function_exists('send_admin_subscription_cancellation_notification')) {
                    $plan_id = get_user_meta($user_id, 'premium_plan', true) ?: 'premium';
                    
                    $cancellation_data = [
                        'user_id' => $user_id,
                        'user_name' => $user->display_name,
                        'user_email' => $user->user_email,
                        'plan_name' => ucfirst($plan_id) . ' Plan',
                        'subscription_id' => $subscription_id,
                        'cancellation_date' => current_time('mysql'),
                        'payment_method' => 'PayPal'
                    ];
                    
                    send_admin_subscription_cancellation_notification($cancellation_data);
                }
            }
        }
        break;
}

http_response_code(200);
echo 'Event received.';