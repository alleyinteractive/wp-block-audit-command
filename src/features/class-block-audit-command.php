<?php
/**
 * Block_Audit_Command class file
 *
 * @package wp-block-audit-command
 */

namespace Alley\WP\Features;

use Alley\WP\Types\Feature;
use Alley\WP_Bulk_Task\Bulk_Task;
use Alley\WP_Bulk_Task\Cursor\Memory_Cursor;
use Alley\WP_Bulk_Task\Progress\Null_Progress_Bar;
use Alley\WP_Bulk_Task\Progress\PHP_CLI_Progress_Bar;
use WP_CLI;
use WP_Post;

use function Alley\WP\match_blocks;
use function WP_CLI\Utils\format_items;
use function WP_CLI\Utils\get_flag_value;

/**
 * Audit block usage in post content.
 */
final class Block_Audit_Command extends WP_CLI\CommandWithDBObject implements Feature {
	/**
	 * Boot the feature.
	 */
	public function boot(): void {
		WP_CLI::add_command( 'block-audit run', [ $this, 'run' ] );
	}

	/**
	 * Report how many of each type of block there is in post content and aggregated details about them.
	 *
	 * The report includes the number of times each block is used, the post types it's used in, and details comprising:
	 *
	 * - For all blocks, the count of 'align' attribute values.
	 * - For 'core/heading' blocks, the count of each heading level used.
	 * - For 'core/embed' blocks, the count of each embed provider used.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : One or more args to pass to WP_Query except for 'order', 'orderby', or 'paged'.
	 *
	 * [--block_name=<block_name>...]
	 * : One or more block names to report on (comma separated). (Default: all block types).
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * [--progress-bar]
	 * : Show the progress bar.
	 *
	 * ## EXAMPLES
	 *
	 *   # Audit specific blocks in posts and pages.
	 *   $ wp block-audit run --post_type=post,page --block_name=core/paragraph,core/table
	 *   +-----------------------------------+-------+------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
	 *   | Block Name                        | Count | Example URL                                                | Post Types      | Details                                                              |
	 *   +-----------------------------------+-------+------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
	 *   | core/paragraph                    | 262   | https://www.example.com/2023/01/13/text-category-blocks/   | ["post","page"] | {"align":{"center":16,"right":1,"left":1}}                           |
	 *   | core/table                        | 4     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 *   +-----------------------------------+-------+------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
	 *
	 *   # Audit all blocks in posts and pages.
	 *   $ wp block-audit run --post_type=post,page
	 *   +-----------------------------------+-------+------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
	 *   | Block Name                        | Count | Example URL                                                | Post Types      | Details                                                              |
	 *   +-----------------------------------+-------+------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
	 *   | core/archives                     | 3     | https://www.example.com/2023/01/13/widgets-block-category/ | ["post"]        |                                                                      |
	 *   | core/button                       | 12    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        | {"align":{"left":2,"center":1,"right":1}}                            |
	 *   | core/code                         | 2     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 *   | core/column                       | 40    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        |                                                                      |
	 *   | core/columns                      | 13    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        | {"align":{"wide":2,"full":1}}                                        |
	 *   | core/cover                        | 21    | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        | {"align":{"left":1,"center":2,"full":1,"wide":2}}                    |
	 *   | core/file                         | 3     | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        |                                                                      |
	 *   | core/gallery                      | 10    | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        |                                                                      |
	 *   | core/group                        | 25    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        |                                                                      |
	 *   | core/heading                      | 23    | https://www.example.com/2023/01/13/text-category-blocks/   | ["post","page"] | {"H1":2,"H2":11,"H3":4,"H4":2,"H5":2,"H6":2}                         |
	 *   | core/html                         | 2     | https://www.example.com/2023/01/13/widgets-block-category/ | ["post"]        |                                                                      |
	 *   | core/image                        | 19    | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        | {"align":{"center":2,"left":2,"right":3,"none":1,"wide":1,"full":1}} |
	 *   | core/list                         | 9     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 *   | core/list-item                    | 6     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 *   | core/media-text                   | 6     | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        | {"align":{"full":1}}                                                 |
	 *   | core/paragraph                    | 262   | https://www.example.com/2023/01/13/text-category-blocks/   | ["post","page"] | {"align":{"center":16,"right":1,"left":1}}                           |
	 *   | core/pullquote                    | 4     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 *   | core/spacer                       | 4     | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        |                                                                      |
	 *   | core/table                        | 4     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 *   +-----------------------------------+-------+------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
	 *
	 * @param array<string> $args       Positional arguments.
	 * @param array<string> $assoc_args Associative arguments.
	 */
	public function run( array $args, array $assoc_args = [] ): void {
		global $wpdb;

		$user_query_args = array_diff_key( $assoc_args, array_flip( [ 'format', 'progress-bar', 'block_name', 'verbose' ] ) );
		$task_name       = get_flag_value( $assoc_args, 'verbose', false )
			? new PHP_CLI_Progress_Bar( 'Bulk Task: audit-blocks' )
			: new Null_Progress_Bar();

		$bulk_task = new Bulk_Task(
			'audit-blocks-' . md5( (string) wp_json_encode( $user_query_args ) ),
			$task_name,
			new Memory_Cursor()
		);

		add_filter( 'ep_skip_query_integration', '__return_true' );

		$query_args = array_merge(
			[
				'post_status' => 'publish',
				'post_type'   => array_diff(
					// This gets all post types in the DB, regardless of whether they're registered. Useful for migrations.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
					$wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT post_type FROM %i', $wpdb->posts ) ),
					[ 'revision' ],
				),
			],
			$user_query_args,
		);

		$query_args = self::process_csv_arguments_to_arrays( $query_args );
		if ( isset( $query_args['post_type'] ) && is_string( $query_args['post_type'] ) && 'any' !== $query_args['post_type'] ) {
			$query_args['post_type'] = explode( ',', $query_args['post_type'] );
		}

		$block_names = get_flag_value( $assoc_args, 'block_name', '' );

		if ( is_string( $block_names ) ) {
			$block_names = explode( ',', $block_names );
			$block_names = array_map( 'trim', $block_names );
		}

		$block_query_args = [
			'flatten'           => true,
			'skip_empty_blocks' => false, // For counting classic blocks.
		];

		if ( ! empty( $block_names ) && is_array( $block_names ) ) {
			$block_query_args['name'] = $block_names;
		}

		$out = [];

		$bulk_task->run(
			$query_args,
			function ( WP_Post $post ) use ( &$out, $block_query_args ) {
				$blocks = match_blocks( $post, $block_query_args );

				if ( ! is_iterable( $blocks ) || empty( $blocks ) ) {
					return;
				}

				foreach ( $blocks as $block ) {
					$block_name = $block['blockName'];

					// Label null blocks as classic blocks if they contain HTML.
					if ( ! $block_name ) {
						$html = $block['innerHTML'];
						$html = trim( $html );

						if ( ! $html ) {
							continue;
						}

						$block_name = 'core/classic';
					}

					if ( ! isset( $out[ $block_name ] ) ) {
						/**
						 * Filters the example URL included for a block type.
						 *
						 * @param string $example_url The example URL. Defaults to the permalink of the post where the block type was first seen.
						 * @param WP_Post $post       The post used to generate the example URL.
						 */
						$example_url = apply_filters( 'alley_block_audit_block_type_example_url', get_permalink( $post ), $post );

						$out[ $block_name ] = [
							'Block Name'  => $block_name,
							'Count'       => 0,
							'Example URL' => $example_url,
							'Post Types'  => [],
							'Details'     => [],
						];
					}

					$out[ $block_name ]['Count']++;

					if ( ! in_array( $post->post_type, $out[ $block_name ]['Post Types'], true ) ) {
						$out[ $block_name ]['Post Types'][] = $post->post_type;
					}

					$out[ $block_name ]['Details'] = $this->with_details(
						$out[ $block_name ]['Details'], // @phpstan-ignore-line
						$block, // @phpstan-ignore-line
						$post,
					);
				}
			},
		);

		if ( count( $out ) === 0 ) {
			\WP_CLI::warning( 'No results found.' );
			return;
		}

		// Sort by block name.
		ksort( $out );
		$first = reset( $out );

		foreach ( $out as &$values ) {
			// Sort details.
			ksort( $values['Details'] );

			// Leave details empty if there are none.
			if ( [] === $values['Details'] ) {
				$values['Details'] = '';
			}
		}

		$format = get_flag_value( $assoc_args, 'format' );

		format_items(
			is_string( $format ) && $format ? $format : 'table',
			$out,
			array_keys( $first ),
		);
	}

	/**
	 * Add to details about blocks of a certain type.
	 *
	 * For example:
	 *
	 * - Keep track of which heading levels are in use.
	 * - Keep track of how many recirculation modules use custom post titles.
	 *
	 * @phpstan-param array<string, array<string, mixed>> $details
	 * @phpstan-param array{blockName: string, attrs: array<string, mixed>, innerHTML: string} $block
	 * @phpstan-return array<string, mixed>
	 *
	 * @param array   $details Accumulated details about blocks of this type so far.
	 * @param array   $block   Block being audited.
	 * @param WP_Post $post    Post where the block was found.
	 * @return array Updated details.
	 */
	private function with_details( array $details, array $block, WP_Post $post ): array {
		static $has_filter = [];

		$html  = new \WP_HTML_Tag_Processor( $block['innerHTML'] );
		$attrs = $block['attrs'];

		// Automatically track standard alignment attribute.
		if ( isset( $attrs['align'] ) && is_string( $attrs['align'] ) ) {
			$details['align'][ $attrs['align'] ] ??= 0;
			$details['align'][ $attrs['align'] ]++;
		}

		$block_name = $block['blockName'];

		if ( ! $block_name ) {
			$block_name = 'core/classic';
		}

		switch ( $block_name ) {
			case 'core/embed':
				if ( ! empty( $attrs['providerNameSlug'] ) ) {
					$details['providerNameSlug'][ $attrs['providerNameSlug'] ] ??= 0;
					$details['providerNameSlug'][ $attrs['providerNameSlug'] ]++;
				}
				break;

			case 'core/heading':
				while ( $html->next_tag() ) {
					$tag = $html->get_tag();

					if ( is_string( $tag ) && preg_match( '/^H\d$/', $tag ) ) {
						$details[ $tag ] ??= 0;
						$details[ $tag ]++;
					}
				}
				break;

			default:
				break;
		}

		$should_filter =
			( $has_filter['all'] ??= has_filter( 'alley_block_audit_block_type_details' ) )
			|| ( $has_filter[ $block_name ] ??= has_filter( "alley_block_audit_{$block_name}_block_type_details" ) );

		if ( $should_filter ) {
			/**
			 * Filters the details about a block.
			 *
			 * @param array   $details    Details about blocks of this type so far.
			 * @param string  $block_name Name of the block being audited.
			 * @param array   $attrs      Block attributes.
			 * @param string  $innerHTML  Inner HTML of the block.
			 * @param array   $block      Block being audited.
			 * @param WP_Post $post       The post where the block was found.
			 * @return array Updated block type details.
			 */
			$details = apply_filters( 'alley_block_audit_block_type_details', $details, $block_name, $attrs, $block['innerHTML'], $block, $post );

			/**
			 * Filters the details about a block.
			 *
			 * The dynamic portion of the hook name, `$block_name`, refers to the name of the block being audited.
			 *
			 * @param array   $details    Details about blocks of this type so far.
			 * @param string  $block_name Name of the block being audited.
			 * @param array   $attrs      Block attributes.
			 * @param string  $innerHTML  Inner HTML of the block.
			 * @param array   $block      Block being audited.
			 * @param WP_Post $post       The post where the block was found.
			 * @return array Updated block type details.
			 */
			$details = apply_filters( "alley_block_audit_{$block_name}_block_type_details", $details, $block_name, $attrs, $block['innerHTML'], $block, $post );
		}

		if ( ! is_array( $details ) ) {
			$details = [];
		}

		return $details;
	}
}
