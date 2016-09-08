<?php

/**
 * Class WPML_GFML_Meta_Update
 */
class WPML_GFML_Filter_Field_Meta {

	/**
	 * @var SitePress
	 */
	public $current_language;

	/**
	 * WPML_GFML_Meta_Update constructor.
	 *
	 * @param $current_language
	 */
	public function __construct( $current_language ) {
		$this->current_language = $current_language;
		add_filter( 'gform_form_post_get_meta', array( $this, 'filter_taxonomy_terms' ), 10, 1 );
	}

	/**
	 * @param $field_data
	 *
	 * @return mixed
	 */
	public function filter_taxonomy_terms( $field_data ) {
		if ( is_array( $field_data['fields'] ) ) {
			foreach ( $field_data['fields'] as &$field ) {
				if ( ! empty( $field->choices ) && 'post_category' === $field->type ) {
					foreach ( $field->choices as &$choice ) {
						$tr_cat = apply_filters( 'wpml_object_id', $choice['value'], 'category', false, $this->current_language );
						if ( null !== $tr_cat ) {
							$tr_cat = get_category( $tr_cat );
							$choice['value'] = $tr_cat->term_id;
							$choice['text'] = $tr_cat->name;
						}
					}
				}
			}
		}

		return $field_data;
	}
}
