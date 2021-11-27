<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {
		$post_types = get_post_types( [ 'public' => true ] );
		$class_name = $attributes['className'];
		ob_start();
		?>
		<div class="<?php echo esc_attr( $class_name ); ?>">
			<h2><?php esc_html_e( 'Post Counts', 'site-counts' ); ?></h2>
			<ul>
			<?php
			foreach ( $post_types as $post_type_slug ) :
				$post_type_object = get_post_type_object( $post_type_slug );
				$post_count       = wp_count_posts( $post_type_slug );
				if ( 0 !== $post_count ) :
					?>
				<li>
					<?php
					/* translators: 1: post count, 2: post type name */
					echo sprintf( __( 'There are %1$d, %2$s.', 'site-counts' ), (int) $post_count, esc_html( $post_type_object->labels->name ) );
					?>
				</li>
				<?php endif; ?>
			<?php endforeach; ?>
			</ul>
			<?php
			$post_id = ! empty( $_GET['post_id'] ) && is_int( $_GET['post_id'] ) ? $_GET['post_id'] : get_the_ID();
			?>
			<p>
				<?php
				/* translators: 1: current post id */
				echo sprintf( __( 'The current post ID is %d.', 'site-counts' ), (int) $post_id );
				?>
			</p>
			<?php
			$tax_query = wp_cache_get( 'tax-query', 'site-counts' );

			if ( ! $tax_query ) {

				$cat_query = new WP_Query(
					[
						'post_type'              => [ 'post', 'page' ],
						'post_status'            => 'any',
						'date_query'             => [
							[
								'hour'    => 9,
								'compare' => '>=',
							],
							[
								'hour'    => 17,
								'compare' => '<=',
							],
						],
						'category_name'          => 'baz',
						'fields'                 => 'ids',
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
					]
				);

				$tag_query = new WP_Query(
					[
						'post_type'              => [ 'post', 'page' ],
						'post_status'            => 'any',
						'date_query'             => [
							[
								'hour'    => 9,
								'compare' => '>=',
							],
							[
								'hour'    => 17,
								'compare' => '<=',
							],
						],
						'tag'                    => 'foo',
						'fields'                 => 'ids',
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
					]
				);

				$ids = array_unique( array_merge( $cat_query->posts, $tag_query->posts ) );
				$ids = array_diff( $ids, [ $post_id ] );

				$tax_query = new WP_Query(
					[
						'post__in'               => $ids,
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					]
				);

				wp_cache_set( 'tax-query', $tax_query, 'site-counts', 10 * MINUTE_IN_SECONDS );
			}

			if ( $tax_query->found_posts ) :
				?>
			<h2>
				<?php
				/* translators: 1: found posts from query */
				echo sprintf( __( '%d posts with the tag of foo and the category of baz', 'site-counts' ), (int) $tax_query->found_posts );
				?>
			</h2>
				<ul>
				<?php
				foreach ( array_slice( $tax_query->posts, 0, 5 ) as $post ) :
					?>
					<li><?php echo esc_html( $post->post_title ); ?></li>
					<?php
				endforeach;
			endif;
			?>
			</ul>
		</div>
		<?php

		return ob_get_clean();
	}
}
