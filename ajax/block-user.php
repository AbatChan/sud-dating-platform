<?php

require_once(dirname(__FILE__, 2) . '/includes/ajax-bootstrap.php');
require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');

try {
    // Use centralized bootstrap for consistent setup
    $current_user_id = sud_ajax_bootstrap([
        'methods' => ['POST'],
        'require_auth' => true,
        'require_nonce' => true,
        'nonce_action' => 'sud_ajax_action',
        'rate_limit' => ['requests' => 10, 'window' => 60, 'action' => 'block_user']
    ]);
    
    // Use centralized input validation
    $user_to_modify_id = sud_validate_input($_POST['user_id'] ?? 0, 'user_id');
    $action = sud_validate_input($_POST['action'] ?? 'block', 'text');

    if ($user_to_modify_id == $current_user_id) {
        throw new Exception('You cannot block/unblock yourself');
    }
    if ($action !== 'block' && $action !== 'unblock') {
        throw new Exception('Invalid action specified');
    }

    if (!function_exists('toggle_user_block')) {
        throw new Exception('Server error: Block functionality is unavailable.');
    }

    $should_block = ($action === 'block');
    $success = toggle_user_block($current_user_id, $user_to_modify_id, $should_block);

    if ($success) {
        if ($should_block && function_exists('toggle_user_favorite')) {
            toggle_user_favorite($user_to_modify_id, false);
        }

        // Use standardized success response
        sud_ajax_success([
            'message' => $should_block ? 'User blocked successfully.' : 'User unblocked successfully.'
        ]);

    } else {
        error_log("SUD AJAX Error: toggle_user_block failed for user {$current_user_id} and target {$user_to_modify_id}");
        throw new Exception($should_block ? 'Failed to block user due to a server issue.' : 'Failed to unblock user due to a server issue.');
    }

} catch (Exception $e) {
    sud_handle_ajax_error($e);
}