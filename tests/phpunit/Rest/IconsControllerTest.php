<?php
/**
 * The REST surface the block editor reads icons from.
 *
 * @package Blockparty\Icons
 */

declare( strict_types=1 );

namespace Blockparty\Icons\Tests\Rest;

use Blockparty\Icons\Tests\TestCase;
use WP_REST_Request;

use function Blockparty\Icons\register_icon_collection;

class IconsControllerTest extends TestCase {

	private int $editor_id;
	private int $subscriber_id;

	public function set_up() {
		parent::set_up();

		$this->editor_id     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$folder = $this->make_icon_folder(
			[
				'arrow-left.svg'  => $this->svg( 'M1 1' ),
				'arrow-right.svg' => $this->svg( 'M2 2' ),
				'star.svg'        => $this->svg( 'M3 3' ),
			]
		);

		register_icon_collection(
			'test-icons',
			[
				'label'  => 'Test icons',
				'type'   => 'folder',
				'source' => $folder,
			]
		);

		// Routes are registered from the collections that exist at rest_api_init.
		do_action( 'rest_api_init' );
	}

	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * @param string $route
	 * @param array  $params
	 *
	 * @return \WP_REST_Response
	 */
	private function get( string $route, array $params = [] ) {
		$request = new WP_REST_Request( 'GET', $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/* --------------------------------------------------------- permissions */

	public function test_listing_requires_edit_posts(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->get( '/icons/v1/test-icons' )->get_status() );
	}

	public function test_a_subscriber_is_refused(): void {
		wp_set_current_user( $this->subscriber_id );

		$this->assertSame( 403, $this->get( '/icons/v1/test-icons' )->get_status() );
	}

	public function test_an_editor_is_allowed(): void {
		wp_set_current_user( $this->editor_id );

		$this->assertSame( 200, $this->get( '/icons/v1/test-icons' )->get_status() );
	}

	/* --------------------------------------------------------------- items */

	public function test_listing_returns_every_icon_keyed_by_name(): void {
		wp_set_current_user( $this->editor_id );

		$data = $this->get( '/icons/v1/test-icons' )->get_data();

		$this->assertSame( [ 'arrow-left', 'arrow-right', 'star' ], array_keys( $data ) );
	}

	public function test_each_icon_exposes_its_fields(): void {
		wp_set_current_user( $this->editor_id );

		$data = $this->get( '/icons/v1/test-icons' )->get_data();
		$icon = $data['arrow-left'];

		$this->assertSame( 'arrow-left', $icon['name'] );
		$this->assertSame( 'arrow-left', $icon['label'] );
		$this->assertSame( 'raw', $icon['type'] );
		$this->assertSame( $this->svg( 'M1 1' ), $icon['content'] );
		$this->assertArrayHasKey( 'version', $icon );
	}

	public function test_totals_are_reported_in_headers(): void {
		wp_set_current_user( $this->editor_id );

		$response = $this->get( '/icons/v1/test-icons' );
		$headers  = $response->get_headers();

		$this->assertSame( 3, $headers['X-WP-Total'] );
		$this->assertSame( 1.0, (float) $headers['X-WP-TotalPages'] );
	}

	/* ---------------------------------------------------------- pagination */

	public function test_per_page_limits_the_result(): void {
		wp_set_current_user( $this->editor_id );

		$data = $this->get( '/icons/v1/test-icons', [ 'per_page' => 2 ] )->get_data();

		$this->assertCount( 2, $data );
		$this->assertSame( [ 'arrow-left', 'arrow-right' ], array_keys( $data ) );
	}

	public function test_the_second_page_continues_where_the_first_stopped(): void {
		wp_set_current_user( $this->editor_id );

		$data = $this->get(
			'/icons/v1/test-icons',
			[
				'per_page' => 2,
				'page'     => 2,
			]
		)->get_data();

		$this->assertSame( [ 'star' ], array_keys( $data ) );
	}

	public function test_page_count_reflects_per_page(): void {
		wp_set_current_user( $this->editor_id );

		$headers = $this->get( '/icons/v1/test-icons', [ 'per_page' => 2 ] )->get_headers();

		$this->assertSame( 3, $headers['X-WP-Total'] );
		$this->assertSame( 2.0, (float) $headers['X-WP-TotalPages'] );
	}

	public function test_a_page_past_the_end_is_empty(): void {
		wp_set_current_user( $this->editor_id );

		$data = $this->get(
			'/icons/v1/test-icons',
			[
				'per_page' => 2,
				'page'     => 99,
			]
		)->get_data();

		$this->assertSame( [], $data );
	}

	/* -------------------------------------------------------------- search */

	public function test_search_narrows_the_list(): void {
		wp_set_current_user( $this->editor_id );

		$data = $this->get( '/icons/v1/test-icons', [ 'search' => 'arrow' ] )->get_data();

		$this->assertSame( [ 'arrow-left', 'arrow-right' ], array_keys( $data ) );
	}

	public function test_search_totals_describe_the_filtered_set(): void {
		wp_set_current_user( $this->editor_id );

		$headers = $this->get( '/icons/v1/test-icons', [ 'search' => 'arrow' ] )->get_headers();

		$this->assertSame( 2, $headers['X-WP-Total'] );
	}

	public function test_search_with_no_match_is_empty(): void {
		wp_set_current_user( $this->editor_id );

		$response = $this->get( '/icons/v1/test-icons', [ 'search' => 'zzzz' ] );

		$this->assertSame( [], $response->get_data() );
		$this->assertSame( 0, $response->get_headers()['X-WP-Total'] );
	}

	/* ---------------------------------------------------------- single item */

	public function test_a_single_icon_can_be_fetched(): void {
		wp_set_current_user( $this->editor_id );

		$response = $this->get( '/icons/v1/test-icons/star' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'star', $response->get_data()['name'] );
		$this->assertSame( $this->svg( 'M3 3' ), $response->get_data()['content'] );
	}

	public function test_an_unknown_icon_is_a_404(): void {
		wp_set_current_user( $this->editor_id );

		$response = $this->get( '/icons/v1/test-icons/nope' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'rest_icon_invalid_name', $response->get_data()['code'] );
	}

	/* --------------------------------------------------------------- routes */

	public function test_a_route_exists_for_each_registered_collection(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/icons/v1/test-icons', $routes );
		$this->assertArrayHasKey( '/icons/v1/test-icons/(?P<name>[\w\-]+)', $routes );
	}

	public function test_no_route_exists_for_an_unregistered_collection(): void {
		wp_set_current_user( $this->editor_id );

		$this->assertSame( 404, $this->get( '/icons/v1/never-registered' )->get_status() );
	}
}
