<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'C:/Users/Administrator/Local Sites/teste-wordpress/app/public/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'fanfic_moderation_messages';
echo "table={$table}\n";
$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
var_export($exists);
echo "\n";
$cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
var_export($cols);
echo "\n";
$rows = $wpdb->get_results("SELECT id, author_id, target_type, target_id, status, unread_for_moderator, unread_for_author, created_at, last_message_at FROM {$table} ORDER BY id DESC LIMIT 10", ARRAY_A);
var_export($rows);
echo "\n";
?>
