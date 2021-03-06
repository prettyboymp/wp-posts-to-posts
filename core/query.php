<?php

/**
 * Handles connected{_to|_from} query vars
 */
class P2P_Query {

	public static $qv_map = array(
		'connected' => 'any',
		'connected_to' => 'to',
		'connected_from' => 'from',
	);

	function init() {
		add_filter( 'posts_clauses', array( 'P2P_Query', 'posts_clauses' ), 10, 2 );
		add_filter( 'the_posts', array( 'P2P_Query', 'the_posts' ), 11, 2 );
	}

	/**
	 * Handles connected* query vars
	 */
	function posts_clauses( $clauses, $wp_query ) {
		global $wpdb;

		foreach ( self::$qv_map as $key => $direction ) {
			$search = $wp_query->get( $key );
			if ( !empty( $search ) )
				break;
		}

		if ( empty( $search ) )
			return $clauses;

		$wp_query->_p2p_cache = true;

		$clauses['fields'] .= ", $wpdb->p2p.*";

		$clauses['join'] .= " INNER JOIN $wpdb->p2p";

		if ( 'any' == $search ) {
			$search = false;
		} else {
			$search = implode( ',', array_map( 'absint', (array) $search ) );
		}

		switch ( $direction ) {
			case 'from':
				$clauses['where'] .= " AND $wpdb->posts.ID = $wpdb->p2p.p2p_to";
				if ( $search ) {
					$clauses['where'] .= " AND $wpdb->p2p.p2p_from IN ($search)";
				}
				break;

			case 'to':
				$clauses['where'] .= " AND $wpdb->posts.ID = $wpdb->p2p.p2p_from";
				if ( $search ) {
					$clauses['where'] .= " AND $wpdb->p2p.p2p_to IN ($search)";
				}
				break;

			case 'any':
				if ( $search ) {
					$clauses['where'] .= " AND (
						($wpdb->posts.ID = $wpdb->p2p.p2p_to AND $wpdb->p2p.p2p_from IN ($search)) OR
						($wpdb->posts.ID = $wpdb->p2p.p2p_from AND $wpdb->p2p.p2p_to IN ($search))
					)";
				} else {
					$clauses['where'] .= " AND ($wpdb->posts.ID = $wpdb->p2p.p2p_to OR $wpdb->posts.ID = $wpdb->p2p.p2p_from)";
				}
				break;
		}

		$connected_meta = $wp_query->get( 'connected_meta' );
		if ( !empty( $connected_meta ) ) {
			$meta_clauses = _p2p_meta_sql_helper( $connected_meta );
			foreach ( $meta_clauses as $key => $value ) {
				$clauses[ $key ] .= $value;
			}
		}

		// Handle ordering
		$connected_orderby = $wp_query->get( 'connected_orderby' );
		if ( $connected_orderby ) {
			$clauses['join'] .= $wpdb->prepare( "
				LEFT JOIN $wpdb->p2pmeta AS p2pm_order ON (
					$wpdb->p2p.p2p_id = p2pm_order.p2p_id AND p2pm_order.meta_key = %s
				)
			", $connected_orderby );

			$connected_order = ( 'DESC' == strtoupper( $wp_query->get('connected_order') ) ) ? 'DESC' : 'ASC';

			$field = 'meta_value';

			if ( $wp_query->get('connected_order_num') )
				$field .= '+0';

			$clauses['orderby'] = "p2pm_order.$field $connected_order";
		}

		return $clauses;
	}

	/**
	 * Pre-populates the p2p meta cache to decrease the number of queries.
	 */
	function the_posts( $the_posts, $wp_query ) {
		if ( empty( $the_posts ) )
			return $the_posts;

		if ( isset( $wp_query->_p2p_cache ) ) {
			update_meta_cache( 'p2p', wp_list_pluck( $the_posts, 'p2p_id' ) );
		}

		return $the_posts;
	}

	public function get_qv( $direction ) {
		return array_search( $direction, self::$qv_map );
	}
}

P2P_Query::init();

