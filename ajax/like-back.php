<?php
// Set content type for JSON response
header('Content-Type: application/json');

try {
    require_once(dirname(__FILE__, 2) . '/includes/config.php');
    require_once(dirname(__FILE__, 2) . '/includes/swipe-functions.php');
    require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');

    // Basic authentication check
    if (!is_user_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit;
    }
    
    $current_user_id = get_current_user_id();
    
} catch (Exception $e) {
    error_log("Like back setup error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Setup error: ' . $e->getMessage()]);
    exit;
}

// Get and validate target user ID
$target_user_id = intval($_POST['target_user_id'] ?? 0);
if (!$target_user_id || $target_user_id === $current_user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid target user']);
    exit;
}

// Check if target user exists
$target_user = get_userdata($target_user_id);
if (!$target_user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

try {
    global $wpdb;
    $swipes_table = $wpdb->prefix . 'sud_user_swipes';
    
    // Check if the target user actually liked us first
    $existing_like = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$swipes_table} 
         WHERE swiper_user_id = %d AND swiped_user_id = %d AND swipe_type = 'like'",
        $target_user_id, $current_user_id
    ));
    
    if (!$existing_like) {
        echo json_encode(['success' => false, 'message' => 'This user has not liked you']);
        exit;
    }
    
    // Check if we already responded to this like
    $our_response = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$swipes_table} 
         WHERE swiper_user_id = %d AND swiped_user_id = %d",
        $current_user_id, $target_user_id
    ));
    
    if ($our_response) {
        if ($our_response->swipe_type === 'like') {
            echo json_encode(['success' => false, 'message' => 'You have already liked this user']);
        } else {
            echo json_encode(['success' => false, 'message' => 'You have already passed on this user']);
        }
        exit;
    }
    
    // Process the like back
    $swipe_result = sud_record_swipe($current_user_id, $target_user_id, 'like');
    
    if (!$swipe_result) {
        throw new Exception('Failed to process like back');
    }
    
    // Check if this created a match
    $match_result = sud_check_and_process_match($current_user_id, $target_user_id);
    
    $response = [
        'success' => true,
        'message' => 'Successfully liked back!',
        'is_match' => false,
        'match_data' => null
    ];
    
    if ($match_result && $match_result['is_match']) {
        $response['is_match'] = true;
        $response['message'] = 'It\'s a match!';
        
        // Get target user data for match popup
        $target_user_data = get_user_profile_data($target_user_id);
        if ($target_user_data) {
            $response['match_data'] = [
                'user_id' => $target_user_id,
                'name' => $target_user_data['name'] ?? 'User',
                'profile_pic' => $target_user_data['profile_pic'] ?? (SUD_IMG_URL . '/default-profile.jpg'),
                'profile_url' => SUD_URL . '/pages/profile?id=' . $target_user_id,
                'message_url' => SUD_URL . '/pages/messages?user=' . $target_user_id
            ];
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Like back error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your like']);
}
?>