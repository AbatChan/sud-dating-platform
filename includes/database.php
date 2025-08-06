<?php
function join_connect_to_wp_db() {
    global $wpdb;
    return $wpdb;
}

function join_save_progress($session_id, $data) {
    $wpdb = join_connect_to_wp_db();
    if(!$wpdb) return false;
    $table = $wpdb->prefix . 'join_registration_progress';

    $existing_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE session_id = %s",
        $session_id
    ));

    $data['updated_at'] = current_time('mysql', 1);

    if($existing_id) {
        $result = $wpdb->update( $table, $data, ['session_id' => $session_id], null, ['%s']);
    } else {
        $data['session_id'] = $session_id;
        $data['created_at'] = current_time('mysql', 1);
        $result = $wpdb->insert($table, $data);
    }
    return $result !== false;
}

function join_get_progress($session_id) {
    $wpdb = join_connect_to_wp_db();
    $table = $wpdb->prefix . 'join_registration_progress';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE session_id = %s",
        $session_id
    ), ARRAY_A);
}
