<?php

/**
 * @author OnTheGo Systems
 */
class GFML_Form {
	/**
	 * Used to cache the translations of multi input options as they need to be accessed multiple times when translating conditional fields rules.
	 *
	 * @var array
	 */
	private $multi_input_translations = array();

	/**
	 * It returns an associative array where the first level key is the original value of the choice "text"
	 * and the value is another associative array with the translations of the "text" and "value" attributes.
	 *
	 * This method is very similar to `\Gravity_Forms_Multilingual::maybe_translate_placeholder` and could be used in the future to remove some duplication.
	 *
	 * @param \GF_Field $field
	 * @param string    $st_context
	 *
	 * @return array
	 */
	protected function get_multi_input_translations( $field, $st_context ) {
		if ( ! $this->multi_input_translations && is_array( $field->choices ) ) {
			$snh        = new GFML_String_Name_Helper();
			$snh->field = $field;

			foreach ( $field->choices as $index => $choice ) {
				$snh->field_choice       = $choice;
				$snh->field_choice_index = $index;

				$choice_id = $choice['text']; // We use the 'text' property in the original language as ID for the translations cluster.

				$this->multi_input_translations[ $choice_id ] = array(
					'text'  => icl_t( $st_context, $snh->get_field_multi_input_choice_text(), $choice['text'] ),
					'value' => null,
				);

				if ( isset( $choice['value'] ) ) {
					$this->multi_input_translations[ $choice_id ]['value'] = icl_t( $st_context, $snh->get_field_multi_input_choice_value(), $choice['value'] );
				}
			}
		}

		return $this->multi_input_translations;
	}

	/**
	 * It matches the field in the rule with the form's field (if present).
	 *
	 * @param array $form
	 * @param array $rule
	 *
	 * @return \GF_Field|null
	 */
	protected function get_field_from_rule( $form, $rule ) {
		if ( $rule && array_key_exists( 'fieldId', $rule ) ) {
			foreach ( $form['fields'] as $field ) {
				if ( (int) $rule['fieldId'] === $field->id ) {
					return $field;
				}
			}
		}

		return null;
	}

}
