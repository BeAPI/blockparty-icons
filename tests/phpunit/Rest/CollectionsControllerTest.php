<?php
/**
 * The endpoint the editor uses to discover which collections exist.
 *
 * @package Blockparty\Icons
 */

declare( strict_types=1 );

namespace Blockparty\Icons\Tests\Rest;

use Blockparty\Icons\Tests\TestCase;
use WP_REST_Request;

use function Blockparty\Icons\register_icon_collection;

class CollectionsControllerTest extends TestCase {

	private int $editor_id;
	private int $subscriber_id;

	public function set_up() {
		parent::set_up();

		$this->editor_id     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
	}

	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	private function boot_rest(): void {
		do_action( 'rest_api_init' );
	}

	private function get_collections() {
		return rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/icons/v1/collections' ) );
	}

	/* --------------------------------------------------------- permissions */

	public function test_the_endpoint_requires_edit_posts(): void {
		$this->boot_rest();
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->get_collections()->get_status() );
	}

	public function test_a_subscriber_is_refused(): void {
		$this->boot_rest();
		wp_set_current_user( $this->subscriber_id );

		$this->assertSame( 403, $this->get_collections()->get_status() );
	}

	/* --------------------------------------------------------------- shape */

	public function test_no_collections_yields_an_empty_response(): void {
		$this->boot_rest();
		wp_set_current_user( $this->editor_id );

		$response = $this->get_collections();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data() );
	}

	public function test_collections_are_keyed_by_name(): void {
		register_icon_collection( 'one', [ 'label' => 'First' ] );
		register_icon_collection( 'two', [ 'label' => 'Second' ] );
		$this->boot_rest();
		wp_set_current_user( $this->editor_id );

		$data = $this->get_collections()->get_data();

		$this->assertSame( [ 'one', 'two' ], array_keys( $data ) );
	}

	public function test_each_collection_reports_name_label_and_count(): void {
		$folder = $this->make_icon_folder(
			[
				'alpha.svg' => $this->svg(),
				'beta.svg'  => $this->svg(),
			]
		);
		register_icon_collection(
			'folder-icons',
			[
				'label'  => 'Folder icons',
				'type'   => 'folder',
				'source' => $folder,
			]
		);
		$this->boot_rest();
		wp_set_current_user( $this->editor_id );

		$entry = $this->get_collections()->get_data()['folder-icons'];

		$this->assertSame( 'folder-icons', $entry['name'] );
		$this->assertSame( 'Folder icons', $entry['label'] );
		$this->assertSame( 2, $entry['count'] );
	}

	public function test_the_listing_carries_no_icon_payloads(): void {
		$folder = $this->make_icon_folder( [ 'alpha.svg' => $this->svg( 'MARKER' ) ] );
		register_icon_collection(
			'folder-icons',
			[
				'type'   => 'folder',
				'source' => $folder,
			]
		);
		$this->boot_rest();
		wp_set_current_user( $this->editor_id );

		$encoded = wp_json_encode( $this->get_collections()->get_data() );

		$this->assertStringNotContainsString(
			'MARKER',
			$encoded,
			'Listing collections must stay a directory, never ship the SVGs.'
		);
	}

	public function test_the_route_is_registered(): void {
		$this->boot_rest();

		$this->assertArrayHasKey( '/icons/v1/collections', rest_get_server()->get_routes() );
	}
}
