<?php

/**
 * Class for importing CSV data into database table
 *
 * @since 1.0.0
 */

class WPDP_Db_Table {
    private $table_name;
    private $csv_file_path;
    private $delimiter;
    private $logger;

    /**
     * Constructor
     *
     * @param string $table_name
     *
     * @since 1.0.0
     */
    public function __construct($table_name,$file_path) {
        global $wpdb;
        $this->table_name    =  $table_name;
        $this->csv_file_path = $file_path;
        $this->delimiter     = ';';
    }


    public function detect_delimiter($file_path) {
        $delimiters = array(
            ',' => 0,
            ';' => 0,
            "\t" => 0,
            '|' => 0
        );
    
        $handle = fopen($file_path, 'r');
    
        if ($handle) {
            $line = fgets($handle);
            fclose($handle);
    
            foreach ($delimiters as $delimiter => &$count) {
                $count = substr_count($line, $delimiter);
            }
        }
    
        return array_search(max($delimiters), $delimiters);
    }
    

    /**
     * Import CSV file into database table
     *
     * @return bool Success status
     *
     * @since 1.0.0
     */
    public function import_csv() {
        if ( ! $this->create_table() ) {
            error_log( 'WPDP Import Error: Failed to create table - ' . $this->table_name );
            return false;
        }

        if ( ! file_exists( $this->csv_file_path ) ) {
            error_log( 'WPDP Import Error: CSV file does not exist - ' . $this->csv_file_path );
            var_dump( 'csv file does not exist' );
            exit;
        }

        $conn = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
        mysqli_options( $conn, MYSQLI_OPT_LOCAL_INFILE, true );

        global $wpdb;
        $csv_file_path = $this->sanitize_file_path( $this->csv_file_path );
        
        if ( $conn->connect_error ) {
            $error_message = 'WPDP Import Error: Database connection failed - ' . $conn->connect_error;
            error_log( $error_message );
            die( "Connection failed: " . $conn->connect_error );
        }
        $this->delimiter = $this->detect_delimiter($this->csv_file_path);

        // Always import into a temp table first so bad rows never touch the main table.
        $table_name = $this->table_name . '_temp';
        if ( ! $this->create_temp_table( $table_name ) ) {
            return false;
        }

        // Live server only
        $query = $wpdb->prepare(
            "LOAD DATA LOCAL INFILE %s
                     INTO TABLE {$table_name}
                     FIELDS TERMINATED BY %s
                     ENCLOSED BY '\"'
                     LINES TERMINATED BY '\\n'
                     IGNORE 1 LINES",
            $csv_file_path,
            $this->delimiter
        );
        $result = $conn->query($query);


        // Local host only.
        // $query = $wpdb->prepare(
        //     "LOAD DATA INFILE %s
        //              INTO TABLE {$this->table_name}
        //              FIELDS TERMINATED BY %s
        //              ENCLOSED BY '\"'
        //              LINES TERMINATED BY '\\n'
        //              IGNORE 1 LINES",
        //     $csv_file_path,
        //     $this->delimiter
        // );
        
        // $result = $wpdb->query($query);


        if ( false === $result ) {
            $error_message = 'WPDP Import Error: Failed to import CSV data into table ' . $table_name . ' - ' . $conn->error;
            error_log( $error_message );
            wpdp_send_error_email( 'CSV Import Failed', 'Failed to import data into temp table: ' . $table_name );
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
            var_dump( 'Error importing CSV data - ' . $conn->error );
            exit;
        }

        $invalid_row = $this->get_first_invalid_row( $table_name );
        if ( ! empty( $invalid_row ) ) {
            error_log(
                sprintf(
                    'WPDP Import Error: Invalid imported row detected in %s from %s. Sample row: event_id_cnty="%s", event_date="%s", year="%s". Import aborted.',
                    $table_name,
                    $this->csv_file_path,
                    isset( $invalid_row['event_id_cnty'] ) ? $invalid_row['event_id_cnty'] : '',
                    isset( $invalid_row['event_date'] ) ? $invalid_row['event_date'] : '',
                    isset( $invalid_row['year'] ) ? $invalid_row['year'] : ''
                )
            );
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
            return false;
        }

        $table_has_data = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

        if($table_has_data > 0){
            // Merge data from temporary table to main table, removing duplicates
            $this->merge_tables($table_name);
        } else {
            $wpdb->query( "
                INSERT INTO {$this->table_name}
                SELECT *
                FROM {$table_name}
            " );
        }

        // Drop the temporary table
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

        return true;
    }

	private function create_temp_table( $temp_table_name ) {
		global $wpdb;

		// Optional: Drop temp table if it exists to avoid "already exists" error
		$wpdb->query( "DROP TABLE IF EXISTS {$temp_table_name}" );

		$result = $wpdb->query( "CREATE TABLE {$temp_table_name} LIKE {$this->table_name}" );

		if ( $result === false ) {
			$error_message = "WPDP Import Error: Failed to create temp table {$temp_table_name} - " . $wpdb->last_error;
			error_log( $error_message );
			return false;
		}
		
		return true;
	}


    private function merge_tables( $temp_table_name ) {
        global $wpdb;
        
        try {
            // Set longer timeout for large datasets
            set_time_limit( 300 ); // 5 minutes
            
            // First, insert new records
            $insert_result = $wpdb->query( "
                INSERT INTO {$this->table_name}
                SELECT t.*
                FROM {$temp_table_name} t
                LEFT JOIN {$this->table_name} m ON t.event_id_cnty = m.event_id_cnty
                WHERE m.event_id_cnty IS NULL
            " );

            // Then, update fatalities, inter1, inter2, actor1, and actor2 when they've changed
            $update_result = $wpdb->query( "
                UPDATE {$this->table_name} m
                INNER JOIN {$temp_table_name} t ON m.event_id_cnty = t.event_id_cnty
                SET 
                    m.fatalities = COALESCE(t.fatalities, 0),
                    m.inter1 = t.inter1,
                    m.inter2 = t.inter2,
                    m.actor1 = t.actor1,
                    m.actor2 = t.actor2
                WHERE COALESCE(t.fatalities, 0) != COALESCE(m.fatalities, 0)
                   OR IFNULL(t.inter1, '') != IFNULL(m.inter1, '')
                   OR IFNULL(t.inter2, '') != IFNULL(m.inter2, '')
                   OR IFNULL(t.actor1, '') != IFNULL(m.actor1, '')
                   OR IFNULL(t.actor2, '') != IFNULL(m.actor2, '')
            " );

            return array(
                'inserted' => $insert_result,
                'updated' => $update_result
            );
        } catch ( Exception $e ) {
            $error_message = "WPDP Import Error: Error merging tables - " . $e->getMessage();
            error_log( $error_message );
            return false;
        }
    }

    /**
     * Sanitize file path
     *
     * @param string $file_path
     * @return string Sanitized file path
     *
     * @since 1.0.0
     */
    private function sanitize_file_path($file_path) {
        // Sanitize the file path to prevent SQL injection
        return addslashes($file_path);
    }

    /**
     * Create database table
     *
     * @return bool Success status
     *
     * @since 1.0.0
     */
    public function create_table() {
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        if($table_exists){
            return true;
        }

        // Get the column names from the CSV file
        $column_names = $this->get_column_names();
        if ( empty( $column_names ) ) {
            error_log( 'WPDP Import Error: Unable to get column names from CSV file - ' . $this->csv_file_path );
            return false;
        }

        $definitions = $this->get_column_definitions($column_names);

        // Create the SQL query to create the table
        $sql = "CREATE TABLE {$this->table_name} (
            " . implode(",\n", $definitions) . ',
            INDEX `disorder_type` (`disorder_type`),
            INDEX `region` (`region`),
            INDEX `event_id_cnty` (`event_id_cnty`),
            INDEX `country` (`country`),
            INDEX `admin1` (`admin1`),
            INDEX `admin2` (`admin2`),
            INDEX `admin3` (`admin3`),
            INDEX `location` (`location`),
            INDEX `event_date` (`event_date`)
        )';

        // Execute the query
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $wpdb->query( $sql );

        // Check if the table was created successfully
        if ( $wpdb->last_error ) {
            $error_message = 'WPDP Import Error: Failed to create table ' . $this->table_name . ' - ' . $wpdb->last_error;
            error_log( $error_message );
            var_dump( $wpdb->last_error );
            exit;
        }
        
        return true;
    }

    /**
     * Get column definitions for table creation
     *
     * @param array $column_names Column names
     * @return array Column definitions
     *
     * @since 1.0.0
     */
    public function get_column_definitions(array $column_names) {
        $column_definitions = array();
        foreach ($column_names as $name) {
            switch ($name) {
                case 'event_id_cnty':
                case 'iso':
                    $column_definitions[] = "`$name` VARCHAR(10)";
                    break;
                case 'event_date':
                    $column_definitions[] = "`$name` VARCHAR(25)";
                    break;
                case 'year':
                    $column_definitions[] = "`$name` YEAR(4)";
                    break;
                case 'time_precision':
                case 'interaction':
                case 'geo_precision':
                case 'fatalities':
                    $column_definitions[] = "`$name` INT";
                    break;
                case 'latitude':
                case 'longitude':
                    $column_definitions[] = "`$name` DECIMAL(10,7)";
                    break;
                case 'notes':
                    $column_definitions[] = "`$name` TEXT";
                    break;
                case 'timestamp':
                    $column_definitions[] = "`$name` BIGINT";
                    break;
                default:
                    $column_definitions[] = "`$name` VARCHAR(100)";
                    break;
            }
        }
        return $column_definitions;
    }

    /**
     * Get column names from CSV file
     *
     * @return array Column names
     *
     * @since 1.0.0
     */
    public function get_column_names() {
        if (!file_exists($this->csv_file_path)) {
            return array();
        }

        $handle = fopen($this->csv_file_path, 'r');

        if (!$handle) {
            return array();
        }

        $column_names = array();
        $row = fgetcsv($handle);

        if ($row && is_array($row)) {
            // Get column names from the first row of the CSV file
            $column_names = array_map('trim', $row);

            // Split columns if they are concatenated in one string
            if (count($column_names) == 1 && strpos($column_names[0], ';') !== false) {
                $column_names = array_map('trim', explode(';', $column_names[0]));
            }
        }

        fclose($handle);

        return $column_names;
    }

    private function get_first_invalid_row( $table_name ) {
        global $wpdb;

        return $wpdb->get_row(
            "
            SELECT event_id_cnty, event_date, year
            FROM {$table_name}
            WHERE event_id_cnty IS NULL
               OR TRIM(event_id_cnty) = ''
               OR event_id_cnty NOT REGEXP '^[A-Za-z0-9_-]+$'
               OR event_date IS NULL
               OR TRIM(event_date) = ''
               OR event_date NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
               OR year IS NULL
               OR year = 0
            LIMIT 1
            ",
            ARRAY_A
        );
    }
}
