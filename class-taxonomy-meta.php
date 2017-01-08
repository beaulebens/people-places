<?php

/**
 * Helpers for working with Taxonomy Meta.
 * Makes it a lot easier to add meta fields, and no have to manually handle them
 * on multiple UIs, saving, adding to tables, etc.
 *
 * Primarily, you want to use Taxonomy_Meta::add();
 */

class Taxonomy_Meta {
	/**
	 * Main interface to adding taxmeta. Call this with appropriate args.
	 * @param String $tax The taxonomy to add this meta field to.
	 * @param Array $args Details on how to set up this meta.
	 *   - key:   The key for storing/accessing this meta field.
	 *   - value: The current value of this meta.
	 *   - label: User-facing label to show next to the input field.
	 *   - type:  What sort of data is it? [ text ].
	 *   - help:  A user-facing help string to display.
	 *   - table: Boolean indicating if this should be included in the term listing table.
	 */
	static function add( $tax, $args ) {
		if ( ! $tax || ! taxonomy_exists( $tax ) ) {
			return false;
		}

		$defaults = array(
			'value' => '',
			'type'  => 'text',
			'table' => true,
		);
		$args = wp_parse_args( $args, $defaults );

		// Add fields to new/edit screens
		add_action( $tax . '_add_form_fields', function() use ( $tax, $args ) {
			Taxonomy_Meta::add_ui( $tax, $args );
		}, 10, 2 );
		add_action( 'created_' . $tax, function( $term_id, $tt_id ) use ( $tax, $args ) {
			Taxonomy_Meta::add_handler( $term_id, $tt_id, $tax, $args );
		}, 10, 2 );
		add_action( $tax . '_edit_form_fields', function( $term, $taxonomy ) use ( $tax, $args ) {
			Taxonomy_Meta::edit_ui( $term, $taxonomy, $tax, $args );
		}, 10, 2 );
		add_action( 'edited_' . $tax, function( $term_id, $tt_id ) use ( $tax, $args ) {
			Taxonomy_Meta::edit_handler( $term_id, $tt_id, $tax, $args );
		}, 10, 2 );

		// Optionally include meta in the term listing table
		if ( $args['table'] ) {
			add_filter( 'manage_edit-' . $tax . '_columns', function( $columns ) use ( $tax, $args ) {
				return Taxonomy_Meta::table_column( $columns, $tax, $args );
			}, 10, 1 );
			add_filter( 'manage_' . $tax . '_custom_column', function( $content, $column_name, $term_id ) use ( $tax, $args ) {
				return Taxonomy_Meta::table_column_data( $content, $column_name, $term_id, $tax, $args );
			}, 10, 3 );
		}
	}

	/**
	 * Handles injecting the required UI for adding new term.
	 */
	static function add_ui( $tax, $args ) {
		Taxonomy_Meta::ui( 'add', $tax, $args );
	}

	/**
	 * Handle the data received when saving new terms.
	 */
	static function add_handler( $term_id, $tt_id, $tax, $args ) {
		if ( isset( $_POST[ $tax . '-' . $args['key'] ] ) && '' !== $_POST[ $tax . '-' . $args['key'] ] ){
			$value = sanitize_text_field( $_POST[ $tax . '-' . $args['key'] ] );
			add_term_meta( $term_id, $tax . '-' . $args['key'], $value, true );
		}
	}

	/**
	 * Handles injecting the required UI for editing existing terms.
	 */
	static function edit_ui( $term, $taxonomy, $tax, $args ) {
		$args['value'] = get_term_meta( $term->term_id, $tax . '-' . $args['key'], true );
		Taxonomy_Meta::ui( 'edit', $tax, $args );
	}

	/**
	 * Handle the data received when saving existing terms.
	 */
	static function edit_handler( $term_id, $tt_id, $tax, $args ) {
		if ( isset( $_POST[ $tax . '-' . $args['key'] ] ) && '' !== $_POST[ $tax . '-' . $args['key'] ] ){
			$value = sanitize_text_field( $_POST[ $tax . '-' . $args['key'] ] );
			update_term_meta( $term_id, $tax . '-' . $args['key'], $value );
		}
	}

	/**
	 * Create consistent UI for save/edit screens.
	 * @param String $screen Which screen is this for [ add | edit ]
	 */
	static function ui( $screen = 'add', $tax, $args ) {
		$id = esc_attr( $tax . '-' . $args['key'] );

		if ( 'add' == $screen ) {
			switch ( $args['type'] ) {
			case 'text':
			default:
				?><div class="form-field term-group">
						<label for="<?php echo $id;  ?>"><?php esc_html_e( $args['label'] ); ?></label>
						<input type="text" name="<?php echo $id;  ?>" id="<?php echo $id;  ?>" value="<?php esc_attr_e( $args['value'] ); ?>" />
						<?php if ( ! empty( $args['help'] ) ) : ?><p><?php esc_html_e( $args['help'] ); ?></p><?php endif; ?>
				</div><?php
				break;
			}
		} else { // screen == edit
			switch ( $args['type'] ) {
			case 'text':
			default:
				?><tr class="form-field">
					<th scope="row">
						<label for="<?php echo $id; ?>"><?php esc_html_e( $args['label'] ); ?></label>
					</th>
					<td>
						<input type="text" name="<?php echo $id;  ?>" id="<?php echo $id;  ?>" value="<?php esc_attr_e( $args['value'] ); ?>" />
						<?php if ( ! empty( $args['help'] ) ) : ?><p class="description"><?php esc_html_e( $args['help'] ); ?></p><?php endif; ?>
					</td>
				</tr><?php
				break;
			}
		}
	}

	/**
	 * Add this meta into the term listing table (optional).
	 */
	static function table_column( $columns, $tax, $args ) {
		$columns[ $args['key'] ] = $args['label'];
		return $columns;
	}

	/**
	 * Add this meta into the term listing table (optional).
	 */
	static function table_column_data( $content, $column_name, $term_id, $tax, $args ) {
		if ( $args['key'] !== $column_name ) {
			return $content;
		}

		$term_id = absint( $term_id );
		$val = get_term_meta( $term_id, $tax . '-' . $args['key'], true );

		if ( !empty( $val ) ){
			$content .= esc_attr( $val );
		}

		return $content;
	}
}
