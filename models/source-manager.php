<?php

namespace SearchRegex;

use SearchRegex\Search_Source;

/**
 * Create Source objects
 */
class Source_Manager {
	/**
	 * Return all the core source types
	 *
	 * @return Array
	 */
	private static function get_core_sources() {
		$sources = [
			[
				'name' => 'posts',
				'class' => 'SearchRegex\Source_Post',
				'label' => __( 'Posts (core & custom)', 'search-regex' ),
				'type' => 'core',
			],
			[
				'name' => 'comment',
				'class' => 'SearchRegex\Source_Comment',
				'label' => __( 'Comments', 'search-regex' ),
				'type' => 'core',
			],
			[
				'name' => 'user',
				'class' => 'SearchRegex\Source_User',
				'label' => __( 'Users', 'search-regex' ),
				'type' => 'core',
			],
			[
				'name' => 'options',
				'class' => 'SearchRegex\Source_Options',
				'label' => __( 'WordPress Options', 'search-regex' ),
				'type' => 'core',
			],
		];

		return apply_filters( 'searchregex_sources_core', $sources );
	}

	/**
	 * Return all the advanced source types
	 *
	 * @return Array
	 */
	private static function get_advanced_sources() {
		$sources = [
			[
				'name' => 'post-meta',
				'class' => 'SearchRegex\Source_Post_Meta',
				'label' => __( 'Post Meta', 'search-regex' ),
				'type' => 'advanced',
			],
			[
				'name' => 'comment-meta',
				'class' => 'SearchRegex\Source_Comment_Meta',
				'label' => __( 'Comment Meta', 'search-regex' ),
				'type' => 'advanced',
			],
			[
				'name' => 'user-meta',
				'class' => 'SearchRegex\Source_User_Meta',
				'label' => __( 'User Meta', 'search-regex' ),
				'type' => 'advanced',
			],
			[
				'name' => 'terms',
				'class' => 'SearchRegex\Source_Terms',
				'label' => __( 'Terms', 'search-regex' ),
				'type' => 'advanced',
			],
		];

		return apply_filters( 'searchregex_sources_advanced', $sources );
	}

	/**
	 * Return an array of all the database sources. Note this is filtered with `searchregex_sources`
	 *
	 * @return Array The array of database sources as name => class
	 */
	public static function get_all_sources() {
		$core_sources = self::get_core_sources();
		$advanced_sources = self::get_advanced_sources();

		// Load custom stuff here
		$plugin_sources = glob( dirname( SEARCHREGEX_FILE ) . '/source/plugin/*.php' );
		foreach ( $plugin_sources as $plugin ) {
			/**
			 * @psalm-suppress UnresolvableInclude
			 */
			require_once $plugin;
		}

		$plugin_sources = apply_filters( 'searchregex_sources_plugin', [] );
		$plugin_sources = array_map( function( $source ) {
			$source['type'] = 'plugin';
			return $source;
		}, $plugin_sources );

		return array_values(
			array_merge(
				array_values( $core_sources ),
				array_values( $advanced_sources ),
				array_values( $plugin_sources )
			)
		);
	}

	public static function get_schema( array $sources = [] ) {
		$all = self::get_all_source_names();
		$handlers = self::get( $all, [] );
		$schema = [];

		foreach ( $handlers as $source ) {
			$newschema = $source->get_schema_for_source();
			$newschema['type'] = $source->get_type() === 'post' ? 'posts' : $source->get_type();

			if ( count( $sources ) === 0 || in_array( $source->get_type(), $sources, true ) ) {
				$schema[] = $newschema;
			}
		}

		return apply_filters( 'searchregex_schema_source', $schema );
	}

	/**
	 * Get all the sources grouped into 'core', 'posttype', and 'plugin' groups.
	 *
	 * @return Array Associative array of sources, grouped by type
	 */
	public static function get_all_grouped() {
		$sources = self::get_all_sources();

		$groups = [
			[
				'name' => 'core',
				'label' => __( 'Standard', 'search-regex' ),
				'sources' => array_values( array_filter( $sources, function( $source ) {
					return $source['type'] === 'core';
				} ) ),
			],
			[
				'name' => 'advanced',
				'label' => __( 'Advanced', 'search-regex' ),
				'sources' => array_values( array_filter( $sources, function( $source ) {
					return $source['type'] === 'advanced';
				} ) ),
			],
			[
				'name' => 'plugin',
				'label' => __( 'Plugins', 'search-regex' ),
				'sources' => array_values( array_filter( $sources, function( $source ) {
					return $source['type'] === 'plugin';
				} ) ),
			],
		];

		return array_values( array_filter( apply_filters( 'searchregex_source_groups', $groups ), function( $group ) {
			return count( $group['sources'] ) > 0;
		} ) );
	}

	/**
	 * Return a particular Source object for the given name
	 *
	 * @param String               $source Source name.
	 * @param array<Search_Filter> $search_filters Search filters.
	 * @return object|null New Source_Flags object
	 */
	private static function get_handler_for_source( $source, array $search_filters ) {
		$sources = self::get_all_sources();

		foreach ( $sources as $handler ) {
			if ( $handler['name'] === $source ) {
				// Only use the filters for this source
				$filters = array_filter( $search_filters, function( $filter ) use ( $source, $handler ) {
					return $filter->is_for_source( $source );
				} );

				// Create the source
				return new $handler['class']( $handler, $filters );
			}
		}

		return null;
	}

	/**
	 * Return a list of all source names only. This can be used for checking a name is allowed.
	 *
	 * @return Array Array of source names
	 */
	public static function get_all_source_names() {
		$sources = self::get_all_sources();

		return array_map( function( $source ) {
			return $source['name'];
		}, $sources );
	}

	/**
	 * Get all the specified sources as source objects
	 *
	 * @param Array                $sources Array of source names.
	 * @param array<Search_Filter> $search_filters Search filters.
	 * @return Array The array of source objects
	 */
	public static function get( $sources, array $search_filters ) {
		$handlers = [];

		// Create handlers for everything else
		foreach ( $sources as $source ) {
			$handler = self::get_handler_for_source( $source, $search_filters );

			if ( $handler ) {
				$handlers[] = $handler;
			}
		}

		return $handlers;
	}

	public static function get_schema_preload( $source_name, $filter ) {
		if ( ! isset( $filter['column'] ) ) {
			return [];
		}

		$source = self::get_handler_for_source( $source_name, [] );

		if ( $source ) {
			$schema = $source->get_schema();

			foreach ( $schema['columns'] as $column ) {
				if ( $column['column'] === $filter['column'] ) {
					$preload = false;
					if ( $column['type'] === 'member' ) {
						$preload = true;
					} elseif ( $column['type'] === 'integer' && ( ! isset( $filter['logic'] ) || ( $filter['logic'] === 'equals' || $filter['logic'] === 'notequals' ) ) ) {
						$preload = true;
					}

					if ( $preload ) {
						$schema = new Schema_Source( $source->get_schema() );
						$filter = Search_Filter_Item::create( $filter, new Schema_Column( $column, $schema ) );

						if ( $filter ) {
							return $source->get_filter_preload( $column, $filter );
						}
					}
				}
			}
		}

		return [];
	}
}

require_once dirname( __DIR__ ) . '/source/has-meta.php';
require_once dirname( __DIR__ ) . '/source/has-terms.php';
require_once dirname( __DIR__ ) . '/source/core/meta.php';
require_once dirname( __DIR__ ) . '/source/core/post.php';
require_once dirname( __DIR__ ) . '/source/core/post-meta.php';
require_once dirname( __DIR__ ) . '/source/core/user.php';
require_once dirname( __DIR__ ) . '/source/core/user-meta.php';
require_once dirname( __DIR__ ) . '/source/core/terms.php';
require_once dirname( __DIR__ ) . '/source/core/comment.php';
require_once dirname( __DIR__ ) . '/source/core/comment-meta.php';
require_once dirname( __DIR__ ) . '/source/core/options.php';
