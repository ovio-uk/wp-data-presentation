<?php

/**
 * View class
 *
 * @since 1.0.0
 */

final class WPDP_Shortcode {

    /**
     * Instance of this class.
     *
     * @since 1.0.0
     *
     * @var WPDP_Shortcode
     */
    protected static $_instance = null;

    public $shortcode_atts = [];

    public $search_location_country = '';

    /**
     * Get instance of the class
     *
     * @since 1.0.0
     *
     * @return WPDP_Shortcode
     */

    public static function get_instance() {

        // If the single instance hasn't been set, set it now.
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->_add_hooks();
        // Start the session if it hasn't been started yet
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $selected_country = $this->get_session_value( 'wpdp_search_location_country' );
        $number = 0;
        if( empty( $selected_country ) && !empty( get_option( 'wpdp_countries' ) ) ){
            foreach( get_option( 'wpdp_countries' ) as $key => $country ){
                if( !empty( $country ) ){
                    $country = str_replace( ' ', '-', strtolower( $country ) );
                    if( isset( $_SESSION['wpdp_session']['wpdp_'.$country] ) && !empty( $_SESSION['wpdp_session']['wpdp_'.$country] ) ){
                        $number++;
                        $session_country = explode( '__', $_SESSION['wpdp_session']['wpdp_'.$country] );
                        $selected_country = $session_country[0];
                    }
                }
            }
        }else{
            if( !empty( $selected_country ) ){
                $selected_country = explode( '__', $selected_country );
                $selected_country = $selected_country[0];
            }
        }
    
        if($number > 1){
            $selected_country = '';
        }

        $this->search_location_country = $selected_country;

    }

    /**
     * Add hooks
     *
     * @since 1.0.0
     */
    private function _add_hooks() {
        add_shortcode('WP_DATA_PRESENTATION', array($this, 'show_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_loader_html'));
        add_action('wp_ajax_save_filter_choices', array($this, 'save_filter_choices'));
        add_action('wp_ajax_nopriv_save_filter_choices', array($this, 'save_filter_choices'));
        add_action('wp_ajax_clear_filter_choices', array($this, 'clear_filter_choices'));
        add_action('wp_ajax_nopriv_clear_filter_choices', array($this, 'clear_filter_choices'));


        add_action('wp_ajax_search_location', array($this, 'search_location'));
        add_action('wp_ajax_nopriv_search_location', array($this, 'search_location'));

        add_action('wp_ajax_search_actor_names', array($this, 'search_actor_names'));
        add_action('wp_ajax_nopriv_search_actor_names', array($this, 'search_actor_names'));

        add_action('wp_ajax_get_locations_html', array($this, 'get_locations_html'));
        add_action('wp_ajax_nopriv_get_locations_html', array($this, 'get_locations_html'));

        add_action('wp_ajax_get_location_level', array($this, 'get_location_level'));
        add_action('wp_ajax_nopriv_get_location_level', array($this, 'get_location_level'));

        if(isset($_GET['test3'])){
            session_start();
            var_dump($_SESSION);exit;
        }
    }

    public function search_actor_names(){
        $search = $_REQUEST['search'];
        $actors = $this->get_actors_names($search);
        echo json_encode($actors);
        die();
    }


    public function search_location(){
        $search = $_REQUEST['search'];
        $locations = $this->get_locations($search);
        echo json_encode($locations);
        die();
    }

    public function get_locations($search){
        global $wpdb;

        $posts = get_posts(array(
            'post_type'      => 'wp-data-presentation',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        if (empty($posts)) {
            return [];
        }

        $locations = [];
        $unique_locations = [];
        foreach ($posts as $id) {
            $table_name   = $wpdb->prefix. 'wpdp_data_' . $id;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            if (!$table_exists) {
                continue;
            }

            if($this->search_location_country != ''){
                $country = sanitize_text_field($this->search_location_country);

                $query = "SELECT DISTINCT country, admin1, admin2, admin3, location FROM {$table_name} 
                          WHERE country = '{$country}' 
                      AND (admin1 LIKE '%".$search."%' 
                      OR admin2 LIKE '%".$search."%' 
                      OR admin3 LIKE '%".$search."%' 
                      OR location LIKE '%".$search."%')";
            }else{
                $query = "SELECT DISTINCT country, admin1, admin2, admin3, location FROM {$table_name} 
                WHERE country LIKE '%".$search."%' 
                OR admin1 LIKE '%".$search."%' 
                OR admin2 LIKE '%".$search."%' 
                OR admin3 LIKE '%".$search."%' 
                OR location LIKE '%".$search."%'";
            }
            $results = $wpdb->get_results($query, ARRAY_A);
            foreach ($results as $result) {
                $matched = false;
                foreach (['admin1', 'admin2', 'admin3', 'location'] as $field) {
                    if (stripos($result[$field], $search) !== false) {
                        $location_key = $result[$field];
                        if (!isset($unique_locations[$location_key])) {
                            $unique_locations[$location_key] = [
                                'country' => $result['country'],
                                'location' => $result[$field],
                                'id'=>$result['country'].'__country'.' + '.$result[$field].'__'.$field,
                            ];
                            $matched = true;
                        }
                        break;
                    }
                }
                if (!$matched && stripos($result['country'], $search) !== false) {
                    $location_key = $result['country'];
                    if (!isset($unique_locations[$location_key])) {
                        $unique_locations[$location_key] = [
                            'country' => $result['country'],
                            'location' => $result['country'],
                            'id'=>$result['country'].'__country',
                        ];
                    }
                }
            }
        }
        
        return array_values($unique_locations);
    }

    public function enqueue_scripts() {
        wp_register_script(WP_DATA_PRESENTATION_NAME . 'select2', WP_DATA_PRESENTATION_URL . 'assets/js/select2.min.js', array('jquery'), WP_DATA_PRESENTATION_VERSION, true);

        wp_register_style(WP_DATA_PRESENTATION_NAME . 'select2', WP_DATA_PRESENTATION_URL . 'assets/css/select2.min.css', [], WP_DATA_PRESENTATION_VERSION);

        wp_register_style(WP_DATA_PRESENTATION_NAME . 'public', WP_DATA_PRESENTATION_URL . 'assets/css/wp-data-presentation-public.css', [], WP_DATA_PRESENTATION_VERSION);

        wp_register_style(WP_DATA_PRESENTATION_NAME . 'jquery-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css', [], WP_DATA_PRESENTATION_VERSION);

        wp_register_script(WP_DATA_PRESENTATION_NAME . 'public', WP_DATA_PRESENTATION_URL . 'assets/js/wp-data-presentation-public.js', array('jquery', 'jquery-ui-datepicker'), WP_DATA_PRESENTATION_VERSION, true);

        wp_register_style(WP_DATA_PRESENTATION_NAME . 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', [], WP_DATA_PRESENTATION_VERSION);

        wp_localize_script(WP_DATA_PRESENTATION_NAME . 'public', 'wpdp_obj', [
            'url'      => WP_DATA_PRESENTATION_URL,
            'ajax_url' => admin_url('admin-ajax.php'),
            'search_info_icon'=>self::info_icon('Search and find a specific event by entering the event ID which can be found for each event in the database.',' position: absolute;top: 6px;right: 5px;'),
        ]);

        wp_register_script(WP_DATA_PRESENTATION_NAME . 'popper.min', WP_DATA_PRESENTATION_URL . 'assets/js/popper.min.js', [], WP_DATA_PRESENTATION_VERSION, true);
        wp_register_script(WP_DATA_PRESENTATION_NAME . 'tooltip', WP_DATA_PRESENTATION_URL . 'assets/js/tippy-bundle.umd.min.js',[], WP_DATA_PRESENTATION_VERSION, true);

    }

    public function add_loader_html() {
        ?>
        <!-- Loader HTML -->
        <div id="wpdp-loader" class="wpdp-loader" style="display:none;">
            <div class="loader">
                <div class="inner"></div>
            </div>
            <h1>Loading...</h1>
        </div>

    <?php }



    public static function get_date_format($date_sample, $graphs = false) {
        $date_formats = [
            'Y-m-d' => ['regex' => '/^\d{4}-\d{2}-\d{2}$/', 'mysql' => '%%Y-%%m-%%d'],
            'Y/m/d' => ['regex' => '/^\d{4}\/\d{2}\/\d{2}$/', 'mysql' => '%%Y/%%m/%%d'],
            'd-m-Y' => ['regex' => '/^\d{2}-\d{2}-\d{4}$/', 'mysql' => '%%d-%%m-%%Y'],
            'd/m/Y' => ['regex' => '/^\d{2}\/\d{2}\/\d{4}$/', 'mysql' => '%%d/%%m/%%Y'],
            'm-d-Y' => ['regex' => '/^\d{2}-\d{2}-\d{4}$/', 'mysql' => '%%m-%%d-%%Y'],
            'm/d/Y' => ['regex' => '/^\d{2}\/\d{2}\/\d{4}$/', 'mysql' => '%%m/%%d/%%Y'],
            'd F Y' => ['regex' => '/^\d{2} \w{3,9} \d{4}$/', 'mysql' => '%%d %%M %%Y']
        ];
    
        if($graphs){
            $date_formats = [
                'Y-m-d' => ['regex' => '/^\d{4}-\d{2}-\d{2}$/', 'mysql' => '%Y-%m-%d'],
                'Y/m/d' => ['regex' => '/^\d{4}\/\d{2}\/\d{2}$/', 'mysql' => '%Y/%m/%d'],
                'd-m-Y' => ['regex' => '/^\d{2}-\d{2}-\d{4}$/', 'mysql' => '%d-%m-%Y'],
                'd/m/Y' => ['regex' => '/^\d{2}\/\d{2}\/\d{4}$/', 'mysql' => '%d/%m/%Y'],
                'm-d-Y' => ['regex' => '/^\d{2}-\d{2}-\d{4}$/', 'mysql' => '%m-%d-%Y'],
                'm/d/Y' => ['regex' => '/^\d{2}\/\d{2}\/\d{4}$/', 'mysql' => '%m/%d/%Y'],
                'd F Y' => ['regex' => '/^\d{2} \w{3,9} \d{4}$/', 'mysql' => '%d %M %Y']
            ];
        }
    
        foreach ($date_formats as $php_format => $format_info) {
            if (preg_match($format_info['regex'], $date_sample)) {
                return [
                    'mysql'=>$format_info['mysql'],
                    'php'=>$php_format
                ];
            }
        }
    
        return false;
    }
    

    public static function get_filters() {
        $atts = self::get_instance()->shortcode_atts;
        if(empty($atts) && isset($_POST['atts'])){
            $atts = json_decode(stripslashes($_POST['atts']), true);
        }

        $posts = get_posts(array(
            'post_type'      => 'wp-data-presentation',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        if (empty($posts)) {
            return 'No data';
        }

        $arr_type = ARRAY_A;
        global $wpdb;
        $years = [];
        $ordered_locations = [];
        $countries = [];
        
        // Build combined queries
        $union_queries = [];
        $years_queries = [];
        $table_names = [];
        
        foreach ($posts as $id) {
            $table_name = $wpdb->prefix . 'wpdp_data_' . $id;
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                $table_names[] = $table_name;
                
                // Add years query
                $years_queries[] = "SELECT DISTINCT event_date FROM {$table_name}";
                
                // Add location queries
                if (self::get_instance()->search_location_country != '' && (isset($atts['type']) && $atts['type'] === 'map')) {
                    $country = sanitize_text_field(self::get_instance()->search_location_country);
                    $union_queries[] = $wpdb->prepare(
                        "SELECT DISTINCT admin1, admin2, admin3, location, %s as country 
                        FROM {$table_name} 
                        WHERE country = %s",
                        $country,
                        $country
                    );
                } elseif (!self::get_instance()->search_location_country && (isset($atts['type']) && $atts['type'] === 'map')) {
                    $union_queries[] = "SELECT DISTINCT country, NULL as admin1, NULL as admin2, NULL as admin3, NULL as location 
                                      FROM {$table_name}";
                } else {
                    $union_queries[] = "SELECT DISTINCT country, admin1, admin2, admin3, location 
                                      FROM {$table_name}";
                }
            }
        }

        if (empty($union_queries)) {
            return ['types' => $inc_type, 'years' => $years, 'locations' => []];
        }

        // Execute years query
        if (!empty($years_queries)) {
            $years_query = implode(' UNION ', $years_queries);
            $dates = $wpdb->get_col($years_query);
            if (!empty($dates)) {
                $years = array_unique($dates);
            }
        }

        // Execute locations query
        $query = implode(' UNION ', $union_queries) . ' ORDER BY country, admin1, admin2, admin3, location';
        $db_locations = $wpdb->get_results($query, ARRAY_A);

        // Process results
        if (self::get_instance()->search_location_country != '' && (isset($atts['type']) && $atts['type'] === 'map')) {
            // Process filtered country results
            foreach ($db_locations as $location) {
                $key_parts = [];
                foreach (['admin1', 'admin2', 'admin3', 'location'] as $level) {
                    if (!empty($location[$level])) {
                        $key = $location[$level] . '__' . $level;
                        $key_parts[] = $key;
                        
                        $current = &$ordered_locations;
                        foreach ($key_parts as $part) {
                            if (!isset($current[$part])) {
                                $current[$part] = [];
                            }
                            $current = &$current[$part];
                        }
                        unset($current);
                    }
                }
            }
        } elseif (!self::get_instance()->search_location_country && (isset($atts['type']) && $atts['type'] === 'map')) {
            // Process countries only
            foreach ($db_locations as $loc) {
                $country_key = $loc['country'] . '__country';
                $ordered_locations[$country_key] = [];
                $countries[] = $loc['country'];
            }
        } else {
            // Only process top level (countries or admin1 depending on context)
            foreach ($db_locations as $location) {
                if (!empty($location['country'])) {
                    $key = $location['country'] . '__country';
                    $ordered_locations[$key] = [];
                }
            }
        }

        if (!empty($countries)) {
            $countries = array_unique($countries);
            update_option('wpdp_countries', $countries);
        }

        usort($years, function ($a, $b) {
            return strtotime($a) - strtotime($b);
        });

        $mapping = get_field('incident_type_filter','option');
        $inc_type = [];
        foreach($mapping as $k1 => $value){
            foreach($value as $k2 => $value2){
                foreach($value2 as $k3 => $value3){
                    if($value3['hierarchial'] !== 'Level 1'){
                        continue;
                    }
                    $incident_type = $value3['type'].'_type';

                    if(!empty($value3[$incident_type])){
                        $inc_type[$value3['text']] = $value3[$incident_type];
                    }
                }
            }
        }

        if(!empty($ordered_locations)){
            $ordered_locations = self::sort_locations_array($ordered_locations);
        }

        return array(
            'types'     => $inc_type,
            'years'     => $years,
            'locations' => $ordered_locations,
        );
    }


    private static function sort_locations_array($array) {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                if (isset($value[0]) && is_string($value[0])) {
                    sort($value);
                } else {
                    $value = self::sort_locations_array($value);
                }
            }
        }
        return $array;
    }

    function printArrayAsList($locations, $level = 0, $parent_key = false) {
        $atts = self::get_instance()->shortcode_atts;
        if(empty($atts) && isset($_POST['atts'])){
            $atts = json_decode(stripslashes($_POST['atts']), true);
        }

        $input_type = (!self::get_instance()->search_location_country &&  $atts['type'] === 'map') ? 'radio' : 'checkbox';

        echo '<ul>';
        foreach ($locations as $key => $value) {
            $key_parts = explode('__', $key);
            $location_name = $key_parts[0];
            $location_type = $key_parts[1];
            $input_val = $parent_key !== false ? $parent_key . ' + ' . $key : $key;
            $checkbox_name = 'wpdp_' . sanitize_title($location_name);
            
            $is_checked = '';
            if (self::get_instance()->search_location_country !== '' && 
                self::get_instance()->search_location_country === $key_parts[0]) {
                $is_checked = 'checked';
            } elseif ($this->get_session_value($checkbox_name) === $input_val) {
                $is_checked = 'checked';
            }

            // Determine if this level can have children
            $can_have_children = false;
            switch ($location_type) {
                case 'country':
                    $can_have_children = true;
                    break;
                case 'admin1':
                    $can_have_children = true;
                    break;
                case 'admin2':
                    $can_have_children = true;
                    break;
                case 'admin3':
                    $can_have_children = true;
                    break;
                default:
                    $can_have_children = false;
            }

            echo '<li' . ($can_have_children ? ' class="expandable"' : '') . '>';
            
            if ($input_type === 'radio') {
                $checkbox_name = 'wpdp_country';
            }
            
            echo sprintf(
                '<input id="%s" type="%s" class="wpdp_filter_checkbox wpdp_location" name="%s" value="%s" %s>',
                $key,
                $input_type,
                $checkbox_name,
                $input_val,
                $is_checked
            );

            if ($can_have_children) {
                echo '<div class="exp_click">';
                if($input_type === 'checkbox'){
                    echo '<span>' . $location_name . '</span>';
                    echo '<span class="dashicons dashicons-arrow-down-alt2 arrow"></span>';
                }else{
                    echo '<label for="' . $key . '">' . $location_name . '</label>';
                }
                echo '</div>';
            } else {
                echo sprintf(
                    '<label class="%s1" for="%s">%s</label>',
                    $input_type,
                    $key,
                    $location_name
                );
            }
            
            echo '</li>';
        }
        echo '</ul>';
    }
    

    public function show_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => '',
            'from' => '',
            'to'   => '',
        ), $atts);


        $this->shortcode_atts = $atts;

        ob_start();
        wp_enqueue_script(WP_DATA_PRESENTATION_NAME . 'select2');
        wp_enqueue_style(WP_DATA_PRESENTATION_NAME . 'select2');
        wp_enqueue_style(WP_DATA_PRESENTATION_NAME . 'public');
        wp_enqueue_style(WP_DATA_PRESENTATION_NAME . 'font-awesome');
        wp_enqueue_script(WP_DATA_PRESENTATION_NAME . 'popper.min');
        wp_enqueue_script(WP_DATA_PRESENTATION_NAME . 'tooltip');
        wp_enqueue_script(WP_DATA_PRESENTATION_NAME . 'public');
        wp_enqueue_style(WP_DATA_PRESENTATION_NAME . 'jquery-ui');
        wp_enqueue_style('dashicons');

        ?>
        <script>
            var wpdp_shortcode_atts = <?php echo wp_json_encode($atts); ?>;
        </script>
        <?php

        ?>

        <div class="wpdp">
            <?php
        $filters = self::get_filters();
        $this->get_html_filter($filters, $atts);
        if (isset($atts['type']) && 'table' === $atts['type']) {
            WPDP_Tables::shortcode_output();
        } elseif (isset($atts['type']) && 'graph' === $atts['type']) {
            WPDP_Graphs::shortcode_output();
        } elseif (isset($atts['type']) && 'map' === $atts['type']) {
            WPDP_Maps::shortcode_output($atts);
        } else {
            WPDP_Tables::shortcode_output($atts);
            echo '<br><hr>';
            WPDP_Graphs::shortcode_output();
        }
        ?>
        </div>

        <script>
            var wpdp_shortcode_atts = '<?php echo json_encode($atts); ?>';
            var wpdp_filter_dates = <?php echo json_encode($filters['years']); ?>;
        </script>


    <?php

        $output = ob_get_clean();
        return $output;
    }

    function get_from_date_value($filters, $atts) {
        if (isset($this->shortcode_atts['from']) && '' != $this->shortcode_atts['from']) {
            return $this->shortcode_atts['from'];
        } else {
            return date('d F Y');
        }
    }

    function get_to_date_value($filters, $atts) {
        if (isset($this->shortcode_atts['from']) && '' != $this->shortcode_atts['from']) {
            return $this->shortcode_atts['from'];
        } else {
            return date('d F Y');
        }
    }

    public static function info_icon($content, $extra_css = ''  ){
        return'
            <span data-tippy-content="'.$content.'" style="cursor:pointer;color:#000;font-size:18px;'.$extra_css.'" class="tippy-icon dashicons dashicons-info"></span>
        ';
    }

    function get_select_unselect_all_html(){
        return '<ul>
                <li class="expandable ">
                    <a style="color: #006cff;" class="select_unselect_all" href="#">Select/Unselect All</a>
                </li>
            </ul>';
    }

    function get_civ_radio_html($text){
        return'
        	<div class="switch-field">
                <input type="radio" id="radio-one" name="target_civ" value="no" '.($this->get_session_value('target_civ') == 'no' || empty($this->get_session_value('target_civ')) ? 'checked' : '').'/>
                <label for="radio-one">All '.$text.'</label>
                <input type="radio" id="radio-two" name="target_civ" value="yes" '.($this->get_session_value('target_civ') == 'yes' ? 'checked' : '').'/>
                <label for="radio-two">Civilian Targeting</label>
            </div>
        ';
    }

    function get_actors_names($search){
        global $wpdb;
        $posts = get_posts(array(
            'post_type'      => 'wp-data-presentation',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        if (empty($posts)) {
            return [];
        }

        $actors = [];
        foreach ($posts as $id) {
            $table_name   = $wpdb->prefix. 'wpdp_data_' . $id;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            $actor_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'actor2'");

            if (!$table_exists) {
                continue;
            }

            if (!empty($actor_column_exists)) {
                $query = $wpdb->prepare(
                    "SELECT DISTINCT actor1 AS actor FROM {$table_name} WHERE actor1 LIKE %s
                     UNION
                     SELECT DISTINCT actor2 AS actor FROM {$table_name} WHERE actor2 LIKE %s
                     LIMIT 20",
                    '%' . $wpdb->esc_like($search) . '%',
                    '%' . $wpdb->esc_like($search) . '%'
                );
            } else {
                $query = $wpdb->prepare(
                    "SELECT DISTINCT actor1 AS actor FROM {$table_name} WHERE actor1 LIKE %s
                     LIMIT 20",
                    '%' . $wpdb->esc_like($search) . '%'
                );
            }
            
            $results = $wpdb->get_col($query);
            $actors = array_merge($actors, $results);
        }

        $unique_actors = array_unique($actors);
        sort($unique_actors);
        
        return array_map(function($actor) {
            return ['id' => $actor, 'text' => $actor];
        }, $unique_actors);
    }

    function get_locations_html(){
        $search_location_country = $_POST['search_location_country'];
        $this->search_location_country = $search_location_country;
        $locations = $this->get_filters()['locations'];
        $this->printArrayAsList($locations);
        wp_die();
    }

    function get_html_filter($filters, $atts) {
        ?>
        <div class="filter_data" style="display:none;">
            <a class="filter" href=""><span class="fas fa-arrow-left"></span></a>
            <div class="con">
                <form id="filter_form" action="" style="margin-top:15px;">


                    <div class="grp incident_type">

                        <div class="title">
                        EVENT TYPE <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                        <div class="content">
                            <?php echo $this->get_select_unselect_all_html();?>
                            <?php 
                                $filter = get_field('incident_type_filter','option');
                                foreach($filter as $filt){
                                    $hierarchy = $this->buildHierarchy($filt['filter']);
                                    echo $this->generateHierarchy($hierarchy);
                                }
                            ?>

                        </div>
                    </div>


                    <div class="grp fatalities ">

                        <div class="title">
                            FATALITIES <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                        <div class="content">
                            <?php echo $this->get_select_unselect_all_html();?>
                            <?php 
                                $filter = get_field('fatalities_filter','option');
                                foreach($filter as $filt){
                                    $hierarchy = $this->buildHierarchy($filt['filter']);
                                    echo $this->generateHierarchy($hierarchy,'fat');
                                }
                            ?>

                        </div>
                    </div>


                    <div class="grp actors">

                        <div class="title">
                        Types of Actors <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                        <div class="content">

                            <?php echo $this->get_select_unselect_all_html();?>

                            <?php 
                                $filter = get_field('actor_filter','option');
                                foreach($filter as $filt){
                                    $hierarchy = $this->buildHierarchy($filt['filter']);
                                    echo $this->generateHierarchy($hierarchy,'actors');
                                }
                            ?>

                        </div>
                    </div>

                    <div class="grp actors_names">

                        <div class="title">
                            ACTORS NAMES <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                        <div class="content">
                            <div>
                                <select name="wpdp_search_actors" id="wpdp_search_actors" multiple="multiple">
                                <?php
                                $selected_actors = $this->get_session_value('search_actors', []);

                                if (!empty($selected_actors)) {
                                    foreach ($selected_actors as $actor) {
                                        echo '<option value="' . esc_attr($actor) . '" selected>' . esc_html($actor) . '</option>';
                                    }
                                }
                                ?>
                                </select>
                            </div>

                        </div>
                    </div>



                    <div class="grp civ_targeting ">

                        <div class="title">
                            CIVILIAN TARGETING <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                        <div class="content">
                            <?php echo $this->get_civ_radio_html('Events');?>
                            <p class="civ_targeting_info">Toggle to switch between seeing all events, or only events that were recorded as targeting civilians.</p>
                        </div>
                    </div>

                    <?php  if($this->search_location_country == '' && (isset($atts['type']) && $atts['type'] === 'map')){ ?>
                        <style>
                            .wpdp .content .wpdp_maps_only{
                                display: none;
                            }
                        </style>
                    <?php } ?>

                    <div class="grp locations">
                        <div class="title">
                            LOCATION <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                        <div class="content">
                            <?php if($atts['type'] === 'map'){ ?>
                                <input type="hidden" name="wpdp_search_location_country" value="<?php echo $this->search_location_country; ?>">
                                <a class="view_countries wpdp_maps_only" href="#"><span class="dashicons dashicons-arrow-left-alt2"></span> View Countries</a>
                       
                            <?php } ?>
                            
                            <div class="wpdp_search_location wpdp_maps_only">
                                <select name="wpdp_search_location" id="wpdp_search_location" multiple="multiple">
                                <?php
                                $selected_locations = $this->get_session_value('search_location', []);

                                if (!empty($selected_locations)) {
                                    foreach ($selected_locations as $location) {
                                        if(strpos($location,'+') !== false){
                                            $text = explode('+',$location);
                                            $first = explode('__',$text[1]);
                                            $second = explode('__',$text[0]);
                                            $final_text = $first[0].' ('.$second[0].')';
                                        }else{
                                            $text = explode('__',$location);
                                            $final_text = $text[0];
                                        }
                                        echo '<option value="' . esc_attr($location) . '" selected>' . esc_html($final_text) . '</option>';
                                    }
                                }
                                ?>
                                </select>
                                <br>
                                <?php echo $this->get_select_unselect_all_html(); ?>
                            </div>
                            <div class="checkboxes_locations">

                                <?php  $this->printArrayAsList($filters['locations']);?>
                            </div>
                        </div>
                    </div>

                    <div class="grp">

                        <div class="title">
                            DATE RANGE <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                        <div class="content <?php echo (isset($atts['type']) && 'map' === $atts['type'] ? 'filter_maps' : ''); ?>">
                            <div class="dates">
                                <label for="wpdp_from">FROM</label>
                                <input value="<?php 
                                    $last_date = end($filters['years']);
                                    if (empty($this->get_session_value('wpdp_from'))) {
                                        if('map' === $atts['type']){
                                            echo date('d F Y', strtotime('-30 days', strtotime($last_date)));
                                        }else{
                                            echo date('d F Y', strtotime('-1 year', strtotime($last_date)));
                                        }
                                    } else {
                                        echo $this->get_session_value('wpdp_from', $this->get_from_date_value($filters, $atts));
                                    }
                                ?>" type="text" name="wpdp_from" id="wpdp_from">
                            </div>
                            <div class="dates">
                                <label style="margin-right: 23px;" for="wpdp_to">TO</label>
                                <input value="<?php 
                                    if (empty($this->get_session_value('wpdp_to'))) {
                                        echo date('d F Y', strtotime($last_date));
                                    } else {
                                        echo $this->get_session_value('wpdp_to', $this->get_to_date_value($filters, $atts));
                                    }
                                ?>" type="text" name="wpdp_to" id="wpdp_to">
                            </div>


                            <div class="date-info">
                                <span>Last data entry: <?php echo date('d F Y', strtotime($last_date)); ?></span>
                                <?php echo self::info_icon('The last data entry from all available data in the database is '.date('d F Y', strtotime($last_date)).'.',' position: relative;top: 3px;right: 0;'); ?>
                            </div>

                            <?php if ('graph' === $atts['type'] || '' == $atts['type']) {?>
                            <div class="dates">
                                <label for="wpdp_date_timeframe">Timeframe</label>
                                <select name="wpdp_date_timeframe" id="wpdp_date_timeframe">
                                    <option value="">Graph Timeframe</option>
                                    <option value="yearly" <?php selected($this->get_session_value('date_timeframe'), 'yearly'); ?>>Yearly</option>
                                    <option value="monthly" <?php selected($this->get_session_value('date_timeframe'), 'monthly'); ?>>Monthly</option>
                                    <option value="weekly" <?php selected($this->get_session_value('date_timeframe'), 'weekly'); ?>>Weekly</option>
                                    <option value="daily" <?php selected($this->get_session_value('date_timeframe'), 'daily'); ?>>Daily</option>
                                </select>
                            </div>
                            <?php } ?>
                        </div>

                    </div>



                    <div class="no_data" style="display:none;">No data found, please adjust filters</div>
                    <input type="submit" value="Apply Filters">
                    <div class="wpdp_clear"><input type="reset" value="Reset Filters"></div>
                </form>
            </div>
        </div>
    <?php

    }

    function get_value_from_incident_type($array){
        if(empty($array)){
            return [];
        }
        $value = [];
        $incidents = get_field('incident_type_filter','option');
        foreach($incidents as $incident){
            foreach($incident['filter'] as $filter){
                $type = $filter['type'];
                $type_value = $filter[$type];

                foreach($array as $ar_value){
                    if($ar_value === $filter['text']){
                        $value = array_merge($value,$type_value);
                    }
                }
            }

        }

        return $value;
    }

    public static function check_if_wpdp_session_exist(){
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $exist = false;
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'wpdp_') !== false) {
                $exist = true;
            }
        }
        return $exist;
    }

    function generateHierarchy($array, $not_incident = false, $first = 1) {
        $html = '<ul class="'.($first == 1 ? 'first_one' : '').'">';
        $class = 'wpdp_incident_type';
        foreach ($array as $item) { 
            $level = substr($item['hierarchial'], -1);
            if ($not_incident) {
                $class = 'wpdp_actors';
                $value = (isset($item['actor_code'])  ? $item['actor_code'] : '');
                if($not_incident === 'fat'){
                    $value = $this->get_value_from_incident_type($item['mapping_to_incident']);
                    $class = 'wpdp_fat';
                }
            } else {
                $type = $item['type'] ?? '';
                $value = $item[$type];
            }
    
            $checkbox_name = sanitize_title($item['text']);
            $checkbox_value = implode('+', $value);

            $is_checked = $this->get_session_value($checkbox_name) === $checkbox_value ? 'checked="checked"' : '';
            if(!self::check_if_wpdp_session_exist()){
                $is_checked = 'checked="checked"';
            }

            $html .= '<li class="expandable">';
            $html .= '<input label_value="'.strtolower($item['text']).'" class="wpdp_filter_checkbox '.$class.' level_'.$level.'" type="checkbox" name="'.$checkbox_name.'" value="'.$checkbox_value.'" '.$is_checked.'>';
            $html .= '<div class="exp_click"><span>' . $item['text'] . '</span><span class="dashicons arrow dashicons-arrow-down-alt2"></span></div>';
            
            if (isset($item['children']) && !empty($item['children'])) {
                $html .= $this->generateHierarchy($item['children'], $not_incident, 0);
            }
            
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }
    
    
    function buildHierarchy($flatArray) {
        $hierarchy = [];
        $levels = [];
        if(is_array($flatArray) && !empty($flatArray)){
            foreach ($flatArray as $item) {
                $level = substr($item['hierarchial'], -1);
                $item['children'] = [];
        
                if ($level == 1) {
                    $hierarchy[] = $item;
                    $levels[1] = &$hierarchy[count($hierarchy) - 1];
                } else {
                    $levels[$level - 1]['children'][] = $item;
                    $levels[$level] = &$levels[$level - 1]['children'][count($levels[$level - 1]['children']) - 1];
                }
            }
        }
    
        return $hierarchy;
    }

    public function get_session_value($key, $default = '') {
        return isset($_SESSION['wpdp_session'][$key]) ? $_SESSION['wpdp_session'][$key] : $default;
    }

    public function save_filter_choices() {
        if (!isset($_POST['filter_data'])) {
            wp_send_json_error('No filter data received');
        }

        if(!empty($_SESSION['wpdp_session'])){
            foreach($_SESSION['wpdp_session'] as $key => $value){
                if($key === 'wpdp_from' || $key === 'wpdp_to'){
                    continue;
                }
                unset($_SESSION['wpdp_session'][$key]);
            }
        }

        $filter_data = $_POST['filter_data'];

        foreach ($filter_data as $key => $value) {
            $_SESSION['wpdp_session'][$key] = $value;
        }

        wp_send_json_success('Filter choices saved');
    }

    public function get_location_level() {
        if (!isset($_POST['parent_key'])) {
            wp_send_json_error('Missing parent key');
        }

        $parent_key = sanitize_text_field($_POST['parent_key']);
        $parent_parts = explode('+',$parent_key);
        foreach($parent_parts as $parent_part){
            $parts = explode('__', $parent_part);
            $parent_value = trim($parts[0]);
            $parent_type = $parts[1];
        }

        global $wpdb;
        $locations = [];
        
        // Define the hierarchy of levels
        $level_hierarchy = [
            'country' => ['admin1', 'admin2', 'admin3', 'location'],
            'admin1' => ['admin2', 'admin3', 'location'],
            'admin2' => ['admin3', 'location'],
            'admin3' => ['location']
        ];

        // Get the starting level based on parent type
        $current_level_index = array_search($parent_type, array_keys($level_hierarchy));
        if ($current_level_index === false) {
            wp_send_json_error('Invalid parent type');
        }

        // Try each remaining level until we find results
        $levels_to_try = $level_hierarchy[$parent_type];
        $found_level = null;
        
        foreach ($levels_to_try as $column) {
            $locations = [];
            
            $posts = get_posts(array(
                'post_type' => 'wp-data-presentation',
                'posts_per_page' => -1,
                'fields' => 'ids',
            ));

            foreach ($posts as $id) {
                $table_name = $wpdb->prefix . 'wpdp_data_' . $id;
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                    $query = $wpdb->prepare(
                        "SELECT DISTINCT {$column} FROM {$table_name} WHERE {$parent_type} = %s AND {$column} IS NOT NULL AND {$column} != ''",
                        $parent_value
                    );

                    $results = $wpdb->get_col($query);
                    $locations = array_merge($locations, $results);
                }
            }

            $locations = array_unique($locations);
            sort($locations);

            // If we found locations at this level, break the loop
            if (!empty($locations)) {
                $found_level = $column;
                break;
            }
        }

        // If no locations were found at any level
        if (empty($locations)) {
            wp_send_json_success('<ul><li>No locations found</li></ul>');
        }

        // Generate HTML for the found level
        ob_start();
        echo '<ul>';
        foreach ($locations as $location) {
            if (trim($location) === '') {
                continue;
            }

            $key = $location . '__' . $found_level;
            $checkbox_name = 'wpdp_' . sanitize_title($location);
            $input_value = $parent_key . ' + ' . $key;
            $is_checked = $this->get_session_value($checkbox_name) === $input_value ? 'checked="checked"' : '';

            $has_children = $found_level !== 'location';

            echo '<li' . ($has_children ? ' class="expandable"' : '') . '>';
            echo '<input type="checkbox" class="wpdp_filter_checkbox wpdp_location" name="' . $checkbox_name . '" value="' . $input_value . '" ' . $is_checked . '>';
            
            if ($has_children) {
                echo '<div class="exp_click"><span>' . $location . '</span>';
                echo '<span class="dashicons dashicons-arrow-down-alt2 arrow"></span></div>';
            } else {
                echo '<label for="' . $checkbox_name . '">' . $location . '</label>';
            }
            
            echo '</li>';
        }
        echo '</ul>';
        
        wp_send_json_success(ob_get_clean());
    }

}

WPDP_Shortcode::get_instance();
