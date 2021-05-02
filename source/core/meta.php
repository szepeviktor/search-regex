<?php

namespace SearchRegex;

use SearchRegex\Search_Source;
use SearchRegex\Sql\Sql_Select;
use SearchRegex\Sql\Sql_Value;

abstract class Source_Meta extends Search_Source {
	public function get_table_id() {
		return 'meta_id';
	}

	public function get_title_column() {
		return 'meta_key';
	}

	abstract public function get_meta_name();

	/**
	 * Return the meta object ID name
	 *
	 * @return String
	 */
	abstract public function get_meta_object_id();

	/**
	 * Return the meta table name
	 *
	 * @return String
	 */
	abstract public function get_meta_table();

	public function save( $row_id, array $updates ) {
		global $wpdb;

		$meta = $this->get_columns_to_change( $updates );

		if ( count( $meta ) > 0 ) {
			$this->log_save( 'meta', $meta );

			// This does all the sanitization
			$result = true;

			if ( searchregex_can_save() ) {
				$result = $wpdb->update( $this->get_meta_table(), $meta, [ $this->get_table_id() => $row_id ] );
				if ( $result === null ) {
					return new \WP_Error( 'searchregex', 'Failed to update meta data: ' . $this->get_meta_table() );
				}

				// Clear any cache
				wp_cache_delete( $this->get_meta_object_id(), $this->get_meta_table() . '_meta' );
			}
		}

		return true;
	}

	public function delete_row( $row_id ) {
		$this->log_save( 'delete meta', $row_id );

		if ( searchregex_can_save() ) {
			global $wpdb;

			$result = $wpdb->delete( $this->get_table_name(), [ $this->get_table_id() => $row_id ] );
			if ( $result ) {
				wp_cache_delete( $this->get_meta_object_id(), $this->get_meta_table() . '_meta' );
				return true;
			}

			return new \WP_Error( 'searchregex_delete', 'Failed to delete meta', 401 );
		}

		return true;
	}

	public function autocomplete( $column, $value ) {
		global $wpdb;

		if ( in_array( $column['column'], [ 'meta_key', 'meta_value' ], true ) ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT " . $column['column'] . " as id," . $column['column'] . " as value FROM {$this->get_table_name()} WHERE " . $column['column'] . " LIKE %s LIMIT %d", '%' . $wpdb->esc_like( $value ) . '%', self::AUTOCOMPLETE_LIMIT ) );
		}

		return [];
	}

	public function get_schema() {
		return [
			'name' => $this->get_meta_name(),
			'table' => $this->get_table_name(),
			'columns' => [
				[
					'column' => $this->get_meta_object_id(),
					'type' => 'integer',
					'title' => __( 'Owner ID', 'search-regex' ),
					'options' => 'api',
					'joined_by' => $this->get_meta_table(),
				],
				[
					'column' => 'meta_key',
					'type' => 'string',
					'title' => __( 'Meta Key', 'search-regex' ),
					'options' => 'api',
					'global' => true,
				],
				[
					'column' => 'meta_value',
					'type' => 'string',
					'title' => __( 'Meta Value', 'search-regex' ),
					'options' => 'api',
					'multiline' => true,
					'global' => true,
				],
			],
		];
	}
}
