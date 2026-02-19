<?php

namespace Blockparty\Icons\Rest;

use Blockparty\Icons\Icon\Collection;
use function Blockparty\Icons\get_icon_collections;

class CollectionsController extends \WP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			'icons/v1',
			'/collections',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	public function get_items_permissions_check( $request ) {
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
		$data        = [];
		$collections = get_icon_collections();

		foreach ( $collections as $collection ) {
			$response_item               = $this->prepare_item_for_response( $collection, $request );
			$data[ $collection->name() ] = $this->prepare_response_for_collection( $response_item );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * @param Collection $item
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

		if ( in_array( 'count', $fields, true ) ) {
			$data['count'] = $item->count();
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		return rest_ensure_response( $data );
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
			'title'      => 'collection',
			'type'       => 'object',
			'properties' => [
				'name'  => [
					'description' => __( 'Identifier of the collection', 'blockparty-icons' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'label' => [
					'description' => __( 'Label of the collection', 'blockparty-icons' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'count' => [
					'description' => __( 'Number of icons in the collection', 'blockparty-icons' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
			],
		];

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
