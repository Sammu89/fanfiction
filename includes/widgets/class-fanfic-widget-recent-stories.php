<?php
/**
 * Recent Stories Widget
 *
 * Displays a list of recently published stories.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Widget_Recent_Stories
 *
 * Widget for displaying recent stories with caching.
 *
 * @since 1.0.0
 */
class Fanfic_Widget_Recent_Stories extends WP_Widget {

	/**
	 * Constructor
	 *
	 * Registers the widget with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			'fanfic_widget_recent_stories',
			__( 'Recent Fanfiction Stories', 'fanfiction-manager' ),
			array(
				'description' => __( 'Display recently published fanfiction stories', 'fanfiction-manager' ),
				'classname'   => 'fanfic-widget fanfic-widget--recent',
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
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Recent Stories', 'fanfiction-manager' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		$show_author = isset( $instance['show_author'] ) ? (bool) $instance['show_author'] : true;
		$show_date = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : true;

		// Sanitize count
		$count = Fanfic_Widgets::sanitize_count( $count, 5, 20, 5 );

		// Try to get from cache
		$cache_key = "fanfic_widget_recent_stories_{$count}";
		$stories = get_transient( $cache_key );

		// If not cached, query stories
		if ( false === $stories ) {
			$stories = get_posts( array(
				'post_type'      => 'fanfiction_story',
				'post_status'    => 'publish',
				'posts_per_page' => $count,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			) );

			// Cache for 10 minutes
			set_transient( $cache_key, $stories, Fanfic_Widgets::CACHE_RECENT_STORIES );
		}

		// Output widget
		echo $args['before_widget'];

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title ) ) . $args['after_title'];
		}

		if ( ! empty( $stories ) ) {
			echo '<ul class="fanfic-widget-list">';

			foreach ( $stories as $story ) {
				$this->render_story_item( $story, $show_author, $show_date );
			}

			echo '</ul>';
		} else {
			Fanfic_Widgets::render_empty_state( __( 'No stories found.', 'fanfiction-manager' ) );
		}

		echo $args['after_widget'];
	}

	/**
	 * Render individual story item
	 *
	 * @since 1.0.0
	 * @param WP_Post $story       Story post object.
	 * @param bool    $show_author Whether to show author.
	 * @param bool    $show_date   Whether to show date.
	 * @return void
	 */
	private function render_story_item( $story, $show_author, $show_date ) {
		echo '<li class="fanfic-widget-item">';

		// Story title and link
		printf(
			'<a href="%s" class="fanfic-widget-link">%s</a>',
			esc_url( get_permalink( $story->ID ) ),
			esc_html( get_the_title( $story->ID ) )
		);

		// Meta information
		if ( $show_author || $show_date ) {
			echo '<div class="fanfic-widget-meta">';

			if ( $show_author ) {
				$author = get_userdata( $story->post_author );
				if ( $author ) {
					printf(
						'<span class="fanfic-widget-author">%s %s</span>',
						/* translators: %s: author name */
						esc_html__( 'by', 'fanfiction-manager' ),
						esc_html( $author->display_name )
					);
				}
			}

			if ( $show_date ) {
				printf(
					'<span class="fanfic-widget-date">%s</span>',
					esc_html( Fanfic_Widgets::get_formatted_date( $story->post_date ) )
				);
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
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Recent Stories', 'fanfiction-manager' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		$show_author = isset( $instance['show_author'] ) ? (bool) $instance['show_author'] : true;
		$show_date = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : true;
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
				<?php esc_html_e( 'Number of stories to show:', 'fanfiction-manager' ); ?>
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
			<input
				type="checkbox"
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_author' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_author' ) ); ?>"
				<?php checked( $show_author ); ?>
			>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_author' ) ); ?>">
				<?php esc_html_e( 'Show author name', 'fanfiction-manager' ); ?>
			</label>
		</p>

		<p>
			<input
				type="checkbox"
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_date' ) ); ?>"
				<?php checked( $show_date ); ?>
			>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>">
				<?php esc_html_e( 'Show publish date', 'fanfiction-manager' ); ?>
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
		$instance['show_author'] = isset( $new_instance['show_author'] ) ? 1 : 0;
		$instance['show_date'] = isset( $new_instance['show_date'] ) ? 1 : 0;

		// Sanitize count range
		$instance['count'] = Fanfic_Widgets::sanitize_count( $instance['count'], 5, 20, 5 );

		// Clear cache on update
		delete_transient( "fanfic_widget_recent_stories_{$instance['count']}" );
		delete_transient( "fanfic_widget_recent_stories_{$old_instance['count']}" );

		return $instance;
	}
}
