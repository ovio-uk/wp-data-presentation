<?php 
/**
 * View class
 *
 * @since 1.0.0
 */

final class WPDP_Graphs {

    /**
     * Instance of this class.
     *
     * @since 1.0.0
     *
     * @var WPDP_Graphs
     */
    protected static $_instance = null;

    /**
     * Get instance of the class
     *
     * @since 1.0.0
     *
     * @return WPDP_Graphs
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
    }

    /**
     * Add hooks
     *
     * @since 1.0.0
     */
    private function _add_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_action( 'wp_ajax_nopriv_wpdp_graph_request', array($this,'get_graph_data') );
        add_action( 'wp_ajax_wpdp_graph_request', array($this,'get_graph_data') );

        
        add_action('init',array($this,'clear_cache'));


    }

    public function clear_cache(){
        if(!isset($_REQUEST['wpdp_clear_cache'])){
            return;
        }

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpdp_cache_%'");
    }

    public function get_graph_data(){
        $types = [
            'country',
            'disorder_type',
            'event_date',
            'fatalities',
        ];

        $filters = [
            'disorder_type' => isset($_REQUEST['type_val']) ? array_filter($_REQUEST['type_val']) : [],
            'locations' => isset($_REQUEST['locations_val']) ? $_REQUEST['locations_val'] : [],
            'from' => isset($_REQUEST['from_val']) ? $_REQUEST['from_val'] : '',
            'to' => isset($_REQUEST['to_val']) ? $_REQUEST['to_val'] : '',
            'timeframe' => isset($_REQUEST['timeframe']) ? $_REQUEST['timeframe'] : '',
            'actors' => isset($_REQUEST['actors_val']) ? $_REQUEST['actors_val'] : [],
            'fatalities' => isset($_REQUEST['fat_val']) ? array_filter($_REQUEST['fat_val']) : [],
            'actor_names' => isset($_REQUEST['actor_names_val']) ? $_REQUEST['actor_names_val'] : '',
            'target_civ' => isset($_REQUEST['target_civ']) ? $_REQUEST['target_civ'] : ''
        ];
        
        $merged_types = [];
        $new_actors = [];

        foreach (array_merge($filters['disorder_type'], $filters['fatalities']) as $value) {
            $parts = explode('+', $value);
            foreach ($parts as $part) {
                if (!in_array($part, $merged_types)) {
                    $merged_types[] = $part;
                }
            }
        }

        $inter_labels = wpdp_get_actors();
        
        if(count($inter_labels) === count($filters['actors'])){
            $filters['actors'] = [];
        }

        $filters['merged_types'] = $merged_types;
        
        if(empty($filters['actors'])){
            // merge array keys and array values
            $filters['actors'] = array_merge(array_keys($inter_labels), array_values($inter_labels));
        }else{
            foreach($filters['actors'] as $actor){
                $value_parts = explode('+', $actor);
                foreach ($value_parts as $part) {
                    if (!in_array($part, $new_actors)) {
                        $new_actors[] = $part;
                        $new_actors[] = $inter_labels[$part];
                    }
                }
            }
    
            if(in_array('No recorded actors', $new_actors)){
                $new_actors[] = '';
            }

            $filters['actors'] = array_unique($new_actors);
        }


        $data = $this->get_data($filters,$types);

        wp_send_json_success($data);
    }

    
    function enqueue_scripts() {
        
        wp_register_script(WP_DATA_PRESENTATION_NAME.'chartjs', WP_DATA_PRESENTATION_URL.'assets/js/chart.js', array('jquery'), WP_DATA_PRESENTATION_VERSION, true);
        
        wp_register_script(WP_DATA_PRESENTATION_NAME.'chartjs-adapter', WP_DATA_PRESENTATION_URL.'assets/js/chartjs-adapter-date-fns.bundle.min.js', array('jquery'), WP_DATA_PRESENTATION_VERSION, true);
        wp_register_script(WP_DATA_PRESENTATION_NAME.'chartjs-moment', WP_DATA_PRESENTATION_URL.'assets/js/moment.min.js', array('jquery'), WP_DATA_PRESENTATION_VERSION, true);
    }
    
    public function build_where_clause($filters, $date_format, $column_exists,$actor_column_exists) {
        $whereSQL = ' WHERE 1=1 ';
        $mysql_date_format = $date_format['mysql'];
        $filter_format_from = date($date_format['php'],strtotime($filters['from']));
        $filter_format_to = date($date_format['php'],strtotime($filters['to']));

        if($filters['from'] != ''){
            $whereSQL .= " AND STR_TO_DATE(event_date, '$mysql_date_format') >= STR_TO_DATE('{$filter_format_from}', '$mysql_date_format')";
        }

        if($filters['to'] != ''){
            $whereSQL .= " AND STR_TO_DATE(event_date, '$mysql_date_format') <= STR_TO_DATE('{$filter_format_to}', '$mysql_date_format')";
        }



        if(!empty($filters['actor_names'])){
            $conditions = [];
            foreach ($filters['actor_names'] as $value) {
                $value_parts = explode('+', $value);
                foreach ($value_parts as $part) {
                    $conditions[] = "actor1 = '" . esc_sql($part) . "'";
                    if($actor_column_exists){
                        $conditions[] = "actor2 = '" . esc_sql($part) . "'";
                    }
                }
            }
            
            $whereSQL .= " AND (" . implode(' OR ', $conditions) . ")";
        }


        if(!empty($filters['target_civ'])){
            if($filters['target_civ'] == 'yes'){
                $whereSQL .= " AND (civilian_targeting != '') ";
            }
        }


        if(!empty($filters['disorder_type']) || !empty($filters['fatalities'])) {
            $whereSQL .= " AND (";
            $conditions_added = false;

            // Disorder type conditions
            if(!empty($filters['disorder_type'])) {
                $values_by_column = [];
                foreach ($filters['disorder_type'] as $value) {
                    $value_parts = explode('+', $value);
                    foreach ($value_parts as $part) {
                        list($val, $col) = explode('__', $part);
                        $values_by_column[$col][] = "'" . esc_sql($val) . "'";
                    }
                }
                
                $conditions = [];
                foreach ($values_by_column as $column => $values) {
                    $conditions[] = "$column IN (" . implode(',', $values) . ")";
                }
                $whereSQL .= "(" . implode(' OR ', $conditions) . ")";
                $conditions_added = true;
            }

            // Fatalities conditions
            if(!empty($filters['fatalities'])) {
                if($conditions_added) {
                    $whereSQL .= " OR ";
                }
                $values_by_column = [];
                foreach ($filters['fatalities'] as $value) {
                    $value_parts = explode('+', $value);
                    foreach ($value_parts as $part) {
                        list($val, $col) = explode('__', $part);
                        $values_by_column[$col][] = "'" . esc_sql($val) . "'";
                    }
                }
                
                $conditions = [];
                foreach ($values_by_column as $column => $values) {
                    $conditions[] = "$column IN (" . implode(',', $values) . ")";
                }
                $whereSQL .= "( fatalities > 0 AND (" . implode(' OR ', $conditions) . "))";
            }

            $whereSQL .= ")";
        }

        if(!empty($filters['actors'])) {
            $actor_values = [];
            foreach ($filters['actors'] as $value) {
                $actor_values = array_merge($actor_values, explode('+', $value));
            }
            
            // Check if "No recorded actors" is present
            $no_recorded_actors_values = ['No recorded actors', '0', ''];
            $has_no_recorded_actors = false;
            $other_actor_values = [];
            
            foreach ($actor_values as $value) {
                if (in_array($value, $no_recorded_actors_values)) {
                    $has_no_recorded_actors = true;
                } else {
                    $other_actor_values[] = $value;
                }
            }
            
            $whereSQL .= " AND (";
            $conditions = [];
            
            // Handle "No recorded actors" with AND logic if present
            if ($has_no_recorded_actors) {
                $no_recorded_escaped = array_map(function($v) {
                    return "'" . esc_sql($v) . "'";
                }, $no_recorded_actors_values);
                
                $no_recorded_condition = "(inter1 IN (" . implode(',', $no_recorded_escaped) . ")";
                
                if ($column_exists) {
                    $no_recorded_condition .= " AND inter2 IN (" . implode(',', $no_recorded_escaped) . ")";
                }
                $no_recorded_condition .= ")";
                $conditions[] = $no_recorded_condition;
            }
            
            // Handle other actors with OR logic if present
            if (!empty($other_actor_values)) {
                $other_escaped = array_map(function($v) {
                    return "'" . esc_sql($v) . "'";
                }, array_unique($other_actor_values));
                
                $other_condition = "(inter1 IN (" . implode(',', $other_escaped) . ")";
                
                if ($column_exists) {
                    $other_condition .= " OR inter2 IN (" . implode(',', $other_escaped) . ")";
                }
                $other_condition .= ")";
                $conditions[] = $other_condition;
            }
            
            // Combine conditions with OR
            $whereSQL .= implode(' OR ', $conditions);
            $whereSQL .= ")";
        }
 

        if(!empty($filters['locations'])){
            $whereSQL .= ' AND (';
            $loci = 0;
            foreach($filters['locations'] as $value){ $loci++;

                $conditions = array();
                if(strpos($value,'+') !== false){
                    $value = explode(' + ',$value);
                    foreach($value as $v){
                        $real_v = explode('__',$v);
                        $column = $real_v[1];
                        $real_value = $real_v[0];
                        $conditions[] = "$column = '{$real_value}'";
                    }
                }else{
                    $real_v = explode('__',$value);
                    $column = $real_v[1];
                    $real_value = $real_v[0];
                    $conditions[] = "$column = '{$real_value}'";
                }
                $whereSQL .= " (".implode(' AND ', $conditions).")";
                if($loci !== count($filters['locations'])){
                    $whereSQL.= ' OR ';
                }
            }
            $whereSQL .= ')';
        }

        return $whereSQL;

    }

    private function get_sql_type($filters, $all_filters){
        if($filters['from'] != '' || $filters['to'] != ''){
            $all_dates = $all_filters['years'];
            $filters['from'] = ($filters['from'] ? $filters['from'] : $all_dates[0]);
            $filters['to'] = ($filters['to'] ? $filters['to'] : end($all_dates));

            $date1 = date_create($filters['from']);
            $date2 = date_create($filters['to']);
            $diff = date_diff($date1, $date2);
            $days = intval($diff->format('%a'));

            if($filters['timeframe'] != ''){
                // Handle custom timeframe selection
                if($filters['timeframe'] == 'yearly'){
                    $sql_type = 'YEAR';
                    $chart_sql = 'year';
                    $interval = 1;
                }
                elseif($filters['timeframe'] == 'monthly'){
                    $sql_type = 'MONTH';
                    $chart_sql = 'month';
                    $interval = 1;
                }
                elseif($filters['timeframe'] == 'weekly'){
                    $sql_type = 'YEARWEEK';
                    $chart_sql = 'week';
                    $interval = 1;
                }
                elseif($filters['timeframe'] == 'daily'){
                    // Auto timeframe based on date range
                    if($days <= 30) { // Up to 1 month
                        $sql_type = 'DAY';
                        $chart_sql = 'day';
                        $interval = 1;
                    }
                    elseif($days <= 60) { // Up to 2 months
                        $sql_type = 'DAY';
                        $chart_sql = 'day';
                        $interval = 2;
                    }
                    elseif($days <= 90) { // Up to 3 months
                        $sql_type = 'DAY';
                        $chart_sql = 'day';
                        $interval = 3;
                    }
                    elseif($days <= 150) { // Up to 5 months
                        $sql_type = 'DAY';
                        $chart_sql = 'day';
                        $interval = 5;
                    }
                    elseif($days <= 180) { // Up to 6 months
                        $sql_type = 'DAY';
                        $chart_sql = 'day';
                        $interval = 6;
                    }
                    elseif($days <= 270) { 
                        $sql_type = 'YEARWEEK';
                        $chart_sql = 'week';
                        $interval = 1;
                    }elseif($days <= 730) { 
                        $sql_type = 'MONTH';
                        $chart_sql = 'month';
                        $interval = 1;
                    }elseif($days <= 2190) { 
                        $sql_type = 'QUARTER';
                        $chart_sql = 'quarter';
                        $interval = 1;
                    }
                    else {
                        $sql_type = 'YEAR';
                        $chart_sql = 'year';
                        $interval = 1;
                    }
                }
            }else{
                if($days <= 30) { // Up to 1 month
                    $sql_type = 'DAY';
                    $chart_sql = 'day';
                    $interval = 1;
                }
                elseif($days <= 60) { // Up to 2 months
                    $sql_type = 'DAY';
                    $chart_sql = 'day';
                    $interval = 2;
                }
                elseif($days <= 90) { // Up to 3 months
                    $sql_type = 'DAY';
                    $chart_sql = 'day';
                    $interval = 3;
                }
                elseif($days <= 150) { // Up to 5 months
                    $sql_type = 'DAY';
                    $chart_sql = 'day';
                    $interval = 5;
                }
                elseif($days <= 180) { // Up to 6 months
                    $sql_type = 'DAY';
                    $chart_sql = 'day';
                    $interval = 6;
                }
                elseif($days <= 270) { 
                    $sql_type = 'WEEK';
                    $chart_sql = 'week';
                    $interval = 1;
                }
                elseif($days <= 270) { 
                    $sql_type = 'WEEK';
                    $chart_sql = 'week';
                    $interval = 1;
                }elseif($days <= 730) { 
                    $sql_type = 'MONTH';
                    $chart_sql = 'month';
                    $interval = 1;
                }elseif($days <= 2190) { 
                    $sql_type = 'QUARTER';
                    $chart_sql = 'quarter';
                    $interval = 1;
                }
                else {
                    $sql_type = 'YEAR';
                    $chart_sql = 'year';
                    $interval = 1;
                }
            }
        }

        return array(
            'sql_type' => $sql_type,
            'chart_sql' => $chart_sql,
            'interval' => $interval,
            'days' => $days
        );
    }

    public function get_data($filters,$types) {
        $posts = get_posts(array(
            'post_type' => 'wp-data-presentation',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
    
        if(empty($posts)){
            return 'No data';
        }

        global $wpdb;
        $agg = [];
        $most_recent_date = null;
        $most_recent_fatal_date = null;
        $all_filters = WPDP_Shortcode::get_filters();
        $sql_type_info = $this->get_sql_type($filters, $all_filters);
        $chart_sql = $sql_type_info['chart_sql'];
        $sql_type = $sql_type_info['sql_type'];
        $intervals = $sql_type_info['interval'];
        $days = $sql_type_info['days'];
        $column_exists_arr = [];
   
        $inter_labels = wpdp_get_actors();

        $data_actors = [];
        $test = [];
        foreach($posts as $id){
            $table_name = $wpdb->prefix. 'wpdp_data_'.$id;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            if(!$table_exists){
                continue;
            }

            $date_sample = $wpdb->get_var("SELECT event_date FROM $table_name LIMIT 1");
            if($date_sample == ''){
                continue;
            }
            $date_format = WPDP_Shortcode::get_date_format($date_sample,true);
            $mysql_date_format = $date_format['mysql'];
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'inter2'");
            $actor_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'actor2'");
            $whereSQL = $this->build_where_clause($filters, $date_format, $column_exists,$actor_column_exists);


            $sql = "SELECT 
            SUM(fatalities) as fatalities_count,
            COUNT(*) as events_count,
            disorder_type, 
            event_type, 
            sub_event_type,
            MAX(STR_TO_DATE(event_date, '$mysql_date_format')) as last_event_date,
            MAX(IF(fatalities > 0, STR_TO_DATE(event_date, '$mysql_date_format'), NULL)) as last_fatal_event_date,


            CASE 
                WHEN '{$sql_type}' = 'YEARWEEK' THEN DATE_FORMAT(STR_TO_DATE(CONCAT(YEARWEEK(STR_TO_DATE(event_date, '$mysql_date_format')), ' Sunday'), '%X%V %W'), '%Y-%m-%d')
                WHEN '{$sql_type}' = 'MONTH' THEN DATE_FORMAT(STR_TO_DATE(event_date, '$mysql_date_format'), '%Y-%m-01')
                WHEN '{$sql_type}' = 'YEAR' THEN DATE_FORMAT(STR_TO_DATE(event_date, '$mysql_date_format'), '%Y-01-01')
                WHEN '{$sql_type}' = 'QUARTER' THEN CONCAT(YEAR(STR_TO_DATE(event_date, '$mysql_date_format')), '-', LPAD(QUARTER(STR_TO_DATE(event_date, '$mysql_date_format')) * 3 - 2, 2, '0'), '-01')
                ELSE DATE_FORMAT(STR_TO_DATE(event_date, '$mysql_date_format'), '%Y-%m-%d')
            END as sql_date
            FROM {$table_name} {$whereSQL} 
            GROUP BY sql_date, disorder_type, event_type, sub_event_type
            ";

            $transient_key = md5($sql);
            $results = get_transient('wpdp_cache_'.$transient_key);
            if(empty($results) || WP_DATA_PRESENTATION_DISABLE_CACHE){
                $results = $wpdb->get_results($sql, ARRAY_A);
                set_transient('wpdp_cache_'.$transient_key, $results);
            }

            if(!empty($results)){
                // Calculate grouping interval if days > 150
                $group_interval = ($days < 270) ? ceil($days / 60) : 1;
                
                foreach($results as $res){

                    // Update the most recent date
                    if (is_null($most_recent_date) || strtotime($res['last_event_date']) > strtotime($most_recent_date)) {
                        $most_recent_date = $res['last_event_date'];
                    }

                    // Track the most recent fatal event date
                    if (is_null($most_recent_fatal_date) || (!is_null($res['last_fatal_event_date']) && strtotime($res['last_fatal_event_date']) > strtotime($most_recent_fatal_date))) {
                        $most_recent_fatal_date = $res['last_fatal_event_date'];
                    }


                    foreach($filters['merged_types'] as $type){
                        $type = explode('__',$type);
                        $value = $type[0];
                        $type = $type[1];

                        // Get the date index for grouping
                        $date_timestamp = strtotime($res['sql_date']);
                        $start_timestamp = strtotime($filters['from'] ?: $res['sql_date']);
                        $days_diff = floor(($date_timestamp - $start_timestamp) / (60 * 60 * 24));
                        $group_index = floor($days_diff / $group_interval);
                        $group_date = date('Y-m-d', strtotime($filters['from'] ?: $res['sql_date']) + ($group_index * $group_interval * 24 * 60 * 60));

                        // Handle disorder_type aggregation
                        if($type === 'disorder_type' && $res['disorder_type'] === $value){

                            $res_key = $res['disorder_type'];
                            if(strpos(strtolower($res['disorder_type']),'strategic') !== false){
                                $res_key = 'Strategic Developments';
                            }

                            if(isset($agg[$res['disorder_type']][$group_date])){
                                $agg[$res_key][$group_date]['fatalities_count'] += $res['fatalities_count'];
                                $agg[$res_key][$group_date]['events_count'] += $res['events_count'];
                            }else{
                                $agg[$res_key][$group_date] = array_merge($res, ['sql_date' => $group_date]);
                            }
                        }

                        // Handle event_type aggregation
                        if($type === 'event_type' && $res['event_type'] === $value){
                            $res_key = $res['event_type'];
                            if(strpos(strtolower($res['event_type']),'strategic') !== false){
                                $res_key = 'Strategic Development Events';
                            }

                            if(isset($agg[$res_key][$group_date])){
                                $agg[$res_key][$group_date]['fatalities_count'] += $res['fatalities_count'];
                                $agg[$res_key][$group_date]['events_count'] += $res['events_count'];
                            }else{
                                $agg[$res_key][$group_date] = array_merge($res, ['sql_date' => $group_date]);
                            }
                        }

                        // Handle sub_event_type aggregation
                        if($type === 'sub_event_type' && $res['sub_event_type'] === $value){
                            if(isset($agg[$res['sub_event_type']][$group_date])){
                                $agg[$res['sub_event_type']][$group_date]['fatalities_count'] += $res['fatalities_count'];
                                $agg[$res['sub_event_type']][$group_date]['events_count'] += $res['events_count'];
                            }else{
                                $agg[$res['sub_event_type']][$group_date] = array_merge($res, ['sql_date' => $group_date]);
                            }
                        }
                    }
                }
            }

            $inter_text = 'inter1';
            if($column_exists){
                $inter_text = 'inter1,inter2';
            }

            $sql_actor = "SELECT 
            COUNT(*) as events_count,
            {$inter_text},
            CASE 
                WHEN '{$sql_type}' = 'YEARWEEK' THEN DATE_FORMAT(STR_TO_DATE(CONCAT(YEARWEEK(STR_TO_DATE(event_date, '$mysql_date_format')), ' Sunday'), '%X%V %W'), '%Y-%m-%d')
                WHEN '{$sql_type}' = 'MONTH' THEN DATE_FORMAT(STR_TO_DATE(event_date, '$mysql_date_format'), '%Y-%m-01')
                WHEN '{$sql_type}' = 'YEAR' THEN DATE_FORMAT(STR_TO_DATE(event_date, '$mysql_date_format'), '%Y-01-01')
                WHEN '{$sql_type}' = 'QUARTER' THEN CONCAT(YEAR(STR_TO_DATE(event_date, '$mysql_date_format')), '-', LPAD(QUARTER(STR_TO_DATE(event_date, '$mysql_date_format')) * 3 - 2, 2, '0'), '-01')
                ELSE DATE_FORMAT(STR_TO_DATE(event_date, '$mysql_date_format'), '%Y-%m-%d')
            END as sql_date
            FROM {$table_name} {$whereSQL} 
            GROUP BY sql_date, {$inter_text}";


            $transient_key = md5($sql_actor);
            $results_actors = get_transient('wpdp_cache_'.$transient_key);
            if(empty($results_actors) || WP_DATA_PRESENTATION_DISABLE_CACHE){
                $results_actors = $wpdb->get_results($sql_actor, ARRAY_A);
                set_transient('wpdp_cache_'.$transient_key, $results_actors);
            }

            $test[] = $results_actors;
            if(!empty($results_actors)){
                foreach($results_actors as $res){

                    foreach($filters['actors'] as $value){
    
                        // Get the date index for grouping
                        $date_timestamp = strtotime($res['sql_date']);
                        $start_timestamp = strtotime($filters['from'] ?: $res['sql_date']);
                        $days_diff = floor(($date_timestamp - $start_timestamp) / (60 * 60 * 24));
                        $group_index = floor($days_diff / $group_interval);
                        $group_date = date('Y-m-d', strtotime($filters['from'] ?: $res['sql_date']) + ($group_index * $group_interval * 24 * 60 * 60));
                        
                        // Handle inter1 and inter2 that sometimes they are integers and sometimes they are strings
                        $inter1 = $res['inter1'];
                        $inter2 = isset($res['inter2']) ? $res['inter2'] : null;
           

                        if( $inter1 === $value || ( isset($inter2) && $inter2 === $value ) ){
                            $text_value = (isset($inter_labels[$value])) ? $inter_labels[$value] : $value;

                            if(isset( $data_actors[$text_value] [$group_date] )){
                                $data_actors[$text_value][$group_date]['events_count'] += $res['events_count'];
                            }else{
                                $data_actors[$text_value][$group_date] = $res;
                            }
    
                        }
    
    
                    }
                }
            }

        }

        if ($most_recent_date) {
            $most_recent_date = date('jS M Y', strtotime($most_recent_date));
        }

        if($most_recent_fatal_date){
            $most_recent_fatal_date = date('jS M Y', strtotime($most_recent_fatal_date));
        }

        // Properly combine Protests and Riots data
        if(isset($agg['Protests']) && isset($agg['Riots'])) {
            $agg['Protests and Riots'] = [];
            
            // Get all unique dates from both arrays
            $all_dates = [];
            if(isset($agg['Protests'])) {
                $all_dates = array_merge($all_dates, array_keys($agg['Protests']));
            }
            if(isset($agg['Riots'])) {
                $all_dates = array_merge($all_dates, array_keys($agg['Riots']));
            }
            $all_dates = array_unique($all_dates);

            // Combine data for each date
            foreach($all_dates as $date) {
                $protests_data = isset($agg['Protests'][$date]) ? $agg['Protests'][$date] : ['fatalities_count' => 0, 'events_count' => 0];
                $riots_data = isset($agg['Riots'][$date]) ? $agg['Riots'][$date] : ['fatalities_count' => 0, 'events_count' => 0];

                $agg['Protests and Riots'][$date] = [
                    'fatalities_count' => $protests_data['fatalities_count'] + $riots_data['fatalities_count'],
                    'events_count' => $protests_data['events_count'] + $riots_data['events_count'],
                    'sql_date' => $date,
                    'last_event_date' => max($protests_data['last_event_date'], $riots_data['last_event_date']),
                    'last_fatal_event_date' => max($protests_data['last_fatal_event_date'], $riots_data['last_fatal_event_date']),
                ];
            }

            // Remove original arrays
            // unset($agg['Protests']);
            // unset($agg['Riots']);
        }

        return [
            'data'=>$agg,
            'data_actors'=>$data_actors,
            'chart_sql' => $chart_sql,
            'intervals' => $intervals,
            'most_recent_date' => $most_recent_date,
            'most_recent_fatal_date' => $most_recent_fatal_date,
        ];

    }

    public function get_text_and_conditions($type){
        $conditions = [];
        $text = '';
        if(strpos($type,'+') !== false){
            $type = explode('+',$type);
            $text = '';
            $i=0;
            foreach($type as $type_v){$i++;
                $type_v = explode('__',$type_v);
                $column = $type_v[1];
                $text .= $type_v[0];
                if(count($type) != $i){
                    $text.= ' & ';
                }
                $conditions[] = "{$column} = '{$text}'";
            }
        }else{
            $type = explode('__',$type);
            $column = $type[1];
            $text = $type[0];
            $conditions[] = "{$column} = '{$text}'";
        }

        return [
            'text'=>$text,
            'conditions'=>$conditions
        ];
    }


    public static function shortcode_output(){
        wp_enqueue_script(WP_DATA_PRESENTATION_NAME.'chartjs');
        wp_enqueue_script(WP_DATA_PRESENTATION_NAME.'chartjs-moment');
        wp_enqueue_script(WP_DATA_PRESENTATION_NAME.'chartjs-adapter');
        $shortcode = WPDP_Shortcode::get_instance();
    ?>
    <div class="wpdp_filter_content table-responsive">
        <canvas id="wpdp_chart" style="height:400px" class="table"></canvas>
        <div class="chart_flex">
            <div class="chart-controls chart">
                <h3>Toggle Event Category Levels <?php echo $shortcode::info_icon('Use these controls to show or hide event categories based on their hierarchy levels.','padding-top: 2px;'); ?></h3>
                <button class="chart-filter-btn top-level active">Top Level</button>
                <button class="chart-filter-btn second-level active">2nd Level</button>
                <button class="chart-filter-btn third-level">3rd Level</button>
                <a href="javascript:void(0)" class="chart-filter-btn all-categories ">Toggle All</a>
            </div>
            <div class="last_updated_chart chart">Last relevant data entry: <span class="last_updated_chart_date"></span> <?php echo $shortcode::info_icon(''); ?><span class="chart_source">Source: ACLED</span></div>
        </div>

        <hr>
        <canvas id="wpdp_chart_fat" style="height:400px" class="table"  ></canvas>
        <div class="chart_flex">
        <div class="chart-controls fat_chart">
                <h3>Toggle Event Category Levels <?php echo $shortcode::info_icon('Use these controls to show or hide event categories based on their hierarchy levels.','padding-top: 2px;'); ?></h3>
                <button class="chart-filter-btn top-level active">Top Level</button>
                <button class="chart-filter-btn second-level active">2nd Level</button>
                <button class="chart-filter-btn third-level">3rd Level</button>
                <a href="javascript:void(0)" class="chart-filter-btn all-categories ">Toggle All</a>
            </div>
            <div class="last_updated_chart chart_fat">Last relevant data entry: <span class="last_updated_chart_date"></span> <?php echo $shortcode::info_icon(''); ?><span class="chart_source">Source: ACLED</span></div>
        </div>
        <hr>
        <canvas id="wpdp_chart_bar_chart" style="height:400px" class="table"  ></canvas>
        <div class="last_updated_chart chart_bar">Last relevant data entry: <span class="last_updated_chart_date"></span> <?php echo $shortcode::info_icon(''); ?><span class="chart_source">Source: ACLED</span></div>
        <p class="chart_bar_note">Types of Actors shown can be aggressors or targets/victims. Civilians are overwhelmingly identified as targets/victims.</p>

    </div>
    <?php }


}

WPDP_Graphs::get_instance();
