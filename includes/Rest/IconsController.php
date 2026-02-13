<?php

namespace Blockparty\Icons\Rest;

use Blockparty\Icons\Icon\Collection;
use Blockparty\Icons\Icon\CollectionItem;

class IconsController extends \WP_REST_Controller {

	/**
	 * @var Collection
	 */
	private $collection;

	public function __construct( Collection $collection ) {
		$this->collection = $collection;
	}

	public function register_routes() {
		register_rest_route(
			'icons/v1',
			'/' . $this->collection->name(),
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		$get_item_args = [
			'context' => $this->get_context_param( [ 'default' => 'view' ] ),
		];
		register_rest_route(
			'icons/v1',
			'/' . $this->collection->name() . '/(?P<name>[\w\-]+)',
			[
				'args'   => [
					'name' => [
						'description' => __( "Icon's name.", 'blockparty-icons' ),
						'type'        => 'string',
					],
				],
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_permissions_check' ],
					'args'                => $get_item_args,
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	public function get_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Retrieves all icons collections.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$paged    = $request->get_param( 'page' ) ?? 1;
		$per_page = $request->get_param( 'per_page' ) ?? 10;
		$search   = $request->get_param( 'search' ) ?? '';
		$paged    = absint( $paged );
		$per_page = absint( $per_page );
		$search   = trim( $search );
		$data     = [];

		$cursor = ( $paged * $per_page ) - $per_page;

		// Get filtered or all items based on search
		if ( ! empty( $search ) ) {
			$filtered_collection = $this->collection->search( $search );
			$items               = $filtered_collection->all();
			$total_count         = $filtered_collection->count();
		} else {
			$items       = $this->collection->all();
			$total_count = $this->collection->count();
		}

		$icons = array_slice( $items, $cursor, $per_page );
		foreach ( $icons as $icon ) {
			$response_item         = $this->prepare_item_for_response( $icon, $request );
			$data[ $icon->name() ] = $this->prepare_response_for_collection( $response_item );
		}

		$response = rest_ensure_response( $data );

		$response->header( 'X-WP-Total', $total_count );
		$response->header( 'X-WP-TotalPages', ceil( $total_count / $per_page ) );

		return $response;
	}

	/**
	 *
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$icon = $this->collection->get( (string) $request->get_param( 'name' ) );
		if ( null === $icon ) {
			return new \WP_Error(
				'rest_icon_invalid_name',
				__( 'Invalid icon name.', 'blockparty-icons' ),
				[ 'status' => 404 ]
			);
		}

		$data = $this->prepare_item_for_response( $icon, $request );

		return rest_ensure_response( $data );
	}

	/**
	 * @param CollectionItem $item
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$fields = $this->get_fields_for_response( $request );
		$data   = [];

		if ( in_array( 'name', $fields, true ) ) {
			$data['name'] = $item->name();
		}

		if ( in_array( 'label', $fields, true ) ) {
			$data['label'] = $item->label();
		}

		if ( in_array( 'type', $fields, true ) ) {
			$data['type'] = $item->type();
		}

		if ( in_array( 'content', $fields, true ) ) {
			$data['content'] = $item->content();
		}

		if ( in_array( 'version', $fields, true ) ) {
			$data['version'] = $item->version();
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		return rest_ensure_response( $data );
	}

	public function get_collection_params() {
		$params                        = parent::get_collection_params();
		$params['per_page']['maximum'] = 500;

		return $params;
	}


	/**
	 * Retrieves the icons collection's schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'icon',
			'type'       => 'object',
			'properties' => [
				'name'    => [
					'description' => __( 'Identifier of the icon', 'blockparty-icons' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'label'   => [
					'description' => __( 'Label of the icon', 'blockparty-icons' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'type'    => [
					'description' => __( 'Type of the icon', 'blockparty-icons' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'content' => [
					'description' => __( 'Content of the icon', 'blockparty-icons' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'version' => [
					'description' => __( 'Version of the icon', 'blockparty-icons' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
			],
		];

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
