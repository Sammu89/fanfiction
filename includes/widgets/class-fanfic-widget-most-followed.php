<?php
/**
 * Most Followed Stories Widget
 *
 * Displays a list of most followed stories.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Widget_Most_Followed
 *
 * Widget for displaying most followed stories.
 *
 * @since 1.0.0
 */
class Fanfic_Widget_Most_Followed extends WP_Widget {

	/**
	 * Constructor
	 *
	 * Registers the widget with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			'fanfic_widget_most_followed',
			__( 'Most Followed Stories', 'fanfiction-manager' ),
			array(
				'description' => __( 'Display most followed fanfiction stories', 'fanfiction-manager' ),
				'classname'   => 'fanfic-widget fanfic-widget--followed',
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
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Most Followed', 'fanfiction-manager' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		$min_follows = ! empty( $instance['min_follows'] ) ? absint( $instance['min_follows'] ) : 1;
		$show_author = isset( $instance['show_author'] ) ? (bool) $instance['show_author'] : true;
		$show_count = isset( $instance['show_count'] ) ? (bool) $instance['show_count'] : true;

		// Sanitize inputs
		$count = Fanfic_Widgets::sanitize_count( $count, 5, 20, 5 );
		$min_follows = max( 1, min( 10, $min_follows ) );

		// Get most followed stories (uses cache from Follows class)
		$stories = Fanfic_Follows::get_most_followed_stories( $count, $min_follows );

		// Output widget
		echo $args['before_widget'];

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title ) ) . $args['after_title'];
		}

		if ( ! empty( $stories ) ) {
			echo '<ul class="fanfic-widget-list">';

			foreach ( $stories as $story_data ) {
				$this->render_story_item( $story_data, $show_author, $show_count );
			}

			echo '</ul>';
		} else {
			Fanfic_Widgets::render_empty_state( __( 'No followed stories found.', 'fanfiction-manager' ) );
		}

		echo $args['after_widget'];
	}

	/**
	 * Render individual story item
	 *
	 * @since 1.0.0
	 * @param object $story_data  Story data with follow count.
	 * @param bool   $show_author Whether to show author.
	 * @param bool   $show_count  Whether to show follow count.
	 * @return void
	 */
	private function render_story_item( $story_data, $show_author, $show_count ) {
		$story = get_post( $story_data->story_id );
		if ( ! $story ) {
			return;
		}

		echo '<li class="fanfic-widget-item">';

		// Story title and link
		printf(
			'<a href="%s" class="fanfic-widget-link">%s</a>',
			esc_url( get_permalink( $story->ID ) ),
			esc_html( get_the_title( $story->ID ) )
		);

		// Meta information
		if ( $show_author || $show_count ) {
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

			if ( $show_count ) {
				echo Fanfic_Widgets::get_follow_count_badge( $story_data->follow_count );
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
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Most Followed', 'fanfiction-manager' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		$min_follows = ! empty( $instance['min_follows'] ) ? absint( $instance['min_follows'] ) : 1;
		$show_author = isset( $instance['show_author'] ) ? (bool) $instance['show_author'] : true;
		$show_count = isset( $instance['show_count'] ) ? (bool) $instance['show_count'] : true;
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
			<label for="<?php echo esc_attr( $this->get_field_id( 'min_follows' ) ); ?>">
				<?php esc_html_e( 'Minimum follows:', 'fanfiction-manager' ); ?>
			</label>
			<input
				type="number"
				class="tiny-text"
				id="<?php echo esc_attr( $this->get_field_id( 'min_follows' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'min_follows' ) ); ?>"
				value="<?php echo esc_attr( $min_follows ); ?>"
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
				id="<?php echo esc_attr( $this->get_field_id( 'show_count' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_count' ) ); ?>"
				<?php checked( $show_count ); ?>
			>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_count' ) ); ?>">
				<?php esc_html_e( 'Show follow count', 'fanfiction-manager' ); ?>
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
		$instance['min_follows'] = ! empty( $new_instance['min_follows'] ) ? absint( $new_instance['min_follows'] ) : 1;
		$instance['show_author'] = isset( $new_instance['show_author'] ) ? 1 : 0;
		$instance['show_count'] = isset( $new_instance['show_count'] ) ? 1 : 0;

		// Sanitize ranges
		$instance['count'] = Fanfic_Widgets::sanitize_count( $instance['count'], 5, 20, 5 );
		$instance['min_follows'] = max( 1, min( 10, $instance['min_follows'] ) );

		// Note: Cache is managed by Follows class, no need to clear here

		return $instance;
	}
}
