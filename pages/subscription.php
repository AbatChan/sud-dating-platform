<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/payment-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/pricing-config.php');
require_once(dirname(__FILE__, 2) . '/includes/transaction-functions.php');

require_login();

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// Get user's subscription data
$subscription_id = get_user_meta($current_user_id, 'subscription_id', true);
$payment_method = get_user_meta($current_user_id, 'payment_method', true);
$subscription_auto_renew = get_user_meta($current_user_id, 'subscription_auto_renew', true);
$subscription_start_date = get_user_meta($current_user_id, 'subscription_start_date', true);
$subscription_end_date = get_user_meta($current_user_id, 'subscription_end_date', true);
$subscription_billing_type = get_user_meta($current_user_id, 'subscription_billing_type', true) ?: 'monthly';

// Get current plan details and trial status
$premium_details = sud_get_user_current_plan_details($current_user_id);
$current_plan = $premium_details['id'] ?? 'free';
$is_premium = $current_plan !== 'free';

// Check trial status
$active_trial = sud_get_active_trial($current_user_id);
$is_on_trial = $active_trial !== false;



// Check if subscription is set to cancel
$is_cancelled = $subscription_auto_renew === '0' && !empty($subscription_id);

// Get user transactions
$user_transactions = sud_get_user_transactions($current_user_id, 100);

// Try to get the actual subscription price from the most recent subscription transaction
$actual_subscription_price = null;
foreach ($user_transactions as $transaction) {
    if (in_array($transaction['type'], ['subscription', 'premium_charge']) && is_numeric($transaction['amount'])) {
        $actual_subscription_price = floatval($transaction['amount']);
        break;
    }
}

$page_title = 'Subscription Management';
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
    <title><?php echo esc_html($page_title); ?> - <?php echo esc_html(SUD_SITE_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
    <style>
        .subscription-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
        }
        
        .subscription-main {
            min-width: 0;
        }
        
        .subscription-card {
            background: #161616;
            border: 1px solid #404040;
            border-radius: 12px;
            padding: 30px;
        }
        
        .plan-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .plan-info h2 {
            color: #FFFFFF;
            margin: 0 0 5px 0;
            font-size: 24px;
        }
        
        .plan-info .plan-price {
            color: #FF66CC;
            font-size: 18px;
            font-weight: 600;
        }
        
        .plan-badge {
            background: #FF66CC;
            color: #FFFFFF;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .subscription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .detail-item {
            background: #262626;
            padding: 15px;
            border-radius: 8px;
        }
        
        .detail-label {
            color: #CCCCCC;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #FFFFFF;
            font-size: 16px;
            font-weight: 600;
        }
        
        .status-active {
            color: #10B981;
        }
        
        .status-cancelled {
            color: #EF4444;
        }
        
        .status-warning {
            color: #F59E0B;
        }
        
        .cancel-section {
            background: #262626;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .cancel-warning {
            background: #FEF3C7;
            border: 1px solid #F59E0B;
            color: #92400E;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .cancel-info {
            background: #DBEAFE;
            border: 1px solid #3B82F6;
            color: #1E40AF;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-danger {
            background: #EF4444;
            color: #FFFFFF;
        }
        
        .btn-danger:hover {
            background: #DC2626;
        }
        
        .btn-primary {
            background: #FF66CC;
            color: #FFFFFF;
        }
        
        .btn-primary:hover {
            background: #E659B5;
            color: white !important;
        }
        
        .btn-secondary {
            background: #6B7280;
            color: #FFFFFF;
        }
        
        .btn-secondary:hover {
            background: #4B5563;
        }
        
        .free-plan-message {
            text-align: center;
            padding: 40px;
            color: #CCCCCC;
        }
        
        .free-plan-message i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #6B7280;
        }
        
        .transactions-sidebar {
            background: #161616;
            border: 1px solid #404040;
            border-radius: 12px;
            padding: 20px;
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 120px);
            display: flex;
            flex-direction: column;
        }
        
        .transactions-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-shrink: 0;
        }
        
        .transactions-list {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        .transactions-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .transactions-list::-webkit-scrollbar-track {
            background: #262626;
            border-radius: 3px;
        }
        
        .transactions-list::-webkit-scrollbar-thumb {
            background: #404040;
            border-radius: 3px;
        }
        
        .transactions-list::-webkit-scrollbar-thumb:hover {
            background: #555555;
        }
        
        .transactions-footer {
            flex-shrink: 0;
            padding-top: 15px;
            border-top: 1px solid #404040;
        }
        
        .transactions-header h3 {
            color: #FFFFFF;
            margin: 0;
            font-size: 18px;
        }
        
        .view-all-link {
            color: #FF66CC;
            text-decoration: none;
            font-size: 12px;
        }
        
        .view-all-link:hover {
            text-decoration: underline;
        }
        
        .transaction-item {
            display: flex;
            align-items: flex-start;
            padding: 12px 0;
            border-bottom: 1px solid #404040;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .transaction-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 14px;
            color: #FFFFFF;
        }
        
        .transaction-details {
            flex: 1;
            min-width: 0;
        }
        
        .transaction-description {
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .transaction-meta {
            color: #CCCCCC;
            font-size: 11px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .transaction-amount {
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 600;
            text-align: right;
            margin-top: 2px;
        }
        
        .subscription-dates {
            font-size: 10px !important;
            color: #999 !important;
            margin-top: 4px !important;
            line-height: 1.3;
        }
        
        .subscription-dates strong {
            color: #CCC !important;
        }
        
        .transaction-amount.negative {
            color: #EF4444;
        }
        
        .transaction-amount.positive {
            color: #10B981;
        }
        
        .empty-transactions {
            text-align: center;
            color: #CCCCCC;
            padding: 30px 0;
        }
        
        .empty-transactions i {
            font-size: 32px;
            margin-bottom: 10px;
            color: #6B7280;
        }
        
        @media (max-width: 768px) {
            .subscription-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .plan-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .subscription-details {
                grid-template-columns: 1fr;
            }
            
            .transactions-sidebar {
                order: -1;
                position: static;
                max-height: 400px;
            }
        }
    </style>
</head>
<body>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>
    <div id="toast-container" class="toast-container"></div>

    <main class="main-content">
        <h1 style="color: #FFFFFF;margin-bottom: 0px;max-width: 1200px;margin: auto;padding: 0 20px;">
            <i class="fas fa-credit-card" style="margin-right: 10px;"></i>
            Subscription Management
        </h1>
        <div class="subscription-container">
            <div class="subscription-main">

            <?php if ($is_on_trial): ?>
                <!-- Trial Plan -->
                <div class="subscription-card">
                    <div class="plan-header">
                        <div class="plan-info">
                            <h2><?php echo ucfirst($active_trial['plan']); ?> Trial</h2>
                            <div class="plan-price">3-Day Free Trial</div>
                        </div>
                        <div class="plan-badge">Trial Active</div>
                    </div>
                    
                    <div class="subscription-details">
                        <div class="detail-item">
                            <div class="detail-label">Trial Status</div>
                            <div class="detail-value status-active">
                                <i class="fas fa-clock"></i> <?php echo $active_trial['days_remaining']; ?> days remaining
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Trial Expires</div>
                            <div class="detail-value"><?php echo date('F j, Y', strtotime($active_trial['end'])); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Plan Type</div>
                            <div class="detail-value"><?php echo ucfirst($active_trial['plan']); ?> Tier</div>
                        </div>
                    </div>
                    
                    <div class="subscription-actions" style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <a href="<?php echo SUD_URL; ?>/pages/premium?direct_pay=true&plan=<?php echo $active_trial['plan']; ?>" class="btn btn-primary">
                            <i class="fas fa-crown"></i> Upgrade Now
                        </a>
                        <a href="mailto:support@swipeupdaddy.com?subject=Trial Cancellation Request" 
                           class="btn btn-secondary">
                            <i class="fas fa-envelope"></i> Cancel Trial
                        </a>
                    </div>
                </div>
            <?php elseif (!$is_premium): ?>
                <!-- Free Plan -->
                <div class="subscription-card">
                    <div class="free-plan-message">
                        <i class="fas fa-user"></i>
                        <h2 style="color: #FFFFFF; margin-bottom: 15px;">Free Plan</h2>
                        <p>You're currently on the free plan. Upgrade to unlock premium features!</p>
                        <a href="<?php echo SUD_URL; ?>/pages/premium" class="btn btn-primary" style="margin-top: 20px;">
                           Upgrade to Premium
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Premium Plan -->
                <div class="subscription-card">
                    <div class="plan-header">
                        <div class="plan-info">
                            <h2><?php echo esc_html($premium_details['name']); ?></h2>
                            <div class="plan-price">
                                <?php if ($subscription_billing_type === 'annual'): ?>
                                    <?php 
                                    // Try to get annual price, or calculate it from monthly + discount
                                    $annual_price = $premium_details['price_annually'] ?? $premium_details['annual_price'] ?? 0;
                                    
                                    // If no annual price, calculate from monthly price and discount
                                    if ($annual_price == 0 && isset($premium_details['price_monthly'])) {
                                        $monthly_price = $premium_details['price_monthly'];
                                        $discount_percent = $premium_details['annual_discount_percent'] ?? 0;
                                        $annual_without_discount = $monthly_price * 12;
                                        $annual_price = $annual_without_discount * (1 - ($discount_percent / 100));
                                        
                                    }
                                    
                                    // Use actual transaction price as final fallback
                                    if ($annual_price == 0 && $actual_subscription_price) {
                                        $annual_price = $actual_subscription_price;
                                    }
                                    ?>
                                    $<?php echo number_format($annual_price, 2); ?>/year
                                <?php else: ?>
                                    <?php 
                                    $monthly_price = $premium_details['price_monthly'] ?? $premium_details['monthly_price'] ?? 0;
                                    // Use actual transaction price as fallback if plan details don't have monthly price
                                    if ($monthly_price == 0 && $actual_subscription_price) {
                                        $monthly_price = $actual_subscription_price;
                                    }
                                    ?>
                                    $<?php echo number_format($monthly_price, 2); ?>/month
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($premium_details['badge'])): ?>
                            <div class="plan-badge"><?php echo esc_html($premium_details['badge']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="subscription-details">
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value <?php echo $is_cancelled ? 'status-cancelled' : 'status-active'; ?>">
                                <?php if ($is_cancelled): ?>
                                    <i class="fas fa-exclamation-triangle"></i> Cancelling
                                <?php else: ?>
                                    <i class="fas fa-check-circle"></i> Active
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php 
                        // Get subscription start from multiple possible sources
                        $start_date = $subscription_start_date ?: get_user_meta($current_user_id, 'subscription_start', true);
                        if ($start_date): 
                        ?>
                        <div class="detail-item">
                            <div class="detail-label">Started</div>
                            <div class="detail-value">
                                <?php echo date('M j, Y', strtotime($start_date)); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php 
                        // Get subscription end from multiple possible sources
                        $end_date = $subscription_end_date ?: get_user_meta($current_user_id, 'subscription_expires', true);
                        if ($end_date): 
                            // Validate date makes sense for billing cycle
                            if ($start_date && $end_date) {
                                $start_timestamp = strtotime($start_date);
                                $end_timestamp = strtotime($end_date);
                                $days_difference = ($end_timestamp - $start_timestamp) / (60 * 60 * 24);
                            }
                        ?>
                        <div class="detail-item">
                            <div class="detail-label"><?php echo $is_cancelled ? 'Ends' : 'Renews'; ?></div>
                            <div class="detail-value">
                                <?php echo date('M j, Y', strtotime($end_date)); ?>
                                <?php if ($subscription_billing_type === 'annual' && isset($days_difference) && $days_difference < 300): ?>
                                    <span style="color: #F59E0B; font-size: 10px; display: block;">⚠️ Date may be incorrect for annual plan</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($payment_method): ?>
                        <div class="detail-item">
                            <div class="detail-label">Payment Method</div>
                            <div class="detail-value">
                                <?php if ($payment_method === 'stripe'): ?>
                                    <i class="fab fa-cc-stripe"></i> Stripe
                                <?php elseif ($payment_method === 'paypal'): ?>
                                    <i class="fab fa-paypal"></i> PayPal
                                <?php else: ?>
                                    <?php echo esc_html(ucfirst($payment_method)); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Benefits -->
                    <div style="margin: 20px 0;">
                        <h3 style="color: #FFFFFF; margin-bottom: 15px;">Your Benefits</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                            <?php foreach ($premium_details['benefits'] as $benefit): ?>
                                <div style="color: #CCCCCC; padding: 8px 0;">
                                    <i class="fas fa-check" style="color: #10B981; margin-right: 8px;"></i>
                                    <?php echo esc_html($benefit); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Cancellation Section -->
                <?php if (!$is_cancelled && !empty($subscription_id)): ?>
                    <div class="cancel-section">
                        <h3 style="color: #FFFFFF; margin-bottom: 15px;">
                            <i class="fas fa-headset" style="color: #6B7280; margin-right: 8px;"></i>
                            Need Help?
                        </h3>
                        
                        <div class="cancel-warning">
                            <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                            <strong>Need to make changes?</strong> Please contact our support team for assistance with subscription modifications or cancellations.
                        </div>

                        <?php if ($subscription_end_date): ?>
                            <div class="cancel-info">
                                <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                                Your subscription will remain active until <strong><?php echo date('F j, Y', strtotime($subscription_end_date)); ?></strong>
                            </div>
                        <?php endif; ?>

                        <a href="mailto:support@swipeupdaddy.com" class="btn btn-secondary">
                            <i class="fas fa-headset"></i> Contact Support
                        </a>
                    </div>
                <?php elseif ($is_cancelled): ?>
                    <div class="cancel-section">
                        <h3 style="color: #F59E0B; margin-bottom: 15px;">
                            <i class="fas fa-clock" style="margin-right: 8px;"></i>
                            Subscription Cancelled
                        </h3>
                        
                        <div class="cancel-info">
                            <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                            Your subscription has been cancelled and will not auto-renew. You'll keep your premium benefits until 
                            <strong><?php echo $subscription_end_date ? date('F j, Y', strtotime($subscription_end_date)) : 'the end of your billing period'; ?></strong>.
                        </div>

                        <p style="color: #CCCCCC; margin: 15px 0;">
                            Want to continue enjoying premium benefits? You can reactivate your subscription anytime.
                        </p>

                        <a href="<?php echo SUD_URL; ?>/pages/premium" class="btn btn-primary">
                            <i class="fas fa-redo"></i> Reactivate Subscription
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            </div>
            
            <!-- Transactions Sidebar -->
            <div class="transactions-sidebar">
                <div class="transactions-header">
                    <h3><i class="fas fa-history" style="margin-right: 8px;"></i>Recent Transactions</h3>
                    <a href="<?php echo SUD_URL; ?>/pages/wallet" class="view-all-link">View All</a>
                </div>
                
                <div class="transactions-list">
                    <?php if (!empty($user_transactions)): ?>
                        <?php foreach ($user_transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-icon" style="background-color: <?php echo esc_attr($transaction['color']); ?>">
                                    <i class="<?php echo esc_attr($transaction['icon']); ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-description" title="<?php echo esc_attr($transaction['description']); ?>">
                                        <?php echo esc_html($transaction['description']); ?>
                                    </div>
                                    <div class="transaction-meta">
                                        <span><?php echo esc_html(sud_format_payment_method($transaction['payment_method'])); ?></span>
                                        <span><?php echo esc_html(date('M j, Y', strtotime($transaction['created_at']))); ?></span>
                                    </div>
                                </div>
                                <div class="transaction-amount <?php echo (strpos($transaction['amount'], '-') === 0) ? 'negative' : 'positive'; ?>">
                                    <?php echo esc_html(sud_format_transaction_amount($transaction['amount'], $transaction['type'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-transactions">
                            <i class="fas fa-receipt"></i>
                            <p>No transactions yet</p>
                            <small>Your subscription, coin purchases, and other payments will appear here</small>
                            <?php if (!$is_premium): ?>
                                <div style="margin-top: 15px;">
                                    <a href="<?php echo SUD_URL; ?>/pages/premium" class="btn btn-primary" style="font-size: 12px; padding: 8px 16px;">
                                        Subscribe to Premium
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="transactions-footer">
                    <a href="<?php echo SUD_URL; ?>/pages/wallet" class="btn btn-secondary" style="width: 100%; text-align: center; display: block; text-decoration: none;">
                        <i class="fas fa-wallet" style="margin-right: 8px;"></i>
                        Manage Wallet
                    </a>
                </div>
            </div>
        </div>
    </main>

    <?php include(dirname(__FILE__, 2) . '/templates/components/user-footer.php'); ?>

</body>
</html>