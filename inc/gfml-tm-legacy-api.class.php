<?php

class GFML_TM_Legacy_API extends Gravity_Forms_Multilingual {

    public function __construct() {
        parent::__construct();
        add_filter( 'WPML_get_translatable_items', array( $this, 'get_translatable_items' ), 10, 3 );
        add_filter( 'WPML_get_translatable_types', array( $this, 'get_translatable_types' ) );
        add_filter( 'WPML_get_translatable_item', array( $this, 'get_translatable_item' ), 10, 2 );
        add_filter( 'WPML_make_external_duplicate', array($this, 'make_duplicate'), 10, 2 );
    }

    public function get_type_prefix() {
        return 'post';
    }

    public function get_type() {
        return $this->get_type_prefix() . '_' . ICL_GRAVITY_FORM_ELEMENT_TYPE;
    }

    public  function get_st_context($form){
        return 'gravity_form';
    }

    function register_strings( $form ) {
        $this->gform_pre_render( $form );
        $this->gform_pre_render_deprecated( $form );

        return true;
    }

    public function get_string_prefix_id( $form ) {

        return isset( $form[ 'id' ] ) ? $form[ 'id' ] : false;
    }

    /**
     * Adds GF items to TM Dashboard screen.
     *
     * @global object $wpdb
     * @global object $sitepress
     * @param array $items
     * @param string $type
     * @param string $filter
     * @return array
     */
    function get_translatable_items( $items, $type, $filter ) {
        if ( $type === ICL_GRAVITY_FORM_ELEMENT_TYPE ) {

            global $wpdb, $sitepress;

            $icl_el_type = $this->get_type();
            $default_lang = $sitepress->get_default_language();
            $active_languages = array_keys( ( array ) $sitepress->get_active_languages() );
            $g_forms = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}rg_form" );

            foreach ( $g_forms as $k => $g_form ) {
                // Create item and add it to the translation table if required
                $new_item = $this->new_external_item( $g_form, false );
                $post_trid = $sitepress->get_element_trid( $new_item->id, $icl_el_type );
                if ( !$post_trid ) {
                    $sitepress->set_element_language_details(
                        $new_item->id,
                        $icl_el_type,
                        false,
                        $default_lang,
                        null,
                        false
                    );
                    $post_trid = $sitepress->get_element_trid( $new_item->id, $icl_el_type );
                }

                // Get translation status for each item
                $post_translations = $sitepress->get_element_translations( $post_trid, $icl_el_type );

                $status = array();
                foreach ( $post_translations as $lang => $translation ) {

                    // Skip inactive languages
                    if ( !in_array( $lang, $active_languages ) ) {
                        continue;
                    }
                    // Skip if 'to_lang' filter set and not matched
                    if ( !empty( $filter[ 'to_lang' ] ) && $filter[ 'to_lang' ] != $lang ) {
                        continue;
                    }

                    // Fetch existing tranlsations
                    $res = $wpdb->get_row(
                        "SELECT status, needs_update FROM {$wpdb->prefix}icl_translation_status WHERE translation_id={$translation->translation_id}"
                    );

                    if ( $res ) {
                        $_suffix = str_replace( '-', '_', $lang );
                        $index = 'status_' . $_suffix;
                        $new_item->$index = $res->status;
                        $index = 'needs_update_' . $_suffix;
                        $new_item->$index = $res->needs_update;
                        if ( $res->needs_update ) {
                            $status = array_merge( $status, array( 'not', 'need-update' ) );
                        } else {
                            if ( $res->status == ICL_TM_IN_PROGRESS
                                 || $res->status == ICL_TM_WAITING_FOR_TRANSLATOR
                            ) {
                                $status[ ] = 'in_progress';
                            } else if ( $res->status == ICL_TM_COMPLETE
                                        || $res->status == ICL_TM_DUPLICATE
                            ) {
                                $status[ ] = 'complete';
                            }
                        }
                        // If filter 'to_lang' mark status on root item
                        if ( !empty( $filter[ 'to_lang' ] ) ) {
                            $new_item->status = $res->needs_update ? ICL_TM_NEEDS_UPDATE : $res->status;
                        }
                    }
                }
                
                // No translations at all (can happen with combination of filter and results)
                $status = empty($status) ? array( 'not' ) : $status;

                // Check for missing translations if filter 'to_lang' is not used
                if ( empty( $filter[ 'to_lang' ] ) ) {
                    foreach ( $active_languages as $lang ) {
                        if ( !array_key_exists( $lang, $post_translations ) ) {
                            $status[ ] = 'not';
                        }
                    }
                }

                /*
                 * Final checks
                 */
                // Check status
                $status = array_unique( $status );
                if (($filter[ 'tstatus' ] !== 'all' && !in_array( $filter[ 'tstatus' ], $status ))
                    || (!empty( $filter[ 'title' ] ) 
                    && strpos( strtolower( $new_item->post_title ), strtolower( $filter[ 'title' ] )) === false)
                ) {
                    continue;
                }

                $items[ ] = $new_item;
            }
        }

        return $items;
    }

    /**
     * Returns the actual Gravity Form ID for a post id that contains the 'external_gravity_form' string
     *
     * @param int $post_id
     *
     * @return int| boolean
     */
    protected function gform_id( $post_id ) {
        //return form id if $post_id is an 'external' GF type
        $prefix = 'external_' . ICL_GRAVITY_FORM_ELEMENT_TYPE . '_';
        $len = strlen( $prefix );
        
        return  (is_string( $post_id ) && substr( $post_id, 0, $len ) === $prefix ) ? ( int ) substr( $post_id, $len ) : false;
    }

    /**
     * For TranslationManagement::send_jobs.
     */
    function get_translatable_item( $item, $id ) {
        if ( $item == null ) {
            global $wpdb;
            $id = $this->gform_id( $id );
            if ( !$id )
                return $item; //not ours

            $g_form = $wpdb->get_row( $wpdb->prepare( "
                    SELECT * 
                    FROM {$wpdb->prefix}rg_form 
                    WHERE id = %d 
                    LIMIT 1", $id ) );
            $item = $this->new_external_item( $g_form, true );
        }
        return $item;
    }

    /**
     * Update translations
     *
     * @param array $form - form information
     * @param bool  $is_new - set to true for newly created form (first save without fields)
     * @param bool  $needs_update - when deleting single field we do not need to change the translation status of the form
     */
    public function update_form_translations( $form, $is_new, $needs_update = true ) {

        global $sitepress, $wpdb, $iclTranslationManagement;

        $post_id = 'external_'.ICL_GRAVITY_FORM_ELEMENT_TYPE.'_'.$form['id'];
        $post = $this->get_translatable_item(null,$post_id);
        $default_lang = $sitepress->get_default_language();
        $icl_el_type = ICL_GRAVITY_FORM_ELEMENT_TYPE;
        $trid = $sitepress->get_element_trid($form['id'], $icl_el_type);

        if ($is_new) {
            $sitepress->set_element_language_details($post->id, $icl_el_type, false, $default_lang, null, false);
        }

        $this->register_strings( $form );

        $sql = "
        	SELECT t.translation_id, s.md5 FROM {$wpdb->prefix}icl_translations t
        		NATURAL JOIN {$wpdb->prefix}icl_translation_status s
        	WHERE t.trid=%d AND t.source_language_code IS NOT NULL";
        $element_translations = $wpdb->get_results( $wpdb->prepare( $sql, $trid ) );

        if ( !empty( $element_translations ) ) {

            $md5 = $iclTranslationManagement->post_md5($post);

            if ($md5 !== $element_translations[0]->md5) { //all translations need update

                $translation_package = $iclTranslationManagement->create_translation_package($post);

                foreach ($element_translations as $trans) {
                    $_prevstate = $wpdb->get_row($wpdb->prepare("
                        SELECT status, translator_id, needs_update, md5, translation_service, translation_package, timestamp, links_fixed
                        FROM {$wpdb->prefix}icl_translation_status
                        WHERE translation_id = %d
                    ", $trans->translation_id), ARRAY_A);
                    if(!empty($_prevstate)){
                        $data['_prevstate'] = serialize($_prevstate);
                    }
                    $data = array('translation_id' => $trans->translation_id,
                                  'translation_package' => serialize($translation_package),
                                  'md5' => $md5,
                    );

                    //update only when something changed (we do not need to change status when deleting a field)
                    if ($needs_update){
                        $data['needs_update'] = 1;
                    }

                    list( $rid ) = $iclTranslationManagement->update_translation_status( $data );
                    $this->update_icl_translate($rid,$post);

                    //change job status only when needs update
                    if ( $needs_update ){
                        $job_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d GROUP BY rid", $rid));
                        if ($job_id){
                            $wpdb->update(
                                "{$wpdb->prefix}icl_translate_job",
                                array( 'translated' => 0 ),
                                array( 'job_id' => $job_id ),
                                array( '%d' ),
                                array( '%d' )
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Update translations when forms are modified in admin.
     */
    function update_icl_translate( $rid, $post ) {

        global $wpdb;

        $job_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d GROUP BY rid", $rid));
        $elements = $wpdb->get_results($wpdb->prepare("SELECT field_type, field_data, tid, field_translate FROM {$wpdb->prefix}icl_translate
        												WHERE job_id=%d",$job_id),OBJECT_K);

        foreach ($post->string_data as $field_type => $field_value) {
            $field_data = base64_encode($field_value);
            if (!isset($elements[$field_type])) {
                //insert new field

                $data = array(
                    'job_id'            => $job_id,
                    'content_id'        => 0,
                    'field_type'        => $field_type,
                    'field_format'      => 'base64',
                    'field_translate'   => 1,
                    'field_data'        => $field_data,
                    'field_data_translated' => 0,
                    'field_finished'    => 0
                );

                $wpdb->insert($wpdb->prefix.'icl_translate', $data);
            } elseif ($elements[$field_type]->field_data != $field_data) {
                //update field value
                $wpdb->update($wpdb->prefix.'icl_translate',
                              array('field_data'=>$field_data, 'field_finished'=>0),
                              array('tid'=>$elements[$field_type]->tid)
                );
            }
        }

        foreach ($elements as $field_type => $el) {
            //delete fields that are no longer present
            if ($el->field_translate && !isset($post->string_data[$field_type])) {
                $wpdb->delete($wpdb->prefix.'icl_translate',array('tid' => $el->tid),array('%d'));
            }
        }
    }

    /**
     * Undocumented.
     */
    function make_duplicate($post_id,$lang) {
        global $wpdb, $sitepress, $iclTranslationManagement;

        $item = $this->get_translatable_item(null,$post_id);
        $form_id = $this->gform_id($post_id);

        if (is_null($item))
            return $post_id; //leave it untouched, not ours

        $icl_el_type = $this->get_type();
        $default_lang = $sitepress->get_default_language();

        $trid = $sitepress->get_element_trid($form_id,$icl_el_type);
        if (!$trid) {
            $sitepress->set_element_language_details($form_id, $icl_el_type, null, $default_lang, null, false);
            $trid = $sitepress->get_element_trid($form_id, $icl_el_type);
        }

        $translation_id = $wpdb->get_var($wpdb->prepare("SELECT translation_id FROM {$wpdb->prefix}icl_translations
														WHERE trid=%d AND language_code=%s AND source_language_code=%s",
                                                        $trid,$lang,$default_lang));
        if (!$translation_id)
            $translation_id = $sitepress->set_element_language_details(null, $icl_el_type, $trid, $lang, $default_lang);
        $translation_package = $iclTranslationManagement->create_translation_package($item);
        $translator_id = 0;
        $translation_service = 'local';

        // add translation_status record
        $data = array(
            'translation_id'        => $translation_id,
            'status'                => ICL_TM_COMPLETE, //don't mark it as duplicate so that it can be edited with TE
            'translator_id'         => 0,
            'needs_update'          => 0,
            'md5'                   => $iclTranslationManagement->post_md5($item),
            'translation_service'   => $translation_service,
            'translation_package'   => serialize($translation_package)
        );


        list($rid) = $iclTranslationManagement->update_translation_status($data);
        $job_id = $iclTranslationManagement->add_translation_job($rid, $translator_id, $translation_package);
        $wpdb->update($wpdb->prefix . 'icl_translate_job', array('translated'=>1), array('job_id'=>$job_id));

        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}icl_translate
                                     SET field_data_translated = field_data, field_finished=1
                                     WHERE job_id=%d AND field_translate=1",$job_id));

        return $job_id;
    }

    /**
     * Undocumented.
     */
    function after_delete_form($form_id) {

        global $sitepress, $wpdb;

        $icl_el_type = $this->get_type();
        $trid = $sitepress->get_element_trid($form_id,$icl_el_type);
        $translation_ids = $wpdb->get_col($wpdb->prepare("SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND element_type=%s", $trid, $icl_el_type));

        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translations WHERE trid=%d", $trid));

        if (!empty($translation_ids)) foreach ($translation_ids as $tid) {
            $rid = $wpdb->get_var($wpdb->prepare("SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $tid));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $tid));
            if($rid){
                $jobs = $wpdb->get_col($wpdb->prepare("SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d", $rid));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d", $rid));
                if(!empty($jobs)){
                    $wpdb->query("DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id IN (".join(',', $jobs).")");
                }
            }
        }
    }

    /**
     * Create a new external item for the Translation Dashboard or for translation jobs.
     * @param Object $g_form
     * @param bool $get_string_data
     * @return stdClass
     */
    function new_external_item( $g_form, $get_string_data = false ) {
        $item = new stdClass();
        $item->external_type = true;
        $item->type = ICL_GRAVITY_FORM_ELEMENT_TYPE;
        $item->id = $g_form->id;
        $item->ID = $g_form->id;
        $item->post_type = ICL_GRAVITY_FORM_ELEMENT_TYPE;
        $item->post_id = 'external_' . $item->post_type . '_' . $item->id;
        $item->post_date = isset( $g_form->modified ) ? $g_form->modified : null;
        $item->post_status = $g_form->is_active ? __( 'Active', 'gravity-forms-ml' ) : __( 'Inactive',
                                                                                           'gravity-forms-ml' );
        $item->post_title = $g_form->title;
        $item->is_translation = false;

        if($get_string_data){
            $item->string_data = $this->get_form_strings( $item->id );
        }

        return $item;
    }

    /**
     * Filters the translatable element types in WPML TM and adds 'Gravity Form' to them.
     * @param Array $types
     * @return Array
     */
    function get_translatable_types( $types ) {
        $types[ ICL_GRAVITY_FORM_ELEMENT_TYPE ] = __('Gravity Form', 'gravity-forms-ml');

        return $types;
    }
}
