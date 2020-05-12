<?php

/**
 * Class to batch-process multiple menu items in a single request
 *
 * @see WP_REST_Posts_Controller
 */
class WP_REST_Menu_Items_Batch_Processor {

	private $controller;
	private $request;

	public function __construct( WP_REST_Menu_Items_Controller $controller, $request ) {
		global $wpdb;

		$this->request = $request;
		$this->controller = $controller;
		$this->wpdb = $wpdb;
	}

	public function process( $navigation_id, $tree ) {
		global $wpdb;

		$validated_operations = $this->bulk_validate( $navigation_id, $tree );
		if ( is_wp_error( $validated_operations ) ) {
			return $validated_operations;
		}

		$wpdb->query( 'START TRANSACTION' );
		$wpdb->query( 'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ' );

		$result = $this->bulk_persist( $validated_operations );

		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );

			return $result;
		}

		$wpdb->query( 'COMMIT' );

		return $result;
	}

	protected function bulk_validate( $navigation_id, $input_tree ) {
		$operations = $this->diff( $navigation_id, $input_tree );

		$this->controller->ignore_position_collision = true;
		foreach ( $operations as $operation ) {
			$result = $operation->validate();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $operations;
	}

	protected function diff( $navigation_id, $tree ) {
		$current_menu_items = wp_get_nav_menu_items( $navigation_id, array( 'post_status' => 'publish,draft' ) );
		$operations = [];

		$stack = [
			[ null, $tree ],
		];
		$updated_ids = [];
		while ( ! empty( $stack ) ) {
			list( $parent_operation, $raw_menu_items ) = array_pop( $stack );
			foreach ( $raw_menu_items as $raw_menu_item ) {
				$children = ! empty( $raw_menu_item['children'] ) ? $raw_menu_item['children'] : [];
				unset( $raw_menu_item['children'] );

				if ( ! empty( $raw_menu_item['id'] ) ) {
					$updated_ids[] = $raw_menu_item['id'];
					// Only process updated menu items
//					if ( $raw_menu_item['dirty'] ) {
					$operation = new UpdateOperation( $this->controller, $raw_menu_item, $parent_operation );
					$operations[] = $operation;
//					}
				} else {
					$operation = new InsertOperation( $this->controller, $raw_menu_item, $parent_operation );
					$operations[] = $operation;
				}

				if ( $children ) {
					array_push( $stack, [ $operation, $children ] );
				}
			}
		}

		// Delete any orphaned items
		foreach ( $current_menu_items as $item ) {
			if ( ! in_array( $item->ID, $updated_ids ) ) {
				$operations[] = new DeleteOperation( $this->controller, [ 'menus' => $navigation_id, 'force' => true, 'id' => $item->ID ] );
			}
		}

		return $operations;
	}

	protected function bulk_persist( $validated_operations ) {
		$response_data = [];

		foreach ( $validated_operations as $operation ) {
			$result = $operation->persist( $this->request );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$response_data[] = [ 'ok' ];
		}

		return $response_data;
	}

}


abstract class Operation {
	const INSERT = 'insert';
	const UPDATE = 'update';
	const DELETE = 'delete';

	protected $controller;
	public $input;
	/** @var Operation */
	public $parent;
	public $prepared_item;
	public $result;

	/**
	 * Operation constructor.
	 *
	 * @param $input
	 * @param $parent
	 * @param $result
	 */
	public function __construct( WP_REST_Menu_Items_Controller $controller, $input, $parent = null ) {
		$this->controller = $controller;
		$this->input = $input;
		$this->parent = $parent;
	}

	public function validate() {
		$result = $this->doValidate();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->prepared_item = $result;

		return $result;
	}

	abstract protected function doValidate();

	public function persist( $request ) {
		if ( ! empty( $this->parent ) && $this->prepared_item ) {
			$this->prepared_item['menu-item-parent-id'] = $this->parent->result->ID;
		}

		$this->result = $this->doPersist( $request );

		return $this->result;
	}

	abstract protected function doPersist( $request );
}

class InsertOperation extends Operation {

	public function doValidate() {
		return $this->controller->create_item_validate( $this->input['id'] ?? null, $this->input );
	}

	public function doPersist( $request ) {
		return $this->controller->create_item_persist( $this->prepared_item, $this->input, $request );
	}

}

class UpdateOperation extends Operation {

	public function doValidate() {
		return $this->controller->update_item_validate( $this->input['id'], $this->input );
	}

	public function doPersist( $request ) {
		return $this->controller->update_item_persist( $this->prepared_item, $this->input, $request );
	}

}

class DeleteOperation extends Operation {

	public function doValidate() {
		return $this->controller->delete_item_validate( $this->input['id'], $this->input );
	}

	public function doPersist( $request ) {
		return $this->controller->delete_item_persist( $this->input['id'] );
	}

}
