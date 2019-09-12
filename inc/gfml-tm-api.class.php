<?php

class GFML_TM_API extends Gravity_Forms_Multilingual {

	private $registered_strings;

	public function __construct() {
		parent::__construct();
		add_action( 'wpml_register_string_packages', array( $this, 'register_missing_packages' ), 10, 2 );
		add_action( 'gform_post_update_form_meta', array($this, 'gform_post_update_form_meta_action'), 10, 3);

		$migrated = get_option( 'gfml_pt_migr_comp' );
		if ( ! $migrated ) {
			if ( $this->gravity_form_table_exists() && $this->has_post_gravity_from_translations() ) {
				require dirname( __FILE__ ) . '/gfml-migration.class.php';
				$migration_object = new GFML_Migration( $this );
				$migration_object->migrate();
			}
			update_option( 'gfml_pt_migr_comp', true );
		}
	}

	public function gform_post_update_form_meta_action() {
		if(!get_option('wpml-package-translation-refresh-required')) {
			update_option( 'wpml-package-translation-refresh-required', true );
		}
	}

	private function gravity_form_table_exists() {
		return (bool) $this->get_forms_table_name();
	}

	/**
	 * @param string $source
	 * @param int $max_length
	 *
	 * @return string
	 */
	private function trim_with_ellipsis( $source, $max_length ) {
		$source_length = mb_strlen( $source );

		if($source_length > $max_length ) {
			$offset = $max_length - 3;
			$source = mb_substr( $source, 0, $offset ) . '...';
		}

		return $source;
	}

	private function build_string_title( $form_field, $form_field_key, $sub_title = '' ) {
		$max_length  = 160;

		$form_field_key  = $this->trim_with_ellipsis($form_field_key, $max_length/3);
		$field_title = '{' . $form_field[ 'id' ] . ':' . $form_field[ 'type' ] . '} ' . $form_field_key;
		$current_length = mb_strlen($field_title);

		if ( $current_length < $max_length && $sub_title && $sub_title != $form_field_key ) {
			$sub_title_max_length = $max_length - ( $current_length + 3 );
			$sub_title = $this->trim_with_ellipsis( $sub_title, $sub_title_max_length);
			if ( $sub_title_max_length > 0 ) {
				$field_title .= ' [' . $this->trim_with_ellipsis( $sub_title, $sub_title_max_length ) . ']';
			} else {
				$field_title = '{' . $form_field[ 'id' ] . ':' . $form_field[ 'type' ] . '} [' . $sub_title . ']';
				$field_title = $this->trim_with_ellipsis( $field_title, $max_length );
			}
		}

		if(mb_strlen($field_title) > $max_length) {
			$field_title = $this->trim_with_ellipsis( $field_title, $max_length );
		}

		return $field_title;
	}

	/**
	 * @return string
	 */
	public function get_type() {
		return 'gravity_form';
	}

	public function get_st_context( $form_id ) {
		return sanitize_title_with_dashes( ICL_GRAVITY_FORM_ELEMENT_TYPE . '-' . $form_id );
	}

	public function get_form_package( $form ) {
		$form_package            = new stdClass();
		$form_package->kind      = __( 'Gravity Form', 'gravity-forms-ml' );
		$form_package->kind_slug = ICL_GRAVITY_FORM_ELEMENT_TYPE;
		$form_package->name      = $form['id'];
		$form_package->title     = $form['title'];
		$form_package->edit_link = admin_url( sprintf( 'admin.php?page=gf_edit_forms&id=%d', $form['id'] ) );

		return $form_package;
	}

	public function register_gf_string( $string_value, $string_name, $package, $string_title, $string_kind = 'LINE' ) {
		$this->registered_strings[] = $string_name;
		do_action( 'wpml_register_string', $string_value, $string_name, $package, $string_title, $string_kind );
	}

	protected function register_strings_common_fields( $form_field, $form_package ) {
		// Filter common properties
		$snh        = new GFML_String_Name_Helper();
		$snh->field = $form_field;
		foreach ( $this->form_fields as $form_field_key ) {
			if ( ! empty( $form_field->{$form_field_key} ) && $form_field->type !== 'page' ) {
				$snh->field_key = $form_field_key;
				$string_name    = $snh->get_field_common();
				$string_title = $this->build_string_title($form_field, $form_field_key , $form_field['label']);
				$string_type = 'LINE';
				$area_fields = array('description', 'errorMessage');
				if(in_array($form_field_key, $area_fields)) {
					$string_type = 'AREA';
				}
				$this->register_gf_string( $form_field->{$form_field_key}, $string_name, $form_package, $string_title, $string_type );
				$this->register_placeholders( $form_package, $form_field );
				$this->register_customLabels( $form_package, $form_field );
			}
		}
	}

	protected function register_global_strings($form_package, $form) {
		$snh        = new GFML_String_Name_Helper();

		if(isset($form['title'])) {
			$string_title = 'Form title';
			$this->register_gf_string( $form[ 'title' ], $snh->get_form_title(), $form_package, $string_title );
		}

		if(isset($form['description'])) {
			$string_title = 'Form description';
			$this->register_gf_string( $form[ 'description' ], $snh->get_form_description(), $form_package, $string_title, 'AREA' );
		}

		if(isset($form['button']['text'])) {
			$string_title = 'Form submit button';
			$this->register_gf_string( $form[ 'button' ][ 'text' ], $snh->get_form_submit_button(), $form_package, $string_title );
		}

		if(isset($form['save']['button']['text'])) {
            $string_title = 'Save and Continue Later';
            $this->register_gf_string( $form[ 'save' ][ 'button' ][ 'text' ], $snh->get_form_save_and_continue_later_text(), $form_package, $string_title );
        }

		$this->register_form_notifications($form_package, $form);
		$this->register_form_confirmations($form_package, $form);
	}

	protected function register_form_notifications($form_package, $form) {
		if(isset($form['notifications']) && $form['notifications']) {
			$snh        = new GFML_String_Name_Helper();
			foreach($form['notifications'] as $notification) {
				$snh->notification = $notification;
				$string_title = 'Notification: ' . $notification[ 'name' ] .' - subject';
				$this->register_gf_string($notification['subject'], $snh->get_form_notification_subject(), $form_package, $string_title );
				$string_title = 'Notification: ' . $notification[ 'name' ] . ' - message';
				$this->register_gf_string($notification['message'], $snh->get_form_notification_message(), $form_package, $string_title, 'AREA' );
			}
		}
	}

	protected function register_form_confirmations($form_package, $form) {
		if(isset($form['confirmations']) && $form['confirmations']) {
			$snh        = new GFML_String_Name_Helper();
			foreach($form['confirmations'] as $confirmation) {
				$snh->confirmation = $confirmation;
				$string_title = 'Confirmation: ' .  $confirmation[ 'name' ] . ' - ' . $confirmation[ 'type' ];

				switch ( $confirmation[ 'type' ] ) {
					case 'message':
						$this->register_gf_string($confirmation['message'], $snh->get_form_confirmation_message(), $form_package, $string_title, 'AREA' );
						break;
					case 'redirect':
						$this->register_gf_string($confirmation['url'], $snh->get_form_confirmation_redirect_url(), $form_package, $string_title );
						$string_data[ $snh->get_form_confirmation_redirect_url() ] = $confirmation[ 'url' ];
						break;
					case 'page':
						$this->register_gf_string($confirmation['pageId'], $snh->get_form_confirmation_page_id(), $form_package, $string_title );
						$string_data[ $snh->get_form_confirmation_page_id() ] = $confirmation[ 'pageId' ];
						break;
				}

			}
		}
	}

	protected function register_strings_field_page( $form_package, $form_field ) {
		$snh = new GFML_String_Name_Helper();
		$snh->field = $form_field;

		foreach ( array( 'text', 'imageUrl' ) as $key ) {
			$snh->field_key = $key;
			if ( ! empty( $form_field->nextButton[ $key ] ) ) {
				$string_name = $snh->get_field_page_nextButton();
				$string_title = $this->build_string_title($form_field, 'next button', $form_field['label']);
				$this->register_gf_string( $form_field->nextButton[ $key ], $string_name, $form_package, $string_title );
			}
			if ( ! empty( $form_field->previousButton[ $key ] ) ) {
				$string_name = $snh->get_field_page_previousButton();
				$string_title = $this->build_string_title($form_field, 'previous button' , $form_field['label']);
				$this->register_gf_string( $form_field->previousButton[ $key ], $string_name, $form_package, $string_title );
			}
		}
	}

	protected function register_strings_field_choices( $form_package, $form_field ) {
		if ( is_array( $form_field->choices ) ) {
			foreach ( $form_field->choices as $choice_index => $choice ) {
				$this->register_strings_field_choice( $form_package, $form_field, $choice_index, $choice );
			}
		}
	}

	protected function register_strings_field_choice( $form_package, $form_field, $choice_index, $choice ) {
		$snh                     = new GFML_String_Name_Helper();
		$snh->field              = $form_field;
		$snh->field_choice       = $choice;
		$snh->field_choice_index = $choice_index;

		$string_name    = $snh->get_field_multi_input_choice_text();
		$string_title   = $this->build_string_title( $form_field, $choice_index . ': ' . $form_field[ 'label' ], 'label' );
		$this->register_gf_string( $choice[ 'text' ], $string_name, $form_package, $string_title );

		if ( isset( $choice[ 'value' ] ) ) {
			$string_name = $snh->get_field_multi_input_choice_value();
			$string_title = $this->build_string_title( $form_field, $choice_index . ': ' . $form_field[ 'label' ], 'value' );
			$this->register_gf_string( $choice[ 'value' ], $string_name, $form_package, $string_title );
		}

		return $string_name;
	}

	protected function register_strings_field_post_custom( $form_package, $form_field ) {
		// TODO if multi options - 'choices' (register and translate) 'inputType' => select, etc.
		$this->register_string_field_property( $form_package, $form_field, 'customFieldTemplate', 'get_field_post_custom_field' );
	}

	protected function register_strings_field_post_category( $form_package, $form_field ) {
		// TODO if multi options - 'choices' have static values (register and translate) 'inputType' => select, etc.
		$this->register_string_field_property( $form_package, $form_field, 'categoryInitialItem', 'get_field_post_category' );
	}

	protected function register_strings_field_address( $form_package, $form_field ) {
		$this->register_string_field_property( $form_package, $form_field, 'copyValuesOptionLabel', 'get_field_address_copy_values_option' );
	}

	protected function register_strings_field_html( $form_package, $form_field ) {
		$this->register_string_field_property( $form_package, $form_field, 'content', 'get_field_html', 'AREA' );
	}

	protected function register_string_field_property( $form_package, $form_field, $field_property, $string_helper_function_name, $string_kind = 'LINE' ) {
		if ( ! empty( $form_field->{$field_property} ) ) {
			$snh        = new GFML_String_Name_Helper();
			$snh->field = $form_field;

			if ( ! method_exists( $snh, $string_helper_function_name ) ) {
				return;
			}

			$string_name  = call_user_func( array( $snh, $string_helper_function_name ) );
			$string_title = $this->build_string_title( $form_field, $form_field['label'] );
			$this->register_gf_string( $form_field->{$field_property}, $string_name, $form_package, $string_title, $string_kind );
		}
	}

	public function register_strings_field_option( $form_package, $form_field ) {
		$snh        = new GFML_String_Name_Helper();
		$snh->field = $form_field;

		if ( isset( $form_field->choices ) && is_array( $form_field->choices ) ) {
			$this->register_strings_field_choices( $form_package, $form_field );
		}
	}

	protected function register_strings_fields( $form_package, $form ) {
		// Common field properties
		$this->get_field_keys();

		// Filter form fields (array of GF_Field objects)
		foreach ( $form[ 'fields' ] as $form_field ) {

			$this->register_strings_common_fields( $form_field, $form_package );

			// Field specific code
			switch ( $form_field->type ) {
				case 'html':
					$this->register_strings_field_html( $form_package, $form_field );
					break;
				case 'page':
					$this->register_strings_field_page( $form_package, $form_field );
					break;
				case 'list':
				case 'select':
				case 'multiselect':
				case 'checkbox':
				case 'radio':
				case 'product':
				case 'option':
					$this->register_strings_field_option( $form_package, $form_field );
					break;
				case 'post_custom_field':
					$this->register_strings_field_post_custom( $form_package, $form_field );
					break;
				case 'post_category':
					$this->register_strings_field_post_category( $form_package, $form_field );
					break;
				case 'address':
					$this->register_strings_field_address( $form_package, $form_field );
					break;
				default:
					do_action( "wpml_gf_register_strings_field_{$form_field->type}", $form, $form_package, $form_field );
			}

			$this->register_placeholders( $form_package, $form_field );
		}
	}

	protected function register_placeholders( $form_package, $form_field ) {
		$snh        = new GFML_String_Name_Helper();
		$snh->field = $form_field;

		$string_name = $snh->get_field_placeholder();
		if ( isset( $form_field->placeholder ) ) {
			$string_title = $this->build_string_title($form_field, 'placeholder', $form_field['label']);
			$this->register_gf_string( $form_field->placeholder, $string_name, $form_package, $string_title );
		}

		if ( isset( $form_field->inputs ) && is_array($form_field->inputs) ) {
			foreach ( $form_field->inputs as $key => $input ) {
				$snh->field_input = $input;
				$snh->field_key   = $key;
				if ( isset( $input[ 'placeholder' ] ) && $input[ 'placeholder' ] ) {
					$string_input_name = $snh->get_field_input_placeholder();
					$string_input_title = $this->build_string_title($form_field, 'placeholder', $input[ 'placeholder' ]);
					$this->register_gf_string( $input[ 'placeholder' ], $string_input_name, $form_package, $string_input_title );
				}
			}
		}
	}

	protected function register_customLabels( $form_package, $form_field ) {
		$snh        = new GFML_String_Name_Helper();
		$snh->field = $form_field;

		if ( isset( $form_field->inputs ) && is_array($form_field->inputs) ) {
			foreach ( $form_field->inputs as $key => $input ) {
				$snh->field_input = $input;
				$snh->field_key   = $key;
				if ( isset( $input[ 'customLabel' ] ) && $input[ 'customLabel' ] ) {
					$string_input_name = $snh->get_field_input_customLabel();
					$string_input_title = $this->build_string_title($form_field, 'custom label', $input[ 'customLabel' ]);
					$this->register_gf_string( $input[ 'customLabel' ], $string_input_name, $form_package, $string_input_title );
				}
			}
		}
	}

	protected function register_strings_main_fields( $form_package, $form ) {
		$form_keys = $this->_get_form_keys();
		foreach ( $form_keys as $key ) {
			$value = ! empty( $form[ $key ] ) ? $form[ $key ] : null;
			if ( $value !== null ) {
				$this->register_gf_string( $value, $key, $form_package, $key );
			}
		}
	}

	protected function register_strings_pagination( $form_package, $form ) {
		// Paging Page Names - $form["pagination"]["pages"][i]
		if ( ! empty( $form[ 'pagination' ] )
		     && isset( $form[ 'pagination' ][ 'pages' ] )
		     && is_array( $form[ 'pagination' ][ 'pages' ] )
		) {

			$snh = new GFML_String_Name_Helper();

			foreach ( $form[ 'pagination' ][ 'pages' ] as $key => $page_title ) {
				$snh->page_index = $key;
				$this->register_gf_string( $page_title, $snh->get_form_pagination_page_title(), $form_package, "page-" . ( intval( $key ) + 1 ) . '-title' );
			}
			$value = ! empty( $form[ 'pagination' ][ 'progressbar_completion_text' ] ) ? $form[ 'pagination' ][ 'progressbar_completion_text' ] : null;
			if ( $value !== null ) {
				$this->register_gf_string( $value, $snh->get_form_pagination_completion_text(), $form_package, "progressbar_completion_text" );
			}
			$value = ! empty( $form[ 'lastPageButton' ][ 'text' ] ) ? $form[ 'lastPageButton' ][ 'text' ] : null;
			if ( $value !== null ) {
				$this->register_gf_string( $value, $snh->get_form_pagination_last_page_button_text(), $form_package, "lastPageButton" );
			}
		}
	}

	protected function register_strings( $form ) {
		global $sitepress;

		if ( ! isset( $form[ 'id' ] ) ) {
			return false;
		}

		$form_id      = $form[ 'id' ];
		$form_package = $this->get_form_package( $form );

		// Cache
		$current_lang = $sitepress->get_current_language();
		if ( isset( $this->_current_forms[ $form_id ][ $current_lang ] ) ) {
			return $this->_current_forms[ $form_id ][ $current_lang ];
		}

		$this->register_strings_main_fields( $form_package, $form );
		$this->register_global_strings( $form_package, $form );
		$this->register_strings_pagination( $form_package, $form );
		$this->register_strings_fields( $form_package, $form );

		$this->_current_forms[ $form_id ][ $current_lang ] = $form;

		return $form;
	}

	public function update_form_translations( $form, $is_new, $needs_update = true ) {
		$this->register_strings( $form );
		$this->cleanup_form_strings( $form );
	}

	protected function cleanup_form_strings( $form ) {
		if ( isset( $form[ 'id' ] ) ) {

			global $wpdb;
			$form_id         = $form[ 'id' ];
			$st_context       = $this->get_st_context( $form_id );
			$database_strings = (bool) $this->registered_strings !== false ? $wpdb->get_col( $wpdb->prepare( "SELECT s.name
                 FROM {$wpdb->prefix}icl_strings s
                 WHERE s.context = %s", $st_context ) ) : array();

			foreach ( $database_strings as $key => $string_name ) {
				if ( !in_array( $string_name, $this->registered_strings ) ) {
					icl_unregister_string( $st_context, $string_name );
				}
			}
		}
	}

	public function after_delete_form( $form_id ) {
		do_action( 'wpml_delete_package', $form_id, ICL_GRAVITY_FORM_ELEMENT_TYPE );
	}

	protected function gform_id( $package_id ) {
		return $package_id;
	}

	private function has_post_gravity_from_translations() {
		global $wpdb;

		$post_gravity_from_translations_query = "SELECT COUNT(*)
                               FROM {$wpdb->prefix}icl_translations
                               WHERE element_type = 'post_gravity_from'";
		$post_gravity_from_translations_count = $wpdb->get_var( $post_gravity_from_translations_query );

		return $post_gravity_from_translations_count == 0;
	}

	public function register_missing_packages() {
		global $wpdb;

		$post_gravity_from_translations_query = $wpdb->prepare( "SELECT rgf.id
			FROM {$this->get_forms_table_name()} rgf
			LEFT JOIN {$wpdb->prefix}icl_translations iclt
				ON rgf.id = iclt.element_id
					AND iclt.element_type = %s
            WHERE iclt.language_code IS NULL", 'package_' . ICL_GRAVITY_FORM_ELEMENT_TYPE );
		$form_ids                             = $wpdb->get_col( $post_gravity_from_translations_query );

		foreach ( $form_ids as $id ) {
			$form = RGFormsModel::get_form_meta( $id );
			$this->update_form_translations( $form, true );
		}
	}
}
