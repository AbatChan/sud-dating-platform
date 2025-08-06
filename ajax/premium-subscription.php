<?php

require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/ajax-security.php');

try {
    // Use centralized security verification
    $user_id = sud_verify_ajax([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_ajax_action',
        'rate_limit' => ['requests' => 5, 'window' => 300, 'action' => 'premium_subscription'] // 5 subscriptions per 5 minutes
    ]);
    $plan_id = isset($_POST['plan_id']) ? sanitize_text_field($_POST['plan_id']) : '';
    $billing_type = isset($_POST['billing_type']) ? sanitize_text_field($_POST['billing_type']) : 'monthly';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : 'card';

    if (!array_key_exists($plan_id, SUD_PREMIUM_CAPABILITIES) || $plan_id === 'free') {
        throw new Exception('Invalid plan selected');
    }

    $payment_success = false;
    $transaction_id = '';

    $payment_settings = get_option('sud_payment_settings');
    $test_mode = isset($payment_settings['test_mode']) ? (bool)$payment_settings['test_mode'] : false;

    if ($payment_method === 'card') {
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        if (empty($token)) {
            throw new Exception('Invalid payment information');
        }

    $api_key = $test_mode ? 
        (isset($payment_settings['stripe_test_secret_key']) ? $payment_settings['stripe_test_secret_key'] : '') : 
        (isset($payment_settings['stripe_live_secret_key']) ? $payment_settings['stripe_live_secret_key'] : '');

        if (empty($api_key)) {
            throw new Exception('Payment gateway not configured');
        }

    if (!class_exists('\Stripe\Stripe')) {
        require_once(dirname(__FILE__, 2) . '/vendor/stripe/stripe-php/init.php');
    }

    try {
        \Stripe\Stripe::setApiKey($api_key);
        $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);

        if (empty($stripe_customer_id)) {
            $user = get_userdata($user_id);
            $customer = \Stripe\Customer::create([
                'email' => $user->user_email,
                'source' => $token,
                'metadata' => [
                    'user_id' => $user_id,
                    'test_mode' => $test_mode ? 'true' : 'false'
                ]
            ]);
            $stripe_customer_id = $customer->id;
            update_user_meta($user_id, 'stripe_customer_id', $stripe_customer_id);
        } else {
            \Stripe\Customer::update($stripe_customer_id, [
                'source' => $token
            ]);
        }

        if ($test_mode) {
            $payment_success = true;
            $transaction_id = 'test_' . time() . '_' . mt_rand(1000, 9999);
        } else {
            if ($billing_type === 'annual') {
                $charge = \Stripe\Charge::create([
                    'amount' => $price * 100, 
                    'currency' => 'usd',
                    'customer' => $stripe_customer_id,
                    'description' => 'Annual subscription to ' . $plan_id . ' plan',
                    'metadata' => [
                        'user_id' => $user_id,
                        'plan_id' => $plan_id,
                        'billing_type' => $billing_type
                    ]
                ]);
                $payment_success = $charge->paid;
                $transaction_id = $charge->id;
            } else {
                $charge = \Stripe\Charge::create([
                    'amount' => $price * 100, 
                    'currency' => 'usd',
                    'customer' => $stripe_customer_id,
                    'description' => 'Monthly subscription to ' . $plan_id . ' plan',
                    'metadata' => [
                        'user_id' => $user_id,
                        'plan_id' => $plan_id,
                        'billing_type' => $billing_type
                    ]
                ]);
                $payment_success = $charge->paid;
                $transaction_id = $charge->id;
            }
        }
    } catch (\Exception $e) {
        // Use comprehensive error mapping for better user experience
        require_once(dirname(__FILE__, 2) . '/includes/payment-functions.php');
        $user_friendly_message = sud_get_user_friendly_stripe_error($e);
        wp_send_json_error(['message' => $user_friendly_message]);
        exit;
    }
} elseif ($payment_method === 'paypal') {
    $transaction_id = isset($_POST['transaction_id']) ? sanitize_text_field($_POST['transaction_id']) : '';

    if (empty($transaction_id)) {
        wp_send_json_error(['message' => 'Invalid transaction ID']);
        exit;
    }

    if ($test_mode || !empty($transaction_id)) {
        $payment_success = true;
    }
} else {
    wp_send_json_error(['message' => 'Invalid payment method']);
    exit;
}

if ($payment_success) {
    global $wpdb;
    $wpdb->query('START TRANSACTION');

    try {
        $start_date = current_time('mysql'); 
        $end_date_obj = new DateTime(current_time('mysql', true)); 
        if ($billing_type === 'annual') {
            $end_date_obj->modify('+1 year');
        } else {
            $end_date_obj->modify('+1 month');
        }
        $end_date = $end_date_obj->format('Y-m-d H:i:s'); 

        update_user_meta($user_id, 'premium_plan', $plan_id);
        update_user_meta($user_id, 'subscription_start', $start_date);
        update_user_meta($user_id, 'subscription_expires', $end_date);
        update_user_meta($user_id, 'subscription_billing_type', $billing_type);
        update_user_meta($user_id, 'subscription_auto_renew', true); 

        $plan_details = sud_get_plan_details($plan_id); 
        if ($plan_details['id'] === 'diamond' && !empty($plan_details['verification_badge_auto']) && $plan_details['verification_badge_auto'] === true) {
            $verification_result = sud_auto_verify_user($user_id);
            if (!$verification_result) {
                error_log("SUD Subscription: Failed to auto-verify Diamond user ID: $user_id");
            } else {
                $admin_notifications = get_option('sud_admin_notifications', []);
                $admin_notifications[] = [
                    'type' => 'verification_auto',
                    'user_id' => $user_id,
                    'message' => 'Congratulations! Your Diamond membership includes automatic verification.',
                    'time' => current_time('mysql')
                ];
                update_option('sud_admin_notifications', $admin_notifications);
            }
        }

        $subscription_id = !empty($transaction_id) ? $transaction_id : uniqid('sub_sud_'); 
        update_user_meta($user_id, 'subscription_id', $subscription_id);
        update_user_meta($user_id, 'payment_method', $payment_method);

        $subscriptions_table = $wpdb->prefix . 'sud_subscriptions';

        if ($wpdb->get_var("SHOW TABLES LIKE '$subscriptions_table'") != $subscriptions_table) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $subscriptions_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                subscription_id varchar(50) NOT NULL,
                plan_id varchar(20) NOT NULL,
                price decimal(10,2) NOT NULL,
                payment_method varchar(20) NOT NULL,
                billing_type varchar(20) DEFAULT 'monthly' NOT NULL,
                transaction_id varchar(100) DEFAULT NULL,
                status varchar(20) DEFAULT 'active' NOT NULL,
                start_date datetime NOT NULL,
                end_date datetime NOT NULL,
                auto_renew tinyint(1) DEFAULT 1 NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY subscription_id (subscription_id),
                KEY status (status)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            if ($wpdb->get_var("SHOW TABLES LIKE '$subscriptions_table'") != $subscriptions_table) {
                throw new Exception("Failed to create subscriptions table");
            }
        }

        $columns = $wpdb->get_results("SHOW COLUMNS FROM $subscriptions_table");
        $column_names = array_map(function($col) { return $col->Field; }, $columns);
        $has_billing_type = in_array('billing_type', $column_names);
        $has_transaction_id = in_array('transaction_id', $column_names);

        if (!$has_billing_type) {
            $wpdb->query("ALTER TABLE $subscriptions_table ADD COLUMN billing_type varchar(20) DEFAULT 'monthly' NOT NULL AFTER payment_method");
        }

        if (!$has_transaction_id) {
            $wpdb->query("ALTER TABLE $subscriptions_table ADD COLUMN transaction_id varchar(100) DEFAULT NULL AFTER " . ($has_billing_type ? "billing_type" : "payment_method"));
        }

        $data = [
            'user_id' => $user_id,
            'subscription_id' => $subscription_id,
            'plan_id' => $plan_id,
            'price' => $price,
            'payment_method' => $payment_method,
            'status' => 'active',
            'start_date' => $start_date,
            'end_date' => $end_date,
            'auto_renew' => 1
        ];

        if ($has_billing_type) {
            $data['billing_type'] = $billing_type;
        }

        if ($has_transaction_id) {
            $data['transaction_id'] = $transaction_id;
        }

        $formats = ['%d', '%s', '%s', '%f', '%s'];
        if ($has_billing_type) {
            $formats[] = '%s';
        }
        if ($has_transaction_id) {
            $formats[] = '%s';
        }
        $formats = array_merge($formats, ['%s', '%s', '%s', '%d']);

        $result = $wpdb->insert($subscriptions_table, $data, $formats);

        if ($result === false) {
            throw new Exception("Failed to record subscription: " . $wpdb->last_error);
        }

        $wpdb->query('COMMIT');

        if (function_exists('add_notification')) {
            add_notification(
                $user_id, 
                'premium_upgrade', 
                'Your premium subscription is now active! Enjoy all the premium features.', 
                null
            );
        }

        wp_send_json_success([
            'message' => 'Subscription activated successfully',
            'subscription_id' => $subscription_id,
            'plan_id' => $plan_id, 
            'expires' => $end_date
        ]);

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log("SUD Subscription activation error for user $user_id: " . $e->getMessage());
        sud_handle_ajax_error($e);
    }
} else {
    throw new Exception('Payment processing failed. Please check your details or try another method');
}

} catch (Exception $e) {
    if (isset($wpdb) && $wpdb->ready) {
        $wpdb->query('ROLLBACK');
    }
    sud_handle_ajax_error($e);
}