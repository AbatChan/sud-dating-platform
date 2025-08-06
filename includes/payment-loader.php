<?php

function sud_load_payment_scripts($page_type = 'one-time') {
    if (function_exists('sud_get_stripe_publishable_key') && function_exists('sud_get_paypal_client_id')) {
        $stripe_key = sud_get_stripe_publishable_key();
        $paypal_client_id = sud_get_paypal_client_id();
    } else {
        $payment_settings = get_option('sud_payment_settings', []);
        $test_mode = $payment_settings['test_mode'] ?? true;
        $stripe_key = $test_mode ? ($payment_settings['stripe_test_publishable_key'] ?? '') : ($payment_settings['stripe_live_publishable_key'] ?? '');
        $paypal_client_id = $test_mode ? ($payment_settings['paypal_test_client_id'] ?? '') : ($payment_settings['paypal_live_client_id'] ?? 'sb');
    }

    if (!empty($stripe_key)) {
        echo '<script src="https://js.stripe.com/v3/" defer></script>' . "\n";
    }

    if (!empty($paypal_client_id) && $paypal_client_id !== 'sb' && strlen($paypal_client_id) > 5) {
        $paypal_sdk_url = 'https://www.paypal.com/sdk/js?client-id=' . esc_attr($paypal_client_id) . '&currency=USD';
        if ($page_type === 'subscription') {
            $paypal_sdk_url .= '&vault=true&intent=subscription';
        }
        echo '<script src="' . $paypal_sdk_url . '" defer></script>' . "\n";
    } elseif (!empty($paypal_client_id)) {
        echo '<!-- PayPal SDK not loaded: Invalid client ID -->' . "\n";
    }

    echo '<script>' . "\n";
    echo 'window.sud_payment_config = {' . "\n";
    echo '    stripe_key: "' . esc_js($stripe_key) . '",' . "\n";
    echo '    paypal_client_id: "' . esc_js($paypal_client_id) . '"' . "\n";
    echo '};' . "\n";
    
    // Set debug mode based on environment
    echo 'window.SUD_DEBUG = (window.location.hostname === "localhost" || window.location.hostname.includes("staging") || window.location.hostname.includes("dev"));' . "\n";
    
    // Immediate console suppression for Stripe errors (must run before Stripe SDK loads)
    echo 'if (!window.SUD_DEBUG) {' . "\n";
    echo '    const origError = console.error;' . "\n";
    echo '    console.error = function(...args) {' . "\n";
    echo '        const msg = args.join(" ");' . "\n";
    echo '        if (msg.includes("api.stripe.com") && (msg.includes("402") || msg.includes("Payment Required"))) return;' . "\n";
    echo '        origError.apply(this, args);' . "\n";
    echo '    };' . "\n";
    echo '}' . "\n";
    
    echo '</script>' . "\n";

    // Load stripe error utilities first (dependency for other payment scripts)
    echo '<script src="' . SUD_JS_URL . '/stripe-error-utils.js" defer></script>' . "\n";
    echo '<script src="' . SUD_JS_URL . '/unified-payment.js" defer></script>' . "\n";
}

function sud_should_load_payment_scripts() {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    $payment_pages = ['premium', 'wallet', 'swipe'];

    return in_array($current_page, $payment_pages);
}