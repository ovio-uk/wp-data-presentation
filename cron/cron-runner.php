<?php
// CLI only
if ( php_sapi_name() !== 'cli' ) {
    exit;
}

// Load WordPress (go up 4 levels)
require_once dirname( __DIR__, 4 ) . '/wp-load.php';

// Trigger your action
do_action( 'wpdp_daily_acled_update' );
