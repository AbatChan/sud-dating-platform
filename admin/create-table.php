<?php
// Admin page to create missing database tables
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_login();

// Check if user is admin
if (!current_user_can('administrator')) {
    wp_die('You do not have permission to access this page.');
}

// Include database setup functions
if (!function_exists('sud_create_user_likes_table')) {
    require_once(dirname(__FILE__, 2) . '/includes/database-setup.php');
}

$message = '';
$error = '';

if (isset($_POST['create_table'])) {
    try {
        if (!function_exists('sud_create_user_likes_table')) {
            $error = "ERROR: sud_create_user_likes_table function not found. Check if database-setup.php is included.";
        } else {
            // Force table creation
            sud_create_user_likes_table();
            
            // Check if table was created
            global $wpdb;
            $table_name = $wpdb->prefix . 'sud_user_likes';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            
            if ($table_exists) {
                $message = "SUCCESS: Table '$table_name' created successfully!";
            } else {
                $error = "ERROR: Table '$table_name' was not created. Database error: " . $wpdb->last_error;
            }
        }
    } catch (Exception $e) {
        $error = "Exception: " . $e->getMessage();
    } catch (Error $e) {
        $error = "Fatal Error: " . $e->getMessage();
    }
}

if (isset($_POST['create_all_tables'])) {
    try {
        // Force all table creation
        sud_create_all_tables();
        $message = "All tables creation process completed. Check database for results.";
    } catch (Exception $e) {
        $error = "Exception during table creation: " . $e->getMessage();
    }
}

// Check current table status
global $wpdb;
$table_name = $wpdb->prefix . 'sud_user_likes';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Missing Database Tables - SUD Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; background: #e6ffe6; padding: 10px; border: 1px solid green; }
        .error { color: red; background: #ffe6e6; padding: 10px; border: 1px solid red; }
        .status { padding: 10px; background: #f0f0f0; border: 1px solid #ccc; }
        button { padding: 10px 20px; margin: 10px 0; background: #0073aa; color: white; border: none; cursor: pointer; }
        button:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>Create Missing Database Tables</h1>
    
    <?php if ($message): ?>
        <div class="success"><?php echo esc_html($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error"><?php echo esc_html($error); ?></div>
    <?php endif; ?>
    
    <div class="status">
        <h3>Current Status:</h3>
        <p><strong>Table <?php echo esc_html($table_name); ?>:</strong> 
        <?php echo $table_exists ? '<span style="color: green;">EXISTS</span>' : '<span style="color: red;">MISSING</span>'; ?></p>
        
        <p><strong>Database Version:</strong> <?php echo esc_html(get_option('sud_db_version', 'Not set')); ?></p>
    </div>
    
    <form method="post">
        <h3>Actions:</h3>
        <button type="submit" name="create_table">Create sud_user_likes Table Only</button>
        <br>
        <button type="submit" name="create_all_tables">Create All SUD Tables</button>
    </form>
    
    <h3>Manual SQL (if needed):</h3>
    <textarea readonly style="width: 100%; height: 200px;">
CREATE TABLE <?php echo esc_html($table_name); ?> (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    liker_user_id BIGINT UNSIGNED NOT NULL,
    liked_user_id BIGINT UNSIGNED NOT NULL,
    like_type VARCHAR(50) NOT NULL,
    like_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_liker_user_id (liker_user_id),
    INDEX idx_liked_user_id (liked_user_id),
    INDEX idx_like_type (like_type),
    INDEX idx_combined_lookup (liker_user_id, liked_user_id, like_type)
) <?php echo $wpdb->get_charset_collate(); ?>;
    </textarea>
    
    <p><a href="<?php echo SUD_URL; ?>/pages/activity">← Back to Activity Page</a></p>
</body>
</html>