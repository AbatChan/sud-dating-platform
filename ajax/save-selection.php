<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/notification-functions.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = isset($_POST['action']) ? sanitize_key($_POST['action']) : ''; 

if ($action !== 'save_gender' && $action !== 'save_looking_for') {
    if (!is_user_logged_in()) {
         if (!isset($_SESSION['join_session_id'])) {
            echo json_encode(['success' => false, 'message' => 'User not logged in.']);
            exit;
         }
         if($action === 'toggle_favorite' || $action === 'save_profile_step') {
            echo json_encode(['success' => false, 'message' => 'Login required for this action.']);
            exit;
         }
    }
}

if ($action === 'save_gender') {
    if (!isset($_SESSION['join_session_id'])) {
         echo json_encode(['success' => false, 'message' => 'Invalid session.']); exit;
    }
    $gender = isset($_POST['gender']) ? sanitize_text_field($_POST['gender']) : '';
    if (empty($gender)) {
        echo json_encode(['success' => false, 'message' => 'Missing gender']); exit;
    }

    if (function_exists('join_save_progress')) {
        $result = join_save_progress($_SESSION['join_session_id'], [
            'gender' => $gender,
            'last_step' => 'gender-selection'
        ]);
        echo json_encode(['success' => (bool)$result]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Saving function unavailable.']);
    }
    exit;
}

if ($action === 'save_looking_for') {
     if (!isset($_SESSION['join_session_id'])) {
         echo json_encode(['success' => false, 'message' => 'Invalid session.']); exit;
    }
    $looking_for = isset($_POST['looking_for']) ? sanitize_text_field($_POST['looking_for']) : '';
    if (empty($looking_for)) {
        echo json_encode(['success' => false, 'message' => 'Missing looking_for']); exit;
    }
     if (function_exists('join_save_progress')) {
        $result = join_save_progress($_SESSION['join_session_id'], [
            'looking_for' => $looking_for,
            'last_step' => 'looking-for'
        ]);
        echo json_encode(['success' => (bool)$result]);
    } else {
         echo json_encode(['success' => false, 'message' => 'Saving function unavailable.']);
    }
    exit;
}

if ($action === 'save_profile_step') {
    $user_id = get_current_user_id();
    $step = isset($_POST['step']) ? intval($_POST['step']) : 0;
    $data = isset($_POST['data']) && is_array($_POST['data']) ? $_POST['data'] : [];

    if ($step <= 0 || empty($data)) {
        echo json_encode(['success' => false, 'message' => 'Missing step or data.']);
        exit;
    }

    $success = true;
    foreach ($data as $key => $value) {
        $sanitized_key = sanitize_key($key);
        $sanitized_value = sanitize_text_field($value); 
        if (!update_user_meta($user_id, $sanitized_key, $sanitized_value)) {
            error_log("save_profile_step: Failed to update meta for key {$sanitized_key} for user {$user_id}");
        }
    }
    update_user_meta($user_id, 'completed_step_' . $step, true);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle_favorite') {
    if (!function_exists('toggle_user_favorite')) {
         echo json_encode(['success' => false, 'message' => 'Server configuration error [TFNC].']);
         exit;
    }

    $user_id_to_toggle = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $status = isset($_POST['favorite']) && $_POST['favorite'] == '1';

    if ($user_id_to_toggle <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID provided.']);
        exit;
    }
    $result = toggle_user_favorite($user_id_to_toggle, $status);
    if ($result !== false) { 
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not update favorite status.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
exit;
?>