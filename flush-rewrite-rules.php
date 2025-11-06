<?php
/**
 * Emergency rewrite rules flush script
 * 
 * Visit this file in your browser to manually flush rewrite rules
 * URL: http://localhost/smpt/wp-content/plugins/fanfiction-manager/flush-rewrite-rules.php
 * 
 * After visiting, you can delete this file.
 */

// Load WordPress
require_once('../../../wp-load.php');

// Manually register all rewrite rules before flushing
if ( class_exists( 'Fanfic_Post_Types' ) ) {
	Fanfic_Post_Types::register();
}
if ( class_exists( 'Fanfic_Taxonomies' ) ) {
	Fanfic_Taxonomies::register();
}
if ( class_exists( 'Fanfic_Rewrite' ) ) {
	Fanfic_Rewrite::add_rewrite_rules();
}

// Flush rewrite rules
flush_rewrite_rules();

echo '<h1>Rewrite Rules Flushed Successfully!</h1>';
echo '<p>Your rewrite rules have been flushed.</p>';
echo '<p><a href="' . home_url('/fanfiction/') . '">Go to Fanfiction Homepage</a></p>';
echo '<p><strong>You can now delete this file: flush-rewrite-rules.php</strong></p>';
