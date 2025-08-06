<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all transactions for a user across different transaction types
 */
function sud_get_user_transactions($user_id, $limit = 50) {
    global $wpdb;
    
    $transactions = [];
    
    
    // Get subscription/premium transactions
    $subscription_transactions = $wpdb->get_results($wpdb->prepare("
        SELECT 
            'subscription' as type,
            description,
            amount,
            payment_method,
            transaction_id,
            status,
            created_at
        FROM {$wpdb->prefix}sud_transactions
        WHERE user_id = %d
        ORDER BY created_at DESC
    ", $user_id));
    
    foreach ($subscription_transactions as $transaction) {
        // Format description dynamically based on context
        $description = $transaction->description;
        $current_user_id = get_current_user_id();
        
        // If viewing own transactions, use "You purchased", otherwise "User purchased"
        if ($user_id == $current_user_id) {
            // Check if description is just the item name (new format)
            if (!str_contains($description, 'purchased') && !str_contains($description, 'Plan')) {
                $description = "You purchased " . $description;
            } elseif (str_starts_with($description, 'User purchased')) {
                $description = str_replace('User purchased', 'You purchased', $description);
            }
        } else {
            // Admin or other user view
            if (!str_contains($description, 'purchased') && !str_contains($description, 'Plan')) {
                $user_data = get_userdata($user_id);
                $username = $user_data ? $user_data->display_name : 'User';
                $description = $username . " purchased " . $description;
            }
        }
        
        // Determine transaction type and icon based on description
        $transaction_type = 'subscription';
        $icon = 'fas fa-crown';
        $color = '#FF66CC';
        
        // Check if it's a boost transaction
        if (str_contains(strtolower($description), 'boost')) {
            $transaction_type = 'boost';
            $icon = 'fas fa-rocket';
            $color = '#9B59B6';
        }
        // Check if it's a super swipe transaction
        elseif (str_contains(strtolower($description), 'swipe')) {
            $transaction_type = 'super_swipe';
            $icon = 'fas fa-heart';
            $color = '#E91E63';
        }
        // Check if it's coin transaction
        elseif (str_contains(strtolower($description), 'coin')) {
            $transaction_type = 'coins';
            $icon = 'fas fa-coins';
            $color = '#F2D04F';
        }
        
        $transactions[] = [
            'type' => $transaction_type,
            'description' => $description,
            'amount' => $transaction->amount,
            'payment_method' => $transaction->payment_method,
            'transaction_id' => $transaction->transaction_id,
            'status' => $transaction->status,
            'created_at' => $transaction->created_at,
            'icon' => $icon,
            'color' => $color
        ];
    }
    
    // Get coin transactions
    $coin_transactions = $wpdb->get_results($wpdb->prepare("
        SELECT 
            'coins' as type,
            coin_amount,
            price as amount,
            payment_method,
            transaction_id,
            status,
            created_at
        FROM {$wpdb->prefix}sud_coin_transactions
        WHERE user_id = %d
        ORDER BY created_at DESC
    ", $user_id));
    
    foreach ($coin_transactions as $transaction) {
        $transactions[] = [
            'type' => 'coins',
            'description' => number_format($transaction->coin_amount) . ' Coins',
            'amount' => $transaction->amount,
            'payment_method' => $transaction->payment_method,
            'transaction_id' => $transaction->transaction_id,
            'status' => $transaction->status,
            'created_at' => $transaction->created_at,
            'icon' => 'fas fa-coins',
            'color' => '#F2D04F'
        ];
    }
    
    // Get gift transactions (sent)
    $gift_transactions = $wpdb->get_results($wpdb->prepare("
        SELECT 
            'gift_sent' as type,
            gift_name,
            coin_amount,
            cash_value,
            receiver_id,
            created_at
        FROM {$wpdb->prefix}sud_gift_transactions
        WHERE sender_id = %d
        ORDER BY created_at DESC
    ", $user_id));
    
    foreach ($gift_transactions as $transaction) {
        $receiver = get_userdata($transaction->receiver_id);
        $receiver_name = $receiver ? $receiver->display_name : 'Unknown User';
        
        $transactions[] = [
            'type' => 'gift_sent',
            'description' => $transaction->gift_name . ' to ' . $receiver_name,
            'amount' => '-' . $transaction->coin_amount . ' coins',
            'payment_method' => 'coins',
            'transaction_id' => null,
            'status' => 'completed',
            'created_at' => $transaction->created_at,
            'icon' => 'fas fa-gift',
            'color' => '#E74C3C'
        ];
    }
    
    // Get boost transactions from user meta
    $boost_history = get_user_meta($user_id, 'boost_history', true);
    if (is_array($boost_history)) {
        foreach ($boost_history as $boost) {
            if (isset($boost['timestamp']) && isset($boost['cost'])) {
                $transactions[] = [
                    'type' => 'boost',
                    'description' => 'Profile Boost',
                    'amount' => '-' . $boost['cost'] . ' coins',
                    'payment_method' => 'coins',
                    'transaction_id' => null,
                    'status' => 'completed',
                    'created_at' => date('Y-m-d H:i:s', $boost['timestamp']),
                    'icon' => 'fas fa-rocket',
                    'color' => '#9B59B6'
                ];
            }
        }
    }
    
    // Get super swipe transactions from user meta
    $super_swipe_history = get_user_meta($user_id, 'super_swipe_purchase_history', true);
    if (is_array($super_swipe_history)) {
        foreach ($super_swipe_history as $swipe) {
            if (isset($swipe['timestamp']) && isset($swipe['cost'])) {
                $transactions[] = [
                    'type' => 'super_swipe',
                    'description' => 'Super Swipe Credits',
                    'amount' => '-' . $swipe['cost'] . ' coins',
                    'payment_method' => 'coins',
                    'transaction_id' => null,
                    'status' => 'completed',
                    'created_at' => date('Y-m-d H:i:s', $swipe['timestamp']),
                    'icon' => 'fas fa-heart',
                    'color' => '#E91E63'
                ];
            }
        }
    }
    
    // Get premium charges/renewals from user meta
    $premium_payment_history = get_user_meta($user_id, 'premium_payment_history', true);
    
    if (is_array($premium_payment_history)) {
        foreach ($premium_payment_history as $payment) {
            if (isset($payment['timestamp']) && isset($payment['amount'])) {
                $plan_name = $payment['plan_name'] ?? 'Premium Plan';
                $billing = $payment['billing_cycle'] ?? 'monthly';
                $transactions[] = [
                    'type' => 'premium_charge',
                    'description' => $plan_name . ' (' . ucfirst($billing) . ')',
                    'amount' => $payment['amount'],
                    'payment_method' => $payment['payment_method'] ?? 'stripe',
                    'transaction_id' => $payment['transaction_id'] ?? null,
                    'status' => 'completed',
                    'created_at' => date('Y-m-d H:i:s', $payment['timestamp']),
                    'icon' => 'fas fa-crown',
                    'color' => '#FF66CC'
                ];
            }
        }
    }
    
    // Check current subscription details for creating a mock recent transaction if user is premium
    $current_plan = get_user_meta($user_id, 'premium_plan', true);
    $subscription_start = get_user_meta($user_id, 'subscription_start', true);
    $subscription_billing_type = get_user_meta($user_id, 'subscription_billing_type', true);
    
    if ($current_plan && $current_plan !== 'free' && $subscription_start) {
        // Check if we already have subscription transactions from database
        $has_subscription_transactions = false;
        foreach ($transactions as $transaction) {
            if (in_array($transaction['type'], ['subscription', 'premium_charge', 'subscription_renewal'])) {
                $has_subscription_transactions = true;
                break;
            }
        }
        
        // Add current subscription as a transaction if no premium history exists AND no database transactions
        if (empty($premium_payment_history) && !$has_subscription_transactions && function_exists('sud_get_user_current_plan_details')) {
            $plan_details = sud_get_user_current_plan_details($user_id);
            if ($plan_details && $plan_details['id'] !== 'free') {
                $amount = $subscription_billing_type === 'annual' 
                    ? ($plan_details['price_annually'] ?? $plan_details['annual_price'] ?? 0)
                    : ($plan_details['price_monthly'] ?? $plan_details['monthly_price'] ?? 0);
                
                $transactions[] = [
                    'type' => 'subscription',
                    'description' => $plan_details['name'] . ' (' . ucfirst($subscription_billing_type ?? 'monthly') . ')',
                    'amount' => $amount,
                    'payment_method' => get_user_meta($user_id, 'payment_method', true) ?: 'stripe',
                    'transaction_id' => get_user_meta($user_id, 'subscription_id', true),
                    'status' => 'completed',
                    'created_at' => $subscription_start,
                    'icon' => 'fas fa-crown',
                    'color' => '#FF66CC'
                ];
            }
        }
    }
    
    // Get subscription renewals from Stripe webhook data if available
    $stripe_renewals = get_user_meta($user_id, 'subscription_renewals', true);
    if (is_array($stripe_renewals)) {
        foreach ($stripe_renewals as $renewal) {
            if (isset($renewal['created']) && isset($renewal['amount'])) {
                $transactions[] = [
                    'type' => 'subscription_renewal',
                    'description' => 'Subscription Renewal',
                    'amount' => ($renewal['amount'] / 100), // Convert cents to dollars
                    'payment_method' => 'stripe',
                    'transaction_id' => $renewal['subscription_id'] ?? null,
                    'status' => 'completed',
                    'created_at' => date('Y-m-d H:i:s', $renewal['created']),
                    'icon' => 'fas fa-sync-alt',
                    'color' => '#FF66CC'
                ];
            }
        }
    }
    
    // This fallback is no longer needed since we have proper database storage
    
    
    // Sort all transactions by date (newest first)
    usort($transactions, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Limit results
    if ($limit > 0) {
        $transactions = array_slice($transactions, 0, $limit);
    }
    
    return $transactions;
}

/**
 * Format transaction amount for display
 */
function sud_format_transaction_amount($amount, $type) {
    if ($type === 'coins' && is_numeric($amount)) {
        return '$' . number_format($amount, 2);
    } elseif (strpos($amount, 'coins') !== false) {
        return $amount; // Already formatted like "-50 coins"
    } elseif (is_numeric($amount)) {
        return '$' . number_format($amount, 2);
    }
    return $amount;
}

/**
 * Get transaction type display name
 */
function sud_get_transaction_type_name($type) {
    $types = [
        'subscription' => 'Subscription',
        'premium_charge' => 'Premium Plan',
        'subscription_renewal' => 'Subscription Renewal',
        'coins' => 'Coins Purchase',
        'gift_sent' => 'Gift Sent',
        'boost' => 'Profile Boost',
        'super_swipe' => 'Super Swipe'
    ];
    
    return $types[$type] ?? ucfirst($type);
}

/**
 * Format payment method for display
 */
function sud_format_payment_method($method) {
    $methods = [
        'stripe' => 'Credit Card',
        'paypal' => 'PayPal',
        'coins' => 'Coins'
    ];
    
    return $methods[$method] ?? ucfirst($method);
}