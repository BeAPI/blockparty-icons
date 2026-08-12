<?php
/**
 * WP-CLI command to migrate beapi/icon-block content.
 *
 * @package Blockparty\Icons
 */

namespace Blockparty\Icons\Cli;

use Blockparty\Icons\Migration\IconBlockMigrator;
use WP_CLI;
use WP_CLI_Command;
use WP_Query;

/**
 * Blockparty Icons WP-CLI commands.
 */
class MigrateFromIconBlockCommand extends WP_CLI_Command {

	/**
	 * Migrate beapi/icon-block and beapi/icon-item blocks to blockparty/icon.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report changes without updating the database.
	 *
	 * [--post-type=<post-types>]
	 * : Comma-separated list of post types. Default: any public post type that shows in the REST API, plus wp_block.
	 *
	 * [--posts-per-page=<number>]
	 * : Batch size. Default: 100.
	 *
	 * ## EXAMPLES
	 *
	 *     wp blockparty-icons migrate-from-icon-block --dry-run
	 *     wp blockparty-icons migrate-from-icon-block --post-type=post,page
	 *     wp blockparty-icons migrate-from-icon-block --url=https://example.com/
	 *
	 * @subcommand migrate-from-icon-block
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 * @author Jules Fell
	 */
	public function __invoke( $args, $assoc_args ): void {
		$dry_run        = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$posts_per_page = (int) ( $assoc_args['posts-per-page'] ?? 100 );
		$post_types     = $this->resolve_post_types( $assoc_args['post-type'] ?? '' );

		if ( empty( $post_types ) ) {
			WP_CLI::error( 'No post types to migrate.' );
		}

		WP_CLI::log(
			sprintf(
				'Migrating site %1$d (%2$s)%3$s — post types: %4$s',
				get_current_blog_id(),
				home_url( '/' ),
				$dry_run ? ' [dry-run]' : '',
				implode( ', ', $post_types )
			)
		);

		$migrator       = new IconBlockMigrator();
		$posts_updated  = 0;
		$posts_scanned  = 0;
		$page           = 1;
		$max_pages      = 1;
		$total_migrated = 0;
		$total_skipped  = 0;

		while ( $page <= $max_pages ) {
			$query = new WP_Query(
				[
					'post_type'              => $post_types,
					'post_status'            => 'any',
					'posts_per_page'         => $posts_per_page,
					'paged'                  => $page,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'ignore_sticky_posts'    => true,
					'no_found_rows'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);

			$max_pages = (int) $query->max_num_pages;

			if ( ! $query->have_posts() ) {
				break;
			}

			foreach ( $query->posts as $post ) {
				++$posts_scanned;

				if ( ! is_string( $post->post_content ) || '' === $post->post_content ) {
					continue;
				}

				if ( false === strpos( $post->post_content, 'beapi/icon-block' ) && false === strpos( $post->post_content, 'beapi/icon-item' ) ) {
					continue;
				}

				$before_migrated = $migrator->migrated;
				$before_skipped  = $migrator->skipped;
				$new_content     = $migrator->migrate_content( $post->post_content );

				$delta_migrated = $migrator->migrated - $before_migrated;
				$delta_skipped  = $migrator->skipped - $before_skipped;
				$total_migrated += $delta_migrated;
				$total_skipped  += $delta_skipped;

				if ( null === $new_content ) {
					continue;
				}

				++$posts_updated;

				WP_CLI::log(
					sprintf(
						'%1$s post %2$d (%3$s) — blocks migrated: %4$d, skipped: %5$d',
						$dry_run ? '[dry-run]' : '[update]',
						(int) $post->ID,
						$post->post_type,
						$delta_migrated,
						$delta_skipped
					)
				);

				if ( ! $dry_run ) {
					// wp_update_post() expects slashed data; without wp_slash(),
					// block comment escapes like \u002d (for "--") become bare "u002d".
					$updated = wp_update_post(
						[
							'ID'           => (int) $post->ID,
							'post_content' => wp_slash( $new_content ),
						],
						true
					);

					if ( is_wp_error( $updated ) ) {
						WP_CLI::warning(
							sprintf(
								'Failed to update post %1$d: %2$s',
								(int) $post->ID,
								$updated->get_error_message()
							)
						);
					}
				}
			}

			++$page;
			wp_reset_postdata();
		}

		WP_CLI::success(
			sprintf(
				'Done. Posts scanned: %1$d, posts %2$s: %3$d, icon blocks migrated: %4$d, skipped: %5$d.',
				$posts_scanned,
				$dry_run ? 'that would update' : 'updated',
				$posts_updated,
				$total_migrated,
				$total_skipped
			)
		);

		$this->report_missing_icons( $migrator->missing_icons );
	}

	/**
	 * Print icons referenced by migrated markup but missing from collections/assets.
	 *
	 * @param array<string, int> $missing_icons Keys are "collection/name", values are counts.
	 * @return void
	 * @author Jules Fell
	 */
	private function report_missing_icons( array $missing_icons ): void {
		if ( empty( $missing_icons ) ) {
			WP_CLI::log( 'No missing icon assets detected.' );
			return;
		}

		arsort( $missing_icons, SORT_NUMERIC );

		WP_CLI::warning(
			sprintf(
				'%d icon asset(s) are referenced in migrated markup but were not found in registered collections. Add them to theme assets (or the matching collection) if needed:',
				count( $missing_icons )
			)
		);

		foreach ( $missing_icons as $key => $count ) {
			WP_CLI::log( sprintf( '  - %s (%d)', $key, $count ) );
		}
	}

	/**
	 * Resolve target post types.
	 *
	 * @param string $raw Comma-separated post types from CLI.
	 * @return string[]
	 * @author Jules Fell
	 */
	private function resolve_post_types( string $raw ): array {
		if ( '' !== trim( $raw ) ) {
			$types = array_filter( array_map( 'trim', explode( ',', $raw ) ) );

			return array_values( $types );
		}

		$types = get_post_types(
			[
				'public'       => true,
				'show_in_rest' => true,
			],
			'names'
		);

		$types[] = 'wp_block';
		$types   = array_unique( array_values( $types ) );

		return $types;
	}
}
