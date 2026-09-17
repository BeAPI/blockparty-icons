<?php
/**
 * Shared base class for the Blockparty Icons test suite.
 *
 * @package Blockparty\Icons
 */

declare( strict_types=1 );

namespace Blockparty\Icons\Tests;

use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Directories created for a single test, removed on tear down.
	 *
	 * @var string[]
	 */
	private array $temp_dirs = [];

	public function set_up() {
		parent::set_up();

		// Collections live in a global that the plugin fills on `init`, which has
		// already fired by the time a test runs. Start every test from nothing so
		// tests cannot leak collections into each other.
		$GLOBALS['blockparty_icon_collections'] = [];

		// The plugin caches its indexes aggressively and keys them on file paths.
		// A stale entry from a previous test would mask a real failure.
		wp_cache_flush();
	}

	public function tear_down() {
		foreach ( $this->temp_dirs as $dir ) {
			$this->delete_tree( $dir );
		}
		$this->temp_dirs = [];

		$GLOBALS['blockparty_icon_collections'] = [];

		parent::tear_down();
	}

	/**
	 * Create a directory for fixtures, removed automatically after the test.
	 *
	 * Fixtures live under WP_CONTENT_DIR because CollectionItemsFactory derives a
	 * sprite's public URL by swapping WP_CONTENT_DIR for WP_CONTENT_URL. Putting
	 * them anywhere else would make that path untestable.
	 *
	 * @param string $prefix
	 *
	 * @return string Absolute path, without a trailing slash.
	 */
	protected function make_temp_dir( string $prefix = 'icons' ): string {
		$dir = WP_CONTENT_DIR . '/bpi-tests/' . $prefix . '-' . wp_generate_password( 8, false );

		if ( ! wp_mkdir_p( $dir ) ) {
			$this->fail( "Could not create fixture directory {$dir}" );
		}

		$this->temp_dirs[] = $dir;

		return $dir;
	}

	/**
	 * Write a folder of SVG files.
	 *
	 * @param array<string, string> $files Map of file name => file contents.
	 *
	 * @return string Absolute path to the folder.
	 */
	protected function make_icon_folder( array $files ): string {
		$dir = $this->make_temp_dir( 'folder' );

		foreach ( $files as $name => $contents ) {
			file_put_contents( $dir . '/' . $name, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		return $dir;
	}

	/**
	 * Write a single file and return its path.
	 *
	 * @param string $name
	 * @param string $contents
	 *
	 * @return string
	 */
	protected function make_file( string $name, string $contents ): string {
		$dir  = $this->make_temp_dir( 'file' );
		$path = $dir . '/' . $name;

		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $path;
	}

	/**
	 * Build a minimal but valid single-icon SVG.
	 *
	 * @param string $marker Text placed in the path data so a test can tell icons apart.
	 *
	 * @return string
	 */
	protected function svg( string $marker = 'M0 0h24v24H0z' ): string {
		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="%s"/></svg>',
			$marker
		);
	}

	/**
	 * Build a sprite containing the given symbol ids.
	 *
	 * @param string[] $ids
	 *
	 * @return string
	 */
	protected function sprite( array $ids ): string {
		$symbols = '';
		foreach ( $ids as $id ) {
			$symbols .= sprintf(
				'<symbol id="%s" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></symbol>',
				$id
			);
		}

		return '<svg xmlns="http://www.w3.org/2000/svg"><defs>' . $symbols . '</defs></svg>';
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir
	 */
	private function delete_tree( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( (array) glob( $dir . '/*' ) as $entry ) {
			if ( is_dir( $entry ) ) {
				$this->delete_tree( $entry );
				continue;
			}
			unlink( $entry ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
