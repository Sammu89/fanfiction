<?php
/**
 * Top Authors Widget
 *
 * Displays a list of top authors by follower count.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Widget_Top_Authors
 *
 * Widget for displaying top authors.
 *
 * @since 1.0.0
 */
class Fanfic_Widget_Top_Authors extends WP_Widget {

	/**
	 * Constructor
	 *
	 * Registers the widget with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			'fanfic_widget_top_authors',
			__( 'Top Fanfiction Authors', 'fanfiction-manager' ),
			array(
				'description' => __( 'Display top authors by follower count', 'fanfiction-manager' ),
				'classname'   => 'fanfic-widget fanfic-widget--authors',
			)
		);
	}

	/**
	 * Display widget output
	 *
	 * @since 1.0.0
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance settings.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		// Get settings with defaults
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Top Authors', 'fanfiction-manager' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		$min_followers = ! empty( $instance['min_followers'] ) ? absint( $instance['min_followers'] ) : 1;
		$show_follower_count = isset( $instance['show_follower_count'] ) ? (bool) $instance['show_follower_count'] : true;
		$show_story_count = isset( $instance['show_story_count'] ) ? (bool) $instance['show_story_count'] : true;

		// Sanitize inputs
		$count = Fanfic_Widgets::sanitize_count( $count, 5, 20, 5 );
		$min_followers = max( 1, min( 10, $min_followers ) );

		// Get top authors (uses cache from Follows class)
		$authors = Fanfic_Follows::get_top_authors( $count, $min_followers );

		// Output widget
		echo $args['before_widget'];

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title ) ) . $args['after_title'];
		}

		if ( ! empty( $authors ) ) {
			echo '<ul class="fanfic-widget-list">';

			foreach ( $authors as $author_data ) {
				$this->render_author_item( $author_data, $show_follower_count, $show_story_count );
			}

			echo '</ul>';
		} else {
			Fanfic_Widgets::render_empty_state( __( 'No authors found.', 'fanfiction-manager' ) );
		}

		echo $args['after_widget'];
	}

	/**
	 * Render individual author item
	 *
	 * @since 1.0.0
	 * @param object $author_data         Author data with follower count.
	 * @param bool   $show_follower_count Whether to show follower count.
	 * @param bool   $show_story_count    Whether to show story count.
	 * @return void
	 */
	private function render_author_item( $author_data, $show_follower_count, $show_story_count ) {
		$author = get_userdata( $author_data->author_id );
		if ( ! $author ) {
			return;
		}

		echo '<li class="fanfic-widget-item">';

		// Author name and link
		printf(
			'<a href="%s" class="fanfic-widget-link fanfic-widget-author-link">%s</a>',
			esc_url( fanfic_get_user_profile_url( $author->ID ) ),
			esc_html( $author->display_name )
		);

		// Meta information
		if ( $show_follower_count || $show_story_count ) {
			echo '<div class="fanfic-widget-meta">';

			if ( $show_follower_count ) {
				echo Fanfic_Widgets::get_follower_count_badge( $author_data->follower_count );
			}

			if ( $show_story_count ) {
				// Get story count for author
				$story_count = count_user_posts( $author->ID, 'fanfiction_story', true );
				echo Fanfic_Widgets::get_story_count_badge( $story_count );
			}

			echo '</div>';
		}

		echo '</li>';
	}

	/**
	 * Display widget settings form
	 *
	 * @since 1.0.0
	 * @param array $instance Widget instance settings.
	 * @return void
	 */
	public function form( $instance ) {
		// Default values
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Top Authors', 'fanfiction-manager' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		$min_followers = ! empty( $instance['min_followers'] ) ? absint( $instance['min_followers'] ) : 1;
		$show_follower_count = isset( $instance['show_follower_count'] ) ? (bool) $instance['show_follower_count'] : true;
		$show_story_count = isset( $instance['show_story_count'] ) ? (bool) $instance['show_story_count'] : true;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'fanfiction-manager' ); ?>
			</label>
			<input
				type="text"
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>"
			>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>">
				<?php esc_html_e( 'Number of authors to show:', 'fanfiction-manager' ); ?>
			</label>
			<input
				type="number"
				class="tiny-text"
				id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>"
				value="<?php echo esc_attr( $count ); ?>"
				min="5"
				max="20"
				step="1"
			>
			<span class="description"><?php esc_html_e( '(5-20)', 'fanfiction-manager' ); ?></span>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'min_followers' ) ); ?>">
				<?php esc_html_e( 'Minimum followers:', 'fanfiction-manager' ); ?>
			</label>
			<input
				type="number"
				class="tiny-text"
				id="<?php echo esc_attr( $this->get_field_id( 'min_followers' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'min_followers' ) ); ?>"
				value="<?php echo esc_attr( $min_followers ); ?>"
				min="1"
				max="10"
				step="1"
			>
			<span class="description"><?php esc_html_e( '(1-10)', 'fanfiction-manager' ); ?></span>
		</p>

		<p>
			<input
				type="checkbox"
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_follower_count' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_follower_count' ) ); ?>"
				<?php checked( $show_follower_count ); ?>
			>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_follower_count' ) ); ?>">
				<?php esc_html_e( 'Show follower count', 'fanfiction-manager' ); ?>
			</label>
		</p>

		<p>
			<input
				type="checkbox"
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_story_count' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_story_count' ) ); ?>"
				<?php checked( $show_story_count ); ?>
			>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_story_count' ) ); ?>">
				<?php esc_html_e( 'Show story count', 'fanfiction-manager' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Update widget settings
	 *
	 * @since 1.0.0
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array Updated settings.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		// Sanitize inputs
		$instance['title'] = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['count'] = ! empty( $new_instance['count'] ) ? absint( $new_instance['count'] ) : 5;
		$instance['min_followers'] = ! empty( $new_instance['min_followers'] ) ? absint( $new_instance['min_followers'] ) : 1;
		$instance['show_follower_count'] = isset( $new_instance['show_follower_count'] ) ? 1 : 0;
		$instance['show_story_count'] = isset( $new_instance['show_story_count'] ) ? 1 : 0;

		// Sanitize ranges
		$instance['count'] = Fanfic_Widgets::sanitize_count( $instance['count'], 5, 20, 5 );
		$instance['min_followers'] = max( 1, min( 10, $instance['min_followers'] ) );

		// Note: Cache is managed by Follows class, no need to clear here

		return $instance;
	}
}
