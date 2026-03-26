<?php 
/**
 * View class
 *
 * @since 1.0.0
 */

final class WPDP_Metabox {

    /**
     * Instance of this class.
     *
     * @since 1.0.0
     *
     * @var WP_VST_Shortcode_View
     */
    protected static $_instance = null;

    /**
     * Get instance of the class
     *
     * @since 1.0.0
     *
     * @return VST_Shortcode_View
     */

    public static function get_instance() {

        // If the single instance hasn't been set, set it now.
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public $api_event_date;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->_add_hooks();
    }

    public function _add_hooks(){
        
        $this->de_acf_free();
        add_filter('acf/settings/url', array($this,'my_acf_settings_url'));
        add_filter('acf/settings/show_admin', array($this,'show_admin'));
        add_filter('acf/render_field/key=field_657e4ec5e8971', array($this,'shortcode_box'), 20, 1);
        add_filter('acf/render_field/key=field_66ad383f1d6af', array($this,'last_updated_field'), 20, 1);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_action('save_post',array($this,'save_presentation'),1);

        add_filter('acf/load_field/name=countries_to_show', array($this,'countries_field'));
        add_action('acf/save_post', array($this,'save_option_page'), 20);
        
        add_action( 'ok_wpdp_remove_countries_records', array($this,'remove_countries_records'), 10, 3 );
        
        // Mapping
        add_filter('acf/load_field/name=fatalities_filter', array($this,'load_fat_choices'));
        // add_filter('acf/load_field/name=actor_filter', array($this,'load_actor_choices'));
        add_filter('acf/load_field/name=incident_type_filter', array($this,'load_incidents_choices'));

        add_filter('acf/load_value/name=incident_type_filter', array($this,'set_default_repeater_values'), 10, 3);


        add_filter('acf/load_field/key=field_667ed6bc35cf2', array($this,'empty_mapping_categories'));

        // Add the cron job hook
        add_action('wpdp_daily_acled_update', array($this, 'update_acled_presentations'));

        // Schedule the cron job if it's not already scheduled
        // if (!wp_next_scheduled('wpdp_daily_acled_update')) {
        //     wp_schedule_event(time(), 'daily', 'wpdp_daily_acled_update');
        // }

        // Schedule the cron job if it's not already scheduled
        if (!wp_next_scheduled('wpdp_weekly_yearly_data_update')) {
            wp_schedule_event(time(), 'weekly', 'wpdp_weekly_yearly_data_update');
        }

        add_action('wpdp_weekly_yearly_data_update', array($this, 'process_yearly_data_update'));

        // Add new hook for file upload
        add_filter('upload_mimes', array($this, 'add_custom_mime_types'));
      
        add_action('rest_api_init', array($this, 'register_cron_endpoint'));

        add_filter('acf/render_field/key=field_6784bd285d65f', array($this,'cache_message_field'), 20, 1);

    }

    public function cache_message_field($field){
        $clear_cache_url = add_query_arg(array(
            'wpdp_clear_cache' => '1',
        ));
    
        echo '<div class="wpdp_cache_message">';
        echo '<a href="' . esc_url($clear_cache_url) . '" class="button button-secondary wpdp_clear_cache">Clear Cache</a>';
        echo '</div>';
    }
    
    /**
     * Register REST API endpoint for cron updates
     */
    public function register_cron_endpoint() {
        register_rest_route('wpdp/v1', '/update-acled', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_cron_request'),
            'permission_callback' => array($this, 'verify_cron_key')
        ));

        // Add new endpoint for exporting ACF options
        register_rest_route('wpdp/v1', '/export-filters', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_export_filters_request'),
        ));
        
    }

    /**
     * Handle the export filters request
     */
    public function handle_export_filters_request($request) {
        $filters = array(
            'incident_type_filter' => get_field('incident_type_filter', 'option'),
            'actor_filter' => get_field('actor_filter', 'option'),
            'fatalities_filter' => get_field('fatalities_filter', 'option')
        );
        
        return new WP_REST_Response($filters, 200);
    }



    /**
     * Verify security key for cron requests
     */
    public function verify_cron_key($request) {
        $security_key = $request->get_param('security_key');
        $valid_key = 'wpdp_acled_2024_RPOzXuBpfp'; // Your secure key here
        
        return !empty($valid_key) && $security_key === $valid_key;
    }

    /**
     * Handle the cron request
     */
    public function handle_cron_request( $request ) {
        try {
            error_log( 'WPDP Cron: Starting ACLED presentations update via REST API' );
            $this->update_acled_presentations();
            return new WP_REST_Response( array( 'status' => 'success', 'message' => 'ACLED presentations updated' ), 200 );
        } catch ( Exception $e ) {
            error_log( 'WPDP Cron Error: Cron request handler failed - ' . $e->getMessage() );
            wpdp_send_error_email( 'Cron Request Handler Failed', 'The cron job request handler encountered an error' );
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Update failed' ), 500 );
        }
    }



    function de_acf_free(){
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Check if the ACF free plugin is activated
        if ( is_plugin_active( 'advanced-custom-fields/acf.php' ) ) {
            // Free plugin activated
            // Free plugin activated, show notice
            add_action( 'admin_notices', function () {
                ?>
                <div class="updated" style="border-left: 4px solid #ffba00;">
                    <p>The ACF plugin cannot be activated at the same time as Third-Party Product and has been deactivated. Please keep ACF installed to allow you to use ACF functionality.</p>
                </div>
                <?php
            }, 99 );

            // Disable ACF free plugin
            deactivate_plugins( 'advanced-custom-fields/acf.php' );
        }
    }

    function empty_mapping_categories($field){
        $mapping = get_field('incident_type_filter','option');
        if(empty($mapping)){
            return $field;
        }

        $db_columns = array(
            'disorder_type',
            'event_type',
            'sub_event_type'
        );
        $types = [];
        foreach($db_columns as $column){
            $types = array_merge($types,$this->get_db_column($column));
        }

        foreach($mapping as $k1 => $value){
            foreach($value as $k2 => $value2){
                foreach($value2 as $k3 => $value3){

                    $incident_type = $value3['type'];

                    if(!empty($value3[$incident_type])){
                        $result = $this->find_element($types, $value3['text'],true);
                        if($result !== false){
                            unset($types[$result]);
                        }
                    }
                }
            }
        }
        
        if(empty($types)){
            return $field;
        }

        $message = '<div id="empty_cats">';
        $message .= '
            <div>
            <h2>Disorder Types</h2>
            <ul>
        ';
        foreach($types as $type){
            $text = explode('__', $type);
            if($text[1] === 'disorder_type'){
                $message .= '<li>'.$text[0].'</li>';
            }
        }
        $message .= '</div></ul>';

        $message .= '
            <div>
            <h2>Event Types</h2>
            <ul>
        ';
        foreach($types as $type){
            $text = explode('__', $type);
            if($text[1] === 'event_type'){
                $message .= '<li>'.$text[0].'</li>';
            }
        }
        $message .= '</div></ul>';

        $message .= '
            <div>
            <h2>Sub Event Types</h2>
            <ul>
        ';
        foreach($types as $type){
            $text = explode('__', $type);
            if($text[1] === 'sub_event_type'){
                $message .= '<li>'.$text[0].'</li>';
            }
        }
        $message .= '</div></ul>';


        $message .= '</div>';
        
        $field['message'] = $message;
        return $field;
    }


    function set_default_repeater_values($value, $post_id, $field) {
        if(!empty($value)){
            return $value;
        }

        $filePath = WP_DATA_PRESENTATION_PATH . '/lib/acf-json/default_incident_types.json';

        $jsonContent = file_get_contents($filePath);

        return json_decode($jsonContent, true);
    }



    private function get_db_column($column_name){
        $posts = get_posts(array(
            'post_type'=>'wp-data-presentation',
            'posts_per_page'=>-1,
            'fields'=>'ids'
        ));

        if(empty($posts)){
            return [];
        }

        global $wpdb;
        $column = [];
        foreach($posts as $id){
            $table_name = $wpdb->prefix. 'wpdp_data_'.$id;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            if(!$table_exists){
                continue;
            }

            $db_column = $wpdb->get_col("SELECT DISTINCT {$column_name} FROM {$table_name}");
            if(!empty($db_column)){
                if($column_name !== 'country'){
                    $db_column = array_map(function($value) use ($column_name) {
                        return $value . '__' . $column_name;
                    }, $db_column);
                }
                $column = array_merge($column, $db_column);
            }


        }

        return $column;
    }

    function load_incident_to_actors($column,$sub_field) {
        
        // Initialize choices array
        $sub_field['choices'] = array();
        
        $incidents = get_field('incident_type_filter','option');
        foreach($incidents as $incident){
            foreach($incident['filter'] as $filter){
                if(strpos($filter['hierarchial'],'1') !== false){
                    $field = '[1]';
                }elseif(strpos($filter['hierarchial'],'2') !== false){
                    $field = '[2]';
                }elseif(strpos($filter['hierarchial'],'3') !== false){
                    $field = '[3]';
                }else{
                    $field = '[4]';
                }
                $sub_field['choices'][$filter['text']] = $filter['text'].' - '.$field;
            }
        }
        
        return $sub_field;
    }


    function load_choices($column,$sub_field) {
        $types= $this->get_db_column($column);
        
        // Initialize choices array
        $sub_field['choices'] = array();
        
        // Populate choices
        if (!empty($types)) {
            foreach ($types as $type) {
                $val_type = explode('_',$type);
                $sub_field['choices'][$type] = $val_type[0];
            }
        }
        
        return $sub_field;
    }

    function load_fat_choices($field) {
        if (!empty($field['sub_fields'])) {
            // Iterate through each sub-field in the main repeater
            foreach ($field['sub_fields'] as &$sub_field) {
                // If the sub-field is a repeater itself, iterate its sub-fields
                if (isset($sub_field['sub_fields']) && is_array($sub_field['sub_fields'])) {
                    
                    foreach ($sub_field['sub_fields'] as &$inner_sub_field) {
                        // Check each inner sub-field type and load choices accordingly
                        if (isset($inner_sub_field['name']) && $inner_sub_field['name'] == 'mapping_to_incident') {
                            $inner_sub_field = $this->load_incident_to_actors('mapping_to_incident',$inner_sub_field);
                        }
                    }
                }
            }
        }

        // Return the field
        return $field;
    }
    
    function load_actor_choices($field) {
        if (!empty($field['sub_fields'])) {
            // Iterate through each sub-field in the main repeater
            foreach ($field['sub_fields'] as &$sub_field) {
                // If the sub-field is a repeater itself, iterate its sub-fields
                if (isset($sub_field['sub_fields']) && is_array($sub_field['sub_fields'])) {
                    
                    foreach ($sub_field['sub_fields'] as &$inner_sub_field) {
                        // Check each inner sub-field type and load choices accordingly
                        if (isset($inner_sub_field['name']) && $inner_sub_field['name'] == 'mapping_to_incident') {
                            $inner_sub_field = $this->load_incident_to_actors('mapping_to_incident',$inner_sub_field);
                        }
                    }
                }
            }
        }

        // Return the field
        return $field;
    }

    function load_incidents_choices($field) {
        if (!empty($field['sub_fields'])) {
            // Iterate through each sub-field in the main repeater
            foreach ($field['sub_fields'] as &$sub_field) {
                // If the sub-field is a repeater itself, iterate its sub-fields
                if (isset($sub_field['sub_fields']) && is_array($sub_field['sub_fields'])) {
                    
                    foreach ($sub_field['sub_fields'] as &$inner_sub_field) {
                        // Check each inner sub-field type and load choices accordingly
                        if (isset($inner_sub_field['name']) && $inner_sub_field['name'] == 'disorder_type') {
                            $inner_sub_field = $this->load_choices('disorder_type',$inner_sub_field);
                        }elseif (isset($inner_sub_field['name']) && $inner_sub_field['name'] == 'event_type') {
                            $inner_sub_field = $this->load_choices('event_type',$inner_sub_field);
                        }elseif (isset($inner_sub_field['name']) && $inner_sub_field['name'] == 'sub_event_type') {
                            $inner_sub_field = $this->load_choices('sub_event_type',$inner_sub_field);
                        }
                    }
                }
            }
        }

        // Return the field
        return $field;
    }
        
    
    

    function save_option_page( $post_id ) {
        // Check if it's not an autosave
        if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return;
        }

        // Check if it's our specific option field
        if( isset($_POST['acf']['field_66747fef0e941']) ) { 
            $new_value = $_POST['acf']['field_66747fef0e941'];
            $this->remove_other_countires_records_cron_job($new_value);
        }
    }


    function remove_countries_records( $table_name, $countries, $post_id ) {
        global $wpdb;
        $countries_placeholders = implode(', ', array_fill(0, count($countries), '%s'));
        $sql = $wpdb->prepare("DELETE FROM {$table_name} WHERE country NOT IN ($countries_placeholders)", ...$countries);
        $result = $wpdb->query($sql);

        if ($result === false) {
            $error = $wpdb->last_error;
            error_log("Database error: " . $error); // Log the error in WordPress error log
            update_post_meta($post_id,'wpdp_countries_updated_error',$error);
        } else {
            // Operation was successful
            update_post_meta($post_id,'wpdp_countries_updated',true);
        }
        
    }
    

    public function remove_other_countires_records_cron_job($countries){

        if(empty($countries)){
            return;
        }

        $posts = get_posts(array(
            'post_type'=>'wp-data-presentation',
            'posts_per_page'=>-1,
            'fields'=>'ids'
        ));

        if(empty($posts)){
            return [];
        }

        global $wpdb;
        foreach($posts as $id){
            $table_name = $wpdb->prefix. 'wpdp_data_'.$id;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            if(!$table_exists){
                continue;
            }

            wp_schedule_single_event( time(), 'ok_wpdp_remove_countries_records', array( $table_name, $countries, $id) );

        }
    }
    
    public function countries_field( $field ) {
        $countries = $this->get_db_column('country');
        
        // Initialize choices array
        $field['choices'] = array();
        
        // Populate choices
        if (!empty($countries)) {
            foreach ($countries as $country) {
                $field['choices'][ $country ] = $country;
            }
        }
        
        // Return the field
        return $field;
    }
    
    public function create_data_table($post_id, $use_posted_data = true){

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
    

        global $wpdb;
        $table_name = $wpdb->prefix. 'wpdp_data_'.$post_id;

        if($use_posted_data){
            $import_file = (isset($_POST['acf']['field_657aa840cb9c5'])) ? $_POST['acf']['field_657aa840cb9c5'] : '';
            $acled_url = (isset($_POST['acf']['field_66a2ceaad7f51'])) ? $_POST['acf']['field_66a2ceaad7f51'] : '';
            $excel_file = (isset($_POST['acf']['field_657aa818cb9c4'])) ? $_POST['acf']['field_657aa818cb9c4'] : '';
        }else{
            $import_file = get_field('import_file',$post_id);
            $acled_url = get_field('acled_url',$post_id);
            $excel_file = get_field('upload_excel_file',$post_id);
        }

        if(empty($this->api_event_date)){
            $this->api_event_date = current_time('Y-m-d');
        }

        if($import_file === 'Upload'){
            $file_path = get_attached_file($excel_file);
        }else{
            $url = $acled_url;
            
            // Remove old authentication parameters (email, key) if present
            $url = remove_query_arg( array( 'email', 'key' ), $url );
            
            // Set event date parameters
            $event_date = date('Y-m-d', strtotime($this->api_event_date . ' -3 months'));
            $url = remove_query_arg( array( 'event_date', 'event_date_where' ), $url );
            $url = add_query_arg( array(
                'event_date' => $event_date,
                'event_date_where' => '>'
            ), $url );

            // Ensure format parameter is set correctly for OAuth API
            // According to ACLED docs, CSV format uses _format=csv (with underscore)
            if ( strpos( $url, '_format=' ) === false ) {
                $url = add_query_arg( '_format', 'csv', $url );
            }

            // Get access token from WPDP_API
            $api = new WPDP_API();
            $access_token = $api->get_valid_access_token();
            
            if ( is_wp_error( $access_token ) ) {
                $error_message = 'WPDP API Token Error: ' . $access_token->get_error_message();
                error_log( $error_message );
                wpdp_send_error_email( 'API Token Error', 'Failed to get API access token for presentation ID: ' . $post_id );
                wp_die( "Error getting API access token: " . $access_token->get_error_message() );
            }

            // Download file with authorization header
            $file_path = $this->download_url_with_auth( $url, $access_token );
            if ( is_wp_error( $file_path ) ) {
                $error_message = 'WPDP Download Error: ' . $file_path->get_error_message();
                error_log( $error_message );
                wpdp_send_error_email( 'File Download Error', 'Failed to download file for presentation ID: ' . $post_id );
                wp_die( "Error downloading file: " . $file_path->get_error_message() );
            }
        }

        $import =  new WPDP_Db_Table( $table_name, $file_path );
        if ( ! $import->import_csv() ) {
            $error_message = 'WPDP Import Error: Failed to import CSV data for presentation ID: ' . $post_id . ' - Table: ' . $table_name;
            error_log( $error_message );
            wpdp_send_error_email( 'CSV Import Failed', 'Failed to import data for presentation ID: ' . $post_id );
            var_dump( 'Error in importing' );
            exit;
        }

        if($import_file !== 'Upload'){
            $attachment_id = get_post_meta($post_id, 'wpdp_last_file_attach_id', true);
            if ($attachment_id) {
                $old_file_path = get_attached_file($attachment_id);
                if ($old_file_path && file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
                wp_delete_attachment($attachment_id, true);
            }
            
            // Upload file to media library
            $attach_id = $this->download_and_upload_csv($file_path, $post_id);

            update_post_meta($post_id,'wpdp_last_file_attach_id',$attach_id);
        }

        delete_post_meta($post_id,'wpdp_countries_updated');
        update_post_meta($post_id,'wpdp_last_updated_date',time());
        
    }

    /**
     * Download URL with authorization header
     *
     * @param string $url URL to download
     * @param string $access_token OAuth access token
     * @return string|WP_Error Path to downloaded file or WP_Error on failure
     */
    public function download_url_with_auth( $url, $access_token ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        
        // Log the request for debugging
        error_log( 'WPDP: Attempting to download from URL: ' . $url );
        error_log( 'WPDP: Token length: ' . strlen( $access_token ) );
        
        $temp_file = wp_tempnam( $url );
        
        $response = wp_remote_get( 
            $url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'text/csv, application/csv, */*',
                ),
                'timeout' => 300, // 5 minutes timeout for large files
                'stream' => true,
                'filename' => $temp_file,
            )
        );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'WPDP Download Error: ' . $response->get_error_message() );
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        
        if ( $status_code !== 200 ) {
            // Try to get error details from the response
            $body = wp_remote_retrieve_body( $response );
            
            // If streaming, the body might be in the file
            if ( empty( $body ) && file_exists( $temp_file ) ) {
                $body = file_get_contents( $temp_file, false, null, 0, 1000 );
            }
            
            error_log( 'WPDP Download Failed - Status: ' . $status_code . ', Body: ' . $body );
            
            // Clean up temp file on error
            if ( file_exists( $temp_file ) ) {
                @unlink( $temp_file );
            }
            
            return new WP_Error(
                'download_failed',
                sprintf( 'Failed to download file. Status: %d, Response: %s', $status_code, $body )
            );
        }
        
        // Get the file path from the response
        $file_path = $response['filename'];
        
        if ( ! file_exists( $file_path ) ) {
            error_log( 'WPDP: Downloaded file does not exist at: ' . $file_path );
            return new WP_Error( 'download_failed', 'Downloaded file does not exist.' );
        }
        
        $file_size = filesize( $file_path );
        error_log( 'WPDP: Successfully downloaded file. Size: ' . $file_size . ' bytes' );
        
        if ( $file_size === 0 ) {
            @unlink( $file_path );
            return new WP_Error( 'download_failed', 'Downloaded file is empty.' );
        }
        
        return $file_path;
    }

    public function download_and_upload_csv($temp_file, $post_id) {
    
        // Prepare file data for upload
        $post_title = get_the_title($post_id);
        $file_name = sanitize_file_name($post_title . '.csv');
        $file_array = array(
            'name'     => $file_name,
            'tmp_name' => $temp_file
        );
    
        // Set upload overrides
        $overrides = array(
            'test_form' => false,
            'test_size' => true,
        );
    
        // Upload the file to the media library
        $time = current_time('mysql');
        $file = wp_handle_sideload($file_array, $overrides, $time);
    
        if (isset($file['error'])) {
            @unlink($temp_file);
            return false;
        }
    
        // Prepare attachment data
        $attachment = array(
            'post_mime_type' => $file['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', $file_name),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
    
        // Insert attachment into the media library
        $attach_id = wp_insert_attachment($attachment, $file['file']);
    
        // Generate metadata for the attachment
        $attach_data = wp_generate_attachment_metadata($attach_id, $file['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        update_post_meta($post_id,'wpdp_last_file_url',$file['url']);
        return $attach_id;
    }
    


    public function save_presentation($post_id){
        global $wpdb;
        if(get_post_type($post_id) !== 'wp-data-presentation'){
            return;
        }

        if(get_post_status($post_id) !== 'publish'){
            return;
        }
        
        
        if($_POST['acf']['field_657aa840cb9c5'] === 'Acled URL'){
            $old_value = get_field('acled_url');
            $new_value = $_POST['acf']['field_66a2ceaad7f51'];
        }else{
            $old_value = (int)get_field('upload_excel_file');
            $new_value = (int)$_POST['acf']['field_657aa818cb9c4'];
        } 

        if($old_value === $new_value){
            return;
        }

        $this->create_data_table($post_id);

        $this->auto_select_mapping();

    }

    function auto_select_mapping(){
        // Auto select mapping.
        $mapping = get_field('incident_type_filter','option');
        if(empty($mapping)){
            return;
        }

        $db_columns = array(
            'disorder_type',
            'event_type',
            'sub_event_type'
        );
        $types = [];
        foreach($db_columns as $column){
            $types = array_merge($types,$this->get_db_column($column));
        }

        $changed = false;
        foreach($mapping as $k1 => $value){
            foreach($value as $k2 => $value2){
                foreach($value2 as $k3 => $value3){
                    $incident_type = $value3['type'];
                    if(is_array($value3[$incident_type]) && empty(array_filter($value3[$incident_type]))){
                        $result = $this->find_element($types, $value3['text']);

                        if($result === false){
                            return;
                        }

                        $mapping[$k1][$k2][$k3]['type'] = $this->find_type($result);
                        $mapping[$k1][$k2][$k3][$incident_type] = array($result);
                        $changed = true;
                    }
                }
            }
        }

        if($changed === true){
            update_field('incident_type_filter',$mapping,'option');
        }
    }

    function find_type($cat_value) {
        foreach (['sub_event_type', 'disorder_type', 'event_type'] as $type) {
            if (strpos($cat_value, $type) !== false) {
                return $type;
            }
        }
    }

    function find_element($array, $text, $return_key = false) {
        foreach ($array as $key => $element) {
            if (strpos(strtolower($element), strtolower($text)) !== false) {
                return ($return_key ? $key : $element);
            }
        }
        return null;
    }
    

    public function last_updated_field($field){
        $post_id = get_the_ID();
        if(get_field('import_file',$post_id) == '' || get_field('import_file',$post_id) === 'Upload'){
            return;
        }

        $url = get_field('acled_url',$post_id);
        $event_date = date('Y-m-d', strtotime('-1 year'));
        $url = remove_query_arg('event_date_where', $url);
        $url = add_query_arg('event_date', $event_date, $url);
        $url = add_query_arg('event_date_where', '>', $url);
        echo '
            <div class="wpdp_last_updated">
                <h3>'.date('d-m-Y H:i:s',get_post_meta($post_id,'wpdp_last_updated_date',true)).'</h3>
                <a href="'.get_post_meta($post_id,'wpdp_last_file_url',true).'" target="_blank" class="button button-primary">Local Server Copy</a>
                <a href="'.$url.'" target="_blank" class="button button-secondary">ACLED Copy</a>
            </div>
        ';
    }


    public function shortcode_box($field){
        echo '<div class="wpdp_shortcode">
        <input type="text" disabled value=" [WP_DATA_PRESENTATION]"> 
        <button class="button button-secondary wpdp_copy">Copy</button>
        </div>';
    }
    

    public function enqueue_scripts() {
        wp_enqueue_script(WP_DATA_PRESENTATION_NAME, WP_DATA_PRESENTATION_URL . 'assets/js/wp-data-presentation-admin.js', array('jquery'), WP_DATA_PRESENTATION_VERSION, false);
        wp_enqueue_style(WP_DATA_PRESENTATION_NAME, WP_DATA_PRESENTATION_URL . 'assets/css/wp-data-presentation-admin.css', false, WP_DATA_PRESENTATION_VERSION, false);

        wp_localize_script(WP_DATA_PRESENTATION_NAME, 'wpdp_obj', array( 'ajax_url' => admin_url('admin-ajax.php')));

    }



    public function my_acf_settings_url( $url ) {
        return WP_DATA_PRESENTATION_ACF_URL;
    }

    
    public function show_admin( $show_admin ) {
        return WP_DATA_PRESENTATION_ACF_SHOW;
    }


    public function update_acled_presentations() {
        $args = array(
            'post_type' => 'wp-data-presentation',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'import_file',
                    'value' => 'Acled URL',
                    'compare' => '='
                ),
                array(
                    'key' => 'include_in_cron_job_updates',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );

        $post_ids = get_posts( $args );
        
        if ( empty( $post_ids ) ) {
            error_log( 'WPDP Cron: No presentations found for update' );
            return;
        }
        
        $has_errors = false;
        $error_count = 0;
        $success_count = 0;
        $total_count = count( $post_ids );
        
        foreach ( $post_ids as $post_id ) {
            try {
                $this->create_data_table( $post_id, false );
                error_log( "WPDP Cron: Successfully updated ACLED presentation: " . $post_id );
                $success_count++;
            } catch ( Exception $e ) {
                $has_errors = true;
                $error_count++;
                error_log( sprintf( 
                    'WPDP Cron Error: Failed to update presentation ID %d (%s) - %s',
                    $post_id,
                    get_the_title( $post_id ),
                    $e->getMessage()
                ) );
            }
        }

        // Clear cache after updates
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpdp_cache_%'" );

        // Send email notification if there were any errors
        if ( $has_errors ) {
            wpdp_send_error_email( 
                'Cron Job Failed', 
                sprintf( 
                    'Cron job completed with %d error(s) out of %d total presentations. Successfully updated: %d',
                    $error_count,
                    $total_count,
                    $success_count
                )
            );
        } else {
            error_log( sprintf( 
                'WPDP Cron: Successfully updated all %d presentations',
                $total_count
            ) );
        }
    }

    public function add_custom_mime_types($mimes) {
        // Add CSV mime type
        $mimes['csv'] = 'text/csv';
        
        // Add Excel mime types
        $mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $mimes['xls'] = 'application/vnd.ms-excel';
        
        return $mimes;
    }

    public function custom_upload_filter($file) {
        $allowed_extensions = array('csv', 'xlsx', 'xls');
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            $file['error'] = 'File type not allowed. Please upload a CSV or Excel file.';
        }

        return $file;
    }


    public function process_yearly_data_update() {
        // Get the last processed date from options
        $last_processed_date = get_option('wpdp_last_processed_date');
        
        if (!$last_processed_date) {
            // First run - start with current date
            $this->api_event_date = current_time('Y-m-d');
        } else {
            // Continue from last processed date
            $this->api_event_date = $last_processed_date;
        }
        
        // Calculate new date range (3 months before the start date)
        $this->api_event_date = date('Y-m-d', strtotime($this->api_event_date . ' -3 months'));
        
        // Update the presentations for this period
        $this->update_acled_presentations();
        
        // Save the new date as last processed
        update_option('wpdp_last_processed_date', $this->api_event_date);
        
        // Count how many iterations we've done
        $iteration_count = get_option('wpdp_update_iteration_count', 0);
        $iteration_count++;
        update_option('wpdp_update_iteration_count', $iteration_count);
        
        // If we haven't done 4 iterations yet, schedule the next one with a small delay
        if ($iteration_count < 4) {
            // Schedule next run in 2 minutes
            wp_schedule_single_event(time() + (2 * MINUTE_IN_SECONDS), 'wpdp_weekly_yearly_data_update');
        } else {
            // Reset for next week
            delete_option('wpdp_last_processed_date');
            delete_option('wpdp_update_iteration_count');
        }
    }

}

WPDP_Metabox::get_instance();