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

		add_filter( 'bulk_actions-edit-people', array( $this, 'bulk_actions_merge' ) );
		add_filter( 'bulk_actions-edit-places', array( $this, 'bulk_actions_merge' ) );

		add_action( 'load-edit-tags.php', array( $this, 'handle_merge_terms' ) );
		add_action( 'load-edit-tags.php', array( $this, 'handle_merge_terms' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	function admin_notices() {
		if ( isset( $_GET['merged'] ) ) {
			$tax = get_current_screen();
			$tax = $tax->taxonomy;

			?><div class="updated">
				<p><?php printf(
					__( 'Those terms have been merged. <a href="%s">Edit merged term?</a>' ),
					esc_url(
						add_query_arg(
							array(
								'taxonomy' => $tax,
								'tag_ID'   => (int) $_GET['merged']
							),
							admin_url( 'term.php' )
						)
					)
				); ?></p>
			</div><?php
		}
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
	 * Add a "Merge" option to the Bulk Actions list on People/Places taxonomy pages.
	 */
	function bulk_actions_merge( $actions ) {
		$actions['merge'] = __( 'Merge' );
		return $actions;
	}

	/**
	 * Handle actually merging the data from multiple People/Places into one.
	 */
	function handle_merge_terms() {
		// Only care about POST requests
		if ( ! isset( $_POST ) ) {
			return;
		}
		$data = array_merge(
			array( 'action' => '', 'action2' => '', 'taxonomy' => '' ),
			$_POST
		);

		// Only care about our own "action"
		if ( empty( $data['action'] ) && empty( $data['action2'] ) ) {
			return;
		}
		if ( 'merge' !== $data['action'] && 'merge' !== $data['action2'] ) {
			return;
		}

		// Need at least 2 terms selected
		if ( empty( $data['delete_tags'] ) || 2 > count( $data['delete_tags'] ) ) {
			return;
		}

		// If this somehow gets triggered for a taxonomy we don't know about, bail
		if ( ! in_array( $data['taxonomy'], array( 'people', 'places' ) ) ) {
			return;
		}

		$tax = get_taxonomy( $data['taxonomy'] );
		if ( !$tax ) {
			return;
		}

		if ( ! current_user_can( $tax->cap->manage_terms ) ) {
			return;
		}

		// Query details for all selected terms
		$terms = get_terms( array(
			'taxonomy' => $data['taxonomy'],
			'include'  => $data['delete_tags']
		) );
		if ( ! $terms ) {
			return;
		}

		// Find the shortest slug, which will be our destination term
		$destination = false;
		$sources = array();
		foreach ( $terms as $term ) {
			if ( ! $destination || strlen( $destination->slug ) > strlen( $term->slug ) ) {
				if ( $destination ) {
					$sources[] = $destination; // out with the old
				}
				$destination = $term;
			} else {
				$sources[] = $term;
			}
		}

		// For all source terms...
		foreach ( $sources as $term ) {
			// Get (all) meta and attempt to merge it onto destination
			$meta = get_term_meta( $term->term_id );
			foreach ( $meta as $key => $value ) {
				// See if the destination already has this meta
				$existing = get_term_meta( $destination->term_id, $key, true );
				if ( $existing && strlen( $existing ) > strlen( $value[0] ) ) {
					// If there's already a value for this key, and it's longer than
					// the one we have, then assume that one should stay. Bigger is better?
					continue;
				}
				update_term_meta( $destination->term_id, $key, $value[0] );
			}

			// If this source term has a longer name, keep it
			if ( strlen( $term->name ) > strlen( $destination->name ) ) {
				wp_update_term(
					$destination->term_id,
					$data['taxonomy'],
					array(
						'name' => $term->name
					)
				);
			}

			// If this source term has a longer description, keep it
			if ( strlen( $term->description ) > strlen( $destination->description ) ) {
				wp_update_term(
					$destination->term_id,
					$data['taxonomy'],
					array(
						'description' => $term->description
					)
				);
			}

			// Migrate any associated posts onto the destination term
			$delete = wp_delete_term(
				$term->term_id,
				$data['taxonomy'],
				array(
					'default'       => $destination->term_id,
					'force_default' => true // Neat trick from https://wordpress.org/plugins/term-management-tools/
				)
			);
			if ( is_wp_error( $delete ) ) {
				continue;
			}
		}

		// Redirect to the single view for the destination term
		// _wp_http_referer
		$paged = 0;
		if ( ! empty( $data['_wp_http_referer'] ) ) {
			parse_str( $data['_wp_http_referer'], $bits );
			if ( ! empty( $bits['paged'] ) ) {
				$paged = $bits['paged'];
			}
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'taxonomy' => $data['taxonomy'],
					'paged'    => $paged,
					'merged'   => $destination->term_id
				),
				admin_url( 'edit-tags.php' )
			)
		);
		exit;
	}

	/**
	 * Register the People taxonomy, which is used to tie accounts together
	 */
	function register_people_taxonomy() {
		$labels = [
			'name'                       => _x( 'People', 'taxonomy general name' ),
			'singular_name'              => _x( 'Person', 'taxonomy singular name' ),
			'search_items'               => __( 'Search People' ),
			'popular_items'              => __( 'Popular People' ),
			'all_items'                  => __( 'All People' ),
			'edit_item'                  => __( 'Edit Person' ),
			'view_item'                  => __( 'View Person' ),
			'update_item'                => __( 'Update Person' ),
			'add_new_item'               => __( 'Add New Person' ),
			'new_item_name'              => __( 'New Person' ),
			'separate_items_with_commas' => __( 'Separate people with commas' ),
			'add_or_remove_items'        => __( 'Add or remove people' ),
			'choose_from_most_used'      => __( 'Choose from the most mentioned people' ),
			'not_found'                  => __( 'No people found' ),
			'no_terms'                   => __( 'No people' ),
			'menu_name'                  => __( 'People' ),
		];
		$args = [
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
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
			'name'                       => _x( 'Places', 'taxonomy general name' ),
			'singular_name'              => _x( 'Place', 'taxonomy singular name' ),
			'search_items'               => __( 'Search Places' ),
			'popular_items'              => __( 'Popular Places' ),
			'all_items'                  => __( 'All Places' ),
			'edit_item'                  => __( 'Edit Place' ),
			'view_item'                  => __( 'View Place' ),
			'update_item'                => __( 'Update Place' ),
			'add_new_item'               => __( 'Add New Place' ),
			'new_item_name'              => __( 'New Place' ),
			'separate_items_with_commas' => __( 'Separate places with commas' ),
			'add_or_remove_items'        => __( 'Add or remove places' ),
			'choose_from_most_used'      => __( 'Choose from the most mentioned places' ),
			'not_found'                  => __( 'No places found' ),
			'no_terms'                   => __( 'No places' ),
			'menu_name'                  => __( 'Places' ),
		];
		$args = [
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
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

		// Add a very basic map to the taxonomy detail page
		add_action( 'places_edit_form_fields', function( $term, $taxonomy ) {
			$lat  = get_term_meta( $term->term_id, 'places-geo_latitude', true );
			$long = get_term_meta( $term->term_id, 'places-geo_longitude', true );

			if ( ! empty( $lat ) && ! empty( $long ) ) {
				?><tr>
					<th><?php _e( 'Map of location' ); ?></th>
					<td>
						<div id="osmmapdiv" style="width:100%; height:400px;"></div>
						<p class="description"><?php _e( 'Based on latitide/longitude.' ); ?></p>
					</td>
				</tr>
				<script src="https://cdn.rawgit.com/openlayers/openlayers.github.io/master/en/v5.3.0/build/ol.js"></script>
				<script>
				jQuery( document ).ready( function(){

					var iconStyle = new ol.style.Style({
						image: new ol.style.Circle({
							radius: 10,
							snapToPixel: false,
							fill: new ol.style.Fill({
								color: [66, 113, 174, 0.7]
							}),
							stroke: new ol.style.Stroke({
								color: [0, 0, 0, 1],
								width: 2
							})
						})
					});

					var iconFeature = new ol.Feature({
						geometry: new ol.geom.Point(ol.proj.fromLonLat([ <?php esc_attr_e( $long ); ?>, <?php esc_attr_e( $lat ); ?> ]))
					});
					iconFeature.setStyle( iconStyle );

					var vectorLayer = new ol.layer.Vector({
						source: new ol.source.Vector({
							features: [ iconFeature ]
						})
					});

					var tileLayer = new ol.layer.Tile({
						source: new ol.source.OSM()
					});

					var map = new ol.Map({
						target: 'osmmapdiv',
						layers: [
							tileLayer,
							vectorLayer
						],
						view: new ol.View({
							center: ol.proj.fromLonLat( [ <?php esc_attr_e( $long ); ?>, <?php esc_attr_e( $lat ); ?> ] ),
							zoom: 16
						})
					});
				});
				</script><?php
			}
		}, 11, 2 );
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
 			$data['taxonomy'] = 'people'; // Required for wp_unique_term_slug() below
 			$existing = wp_insert_term(
				$data['name'],
				'people',
				array(
					'slug' => wp_unique_term_slug(
						sanitize_title_with_dashes(
							$data['name']
						),
						(object) $data
					)
				)
			);
 			if ( $existing && ! is_wp_error( $existing ) ) {
 				$existing = $existing['term_id'];

 				// Store the id we used as term meta, so that we don't recreate later
 				add_term_meta( $existing, 'people-' . $meta, $value );
 			}
 		}

 		if ( is_wp_error( $existing ) ) {
 			return false;
 		}

 		return wp_set_object_terms( $post_id, array( intval( $existing ) ), 'people', true );
 	}

	/**
	 * Associate a Place with a Post.
	 * @param String $meta    The slug of the taxonomy. Creates an identifier for meta.
	 * @param Mixed $value    Either an int or a string. Unique identifier for this Place.
	 * @param Array $data     Additional data for this Place. Keys can be `name`, `address`, `geo_latitude` and `geo_longitude`.
	 * @param Int $post_id    The post id to associate this Place with.
	 */
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
			// Create a new entry, likely with duplicates based on names
			$data['taxonomy'] = 'places'; // Required for wp_unique_term_slug() below
			$inserted = wp_insert_term(
				$data['name'],
				'places',
				array(
					'slug' => wp_unique_term_slug(
						sanitize_title_with_dashes(
							$data['name']
						),
						(object) $data
					)
				)
			);
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

		return wp_set_object_terms( $post_id, array( intval( $existing ) ), 'places', true );
	}
}

new People_Places();
