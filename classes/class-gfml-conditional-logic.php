<?php

/**
 * @author OnTheGo Systems
 */
class GFML_Conditional_Logic extends GFML_Form {
	/**
	 * It translates the attributes of the conditional logic, before translating the fields.
	 * This is necessary, or it would not be possible to adjust the conditional logic based on values from "choices" fields.
	 *
	 * @param array  $form
	 * @param string $st_context
	 *
	 * @return array
	 */
	public function translate_conditional_logic( $form, $st_context ) {
		foreach ( $form['fields'] as $id => &$field ) {

			if ( $field->conditionalLogic && $field->conditionalLogic['rules'] ) {
				foreach ( $field->conditionalLogic['rules'] as &$rule ) {
					$rule_field = $this->get_field_from_rule( $form, $rule );
					if ( $rule_field ) {
						$translations = $this->get_multi_input_translations( $rule_field, $st_context );
						if ( array_key_exists( $rule['value'], $translations ) && isset( $rule_field->choices ) && is_array( $rule_field->choices ) ) {
							$translated_rule = $translations[ $rule['value'] ];

							if ( array_key_exists( 'text', $translated_rule ) ) {
								$rule['value'] = $translated_rule['text'];
							} elseif ( array_key_exists( 'value', $translated_rule ) ) {
								$rule['value'] = $translated_rule['value'];
							}
						}
					}
				}
			}
		}

		return $form;
	}
}
