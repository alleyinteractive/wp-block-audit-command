<?php
/**
 * Block_Audit_Command class file
 *
 * @package wp-block-audit-command
 */

namespace Alley\WP\Features;

use Alley\WP\Types\Feature;
use Alley\WP_Bulk_Task\Bulk_Task;
use Alley\WP_Bulk_Task\Progress\Null_Progress_Bar;
use Alley\WP_Bulk_Task\Progress\PHP_CLI_Progress_Bar;
use WP_CLI;

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
		WP_CLI::add_command( 'block-audit', $this );
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
	 *  [--<field>=<value>]
	 * : One or more args to pass to WP_Query except for 'order', 'orderby', or 'paged'.
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
	 * [--verbose]
	 * : Turn on verbose mode.
	 *
	 * [--rewind]
	 * : Resets the cursor so the next time the command is run it will start from the beginning.
	 *
	 * ## EXAMPLES
	 *
	 * $ wp block-audit run --post_type=post,page
	 * +-----------------------------------+-------+------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
	 * | Block Name                        | Count | Example URL                                                | Post Types      | Details                                                              |
	 * +-----------------------------------+-------+------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
	 * | core/archives                     | 3     | https://www.example.com/2023/01/13/widgets-block-category/ | ["post"]        |                                                                      |
	 * | core/button                       | 12    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        | {"align":{"left":2,"center":1,"right":1}}                            |
	 * | core/code                         | 2     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 * | core/column                       | 40    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        |                                                                      |
	 * | core/columns                      | 13    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        | {"align":{"wide":2,"full":1}}                                        |
	 * | core/cover                        | 21    | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        | {"align":{"left":1,"center":2,"full":1,"wide":2}}                    |
	 * | core/file                         | 3     | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        |                                                                      |
	 * | core/gallery                      | 10    | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        |                                                                      |
	 * | core/group                        | 25    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        |                                                                      |
	 * | core/heading                      | 23    | https://www.example.com/2023/01/13/text-category-blocks/   | ["post","page"] | {"H1":2,"H2":11,"H3":4,"H4":2,"H5":2,"H6":2}                         |
	 * | core/html                         | 2     | https://www.example.com/2023/01/13/widgets-block-category/ | ["post"]        |                                                                      |
	 * | core/image                        | 19    | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        | {"align":{"center":2,"left":2,"right":3,"none":1,"wide":1,"full":1}} |
	 * | core/list                         | 9     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 * | core/list-item                    | 6     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 * | core/media-text                   | 6     | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        | {"align":{"full":1}}                                                 |
	 * | core/paragraph                    | 262   | https://www.example.com/2023/01/13/text-category-blocks/   | ["post","page"] | {"align":{"center":16,"right":1,"left":1}}                           |
	 * | core/pullquote                    | 4     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 * | core/spacer                       | 4     | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        |                                                                      |
	 * | core/table                        | 4     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
	 * +-----------------------------------+-------+---------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
	 *
	 * @phpstan-param array<string> $args
	 * @phpstan-param array<string, string> $assoc_args
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function run( array $args, array $assoc_args = [] ): void {
		global $wpdb;

		$out = [];

		$user_query_args = array_diff_key( $assoc_args, array_flip( [ 'format', 'verbose', 'rewind' ] ) );

		$bulk_task = new Bulk_Task(
			'audit-blocks-' . md5( (string) wp_json_encode( $user_query_args ) ),
			get_flag_value( $assoc_args, 'verbose', false )
				? new PHP_CLI_Progress_Bar( 'Bulk Task: audit-blocks' )
				: new Null_Progress_Bar(),
		);

		if ( get_flag_value( $assoc_args, 'rewind', false ) ) {
			$bulk_task->cursor->reset();
			\WP_CLI::log( 'Rewound the cursor. Run again without the --rewind flag to process posts.' );
			return;
		}

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

		$bulk_task->run(
			$query_args,
			function ( \WP_Post $post ) use ( &$out ) {
				$blocks = match_blocks(
					$post,
					[
						'flatten'           => true,
						'skip_empty_blocks' => false, // For counting classic blocks.
					],
				);

				if ( ! is_iterable( $blocks ) ) {
					return;
				}

				foreach ( $blocks as $block ) {
					$block_name = $block['blockName'];

					// Label null blocks as classic blocks if they contain HTML.
					if ( ! $block_name ) {
						$html = $block['innerHTML'] ?? '';
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
						 */
						$example_url = apply_filters( 'alley_block_audit_block_type_example_url', get_permalink( $post ) );

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
					);
				}
			},
		);

		if ( count( $out ) === 0 ) {
			\WP_CLI::warning( 'No results. Run again with the --rewind flag to reset the cursor.' );
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
	 * @param array $details Accumulated details about blocks of this type so far.
	 * @param array $block   Block being audited.
	 * @return array Updated details.
	 */
	private function with_details( array $details, array $block ): array {
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
			 * @param array  $details    Details about blocks of this type so far.
			 * @param string $block_name Name of the block being audited.
			 * @param array  $attrs      Block attributes.
			 * @param string $innerHTML  Inner HTML of the block.
			 * @param array  $block      Block being audited.
			 * @return array Updated block type details.
			 */
			$details = apply_filters( "alley_block_audit_block_type_details", $details, $block_name, $attrs, $block['innerHTML'], $block );

			/**
			 * Filters the details about a block.
			 *
			 * The dynamic portion of the hook name, `$block_name`, refers to the name of the block being audited.
			 *
			 * @param array  $details    Details about blocks of this type so far.
			 * @param string $block_name Name of the block being audited.
			 * @param array  $attrs      Block attributes.
			 * @param string $innerHTML  Inner HTML of the block.
			 * @param array  $block      Block being audited.
			 * @return array Updated block type details.
			 */
			$details = apply_filters( "alley_block_audit_{$block_name}_block_type_details", $details, $block_name, $attrs, $block['innerHTML'], $block );
		}

		if ( ! is_array( $details ) ) {
			$details = [];
		}

		return $details;
	}
}
