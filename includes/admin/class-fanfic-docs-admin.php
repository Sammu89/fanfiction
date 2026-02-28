<?php
/**
 * Documentation Admin Page
 *
 * Renders the Documentation admin page with expandable accordions.
 * Each accordion fetches its content on first expand via AJAX, converting
 * the corresponding Markdown file to HTML server-side.
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Docs_Admin
 *
 * @since 2.2.0
 */
class Fanfic_Docs_Admin {

	/**
	 * Whitelist of doc IDs to file paths.
	 * All paths must be inside the plugin directory.
	 *
	 * @since 2.2.0
	 * @var array<string,string>|null
	 */
	private static $docs_map = null;

	/**
	 * Register AJAX hooks.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_fanfic_fetch_doc', array( __CLASS__, 'ajax_fetch_doc' ) );
	}

	/**
	 * Render the Documentation admin page.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		$accordions = array(
			array(
				'id'    => 'status-system',
				'label' => __( 'Status Classification System', 'fanfiction-manager' ),
			),
			array(
				'id'    => 'coauthors',
				'label' => __( 'Co-Authors', 'fanfiction-manager' ),
			),
			array(
				'id'    => 'translated-stories',
				'label' => __( 'Translated Stories', 'fanfiction-manager' ),
			),
			array(
				'id'    => 'shortcode',
				'label' => __( 'Shortcode', 'fanfiction-manager' ),
			),
		);

		$nonce    = wp_create_nonce( 'fanfic_fetch_doc' );
		$ajax_url = admin_url( 'admin-ajax.php' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description"><?php esc_html_e( 'Reference guides for Fanfiction Manager features and systems.', 'fanfiction-manager' ); ?></p>

			<div class="fanfic-docs-accordion" role="list">
				<?php foreach ( $accordions as $item ) : ?>
					<div class="fanfic-docs-accordion-item"
						 id="fanfic-doc-item-<?php echo esc_attr( $item['id'] ); ?>"
						 role="listitem">
						<div class="fanfic-docs-accordion-header">
							<button
								type="button"
								class="fanfic-docs-accordion-toggle"
								data-doc="<?php echo esc_attr( $item['id'] ); ?>"
								aria-expanded="false"
								aria-controls="fanfic-doc-body-<?php echo esc_attr( $item['id'] ); ?>">
								<span class="fanfic-docs-accordion-icon dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
								<?php echo esc_html( $item['label'] ); ?>
							</button>
						</div>
						<div
							class="fanfic-docs-accordion-body"
							id="fanfic-doc-body-<?php echo esc_attr( $item['id'] ); ?>"
							role="region"
							aria-labelledby="fanfic-doc-item-<?php echo esc_attr( $item['id'] ); ?>"
							hidden>
							<div class="fanfic-docs-accordion-content">
								<div class="fanfic-doc-loading">
									<span class="spinner is-active"></span>
									<?php esc_html_e( 'Loading&hellip;', 'fanfiction-manager' ); ?>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<script>
		(function ($) {
			var config = {
				ajaxUrl: <?php echo wp_json_encode( $ajax_url ); ?>,
				nonce:   <?php echo wp_json_encode( $nonce ); ?>
			};

			$('.fanfic-docs-accordion-toggle').on('click', function () {
				var $btn      = $(this);
				var $item     = $btn.closest('.fanfic-docs-accordion-item');
				var $body     = $item.find('.fanfic-docs-accordion-body');
				var $icon     = $btn.find('.fanfic-docs-accordion-icon');
				var $content  = $body.find('.fanfic-docs-accordion-content');
				var isOpen    = $btn.attr('aria-expanded') === 'true';

				if ( isOpen ) {
					$btn.attr('aria-expanded', 'false');
					$body.attr('hidden', '');
					$icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
					return;
				}

				$btn.attr('aria-expanded', 'true');
				$body.removeAttr('hidden');
				$icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');

				if ( $content.data('loaded') ) {
					return;
				}

				var docId = $btn.data('doc');

				$.post(config.ajaxUrl, {
					action: 'fanfic_fetch_doc',
					nonce:  config.nonce,
					doc:    docId
				})
				.done(function (response) {
					if ( response && response.success ) {
						$content.html(response.data.html);
						$content.data('loaded', true);
					} else {
						$content.html(
							'<p class="notice notice-error inline" style="padding:8px 12px;">' +
							(response && response.data && response.data.message
								? response.data.message
								: <?php echo wp_json_encode( __( 'Failed to load documentation.', 'fanfiction-manager' ) ); ?>) +
							'</p>'
						);
					}
				})
				.fail(function () {
					$content.html(
						'<p class="notice notice-error inline" style="padding:8px 12px;">' +
						<?php echo wp_json_encode( __( 'Request failed. Please reload the page and try again.', 'fanfiction-manager' ) ); ?> +
						'</p>'
					);
				});
			});
		}(jQuery));
		</script>
		<?php
	}

	/**
	 * AJAX handler: fetch and convert a documentation file.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function ajax_fetch_doc() {
		check_ajax_referer( 'fanfic_fetch_doc', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		$doc_id  = isset( $_POST['doc'] ) ? sanitize_key( wp_unslash( $_POST['doc'] ) ) : '';
		$map     = self::get_docs_map();

		if ( '' === $doc_id || ! isset( $map[ $doc_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown documentation section.', 'fanfiction-manager' ) ) );
		}

		$file_path = $map[ $doc_id ];

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Documentation file not found.', 'fanfiction-manager' ) ) );
		}

		$raw  = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$html = self::markdown_to_html( (string) $raw );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Return the whitelist map of doc IDs to absolute file paths.
	 *
	 * @since 2.2.0
	 * @return array<string,string>
	 */
	private static function get_docs_map() {
		if ( null === self::$docs_map ) {
			self::$docs_map = array(
				'status-system'      => FANFIC_PLUGIN_DIR . 'docs/backend/status-system.md',
				'coauthors'          => FANFIC_PLUGIN_DIR . 'docs/backend/coauthors.md',
				'translated-stories' => FANFIC_PLUGIN_DIR . 'docs/backend/translated-stories.md',
				'shortcode'          => FANFIC_PLUGIN_DIR . 'docs/backend/shortcodes.md',
			);
		}
		return self::$docs_map;
	}

	// -------------------------------------------------------------------------
	// Markdown → HTML converter
	// -------------------------------------------------------------------------

	/**
	 * Convert a Markdown string to sanitized HTML.
	 *
	 * Supports: headings (h1-h4), paragraphs, fenced code blocks, inline code,
	 * blockquotes, unordered lists, ordered lists, tables, horizontal rules,
	 * bold, and italic.
	 *
	 * @since 2.2.0
	 * @param string $markdown Raw Markdown text.
	 * @return string Safe HTML.
	 */
	private static function markdown_to_html( $markdown ) {
		$markdown = str_replace( array( "\r\n", "\r" ), "\n", $markdown );
		$lines    = explode( "\n", $markdown );
		$html     = '';
		$i        = 0;
		$count    = count( $lines );

		while ( $i < $count ) {
			$line = $lines[ $i ];

			// --- Fenced code block ----------------------------------------
			if ( preg_match( '/^```(\w*)$/', $line, $m ) ) {
				$lang       = $m[1];
				$code_lines = array();
				$i++;
				while ( $i < $count && '```' !== $lines[ $i ] ) {
					$code_lines[] = esc_html( $lines[ $i ] );
					$i++;
				}
				$lang_attr = $lang ? ' class="language-' . esc_attr( $lang ) . '"' : '';
				$html     .= '<pre><code' . $lang_attr . '>' . implode( "\n", $code_lines ) . '</code></pre>' . "\n";
				$i++;
				continue;
			}

			// --- Heading (# through ####) ----------------------------------
			if ( preg_match( '/^(#{1,4})\s+(.+)$/', $line, $m ) ) {
				$level = strlen( $m[1] );
				$html .= '<h' . $level . '>' . self::inline_markdown( $m[2] ) . '</h' . $level . '>' . "\n";
				$i++;
				continue;
			}

			// --- Horizontal rule ------------------------------------------
			if ( preg_match( '/^[-*_]{3,}$/', trim( $line ) ) ) {
				$html .= '<hr>' . "\n";
				$i++;
				continue;
			}

			// --- Blockquote -----------------------------------------------
			if ( preg_match( '/^>\s?(.*)$/', $line, $m ) ) {
				$bq_lines = array( $m[1] );
				$i++;
				while ( $i < $count && preg_match( '/^>\s?(.*)$/', $lines[ $i ], $bm ) ) {
					$bq_lines[] = $bm[1];
					$i++;
				}
				$html .= '<blockquote><p>' . self::inline_markdown( implode( ' ', $bq_lines ) ) . '</p></blockquote>' . "\n";
				continue;
			}

			// --- Table (lines starting with |) ----------------------------
			if ( preg_match( '/^\|/', $line ) ) {
				$table_lines = array();
				while ( $i < $count && preg_match( '/^\|/', $lines[ $i ] ) ) {
					$table_lines[] = $lines[ $i ];
					$i++;
				}
				$html .= self::render_md_table( $table_lines );
				continue;
			}

			// --- Unordered list -------------------------------------------
			if ( preg_match( '/^[-*+]\s+(.+)$/', $line, $m ) ) {
				$html .= '<ul>' . "\n";
				while ( $i < $count && preg_match( '/^[-*+]\s+(.+)$/', $lines[ $i ], $lm ) ) {
					$html .= '<li>' . self::inline_markdown( $lm[1] ) . '</li>' . "\n";
					$i++;
				}
				$html .= '</ul>' . "\n";
				continue;
			}

			// --- Ordered list ---------------------------------------------
			if ( preg_match( '/^\d+\.\s+(.+)$/', $line, $m ) ) {
				$html .= '<ol>' . "\n";
				while ( $i < $count && preg_match( '/^\d+\.\s+(.+)$/', $lines[ $i ], $lm ) ) {
					$html .= '<li>' . self::inline_markdown( $lm[1] ) . '</li>' . "\n";
					$i++;
				}
				$html .= '</ol>' . "\n";
				continue;
			}

			// --- Blank line -----------------------------------------------
			if ( '' === trim( $line ) ) {
				$i++;
				continue;
			}

			// --- Paragraph ------------------------------------------------
			$para_lines = array();
			$block_start = '/^(#{1,4}\s|[-*+]\s|\d+\.\s|>\s|```|[-*_]{3,}$|\|)/';
			while ( $i < $count && '' !== trim( $lines[ $i ] ) && ! preg_match( $block_start, $lines[ $i ] ) ) {
				$para_lines[] = $lines[ $i ];
				$i++;
			}
			if ( ! empty( $para_lines ) ) {
				$html .= '<p>' . self::inline_markdown( implode( ' ', $para_lines ) ) . '</p>' . "\n";
			}
		}

		return wp_kses_post( $html );
	}

	/**
	 * Apply inline Markdown formatting to a single text node.
	 *
	 * Escapes HTML first, then applies bold, italic, and inline code patterns.
	 *
	 * @since 2.2.0
	 * @param string $text Raw text.
	 * @return string Text with HTML inline elements.
	 */
	private static function inline_markdown( $text ) {
		$text = esc_html( $text );

		// Inline code — must be processed before bold/italic.
		$text = preg_replace( '/`(.+?)`/', '<code>$1</code>', $text );

		// Bold **text**.
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );

		// Italic *text* (single asterisk, not followed by another).
		$text = preg_replace( '/\*([^*]+)\*/', '<em>$1</em>', $text );

		return $text;
	}

	/**
	 * Render a Markdown pipe table as an HTML table.
	 *
	 * @since 2.2.0
	 * @param string[] $lines Raw table lines (all start with `|`).
	 * @return string HTML table markup.
	 */
	private static function render_md_table( $lines ) {
		if ( count( $lines ) < 2 ) {
			return '';
		}

		$html = '<table class="widefat striped fanfic-doc-table">' . "\n";

		// Header row (index 0).
		$header_cells = self::parse_table_row( $lines[0] );
		$html        .= '<thead><tr>';
		foreach ( $header_cells as $cell ) {
			$html .= '<th>' . self::inline_markdown( $cell ) . '</th>';
		}
		$html .= '</tr></thead>' . "\n";

		// Index 1 is the separator row (|---|---| etc.) — skip it.
		$html .= '<tbody>' . "\n";
		for ( $r = 2; $r < count( $lines ); $r++ ) {
			$cells = self::parse_table_row( $lines[ $r ] );
			$html .= '<tr>';
			foreach ( $cells as $cell ) {
				$html .= '<td>' . self::inline_markdown( $cell ) . '</td>';
			}
			$html .= '</tr>' . "\n";
		}
		$html .= '</tbody></table>' . "\n";

		return $html;
	}

	/**
	 * Split a Markdown table row string into cell values.
	 *
	 * @since 2.2.0
	 * @param string $line Raw table row (e.g. `| Foo | Bar |`).
	 * @return string[] Cell values.
	 */
	private static function parse_table_row( $line ) {
		$line = trim( $line );
		$line = preg_replace( '/^\||\|$/', '', $line );
		return array_map( 'trim', explode( '|', $line ) );
	}
}
