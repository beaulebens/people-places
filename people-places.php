<?php
/*
Plugin Name: People & Places
Description: Add taxonomies for keeping track of people and locations, across different post types and data sources. Built primarily to work with Keyring Social Importers.
Version: 1.0
Author: Beau Lebens
Author URI: http://dentedreality.com.au
License: GPL v3 or newer <https://www.gnu.org/licenses/gpl.txt>
*/

require_once dirname( __FILE__ ) . '/class-taxonomy-meta.php';

class People_Places {
	function __construct() {
		add_action( 'init', array( $this, 'register_people_taxonomy' ) );
		add_action( 'init', array( $this, 'register_places_taxonomy' ) );

		add_action( 'restrict_manage_posts', array( $this, 'post_list_filter_people' ) );
		add_action( 'restrict_manage_posts', array( $this, 'post_list_filter_places' ) );
	}

	/**
	 * Since we're introducing new taxonomies, and we're likely to end up with a lot
	 * of posts using them, let's make it a little easier to filter the admin to view.
	 */
	function post_list_filter_people() {
		$terms = get_terms( 'people' );
		if ( count( $terms ) ) :
		?><select name="people" id="people">
		<option value=""><?php esc_html_e( 'All People' ); ?></option>
		<?php foreach ( $terms as $term ): ?>
		<option<?php selected( isset( $_REQUEST['people'] ) && $_REQUEST['people'] == $term->slug ); ?> value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
		<?php endforeach; ?>
		</select><?php
		endif;
	}

	function post_list_filter_places() {
		$terms = get_terms( 'places' );
		if ( count( $terms ) ) :
		?><select name="places" id="places">
		<option value=""><?php esc_html_e( 'All Places' ); ?></option>
		<?php foreach ( $terms as $term ): ?>
		<option<?php selected( isset( $_REQUEST['places'] ) && $_REQUEST['places'] == $term->slug ); ?> value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
		<?php endforeach; ?>
		</select><?php
		endif;
	}

	/**
	 * Register the People taxonomy, which is used to tie accounts together
	 */
	function register_people_taxonomy() {
		$labels = [
		'name'              => _x( 'People', 'taxonomy general name' ),
		'singular_name'     => _x( 'Person', 'taxonomy singular name' ),
		'search_items'      => __( 'Search People' ),
		'all_items'         => __( 'All People' ),
		'edit_item'         => __( 'Edit Person' ),
		'update_item'       => __( 'Update Person' ),
		'add_new_item'      => __( 'Add New Person' ),
		'new_item_name'     => __( 'New Person' ),
		'menu_name'         => __( 'People' ),
		];
		$args = [
		'hierarchical'      => false,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'people' ],
		];
		register_taxonomy( 'people', ['post'], $args );

		$this->register_people_meta();

		// Clean up columns a bit
		add_filter( 'manage_edit-people_columns', function( $columns ) {
			// Remove description, which could be long here.
			unset( $columns['description'] );

			// Shuffle "count" to the end of the table.
			if ( ! empty( $columns['posts'] ) ) {
				$posts = $columns['posts'];
				unset( $columns['posts'] );
				$columns['posts'] = $posts;
			}

			return $columns;
		}, 10, 1 );
	}

	/**
	 * Register associated meta for the People taxonomy
	 */
	function register_people_meta() {
		Taxonomy_Meta::add( 'people', array(
			'key'   => 'email',
			'label' => __( 'Email' ),
			'type'  => 'text',
			'help'  => __( "This person's main email address (also used to show a Gravatar)." ),
			'table' => false,
		) );
		Taxonomy_Meta::add( 'people', array(
			'key'   => 'url',
			'label' => __( 'Web Address' ),
			'type'  => 'text',
			'help'  => __( "If they have one, what is this person's website/URL?" ),
			'table' => false,
		) );
	}

	/**
	 * Get an image tag for the Gravatar of a user, based on their email address.
	 * @param Int $term_id The id for this person's term entry
	 * @param Int $size Pixel size of the image.
	 * @return String Image tag for their Gravatar
	 */
	static function get_gravatar( $term_id, $size = 80 ) {
		if ( $user = get_term_meta( $term_id, 'people-email', true ) ) {
			$user = get_avatar( $user, $size );
		}
		return $user;
	}

	/**
	 * Get the email address for someone, based on their term_id
	 * @param  Int $term_id The id for this person's term entry
	 * @return String Their email address
	 */
	static function get_email( $term_id ) {
		return get_term_meta( $term_id, 'people-email', true );
	}

	/**
	 * Get the URL for someone, based on their term_id
	 * @param  Int $term_id The id for this person's term entry
	 * @return String Their URL
	 */
	static function get_url( $term_id ) {
		return get_term_meta( $term_id, 'people-url', true );
	}

	/**
	 * Register the Places taxonomy, which allows us to tie geo data together under
	 * a single location entry.
	 */
	function register_places_taxonomy() {
		$labels = [
		'name'              => _x( 'Places', 'taxonomy general name' ),
		'singular_name'     => _x( 'Place', 'taxonomy singular name' ),
		'search_items'      => __( 'Search Places' ),
		'all_items'         => __( 'All Places' ),
		'edit_item'         => __( 'Edit Place' ),
		'update_item'       => __( 'Update Place' ),
		'add_new_item'      => __( 'Add New Place' ),
		'new_item_name'     => __( 'New Place' ),
		'menu_name'         => __( 'Places' ),
		];
		$args = [
		'hierarchical'      => false,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'places' ],
		];
		register_taxonomy( 'places', ['post'], $args );

		$this->register_places_meta();

		// Clean up columns a bit
		add_filter( 'manage_edit-places_columns', function( $columns ) {
			// Remove description, which could be long here.
			unset( $columns['description'] );

			// Shuffle "count" to the end of the table.
			if ( ! empty( $columns['posts'] ) ) {
				$posts = $columns['posts'];
				unset( $columns['posts'] );
				$columns['posts'] = $posts;
			}

			return $columns;
		}, 10, 1 );
	}

	/**
	 * Register meta associated with locations/places
	 */
	function register_places_meta() {
		Taxonomy_Meta::add( 'places', array(
			'key'   => 'address',
			'label' => __( 'Street Address' ),
			'type'  => 'text',
			'help'  => __( "Full street address, including city, state etc." ),
			'table' => false,
		) );
		Taxonomy_Meta::add( 'places', array(
			'key'   => 'geo_latitude',
			'label' => __( 'Latitude' ),
			'type'  => 'text',
			'help'  => __( "The latitude (in decimal notation) for this location." ),
			'table' => false,
		) );
		Taxonomy_Meta::add( 'places', array(
			'key'   => 'geo_longitude',
			'label' => __( 'Longitude' ),
			'type'  => 'text',
			'help'  => __( "The longitude (in decimal notation) for this location." ),
			'table' => false,
		) );
	}

	/**
	 * Helper to handle association a person to a post. It will automatically
	 * create a term entry for the person if required, or use an existing one
	 * based on matching the id supplied.
	 * @param String $meta The key for the meta item to match against
	 * @param Mixed $value The value for the key (id/username/etc)
	 * @param Array $data Additional data used if we need to create the term.
	 *                    Should contain a 'name' at least.
	 * @param Int $post_id The post id to associate this entry with
	 */
	 static function add_person_to_post( $meta, $value, $data, $post_id ) {
 		// Resolve to existing entry?
 		$existing = get_terms( array(
 			'taxonomy'   => 'people',
 			'hide_empty' => false,
 			'meta_query' => array(
 				array(
 					'key'     => 'people-' . $meta,
 					'value'   => $value,
 					'compare' => '=',
 				)
 			)
 		) );
 		if ( $existing && ! is_wp_error( $existing ) ) {
 			$existing = $existing[0]->term_id;
 		} else {
 			// Create new entry to attach this data to
 			// @todo Handle duplicate names when we find people on different networks (auto-merge?)
 			$existing = wp_insert_term( $data['name'], 'people' );
 			if ( $existing && ! is_wp_error( $existing ) ) {
 				$existing = $existing['term_id'];

 				// Store the id we used as term meta, so that we don't recreate later
 				add_term_meta( $existing, 'people-' . $meta, $value );
 			}
 		}

 		if ( is_wp_error( $existing ) ) {
 			return false;
 		}

 		return wp_set_object_terms( $post_id, array( intval( $existing ) ),'people', true );
 	}

	static function add_place_to_post( $meta, $value, $data, $post_id ) {
		$existing = false;
		// Resolve to existing entry?
		$found = get_terms( array(
			'taxonomy'   => 'places',
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key'     => 'places-' . $meta,
					'value'   => $value,
					'compare' => '=',
				)
			)
		) );
		if ( $found && ! is_wp_error( $found ) ) {
			$existing = $found[0]->term_id;
		} else if ( ! empty( $data['geo_latitude'] ) && ! empty( $data['geo_longitude'] ) ) {
			// Match if there's somewhere with the same co-ordinates
			$found = get_terms( array(
				'taxonomy'   => 'places',
				'hide_empty' => false,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => 'places-geo_latitude',
						'value'   => $data['geo_latitude'],
						'compare' => '=',
					),
					array(
						'key'     => 'places-geo_longitude',
						'value'   => $data['geo_longitude'],
						'compare' => '=',
					)
				)
			) );
			if ( $found && ! is_wp_error( $found ) ) {
				$existing = $found[0]->term_id;
			}
		}

		// Create new entry to attach this data to if we haven't found one
		if ( ! $existing ) {
			// @todo handle duplicate names of places (e.g. Costco)
			// We don't match very well since coordinates are likely to be different, even for the same place.
			$inserted = wp_insert_term( $data['name'], 'places' );
			if ( $inserted && ! is_wp_error( $inserted ) ) {
				$existing = $inserted['term_id'];
			}
		}

		// Store the id we used as term meta, so that we don't recreate later
		add_term_meta( $existing, 'places-' . $meta, $value, true );

		// Now store additional information if we've got it
		if ( ! empty( $data['address'] ) ) {
			add_term_meta( $existing, 'places-address', $data['address'], true );
		}
		if ( ! empty( $data['geo_latitude'] ) ) {
			add_term_meta( $existing, 'places-geo_latitude', $data['geo_latitude'], true );
		}
		if ( ! empty( $data['geo_longitude'] ) ) {
			add_term_meta( $existing, 'places-geo_longitude', $data['geo_longitude'], true );
		}

		return wp_set_object_terms( $post_id, array( intval( $existing ) ), 'places' );
	}
}

new People_Places();
