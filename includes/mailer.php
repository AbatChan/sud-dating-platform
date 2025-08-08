<?php
require_once(__DIR__ . '/config.php');

if (!defined('USE_WP_MAIL')) {
    define('USE_WP_MAIL', true);
}

if (!function_exists('sud_get_styled_email_html')) {
    function sud_get_styled_email_html($subject_title, $email_heading, $body_html_content, $button_details = []) {
        // Use your CSS variables or define them
        $primary_color = '#FF66CC';
        $primary_hover = '#E659B5';
        $text_color_light = '#ffffff';
        $text_color_dark = '#333333';
        $bg_color_page = '#f4f4f4';
        $bg_color_container = '#ffffff';

        $site_logo_url = '';
        if (defined('SUD_IMG_URL') && SUD_IMG_URL) {
            $site_logo_url = SUD_IMG_URL . '/logo.png';
        }

        // Fallback to WordPress custom logo if SUD logo isn't set or SUD_IMG_URL isn't defined
        if (empty($site_logo_url) && function_exists('get_custom_logo')) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_data = wp_get_attachment_image_src($custom_logo_id, 'full');
                if ($logo_data && isset($logo_data[0])) {
                    $site_logo_url = $logo_data[0];
                }
            }
        }

        $current_year = date('Y');
        $site_name = get_bloginfo('name');

        $buttons_html = '';
        if (!empty($button_details)) {
            $buttons_html .= '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="padding-top:15px; padding-bottom:15px;"><tr><td align="center">';
            foreach ($button_details as $button) {
                if (!empty($button['link']) && !empty($button['text'])) {
                    $buttons_html .= '
                        <table border="0" cellpadding="0" cellspacing="0" style="display:inline-block; margin: 5px;">
                            <tr>
                                <td align="center" bgcolor="' . $primary_color . '" style="border-radius: 50px;">
                                    <a href="' . esc_url($button['link']) . '" target="_blank"
                                       style="font-size: 15px; font-family: Lato, Arial, sans-serif; color: ' . $text_color_light . '; text-decoration: none;
                                              border-radius: 50px; padding: 12px 25px; border: 1px solid ' . $primary_color . ';
                                              display: inline-block; font-weight: bold;">' . esc_html($button['text']) . '</a>
                                </td>
                            </tr>
                        </table>';
                }
            }
            $buttons_html .= '</td></tr></table>';
        }

        $email_html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($subject_title) . '</title>
            <style type="text/css">
                body { margin: 0; padding: 0; width: 100% !important; -webkit-font-smoothing: antialiased; font-family: Lato, Arial, sans-serif; background-color: ' . $bg_color_page . '; color: ' . $text_color_dark . ';}
                table { border-collapse: collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; }
                img { border: 0; display: block; outline:none; text-decoration:none; -ms-interpolation-mode:bicubic; max-width:100%; height:auto; }
                p { margin: 0 0 1em 0; }
                a { color: ' . $primary_color . '; text-decoration: underline; }
                .container { width: 100%; max-width: 600px; margin: 0 auto; background-color: ' . $bg_color_container . '; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
                .header { padding: 25px 30px; text-align: center; background-color: #000000; }
                .content { padding: 30px; text-align: left; font-size: 16px; line-height: 1.6; color: ' . $text_color_dark . '; }
                .content h2 { color: ' . $primary_color . '; font-size: 22px; margin-top: 0; margin-bottom: 20px; text-align: center; font-weight: 600;}
                .footer { padding: 20px 30px; text-align: center; font-size: 12px; color: #888888; background-color: #f0f0f0; border-top: 1px solid #e0e0e0;}
                .button-link a:hover { background-color: ' . $primary_hover . ' !important; border-color: ' . $primary_hover . ' !important; }
                .code-block { font-size: 22px; font-weight: bold; color: ' . $primary_color . '; text-align: center; padding: 12px; margin: 25px auto; letter-spacing: 4px; border: 1px dashed ' . $primary_color . '; background-color: #f9f5eb; border-radius: 4px; max-width: 200px; }
                ul { padding-left: 20px; margin-bottom: 15px; } li { margin-bottom: 5px; }
                blockquote { border-left: 3px solid ' . $primary_color . '; padding-left: 15px; margin-left:0; font-style: italic; color: #555555; background-color: #f9f9f9; padding: 10px 15px; border-radius:4px;}
            </style>
        </head>
        <body style="margin: 0; padding: 0; width: 100% !important; -webkit-font-smoothing: antialiased; font-family: Lato, Arial, sans-serif; background-color: ' . $bg_color_page . '; color: ' . $text_color_dark . ';">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="' . $bg_color_page . '" style="padding: 20px 0;">
                <tr>
                    <td align="center" valign="top">
                        <table class="container" border="0" cellpadding="0" cellspacing="0" style="width: 100%; max-width: 600px; margin: 0 auto; background-color: ' . $bg_color_container . '; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
                            <tr>
                                <td class="header" style="padding: 25px 30px; text-align: center; background-color: #000000;">
                                   <h1 style="color:#fff; margin:0; font-family: Lato, Arial, sans-serif; font-size:24px;">' . esc_html($site_name) . '</h1>
                                </td>
                            </tr>
                            <tr>
                                <td class="content" style="padding: 35px; text-align: left; font-size: 16px; line-height: 1.7; color: ' . $text_color_dark . ';">
                                    <h2 style="color: ' . $primary_color . '; font-size: 22px; margin-top: 0; margin-bottom: 25px; text-align: center; font-weight: 600;">' . esc_html($email_heading) . '</h2>
                                    ' . $body_html_content . '
                                    ' . $buttons_html . '
                                </td>
                            </tr>
                            <tr>
                                <td class="footer" style="padding: 25px 30px; text-align: center; font-size: 12px; color: #888888; background-color: #f0f0f0; border-top: 1px solid #e0e0e0;">
                                    &copy; ' . $current_year . ' ' . esc_html($site_name) . '. All rights reserved.<br>
                                    </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        return $email_html;
    }
}


function send_verification_code($email, $code) {
    $primary_color = '#FF66CC';
    $site_name = get_bloginfo('name');
    $subject_for_email_client = "Verify your email address - " . $site_name;
    $email_heading_in_body = "Verify Your Email Address";

    $body_content = "
        <p style=\"text-align:center;\">Thank you for signing up with " . esc_html($site_name) . "! Please use the verification code below to complete your registration:</p>
        <div class='code-block' style='font-size: 22px; font-weight: bold; color: {$primary_color}; text-align: center; padding: 12px; margin: 25px auto; letter-spacing: 4px; border: 1px dashed {$primary_color}; background-color: #f9f5eb; border-radius: 4px; max-width: 200px;'>{$code}</div>
        <p style=\"text-align:center;\">Enter this code on the verification page to activate your account.</p>
        <p style=\"text-align:center; font-size:0.9em; color:#777;\">If you didn't request this verification, please ignore this email.</p>";

    $message_html = sud_get_styled_email_html($subject_for_email_client, $email_heading_in_body, $body_content);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_name = get_bloginfo('name');
    $from_email = get_option('admin_email');
    $headers[] = "From: {$from_name} <{$from_email}>";

    if (USE_WP_MAIL) {
        $result = wp_mail($email, $subject_for_email_client, $message_html, $headers);
        return $result;
    } else {
        // PHP mail() fallback - ensure your server is configured for this
        $php_mail_headers = implode("\r\n", $headers);
        $result = mail($email, $subject_for_email_client, $message_html, $php_mail_headers);
        return $result;
    }
}

function send_password_reset($email, $reset_link) {
    $site_name = get_bloginfo('name');
    $subject_for_email_client = sprintf('[%s] Password Reset Request', $site_name);
    $email_heading_in_body = "Password Reset Request";

    $body_content = '
        <p>Hello,</p>
        <p>Someone requested a password reset for your ' . esc_html($site_name) . ' account. If this was you, please click the button below to choose a new password.</p>
        <p>If you did not request a password reset, please ignore this email or contact our support team if you have any concerns.</p>
        <p style="font-size:0.9em; color:#777;">This password reset link is valid for 24 hours.</p>
        <hr style="border:none; border-top:1px solid #eeeeee; margin: 25px 0;">
        <p style="font-size:0.85em; color:#777777; text-align:center;">If you\'re having trouble with the button, you can copy and paste the following link into your web browser:</p>
        <p style="word-break: break-all; text-align:center; font-size:0.9em; margin-top:5px;"><a href="'.esc_url($reset_link).'">' . esc_html($reset_link) . '</a></p>';

    $button_details = [
        ['text' => 'Reset Your Password', 'link' => $reset_link]
    ];

    $message_html = sud_get_styled_email_html($subject_for_email_client, $email_heading_in_body, $body_content, $button_details);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_name = get_bloginfo('name');
    $from_email = get_option('admin_email');
    $headers[] = "From: {$from_name} <{$from_email}>";

    return wp_mail($email, $subject_for_email_client, $message_html, $headers);
}

function send_password_changed_notification($email) {
    $site_name = get_bloginfo('name');
    $subject_for_email_client = sprintf('[%s] Your Password Has Been Changed', $site_name);
    $email_heading_in_body = "Password Changed Successfully";

    $body_content = '
        <p>Hello,</p>
        <p>This email confirms that the password for your ' . esc_html($site_name) . ' account was recently changed.</p>
        <p>If you made this change, you can safely disregard this email.</p>
        <p style="padding:10px 15px; background-color:#f8d7da; border:1px solid #f5c6cb; color:#721c24; border-radius:4px; margin-top:20px; margin-bottom:20px;">
            <strong>Important:</strong> If you did NOT change your password, please contact our support team immediately as your account may have been compromised.
        </p>
        <p>Thank you for being a part of our community.</p>';

    $button_details = [];

    $message_html = sud_get_styled_email_html($subject_for_email_client, $email_heading_in_body, $body_content, $button_details);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_name = get_bloginfo('name');
    $from_email = get_option('admin_email');
    $headers[] = "From: {$from_name} <{$from_email}>";

    return wp_mail($email, $subject_for_email_client, $message_html, $headers);
}

if (!function_exists('sud_send_event_notification_email')) {
    function sud_send_event_notification_email($recipient_user_id_or_email, $recipient_name, $event_type, $actor_name, $actor_profile_link, $event_details_html, $cta_button_text, $cta_button_link) {
        $recipient_user = null;
        $recipient_email = '';

        if (is_numeric($recipient_user_id_or_email)) {
            $recipient_user = get_userdata(intval($recipient_user_id_or_email));
            if ($recipient_user) {
                $recipient_email = $recipient_user->user_email;
            }
        } elseif (is_email($recipient_user_id_or_email)) {
            $recipient_email = $recipient_user_id_or_email;
            $recipient_user = get_user_by('email', $recipient_email);
        }

        if (empty($recipient_email) || !$recipient_user) {
            return false;
        }
        
        if (preg_match('/@sud\.com$/i', $recipient_email)) {
            return true;
        }
        
        $preference_meta_key = '';
        switch ($event_type) {
            case 'new_message':
                $preference_meta_key = 'message_notifications';
                break;
            case 'profile_view':
                $preference_meta_key = 'view_notifications';
                break;
            case 'new_favorite':
                $preference_meta_key = 'favorite_notifications';
                break;
            case 'new_match':
                $preference_meta_key = 'match_notifications';
                break;
            default:
                $preference_meta_key = 'email_notifications';
                break;
        }

        $email_preference_enabled = true;
        $general_email_notifications_enabled = true;

        if ($recipient_user) {
            $user_all_settings = get_user_meta($recipient_user->ID, 'user_settings', true);
            if (!is_array($user_all_settings)) {
                $user_all_settings = [];
            }

            $setting_defaults = [
                'email_notifications' => true,
                'message_notifications' => true,
                'favorite_notifications' => true,
                'view_notifications' => true,
                'match_notifications' => true,
                // Add any other specific keys checked by $preference_meta_key if they have a default
            ];
            
            $user_settings_with_defaults = array_merge($setting_defaults, $user_all_settings);

            if (isset($user_settings_with_defaults['email_notifications']) && $user_settings_with_defaults['email_notifications'] === false) {
                $general_email_notifications_enabled = false;
            }

            if (!empty($preference_meta_key)) {
                if (array_key_exists($preference_meta_key, $user_settings_with_defaults)) {
                    if ($user_settings_with_defaults[$preference_meta_key] === false) {
                        $email_preference_enabled = false;
                    }
                }
            }
        } else {
            $email_preference_enabled = false;
            $general_email_notifications_enabled = false;
        }

        if (!$email_preference_enabled || !$general_email_notifications_enabled) {
            return true;
        }

        $site_name = get_bloginfo('name');
        $subject = '';
        $email_heading = '';

        switch ($event_type) {
            case 'new_message':
                $subject = sprintf('%s sent you a new message on %s!', esc_html($actor_name), esc_html($site_name));
                $email_heading = 'You Have a New Message!';
                break;
            case 'profile_view':
                $subject = sprintf('%s viewed your profile on %s!', esc_html($actor_name), esc_html($site_name));
                $email_heading = 'Someone New Viewed Your Profile!';
                break;
            case 'new_favorite':
                $subject = sprintf('%s added you to their favorites on %s!', esc_html($actor_name), esc_html($site_name));
                $email_heading = 'You\'ve Been Favorited!';
                break;
            default:
                $subject = sprintf('New activity on your %s account', esc_html($site_name));
                $email_heading = 'New Account Activity';
                break;
        }

        $body_greeting = "<p>Hello " . esc_html($recipient_name) . ",</p>";

        $button_details = [];
        if (!empty($cta_button_link) && !empty($cta_button_text)) {
            $button_details[] = ['text' => $cta_button_text, 'link' => $cta_button_link];
        }

        if (!function_exists('sud_get_styled_email_html')) {
            return false;
        }
        
        $message_html = sud_get_styled_email_html($subject, $email_heading, $body_greeting . $event_details_html, $button_details);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_name_header = get_bloginfo('name');
        $from_email_header = get_option('admin_email');
        $headers[] = "From: {$from_name_header} <{$from_email_header}>";

        $sent = wp_mail($recipient_email, $subject, $message_html, $headers);
        return $sent;
    }
}

// Payment notification functions
if (!function_exists('send_payment_confirmation_email')) {
    function send_payment_confirmation_email($user_id, $transaction_data) {
        $user = get_userdata($user_id);
        if (!$user) return false;
        
        $user_email = $user->user_email;
        
        // Skip internal emails (both legacy SUD and new SUD domains)
        if (preg_match('/@sud\.com$/i', $user_email)) {
            return true;
        }
        
        // Check user email preferences
        $user_settings = get_user_meta($user_id, 'user_settings', true);
        if (!is_array($user_settings)) $user_settings = [];
        
        $email_notifications_enabled = isset($user_settings['email_notifications']) ? 
            (bool)$user_settings['email_notifications'] : true;
        
        if (!$email_notifications_enabled) {
            return true; // User has disabled email notifications
        }
        
        $transaction_type = $transaction_data['type'] ?? 'purchase';
        $amount = $transaction_data['amount'] ?? '0.00';
        $item_name = $transaction_data['item_name'] ?? 'Purchase';
        $payment_method = $transaction_data['payment_method'] ?? 'Credit Card';
        $transaction_id = $transaction_data['transaction_id'] ?? '';
        
        $subject = "Payment Confirmation - $item_name";
        $email_heading = "Payment Successful!";
        
        $body_html = "<p>Hi " . esc_html($user->display_name) . ",</p>";
        $body_html .= "<p>Your payment has been successfully processed!</p>";
        $body_html .= "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        $body_html .= "<h3 style='margin-top: 0;'>Transaction Details</h3>";
        $body_html .= "<p><strong>Item:</strong> " . esc_html($item_name) . "</p>";
        $body_html .= "<p><strong>Amount:</strong> $" . esc_html($amount) . "</p>";
        $body_html .= "<p><strong>Payment Method:</strong> " . esc_html($payment_method) . "</p>";
        if ($transaction_id) {
            $body_html .= "<p><strong>Transaction ID:</strong> " . esc_html($transaction_id) . "</p>";
        }
        $body_html .= "<p><strong>Date:</strong> " . date('F j, Y \a\t g:i A') . "</p>";
        $body_html .= "</div>";
        
        if ($transaction_type === 'coins') {
            $body_html .= "<p>Your coins have been added to your wallet and are ready to use!</p>";
        } elseif ($transaction_type === 'boost') {
            $body_html .= "<p>Your boost is now active and will help increase your profile visibility!</p>";
        } elseif ($transaction_type === 'swipe-up') {
            $body_html .= "<p>Your Super Swipes have been added to your account. Use them wisely!</p>";
        } elseif ($transaction_type === 'subscription') {
            $body_html .= "<p>Welcome to Premium! You now have access to all premium features.</p>";
        }
        
        $body_html .= "<p>Thank you for your purchase!</p>";
        
        $button_details = [
            ['text' => 'View My Account', 'link' => SUD_URL . '/pages/profile']
        ];
        
        $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html, $button_details);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_name = get_bloginfo('name');
        $from_email = get_option('admin_email');
        $headers[] = "From: {$from_name} <{$from_email}>";
        
        $email_sent = wp_mail($user_email, $subject, $message_html, $headers);
        if ($email_sent) {
        } else {
            error_log("SUD PAYMENT: Failed to send payment confirmation email to $user_email");
        }
        return $email_sent;
    }
}

if (!function_exists('send_admin_payment_notification')) {
    function send_admin_payment_notification($transaction_data) {
        // Check admin notification settings
        if (function_exists('sud_admin_should_receive_email')) {
            if (!sud_admin_should_receive_email('admin_payment_notifications')) {
                return true; // Settings disabled, but not an error
            }
        }
        
        // Get eligible admin users instead of just main admin email
        $admin_users = function_exists('sud_get_eligible_admin_users') ? 
            sud_get_eligible_admin_users() : [];
            
        if (empty($admin_users)) {
            // Fallback to main admin email if no eligible admins
            $admin_email = get_option('admin_email');
            if (empty($admin_email)) return false;
        }
        
        $user_id = $transaction_data['user_id'] ?? 0;
        $user = get_userdata($user_id);
        $user_name = $user ? $user->display_name : 'Unknown User';
        $user_email = $user ? $user->user_email : 'Unknown Email';
        
        $transaction_type = $transaction_data['type'] ?? 'purchase';
        $amount = $transaction_data['amount'] ?? '0.00';
        $item_name = $transaction_data['item_name'] ?? 'Purchase';
        $payment_method = $transaction_data['payment_method'] ?? 'Credit Card';
        $transaction_id = $transaction_data['transaction_id'] ?? '';
        
        $subject = "New Payment: $item_name - $" . $amount;
        $email_heading = "Payment Received";
        
        $body_html = "<p>A new payment has been received:</p>";
        $body_html .= "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        $body_html .= "<h3 style='margin-top: 0;'>Transaction Details</h3>";
        $body_html .= "<p><strong>Customer:</strong> " . esc_html($user_name) . " (" . esc_html($user_email) . ")</p>";
        $body_html .= "<p><strong>Item:</strong> " . esc_html($item_name) . "</p>";
        $body_html .= "<p><strong>Amount:</strong> $" . esc_html($amount) . "</p>";
        $body_html .= "<p><strong>Payment Method:</strong> " . esc_html($payment_method) . "</p>";
        if ($transaction_id) {
            $body_html .= "<p><strong>Transaction ID:</strong> " . esc_html($transaction_id) . "</p>";
        }
        $body_html .= "<p><strong>Date:</strong> " . date('F j, Y \a\t g:i A') . "</p>";
        $body_html .= "</div>";
        
        $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_name = get_bloginfo('name');
        $from_email = get_option('admin_email') ?: 'noreply@' . parse_url(home_url(), PHP_URL_HOST);
        $headers[] = "From: {$from_name} <{$from_email}>";
        
        $all_emails_sent = true;
        
        if (!empty($admin_users)) {
            // Send to eligible admin users
            foreach ($admin_users as $admin_user) {
                if ($admin_user instanceof WP_User && !empty($admin_user->user_email)) {
                    // Support for email testing - override admin email if filter is set
                    $admin_email_to = apply_filters('sud_override_admin_email_for_test', $admin_user->user_email);
                    $email_sent = wp_mail($admin_email_to, $subject, $message_html, $headers);
                    if ($email_sent) {
                    } else {
                        error_log("SUD PAYMENT: Failed to send admin payment notification to " . $admin_user->user_email);
                        $all_emails_sent = false;
                    }
                }
            }
        } else {
            // Fallback to main admin email
            $email_sent = wp_mail($admin_email, $subject, $message_html, $headers);
            if ($email_sent) {
            } else {
                error_log("SUD PAYMENT: Failed to send admin payment notification to $admin_email (fallback)");
            }
            return $email_sent;
        }
        
        return $all_emails_sent;
    }
}

if (!function_exists('send_subscription_success_email')) {
    function send_subscription_success_email($user_id, $plan_details, $subscription_data = []) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $plan_name = $plan_details['name'] ?? 'Premium Plan';
        $plan_price = $plan_details['price_monthly'] ?? 0;
        $billing_cycle = $subscription_data['billing_cycle'] ?? 'monthly';
        
        $subject = "Welcome to {$plan_name} - " . get_bloginfo('name');
        $email_heading = "Welcome to {$plan_name}!";
        
        $body_html = "<p>Dear " . esc_html($user->display_name) . ",</p>";
        $body_html .= "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        $body_html .= "<h3 style='margin-top: 0; color: #155724;'>🎉 Welcome to {$plan_name}!</h3>";
        $body_html .= "<p><strong>Your subscription is now active.</strong></p>";
        
        if ($billing_cycle === 'annual') {
            $body_html .= "<p><strong>Plan:</strong> {$plan_name} (Annual) - $" . number_format($plan_details['price_annually'], 2) . "/year</p>";
        } else {
            $body_html .= "<p><strong>Plan:</strong> {$plan_name} (Monthly) - $" . number_format($plan_price, 2) . "/month</p>";
        }
        
        $body_html .= "</div>";
        $body_html .= "<p>You now have access to all premium features! Here's what you can enjoy:</p>";
        
        if (!empty($plan_details['benefits'])) {
            $body_html .= "<ul>";
            foreach ($plan_details['benefits'] as $benefit) {
                $body_html .= "<li>" . esc_html($benefit) . "</li>";
            }
            $body_html .= "</ul>";
        }
        
        $body_html .= "<p>You can manage your subscription, view billing details, or cancel anytime from your account.</p>";
        
        $button_details = [
            ['text' => 'Manage Subscription', 'link' => SUD_URL . '/pages/subscription'],
            ['text' => 'Explore Premium Features', 'link' => SUD_URL . '/pages/dashboard']
        ];
        
        if (!function_exists('sud_get_styled_email_html')) {
            return false;
        }
        
        $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html, $button_details);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_name = get_bloginfo('name');
        $from_email = get_option('admin_email');
        $headers[] = "From: {$from_name} <{$from_email}>";
        
        return wp_mail($user->user_email, $subject, $message_html, $headers);
    }
}

if (!function_exists('send_admin_subscription_cancellation_notification')) {
    function send_admin_subscription_cancellation_notification($cancellation_data) {
        // Check admin notification settings
        if (function_exists('sud_admin_should_receive_email')) {
            if (!sud_admin_should_receive_email('admin_payment_notifications')) {
                return true; // Settings disabled, but not an error
            }
        }
        
        // Get eligible admin users
        $admin_users = function_exists('sud_get_eligible_admin_users') ? 
            sud_get_eligible_admin_users() : [];
            
        if (empty($admin_users)) {
            // Fallback to main admin email if no eligible admins
            $admin_email = get_option('admin_email');
            if (empty($admin_email)) return false;
        }
        
        $user_name = $cancellation_data['user_name'] ?? 'Unknown User';
        $user_email = $cancellation_data['user_email'] ?? 'Unknown Email';
        $plan_name = $cancellation_data['plan_name'] ?? 'Premium Plan';
        $subscription_id = $cancellation_data['subscription_id'] ?? '';
        $payment_method = $cancellation_data['payment_method'] ?? 'Unknown';
        
        $subject = "Subscription Cancelled: $user_name - $plan_name";
        $email_heading = "Subscription Cancellation";
        
        $body_html = "<p>A user has cancelled their premium subscription:</p>";
        $body_html .= "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        $body_html .= "<h3 style='margin-top: 0; color: #856404;'>⚠️ Cancellation Details</h3>";
        $body_html .= "<p><strong>Customer:</strong> " . esc_html($user_name) . " (" . esc_html($user_email) . ")</p>";
        $body_html .= "<p><strong>Plan:</strong> " . esc_html($plan_name) . "</p>";
        $body_html .= "<p><strong>Payment Method:</strong> " . esc_html($payment_method) . "</p>";
        if ($subscription_id) {
            $body_html .= "<p><strong>Subscription ID:</strong> " . esc_html($subscription_id) . "</p>";
        }
        $body_html .= "<p><strong>Cancellation Date:</strong> " . date('F j, Y \\a\\t g:i A', current_time('timestamp')) . "</p>";
        $body_html .= "</div>";
        $body_html .= "<p>The user's account will remain premium until their current billing period ends.</p>";
        
        $button_details = [
            ['text' => 'View User Management', 'link' => admin_url('admin.php?page=' . brand_css_class('user-management'))]
        ];
        
        $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html, $button_details);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_name = get_bloginfo('name');
        $from_email = get_option('admin_email') ?: 'noreply@' . parse_url(home_url(), PHP_URL_HOST);
        $headers[] = "From: {$from_name} <{$from_email}>";
        
        $all_emails_sent = true;
        
        if (!empty($admin_users)) {
            // Send to eligible admin users
            foreach ($admin_users as $admin_user) {
                if ($admin_user instanceof WP_User && !empty($admin_user->user_email)) {
                    // Support for email testing - override admin email if filter is set
                    $admin_email_to = apply_filters('sud_override_admin_email_for_test', $admin_user->user_email);
                    $email_sent = wp_mail($admin_email_to, $subject, $message_html, $headers);
                    if ($email_sent) {
                    } else {
                        error_log("SUD SUBSCRIPTION: Failed to send cancellation notification to " . $admin_user->user_email);
                        $all_emails_sent = false;
                    }
                }
            }
        } else {
            // Fallback to main admin email
            $email_sent = wp_mail($admin_email, $subject, $message_html, $headers);
            if ($email_sent) {
            } else {
                error_log("SUD SUBSCRIPTION: Failed to send cancellation notification to $admin_email (fallback)");
            }
            return $email_sent;
        }
        
        return $all_emails_sent;
    }

    /**
     * Send trial welcome email to user when they successfully start a trial
     */
    function send_trial_welcome_email($user_id, $trial_data) {
        $user = get_userdata($user_id);
        if (!$user) {
            error_log("SUD TRIAL: Cannot send welcome email - user not found: $user_id");
            return false;
        }

        $plan_name = ucfirst($trial_data['plan'] ?? 'Premium');
        $trial_days = $trial_data['days'] ?? 3;
        $end_date = isset($trial_data['end']) ? date('F j, Y', strtotime($trial_data['end'])) : date('F j, Y', strtotime('+3 days'));
        
        $subject = "🎉 Your {$plan_name} Trial Has Started - " . get_bloginfo('name');
        $email_heading = "Welcome to Your Free Trial!";
        
        $body_html = "<p>Dear " . esc_html($user->display_name) . ",</p>";
        $body_html .= "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        $body_html .= "<h3 style='margin-top: 0; color: #155724;'>🎉 Your {$plan_name} Trial is Now Active!</h3>";
        $body_html .= "<p><strong>Trial Duration:</strong> {$trial_days} days</p>";
        $body_html .= "<p><strong>Trial Expires:</strong> {$end_date}</p>";
        $body_html .= "</div>";
        
        $body_html .= "<p>Your trial will automatically convert to a paid subscription unless you cancel before it expires.</p>";
        $body_html .= "<p>Enjoy exploring all the premium features!</p>";
        
        $button_details = [
            ['text' => 'Start Exploring', 'link' => SUD_URL . '/pages/dashboard'],
            ['text' => 'Manage Subscription', 'link' => SUD_URL . '/pages/subscription']
        ];
        
        if (!function_exists('sud_get_styled_email_html')) {
            error_log("SUD TRIAL: sud_get_styled_email_html function not available");
            return false;
        }
        
        $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html, $button_details);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_name = get_bloginfo('name');
        $from_email = 'support@swipeupdaddy.com';
        $headers[] = "From: {$from_name} <{$from_email}>";
        
        // Support for email testing - override user email if filter is set
        $email_to = apply_filters('sud_override_user_email_for_test', $user->user_email);
        $email_sent = wp_mail($email_to, $subject, $message_html, $headers);
        
        if ($email_sent) {
            //error_log("SUD TRIAL: Welcome email sent successfully to {$email_to} for {$plan_name} trial");
        } else {
            error_log("SUD TRIAL: Failed to send welcome email to {$user->user_email} for {$plan_name} trial");
        }
        
        return $email_sent;
    }

    /**
     * Send admin notification when a user starts a trial
     */
    function send_admin_trial_notification($user_id, $trial_data) {
        $user = get_userdata($user_id);
        if (!$user) {
            error_log("SUD TRIAL: Cannot send admin notification - user not found: $user_id");
            return false;
        }

        $plan_name = ucfirst($trial_data['plan'] ?? 'Premium');
        $trial_days = $trial_data['days'] ?? 3;
        $end_date = isset($trial_data['end']) ? date('F j, Y', strtotime($trial_data['end'])) : date('F j, Y', strtotime('+3 days'));
        
        $subject = "New {$plan_name} Trial Started - " . get_bloginfo('name');
        $email_heading = "New Trial Activation";
        
        $body_html = "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        $body_html .= "<h3 style='margin-top: 0; color: #856404;'>🔔 New Trial Started</h3>";
        $body_html .= "<p><strong>User:</strong> " . esc_html($user->display_name) . " (" . esc_html($user->user_email) . ")</p>";
        $body_html .= "<p><strong>Plan:</strong> {$plan_name}</p>";
        $body_html .= "<p><strong>Trial Duration:</strong> {$trial_days} days</p>";
        $body_html .= "<p><strong>Expires:</strong> {$end_date}</p>";
        $body_html .= "<p><strong>User ID:</strong> {$user_id}</p>";
        $body_html .= "</div>";
        
        $body_html .= "<p>A user has successfully started a free trial subscription.</p>";
        $body_html .= "<p>The trial will automatically convert to a paid subscription unless the user cancels.</p>";
        
        $button_details = [
            ['text' => 'View User Profile', 'link' => admin_url("user-edit.php?user_id={$user_id}")],
            ['text' => 'Admin Dashboard', 'link' => admin_url('admin.php')]
        ];
        
        if (!function_exists('sud_get_styled_email_html')) {
            error_log("SUD TRIAL: sud_get_styled_email_html function not available for admin notification");
            return false;
        }
        
        $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html, $button_details);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_name = get_bloginfo('name');
        $from_email = 'support@swipeupdaddy.com';
        $headers[] = "From: {$from_name} <{$from_email}>";
        
        // Check if admin trial notifications are enabled
        $should_send_admin_email = function_exists('sud_admin_should_receive_email') ? 
            sud_admin_should_receive_email('admin_trial_notifications') : true;
            
        if (!$should_send_admin_email) {
            error_log("SUD TRIAL: Admin trial notifications are disabled - skipping admin email");
            return true; // Return true since this isn't an email failure
        }

        // Get admin users who should receive notifications
        $admin_notification_settings = function_exists('sud_get_admin_notification_settings') ? 
            sud_get_admin_notification_settings() : [];
        
        if ($admin_notification_settings['enable_all_admins'] ?? true) {
            // Send to all administrators
            $admin_users = get_users(['capability' => 'manage_options']);
        } else {
            // Send only to specifically enabled admins
            $enabled_admin_ids = $admin_notification_settings['enabled_admin_users'] ?? [];
            if (empty($enabled_admin_ids)) {
                error_log("SUD TRIAL: No specific admins enabled for notifications");
                return true;
            }
            $admin_users = get_users([
                'include' => $enabled_admin_ids,
                'capability' => 'manage_options'
            ]);
        }
        
        $admin_email = 'support@swipeupdaddy.com';
        $all_emails_sent = true;
        
        if (!empty($admin_users)) {
            // Send to eligible admin users
            foreach ($admin_users as $admin_user) {
                if ($admin_user instanceof WP_User && !empty($admin_user->user_email)) {
                    // Support for email testing - override admin email if filter is set
                    $admin_email_to = apply_filters('sud_override_admin_email_for_test', $admin_user->user_email);
                    $email_sent = wp_mail($admin_email_to, $subject, $message_html, $headers);
                    if ($email_sent) {
                        //error_log("SUD TRIAL: Admin notification sent successfully to " . $admin_email_to);
                    } else {
                        error_log("SUD TRIAL: Failed to send admin notification to " . $admin_user->user_email);
                        $all_emails_sent = false;
                    }
                }
            }
        } else {
            // Fallback to main admin email
            $email_sent = wp_mail($admin_email, $subject, $message_html, $headers);
            if ($email_sent) {
                //error_log("SUD TRIAL: Admin notification sent successfully to $admin_email (fallback)");
            } else {
                error_log("SUD TRIAL: Failed to send admin notification to $admin_email (fallback)");
            }
            return $email_sent;
        }
        
        return $all_emails_sent;
    }

    /**
     * Send trial cancellation email to user when they cancel their trial
     */
    function send_trial_cancellation_email($user_id, $trial_data) {
        $user = get_userdata($user_id);
        if (!$user) {
            error_log("SUD TRIAL: Cannot send cancellation email - user not found: $user_id");
            return false;
        }

        $plan_name = ucfirst($trial_data['plan'] ?? 'Premium');
        $cancelled_date = isset($trial_data['cancelled_date']) ? date('F j, Y', strtotime($trial_data['cancelled_date'])) : date('F j, Y');
        
        $subject = "Trial Cancelled - " . get_bloginfo('name');
        $email_heading = "Trial Cancelled";
        
        $body_html = "<p>Dear " . esc_html($user->display_name) . ",</p>";
        $body_html .= "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        $body_html .= "<h3 style='margin-top: 0; color: #856404;'>Your {$plan_name} Trial Has Been Cancelled</h3>";
        $body_html .= "<p><strong>Cancellation Date:</strong> {$cancelled_date}</p>";
        $body_html .= "<p>Your trial access will continue until the original expiry date, after which you'll return to the free plan.</p>";
        $body_html .= "</div>";
        
        $body_html .= "<p>We're sorry to see you go! If you change your mind, you can subscribe anytime to regain premium access.</p>";
        $body_html .= "<p>If you cancelled due to an issue, please let us know how we can improve.</p>";
        
        $button_details = [
            ['text' => 'Subscribe Now', 'link' => SUD_URL . '/pages/premium'],
            ['text' => 'Contact Support', 'link' => 'mailto:support@swipeupdaddy.com']
        ];
        
        if (!function_exists('sud_get_styled_email_html')) {
            error_log("SUD TRIAL: sud_get_styled_email_html function not available");
            return false;
        }
        
        $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html, $button_details);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_name = get_bloginfo('name');
        $from_email = 'support@swipeupdaddy.com';
        $headers[] = "From: {$from_name} <{$from_email}>";
        
        // Support for email testing - override user email if filter is set
        $email_to = apply_filters('sud_override_user_email_for_test', $user->user_email);
        $email_sent = wp_mail($email_to, $subject, $message_html, $headers);
        
        if ($email_sent) {
            error_log("SUD TRIAL: Cancellation email sent successfully to {$email_to} for {$plan_name} trial");
        } else {
            error_log("SUD TRIAL: Failed to send cancellation email to {$user->user_email} for {$plan_name} trial");
        }
        
        return $email_sent;
    }

    /**
     * Send 1-day expiry warning email to user before trial expires
     */
    function send_trial_expiry_warning_email($user_id, $trial_data) {
        $user = get_userdata($user_id);
        if (!$user) {
            error_log("SUD TRIAL: Cannot send expiry warning email - user not found: $user_id");
            return false;
        }

        $plan_name = ucfirst($trial_data['plan'] ?? 'Premium');
        $expiry_date = isset($trial_data['end']) ? date('F j, Y \a\t g:i A', strtotime($trial_data['end'])) : date('F j, Y \a\t g:i A', strtotime('+1 day'));
        
        $subject = "🔔 Your {$plan_name} Trial Expires Tomorrow - " . get_bloginfo('name');
        $email_heading = "Trial Expiring Soon";
        
        $body_html = "<p>Dear " . esc_html($user->display_name) . ",</p>";
        $body_html .= "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        $body_html .= "<h3 style='margin-top: 0; color: #856404;'>🔔 Your {$plan_name} Trial Expires Tomorrow</h3>";
        $body_html .= "<p><strong>Trial Expires:</strong> {$expiry_date}</p>";
        $body_html .= "<p>Your trial will automatically convert to a paid subscription unless you cancel before it expires.</p>";
        $body_html .= "</div>";
        
        $body_html .= "<p>If you've enjoyed your premium experience, no action is needed - your subscription will continue seamlessly.</p>";
        $body_html .= "<p>If you'd like to cancel, you can do so anytime before the expiry date.</p>";
        
        $button_details = [
            ['text' => 'Manage Subscription', 'link' => SUD_URL . '/pages/subscription'],
            ['text' => 'View Premium Plans', 'link' => SUD_URL . '/pages/premium']
        ];
        
        if (!function_exists('sud_get_styled_email_html')) {
            error_log("SUD TRIAL: sud_get_styled_email_html function not available");
            return false;
        }
        
        $message_html = sud_get_styled_email_html($subject, $email_heading, $body_html, $button_details);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_name = get_bloginfo('name');
        $from_email = 'support@swipeupdaddy.com';
        $headers[] = "From: {$from_name} <{$from_email}>";
        
        // Support for email testing - override user email if filter is set
        $email_to = apply_filters('sud_override_user_email_for_test', $user->user_email);
        $email_sent = wp_mail($email_to, $subject, $message_html, $headers);
        
        if ($email_sent) {
            error_log("SUD TRIAL: Expiry warning email sent successfully to {$email_to} for {$plan_name} trial");
        } else {
            error_log("SUD TRIAL: Failed to send expiry warning email to {$user->user_email} for {$plan_name} trial");
        }
        
        return $email_sent;
    }
}

?>