<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php'); 
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');

require_login();

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// Use centralized validation for all profile requirements (no photo requirement)
validate_core_profile_requirements($current_user_id, 'withdrawal');

$user_plan_details = sud_get_user_current_plan_details($current_user_id);
$user_plan_id = $user_plan_details['id'];
$user_tier_level = (int)($user_plan_details['tier_level'] ?? 0);
$withdrawal_scope = $user_plan_details['withdrawal_scope'] ?? 'none';

// Withdrawal permission based on tier level
$can_withdraw_at_all = false;
$withdrawal_message = '';
$minimum_withdrawal_usd = 50; // Set minimum to $50 for all withdrawals

// Check if user is on trial
$active_trial = sud_get_active_trial($current_user_id);
$is_on_trial = $active_trial !== false;

if ($user_plan_id === 'free') {
    $withdrawal_message = 'go_premium';
} elseif ($is_on_trial) {
    $withdrawal_message = 'trial_not_allowed';
} elseif ($user_plan_id === 'gold' || $user_plan_id === 'diamond') {
    $can_withdraw_at_all = true;
}

$coin_balance = (float) get_user_meta($current_user_id, 'coin_balance', true);
$coin_to_usd_rate = defined('SUD_COIN_WITHDRAWAL_RATE_USD') ? SUD_COIN_WITHDRAWAL_RATE_USD : 0.10;

global $wpdb;
$withdrawal_table = $wpdb->prefix . 'sud_withdrawals';
$history = $wpdb->get_results($wpdb->prepare(
    "SELECT requested_at, amount_coins, net_payout_usd, currency, method, destination, status
     FROM {$withdrawal_table} WHERE user_id = %d ORDER BY requested_at DESC LIMIT 50",
    $current_user_id
), ARRAY_A);

$header_data = [
    'current_user' => $current_user,
    'display_name' => $current_user->display_name,
    'profile_pic_url' => get_user_meta($current_user_id, 'profile_picture', true) ? wp_get_attachment_image_url(get_user_meta($current_user_id, 'profile_picture', true), 'thumbnail') : SUD_IMG_URL . '/default-profile.jpg',
    'completion_percentage' => function_exists('get_profile_completion_percentage') ? get_profile_completion_percentage($current_user_id) : 0,
    'unread_message_count' => function_exists('get_user_messages') ? (get_user_messages($current_user_id)['unread_count'] ?? 0) : 0,
    'coin_balance' => $coin_balance,
    'header_user_is_verified' => (bool) get_user_meta($current_user_id, 'is_verified', true),
];

$prefill_amount_coins = 0;
$prefill_amount_usd = 0;

if (isset($_GET['amount_usd'])) {
    $prefill_amount_usd = filter_var($_GET['amount_usd'], FILTER_VALIDATE_FLOAT);
    if ($prefill_amount_usd && $prefill_amount_usd > 0 && $coin_to_usd_rate > 0) {
        $prefill_amount_coins = floor($prefill_amount_usd / $coin_to_usd_rate); 

        if ($prefill_amount_coins > $coin_balance) {
            $prefill_amount_coins = floor($coin_balance);
        }

        if ($prefill_amount_coins <= 0 && $coin_balance >= 1) {
            $prefill_amount_coins = 1;
        } elseif ($prefill_amount_coins <= 0) {
            $prefill_amount_coins = 0; 
        }
    } else {
        $prefill_amount_coins = 0; 
    }
} elseif (isset($_GET['amount_coins'])) {
    $prefill_amount_coins = filter_var($_GET['amount_coins'], FILTER_VALIDATE_INT);
    if ($prefill_amount_coins && $prefill_amount_coins > 0) {
        if ($prefill_amount_coins > $coin_balance) {
            $prefill_amount_coins = floor($coin_balance);
        }
    } else {
        $prefill_amount_coins = 0;
    }
}

// Re-fetch coin_balance as it might have been adjusted by prefill logic - though unlikely needed here.
// $coin_balance = (float) get_user_meta($current_user_id, 'coin_balance', true);

$page_title = "Withdraw Coins";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        if ( function_exists( 'get_site_icon_url' ) && ( $icon_url = get_site_icon_url() ) ) {
        echo '<link rel="icon" href="' . esc_url( $icon_url ) . '" />';
        }
    ?>
    <title><?php echo esc_html($page_title); ?> - Loyalty Meets Royalty</title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/wallet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
</head>
<body>
    <?php extract($header_data); include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>

    <main class="main-content">
        <div class="container withdrawal-container">
            <h2>Withdraw Coins</h2>
            
            <div class="withdrawal-box balance-info">
                <span>Your Available Balance</span>
                <span class="balance-amount">
                    <img src="<?php echo SUD_IMG_URL; ?>/sud-coin.png" alt="SUD">
                    <?php echo number_format($coin_balance); ?>
                </span>
                <?php $usd_equivalent = $coin_balance * $coin_to_usd_rate; ?>
                <span class="usd-equivalent">(Approx. <?php echo esc_html(sprintf("$%.2f USD", $usd_equivalent)); ?> withdrawable value)</span>
            </div>

            <?php if (!$can_withdraw_at_all): ?>
                <div class="withdrawal-box">
                    <div class="upgrade-prompt feature-lock">
                        <i class="fas fa-coins" style="font-size: 40px; color: var(--sud-primary); margin-bottom: 15px;"></i>
                        <?php if ($withdrawal_message === 'go_premium'): ?>
                            <h3>Withdrawal Unavailable</h3>
                            <p>Coin withdrawal is a premium feature. Upgrade to premium to convert your SUD Coins into cash.</p>
                            <a href="<?php echo SUD_URL; ?>/pages/premium" class="btn btn-primary">Go Premium</a>
                        <?php elseif ($withdrawal_message === 'trial_not_allowed'): ?>
                            <h3>Withdrawals Not Available During Trial</h3>
                            <p>Coin withdrawals are not available during the trial period. Upgrade to Gold or Diamond to access withdrawals.</p>
                            <a href="<?php echo SUD_URL; ?>/pages/premium?highlight_plan=gold&direct_pay=true&plan=gold" class="btn btn-primary">Upgrade to Gold</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="withdrawal-box withdrawal-form">
                    <h3>Request Withdrawal</h3>
                    <p class="form-note">
                        The conversion rate is <?php echo esc_html(sprintf("1 Coin = $%.2f USD", $coin_to_usd_rate)); ?>.
                        Processing may take 3-5 business days.
                    </p>

                    <?php if ($user_plan_id === 'gold'): ?>
                        <div class="card card-withdrawal-explain">
                            <p class="withdrawal-explain-text">Gold members enjoy worldwide withdrawal capability. Please provide your payment details below.</p>
                        </div>
                    <?php elseif ($user_plan_id === 'diamond'): ?>
                        <div class="card card-withdrawal-explain">
                            <p class="withdrawal-explain-text">Diamond members enjoy worldwide withdrawal capability with priority processing. Please provide your payment details below.</p>
                        </div>
                    <?php endif; ?>

                    <form id="withdrawal-request-form" novalidate>
                        <?php wp_nonce_field('sud_request_withdrawal_action', 'sud_withdrawal_nonce'); ?>
                        <div class="form-group">
                            <label for="withdrawal-amount">Amount to Withdraw (Coins)</label>
                            <input type="number" id="withdrawal-amount" name="amount" class="form-input" required
                                   min="<?php echo number_format($minimum_withdrawal_usd / $coin_to_usd_rate, 0); ?>"
                                   max="<?php echo esc_attr($coin_balance); ?>"
                                   step="1"
                                   placeholder="Enter coin amount">
                            <div id="withdrawal-usd-value" class="form-note">Withdrawal Value: $0.00 USD</div>
                        </div>
                        <div class="form-group">
                            <label for="withdrawal-method">Withdrawal Method</label>
                            <select id="withdrawal-method" name="method" class="form-select" required>
                                <option value="">Select Method...</option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>

                        <div class="form-group paypal-details" style="display: none;">
                            <label for="paypal-email">PayPal Email Address</label>
                            <input type="email" id="paypal-email" name="paypal_email" class="form-input" placeholder="your.paypal@email.com">
                            <p class="form-note">Ensure this email is correct and verified with PayPal.</p>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submit-withdrawal-btn" disabled>Request Withdrawal</button>
                        </div>
                        <div id="withdrawal-error" class="form-error"></div>
                    </form>
                </div>

                <div class="withdrawal-box withdrawal-history">
                    <h3>Withdrawal History</h3>
                    <?php if (empty($history)): ?>
                        <p>You have no withdrawal history.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date Requested</th>
                                    <th>Coins</th>
                                    <th>Payout (USD)</th>
                                    <th>Method</th>
                                    <th>Destination</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $item): ?>
                                <tr>
                                    <td><?php echo esc_html(date('M j, Y H:i', strtotime($item['requested_at']))); ?></td>
                                    <td><?php echo esc_html(number_format((float)$item['amount_coins'])); ?><img src="<?php echo SUD_IMG_URL; ?>/sud-coin.png" alt="SUD" class="coin-xxs"></td>
                                     <td>$<?php echo esc_html(number_format((float)$item['net_payout_usd'], 2)); ?></td>
                                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $item['method']))); ?></td>
                                    <td><?php
                                        if ($item['method'] === 'paypal' && !empty($item['destination'])) {
                                            $parts = explode('@', $item['destination']);
                                            if (count($parts) === 2) echo esc_html(substr($parts[0], 0, 2) . '***@' . $parts[1]);
                                            else echo esc_html(substr($item['destination'], 0, 3) . '...***');
                                        } else { echo 'Details Hidden'; }
                                    ?></td>
                                    <td><span class="status-<?php echo esc_attr($item['status']); ?>"><?php echo esc_html(ucfirst($item['status'])); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="toast-container" class="toast-container"></div>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-footer.php'); ?>

    <?php if ($can_withdraw_at_all): // Only include JS if withdrawal form is potentially shown ?>
    <script>
        jQuery(document).ready(function($) {
            const coinBalance = <?php echo floatval($coin_balance); ?>;
            const prefillCoins = <?php echo intval($prefill_amount_coins); ?>;

            const minWithdrawalUsd = <?php echo floatval($minimum_withdrawal_usd); ?>;

            const coinToUsdRate = <?php echo defined('SUD_COIN_WITHDRAWAL_RATE_USD') ? SUD_COIN_WITHDRAWAL_RATE_USD : 0.10; ?>;
            const $amountInput = $('#withdrawal-amount');
            const $payoutValueDisplay = $('#withdrawal-usd-value');
            const $methodSelect = $('#withdrawal-method');
            const $paypalDetails = $('.paypal-details');
            const $paypalEmailInput = $('#paypal-email');
            const $submitBtn = $('#submit-withdrawal-btn');
            const $form = $('#withdrawal-request-form');
            const $errorDiv = $('#withdrawal-error');

            if (prefillCoins > 0 && prefillCoins <= coinBalance) {
                $amountInput.val(prefillCoins);
                let prefillUsdValue = (prefillCoins * coinToUsdRate).toFixed(2);
                $payoutValueDisplay.text('Withdrawal Value: $' + prefillUsdValue + ' USD');
                $amountInput.trigger('input'); 

            } else {
                $payoutValueDisplay.text('Withdrawal Value: $0.00 USD');
            }

            $amountInput.on('input change keyup', function() {
                let amountCoins = parseFloat($(this).val()) || 0;
                if (amountCoins < 0) amountCoins = 0;

                if (amountCoins > coinBalance) {
                    amountCoins = coinBalance;
                    $(this).val(amountCoins);
                }

                let usdValue = (amountCoins * coinToUsdRate).toFixed(2);
                $payoutValueDisplay.text('Withdrawal Value: $' + usdValue + ' USD');
                validateForm(); 
            });

            $methodSelect.on('change', function() {
                let selectedMethod = $(this).val();
                $paypalDetails.toggle(selectedMethod === 'paypal');
                validateForm();
            });

            $paypalEmailInput.on('input change keyup', validateForm);

            function validateForm() {
                $errorDiv.hide().text('');
                let isValid = true;
                let amount = parseFloat($amountInput.val()) || 0;
                let method = $methodSelect.val();
                let usdValue = amount * coinToUsdRate;

                if (isNaN(amount) || amount <= 0) { 
                    isValid = false;
                    if (amount < 0) $errorDiv.text('Amount cannot be negative.').show();
                }
                if (amount > coinBalance) {
                    isValid = false;
                    $errorDiv.text('Amount exceeds balance.').show();
                }
                
                if (minWithdrawalUsd > 0 && usdValue > 0 && usdValue < minWithdrawalUsd) {
                    isValid = false;
                    $errorDiv.text('Withdrawal amount is below the $' + minWithdrawalUsd.toFixed(2) + ' minimum.').show();
                }

                if (!method) { isValid = false; }
                if (method === 'paypal') {
                    let email = $paypalEmailInput.val().trim();
                    let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!email || !emailRegex.test(email)) { isValid = false; if(email) $errorDiv.text('Invalid PayPal email.').show(); }
                }
                $submitBtn.prop('disabled', !isValid);
                return isValid;
            }

            $form.on('submit', function(e) {
                e.preventDefault();
                if (!validateForm() || $submitBtn.prop('disabled')) return;

                const originalButtonText = $submitBtn.html();
                $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Requesting...');
                $errorDiv.hide().text('');
                const formData = $(this).serialize();

                $.ajax({
                    type: 'POST',
                    url: sud_config_base.sud_url + '/ajax/request-withdrawal.php',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.success) {
                            SUD.showToast('success', 'Request Submitted', response.data.message || 'Withdrawal request submitted.');
                             if(response.data.new_balance_formatted && typeof response.data.new_balance_raw !== 'undefined') {
                                 const newBalanceRaw = parseFloat(response.data.new_balance_raw);
                                 $('.balance-amount').html('<img src="<?php echo SUD_IMG_URL; ?>/sud-coin.png" alt="SUD"> ' + response.data.new_balance_formatted);
                                 $('.sud-coin .coin-count').text(response.data.new_balance_formatted);
                                 $amountInput.attr('max', newBalanceRaw);
                             }
                            $form[0].reset();
                            $payoutValueDisplay.text('Withdrawal Value: $0.00 USD');
                            $paypalDetails.hide();
                            $submitBtn.prop('disabled', true).html('Submitted');
                            setTimeout(function(){ location.reload(); }, 3000);
                        } else {
                            SUD.showToast('error', 'Request Failed', response.data.message || 'Could not submit request.');
                            $submitBtn.prop('disabled', false).html(originalButtonText);
                            $errorDiv.text(response.data.message || 'An unknown error occurred.').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        SUD.showToast('error', 'Connection Error', 'Could not connect to server. Please try again.');
                        console.error("Withdrawal AJAX error:", xhr.status, error, xhr.responseText);
                        $submitBtn.prop('disabled', false).html(originalButtonText);
                         $errorDiv.text('A connection error occurred. Please check your internet and try again.').show();
                    }
                });
            });

            // Initial validation check
            validateForm();
        });
    </script>
    <?php endif; ?>
</body>
</html>