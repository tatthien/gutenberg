<?php

/**
 * Class to batch-process multiple menu items in a single request
 *
 * @see WP_REST_Posts_Controller
 */
class WP_REST_Menu_Items_Batch_Processor {

	private $controller;

	public function __construct( WP_REST_Menu_Items_Controller $controller ) {
		$this->controller = $controller;
	}

	public function process( $navigation_id, $tree ) {
		$this->controller->ignore_position_collision = true;

		$current_menu_items = wp_get_nav_menu_items( $navigation_id, array( 'post_status' => 'publish,draft' ) );

		// @TODO: BEGIN transaction
		$stack = [
			[ 0, $tree ],
		];
		$updated_ids = [];
		while ( ! empty( $stack ) ) {
			list( $parent_id, $raw_menu_items ) = array_pop( $stack );
			foreach ( $raw_menu_items as $raw_menu_item ) {
				$raw_menu_item['parent'] = $parent_id;
				$children = ! empty( $raw_menu_item['children'] ) ? $raw_menu_item['children'] : [];
				unset( $raw_menu_item['children'] );

				if ( $raw_menu_item['id'] ) {
					$updated_ids[] = $raw_menu_item['id'];
					$result = $this->request_update( $raw_menu_item );
				} else {
					$result = $this->request_insert( $raw_menu_item );
				}
				if ( is_wp_error( $result ) ) {
					// @TODO: ROLLBACK transaction
					return $result;
				}
				$database_id = $result['id'];
				if ( $children ) {
					array_push( $stack, [ $database_id, $children ] );
				}
			}
		}

		// Delete any orphaned items
		foreach ( $current_menu_items as $item ) {
			if ( ! in_array( $item->ID, $updated_ids ) ) {
				$this->request_delete( $item->ID );
			}
		}
		// @TODO: COMMIT transaction

		return $this->request_items();
	}

	public function request_insert( $raw_data ) {
		$response = $this->controller->create_item( $this->mock_request( $raw_data ) );

		return is_wp_error( $response ) ? $response : $response->get_data();
	}

	public function request_update( $raw_data ) {
		$response = $this->controller->update_item( $this->mock_request( $raw_data ) );

		return is_wp_error( $response ) ? $response : $response->get_data();
	}

	public function request_delete( $id ) {
		$response = $this->controller->delete_item( $this->mock_request(
			[ 'id' => $id, 'force' => true ]
		) );

		return is_wp_error( $response ) ? $response : $response->get_data();
	}

	public function request_items() {
		return $this->controller->get_items( $this->mock_request() );
	}

	private function mock_request( $input = [] ) {
		$mock_request = new WP_REST_Request();
		foreach ( $input as $k => $v ) {
			$mock_request[ $k ] = $v;
		}

		return $mock_request;
	}

}
