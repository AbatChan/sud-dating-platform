<?php

defined( 'ABSPATH' ) or die( 'Cannot access this file directly.' );

function sud_create_all_tables() {
    sud_create_join_progress_table();
    sud_create_gifts_table();
    sud_create_user_gifts_table();
    sud_create_messages_table();
    sud_create_blocked_users_table();
    sud_create_favorites_table();
    sud_create_subscriptions_table();
    sud_create_transactions_table();
    sud_create_withdrawals_table();

    sud_create_coin_transactions_table_setup();
    sud_create_gift_transactions_table_setup();
    sud_create_user_reports_table_setup();
    sud_create_notification_settings_table_setup();
    sud_create_monitored_messages_table_setup();
    sud_create_monitored_swipes_table_setup();

    sud_create_user_swipes_table();
    sud_create_user_likes_table();
    sud_create_daily_swipe_counts_table();
}

function sud_create_join_progress_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'join_registration_progress';
    $charset_collate = $wpdb->get_charset_collate();

    $table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name);
    if ($table_exists) return;

    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) UNSIGNED DEFAULT NULL,
        email varchar(100) DEFAULT NULL,
        gender varchar(50) DEFAULT NULL,
        looking_for varchar(50) DEFAULT NULL,
        role varchar(50) DEFAULT NULL,
        functional_role varchar(50) DEFAULT NULL,
        password varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        last_step varchar(100) DEFAULT NULL,
        verification_code varchar(10) DEFAULT NULL,
        verified tinyint(1) DEFAULT 0,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY session_id (session_id),
        KEY user_id (user_id),
        KEY email (email)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD Setup Error: Failed to create/update table $table_name");
    }
}

function sud_create_gifts_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_gifts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        icon varchar(100) DEFAULT NULL,
        image_filename varchar(100) DEFAULT NULL,
        cost int(11) NOT NULL DEFAULT 0,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        sort_order int(11) DEFAULT 0,
        PRIMARY KEY  (id),
        KEY `is_active` (`is_active`),
        KEY `sort_order` (`sort_order`)
    ) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name) {
     $gift_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($gift_count == 0) {
        $default_gifts = [

            ['name' => 'Ruby Heart',        'image_filename' => 'ruby-heart.png',        'cost' => 1000, 'sort_order' => 10], 
            ['name' => 'Royal Crown (Red)', 'image_filename' => 'royal-crown-red.png',   'cost' => 300,  'sort_order' => 20], 
            ['name' => 'Diamond Crown',     'image_filename' => 'diamond-crown-gold.png','cost' => 500,  'sort_order' => 30], 
            ['name' => 'Magic Castle',      'image_filename' => 'magic-castle.png',      'cost' => 1500, 'sort_order' => 40], 
            ['name' => 'Kiss Lips',         'image_filename' => 'kiss-lips.png',         'cost' => 75,   'sort_order' => 50], 
            ['name' => 'Teddy Bear',        'image_filename' => 'teddy-bear.png',        'cost' => 150,  'sort_order' => 60], 
            ['name' => 'Love Letter',       'image_filename' => 'love-letter.png',       'cost' => 99,   'sort_order' => 70], 
        
            ['name' => 'Wink Emoji',        'image_filename' => 'wink-emoji.png',        'cost' => 10,   'sort_order' => 100], 
            ['name' => 'Coffee Cup',        'image_filename' => 'coffee-cup.png',        'cost' => 50,   'sort_order' => 110], 
            ['name' => 'Single Rose',       'image_filename' => 'single-rose.png',       'cost' => 70,   'sort_order' => 120], 
            ['name' => 'Cupcake',           'image_filename' => 'cupcake.png',           'cost' => 85,   'sort_order' => 130], 
            ['name' => 'Cocktail Drink',    'image_filename' => 'cocktail-drink.png',    'cost' => 120,  'sort_order' => 140], 
            ['name' => 'Heart Balloon',     'image_filename' => 'heart-balloon.png',     'cost' => 150,  'sort_order' => 150], 
        
            ['name' => 'Strawberry Kiss',   'image_filename' => 'strawberry-kiss.png',   'cost' => 200,  'sort_order' => 200], 
            ['name' => 'Box of Chocolates', 'image_filename' => 'chocolates-box.png',    'cost' => 250,  'sort_order' => 210], 
            ['name' => 'Champagne Bottle',  'image_filename' => 'champagne-bottle.png',  'cost' => 300,  'sort_order' => 220], 
            ['name' => 'Rose Bouquet',      'image_filename' => 'rose-bouquet.png',      'cost' => 350,  'sort_order' => 230], 
            ['name' => 'Perfume Bottle',    'image_filename' => 'perfume-bottle.png',    'cost' => 400,  'sort_order' => 240], 
            ['name' => 'Heart Lock',        'image_filename' => 'heart-lock.png',        'cost' => 499,  'sort_order' => 250], 
        
            ['name' => 'Diamond Ring Box',  'image_filename' => 'ring-box-diamond.png',  'cost' => 750,  'sort_order' => 300], 
            ['name' => 'Golden Key',        'image_filename' => 'golden-key.png',        'cost' => 777,  'sort_order' => 310], 
            ['name' => 'Eiffel Tower',      'image_filename' => 'eiffel-tower.png',      'cost' => 850,  'sort_order' => 320], 
            ['name' => 'Necklace Box',      'image_filename' => 'necklace-box-open.png', 'cost' => 999,  'sort_order' => 330], 
            ['name' => 'Diamond Necklace',  'image_filename' => 'diamond-necklace.png',  'cost' => 1500, 'sort_order' => 340], 
            ['name' => 'Luxury Watch',      'image_filename' => 'luxury-watch-silver.png','cost' => 2000, 'sort_order' => 350], 
            ['name' => 'Sports Car (Red)',  'image_filename' => 'sports-car-red.png',    'cost' => 2500, 'sort_order' => 360], 
            ['name' => 'Small Yacht',       'image_filename' => 'small-yacht.png',       'cost' => 3500, 'sort_order' => 370], 
            ['name' => 'Private Jet Icon',  'image_filename' => 'private-jet-icon.png',  'cost' => 5000, 'sort_order' => 380], 
        
            ['name' => 'Galaxy Flower Lamp', 'image_filename' => 'galaxy-flower-lamp.png', 'cost' => 999, 'sort_order' => 390],
            ['name' => 'Royal Throne (Gold)', 'image_filename' => 'royal-throne-gold.png', 'cost' => 1800, 'sort_order' => 400],
            ['name' => 'Gold Crown (Simple)','image_filename' => 'gold-crown-simple.png','cost' => 199,  'sort_order' => 410], 
            ['name' => 'Cute Panda',        'image_filename' => 'cute-panda.png',        'cost' => 348,  'sort_order' => 420], 
            ['name' => 'Palm Tree Island',  'image_filename' => 'palm-tree-island.png',  'cost' => 450,  'sort_order' => 430], 
            ['name' => 'Angel Wings',       'image_filename' => 'angel-wings.png',       'cost' => 600,  'sort_order' => 440], 
            ['name' => 'Treasure Chest',    'image_filename' => 'treasure-chest.png',    'cost' => 650,  'sort_order' => 450],
        
        ];

        foreach ($default_gifts as $gift) {
            $insert_data = [
                'name' => $gift['name'],
                'icon' => $gift['icon'] ?? null,
                'image_filename' => $gift['image_filename'] ?? null,
                'cost' => $gift['cost'],
                'is_active' => 1,
                'sort_order' => $gift['sort_order']
            ];
            $wpdb->insert( $table_name, $insert_data );
        }
    }
    } else {
        error_log("SUD Setup Error: Failed to create table $table_name");
    }
}

function sud_create_user_gifts_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_gifts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        sender_id bigint(20) UNSIGNED NOT NULL,
        receiver_id bigint(20) UNSIGNED NOT NULL,
        gift_id bigint(20) UNSIGNED NOT NULL,
        cost_paid int(11) NOT NULL DEFAULT 0,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY `sender_id` (`sender_id`),
        KEY `receiver_id` (`receiver_id`),
        KEY `gift_id` (`gift_id`),
        KEY `timestamp` (`timestamp`)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD Setup Error: Failed to create table $table_name");
    }
}

function sud_create_messages_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        sender_id bigint(20) UNSIGNED NOT NULL,
        receiver_id bigint(20) UNSIGNED NOT NULL,
        message longtext NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        is_read tinyint(1) DEFAULT 0,
        deleted_by_sender tinyint(1) DEFAULT 0,
        deleted_by_receiver tinyint(1) DEFAULT 0,
        PRIMARY KEY  (id),
        KEY sender_receiver (sender_id, receiver_id),
        KEY receiver_read (receiver_id, is_read),
        KEY timestamp (timestamp)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD Setup Error: Failed to create table $table_name");
    }
}

function sud_create_blocked_users_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_blocked_users';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        blocked_user_id bigint(20) UNSIGNED NOT NULL,
        blocked_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_blocked_pair (user_id, blocked_user_id),
        KEY user_id (user_id),
        KEY blocked_user_id (blocked_user_id)
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta($sql);

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD Setup Error: Failed to create/update table $table_name. DB error: " . $wpdb->last_error);
    } else {
        error_log("SUD SUCCESS (database-setup): Table $table_name created/verified.");
    }
}

function sud_create_favorites_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_favorites';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        favorite_id bigint(20) UNSIGNED NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_favorite_pair (user_id, favorite_id),
        KEY user_id (user_id),
        KEY favorite_id (favorite_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD Setup Error: Failed to create table $table_name");
    }
}

function sud_create_subscriptions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_subscriptions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        subscription_id varchar(100) NOT NULL,
        plan_id varchar(50) NOT NULL,
        price decimal(10,2) NOT NULL,
        payment_method varchar(50) NOT NULL,
        billing_type varchar(20) DEFAULT 'monthly' NOT NULL,
        transaction_id varchar(100) DEFAULT NULL,
        status varchar(20) DEFAULT 'active' NOT NULL,
        start_date datetime NOT NULL,
        end_date datetime NOT NULL,
        auto_renew tinyint(1) DEFAULT 1 NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        UNIQUE KEY uk_subscription_id (subscription_id),
        KEY status_end_date (status, end_date)
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta($sql);
    
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD Setup Error: Failed to create or verify table $table_name after dbDelta call.");
    }
}

function sud_create_withdrawals_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_withdrawals';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` bigint(20) UNSIGNED NOT NULL,
      `amount_coins` decimal(12,2) NOT NULL COMMENT 'Amount in COINS requested',
      `net_payout_usd` decimal(10,2) NOT NULL COMMENT 'Actual USD amount to be paid out',
      `currency` varchar(10) NOT NULL DEFAULT 'USD',
      `method` varchar(50) NOT NULL COMMENT 'e.g., paypal, bank_transfer',
      `destination` text NOT NULL COMMENT 'e.g., PayPal email, masked bank info/reference',
      `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, completed, failed, cancelled',
      `requested_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `processed_at` datetime NULL DEFAULT NULL,
      `transaction_id` varchar(100) NULL DEFAULT NULL COMMENT 'ID from payment processor upon completion',
      `admin_notes` text NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `status` (`status`),
      KEY `requested_at` (`requested_at`)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD Setup Error: Failed to create/update table $table_name");
    }
}

function sud_create_monitored_swipes_table_setup() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $monitored_swipes_table = $wpdb->prefix . 'sud_monitored_swipes';

    $sql_monitored_swipes = "CREATE TABLE $monitored_swipes_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        swiper_user_id BIGINT(20) UNSIGNED NOT NULL,
        swiped_user_id BIGINT(20) UNSIGNED NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        swipe_type VARCHAR(20) NOT NULL,
        is_match TINYINT(1) DEFAULT 0 NOT NULL,
        action_timestamp DATETIME NOT NULL,
        notification_status VARCHAR(20) DEFAULT 'unread' NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY swiper_user_id (swiper_user_id),
        KEY swiped_user_id (swiped_user_id),
        KEY action_type (action_type),
        KEY notification_status (notification_status),
        KEY is_match (is_match)
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta($sql_monitored_swipes);

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $monitored_swipes_table)) != $monitored_swipes_table) {
        error_log("SUD CRITICAL (database-setup): Failed to create $monitored_swipes_table. DB error: " . $wpdb->last_error);
    } else {
    }
}

function sud_create_monitored_messages_table_setup() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $monitored_messages_table = $wpdb->prefix . 'sud_monitored_messages';

    $sql_monitored_messages = "CREATE TABLE $monitored_messages_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        original_message_id BIGINT(20) UNSIGNED NOT NULL,
        sender_user_id BIGINT(20) UNSIGNED NOT NULL,
        recipient_user_id BIGINT(20) UNSIGNED NOT NULL,
        message_snippet VARCHAR(255) DEFAULT '' NOT NULL,
        message_timestamp DATETIME NOT NULL,
        notification_status VARCHAR(20) DEFAULT 'unread' NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY original_message_id (original_message_id),
        KEY sender_user_id (sender_user_id),
        KEY recipient_user_id (recipient_user_id),
        KEY notification_status (notification_status)
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta($sql_monitored_messages);

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $monitored_messages_table)) != $monitored_messages_table) {
        error_log("SUD CRITICAL (database-setup): Failed to create $monitored_messages_table. DB error: " . $wpdb->last_error);
    } else {
    }
}

function sud_create_coin_transactions_table_setup() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'sud_coin_transactions';
    error_log("SUD DEBUG (database-setup): Checking/Creating $table_name.");

    $table_exists_before = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name);

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        coin_amount int(11) NOT NULL,
        price decimal(10,2) NOT NULL,
        payment_method varchar(50) NOT NULL,
        transaction_id varchar(100) DEFAULT NULL,
        order_uid varchar(36) DEFAULT NULL,
        status varchar(20) DEFAULT 'completed' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY transaction_id (transaction_id),
        UNIQUE KEY order_uid_unique (order_uid),
        KEY status (status)
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta($sql);

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD CRITICAL (database-setup): Failed to create $table_name. DB error: " . $wpdb->last_error);
    } else {
        error_log("SUD SUCCESS (database-setup): Table $table_name " . ($table_exists_before ? "verified." : "created."));
    }
}

function sud_create_gift_transactions_table_setup() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'sud_gift_transactions';
    error_log("SUD DEBUG (database-setup): Checking/Creating $table_name.");
    $table_exists_before = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name);

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        sender_id bigint(20) NOT NULL,
        receiver_id bigint(20) NOT NULL,
        gift_type varchar(50) NOT NULL,
        gift_name varchar(100) NOT NULL,
        coin_amount int(11) NOT NULL,
        cash_value decimal(10,2) NOT NULL,
        status varchar(20) DEFAULT 'pending' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        processed_at datetime DEFAULT NULL,
        admin_notes text DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY sender_id (sender_id),
        KEY receiver_id (receiver_id),
        KEY status (status)
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta($sql);

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD CRITICAL (database-setup): Failed to create $table_name. DB error: " . $wpdb->last_error);
    } else {
        error_log("SUD SUCCESS (database-setup): Table $table_name " . ($table_exists_before ? "verified." : "created."));
    }
}

function sud_create_user_reports_table_setup() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'sud_user_reports';
    error_log("SUD DEBUG (database-setup): Checking/Creating $table_name.");
    $table_exists_before = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name);

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        reporter_id bigint(20) NOT NULL,
        reported_user_id bigint(20) NOT NULL,
        reason varchar(100) NOT NULL,
        details text,
        status varchar(20) DEFAULT 'pending' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        resolved_at datetime DEFAULT NULL,
        admin_notes text DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY reporter_id (reporter_id),
        KEY reported_user_id (reported_user_id),
        KEY status (status)
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta($sql);

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD CRITICAL (database-setup): Failed to create $table_name. DB error: " . $wpdb->last_error);
    } else {
        error_log("SUD SUCCESS (database-setup): Table $table_name " . ($table_exists_before ? "verified." : "created."));
    }
}

function sud_create_transactions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_transactions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        description varchar(255) NOT NULL,
        amount decimal(10,2) NOT NULL,
        payment_method varchar(50) NOT NULL,
        transaction_id varchar(100) DEFAULT NULL,
        status varchar(20) DEFAULT 'completed' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD Setup Error: Failed to create table $table_name");
    }
}

function sud_create_user_swipes_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_swipes';
    $charset_collate = $wpdb->get_charset_collate();

    error_log("SUD DEBUG (database-setup): Checking/Creating $table_name.");

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        swiper_user_id BIGINT UNSIGNED NOT NULL,
        swiped_user_id BIGINT UNSIGNED NOT NULL,
        swipe_type VARCHAR(10) NOT NULL,
        swipe_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_match TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        INDEX idx_swiper_user_id (swiper_user_id),
        INDEX idx_swiped_user_id (swiped_user_id),
        UNIQUE KEY unique_swipe_pair (swiper_user_id, swiped_user_id)
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    
    $result = dbDelta($sql);
    
    if ($wpdb->last_error) {
        error_log("SUD ERROR (database-setup): Database error during $table_name creation: " . $wpdb->last_error);
    }
    
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD ERROR (database-setup): Failed to create/update table $table_name");
    } else {
        error_log("SUD SUCCESS (database-setup): Table $table_name created/verified.");
    }
}

function sud_create_user_likes_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_user_likes';
    $charset_collate = $wpdb->get_charset_collate();

    error_log("SUD DEBUG (database-setup): Checking/Creating $table_name.");

    $sql = "CREATE TABLE $table_name (
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
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    
    $result = dbDelta($sql);
    
    if ($wpdb->last_error) {
        error_log("SUD ERROR (database-setup): Database error during $table_name creation: " . $wpdb->last_error);
    }
    
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD ERROR (database-setup): Failed to create/update table $table_name");
    } else {
        error_log("SUD SUCCESS (database-setup): Table $table_name created/verified.");
    }
}

function sud_create_daily_swipe_counts_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sud_daily_swipe_counts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        swipe_date DATE NOT NULL,
        swipe_count INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY unique_user_date_swipe (user_id, swipe_date)
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta($sql);
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD Setup Error: Failed to create/update table $table_name");
    }
}

function sud_create_notification_settings_table_setup() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'sud_notification_settings';
    error_log("SUD DEBUG (database-setup): Checking/Creating $table_name.");
    $table_exists_before = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name);

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        notification_type varchar(50) NOT NULL,
        is_enabled tinyint(1) DEFAULT 1 NOT NULL,
        notification_text text NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY notification_type (notification_type)
    ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta($sql);

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        error_log("SUD CRITICAL (database-setup): Failed to create $table_name. DB error: " . $wpdb->last_error);
    } else {
        error_log("SUD SUCCESS (database-setup): Table $table_name " . ($table_exists_before ? "verified." : "created."));
        if (!$table_exists_before) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            if ($count == 0) {
                error_log("SUD DEBUG (database-setup): Populating $table_name with default notifications.");
                $default_notifications = [
                    ['notification_type' => 'new_message', 'notification_text' => 'You have received a new message from {sender_name}.'],
                    ['notification_type' => 'new_favorite', 'notification_text' => '{user_name} has added you to their favorites.'],
                    ['notification_type' => 'gift_received', 'notification_text' => 'You have received a {gift_name} from {sender_name}.'],
                    ['notification_type' => 'verification_approved', 'notification_text' => 'Your profile has been verified! You now have a verified badge on your profile.'],
                    ['notification_type' => 'profile_view', 'notification_text' => '{viewer_name} viewed your profile.']
                ];
                foreach ($default_notifications as $notification) {
                    $wpdb->insert(
                        $table_name,
                        [
                            'notification_type' => $notification['notification_type'],
                            'notification_text' => $notification['notification_text'],
                            'is_enabled' => 1,
                            // 'updated_at' will default to CURRENT_TIMESTAMP
                        ],
                        ['%s', '%s', '%d']
                    );
                }
            }
        }
    }
}
?>