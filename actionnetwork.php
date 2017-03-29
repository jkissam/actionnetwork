<?php
/*
 * @package ActionNetwork
 * @version 1.0-beta5
 *
 * Plugin Name: Action Network
 * Description: Integrates with Action Network (actionnetwork.org)'s API to provide action embed codes as shortcodes
 * Author: Jonathan Kissam
 * Text Domain: actionnetwork
 * Domain Path: /languages
 * Version: 1.0-beta5
 * Author URI: http://jonathankissam.com
 */

/**
 * Includes
 */
if (!defined('JONATHANKISSAM_BRANDING_VERSION')) {
	require_once( plugin_dir_path( __FILE__ ) . 'jk_wp_branding/jk_branding.inc.php' );
}
if (!class_exists('ActionNetwork')) {
	require_once( plugin_dir_path( __FILE__ ) . 'includes/actionnetwork.class.php' );
}
if (!class_exists('ActionNetwork_Sync')) {
	require_once( plugin_dir_path( __FILE__ ) . 'includes/actionnetwork-sync.class.php' );
}

/**
 * Set up options
 */
add_option( 'actionnetwork_api_key', null );

/**
 * Installation, database setup
 */
global $actionnetwork_version;
$actionnetwork_version = '1.0-beta5';
global $actionnetwork_db_version;
$actionnetwork_db_version = '1.0.7';

function actionnetwork_install() {

	global $wpdb;
	global $actionnetwork_version;
	global $actionnetwork_db_version;
	global $actionnetwork_update_sync;
	$installed_version = get_option( 'actionnetwork_version' );
	$installed_db_version = get_option( 'actionnetwork_db_version' );

	$notices = get_option('actionnetwork_deferred_admin_notices', array());

	if ($installed_version != $actionnetwork_version) {

		// test for particular updates here

		// on first installation
		if (!$installed_version) {
			$notices[] = sprintf(
				/* translators: %s is link to text "settings page" */
				__('Thank for you installing the Action Network plugin. If you are an Action Network partner and have an API key, please visit the plugin %s and enter your API key.', 'actionnetwork'),
				'<a href="admin.php?page=actionnetwork&actionnetwork_tab=settings">' . __('settings page','actionnetwork') . '</a>'
			);
		}

		update_option( 'actionnetwork_version', $actionnetwork_version );
	}

	if ($installed_db_version != $actionnetwork_db_version) {
		
		// test for particular updates
		if ( $installed_db_version && ($actionnetwork_db_version == '1.0.7') ) {
			$notices[] = __('Database updated to add description and location fields to actionnetwork table, and remove end_date', 'actionnetwork');
			// force updating all actions in the database
			update_option('actionnetwork_cache_timestamp', 0 );
		}
		
		// test for particular updates
		if ( $installed_db_version && ($actionnetwork_db_version == '1.0.6') ) {
			$notices[] = __('Database updated to add "end_date" field to actionnetwork table', 'actionnetwork');
		}
		
		if ( $installed_db_version && ($actionnetwork_db_version == '1.0.5') ) {
			$notices[] = __('Database updated to add "hidden" field to actionnetwork table', 'actionnetwork');
		}
		
		if ( $installed_db_version && ($actionnetwork_db_version == '1.0.4') ) {
			$notices[] = __('Database updated to add table actionnetwork_queue', 'actionnetwork');
		}

		$table_name = $wpdb->prefix . 'actionnetwork';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			wp_id mediumint(9) NOT NULL AUTO_INCREMENT,
			an_id varchar(64) DEFAULT '' NOT NULL,
			type varchar(24) DEFAULT '' NOT NULL, 
			name varchar(255) DEFAULT '' NOT NULL,
			title varchar (255) DEFAULT '' NOT NULL,
			created_date bigint DEFAULT NULL,
			modified_date bigint DEFAULT NULL,
			start_date bigint DEFAULT NULL,
			browser_url varchar(255) DEFAULT '' NOT NULL,
			embed_standard_default_styles text NOT NULL,
			embed_standard_layout_only_styles text NOT NULL,
			embed_standard_no_styles text NOT NULL,
			embed_full_default_styles text NOT NULL,
			embed_full_layout_only_styles text NOT NULL,
			embed_full_no_styles text NOT NULL,
			description text NOT NULL,
			location text NOT NULL,
			enabled tinyint(1) DEFAULT 0 NOT NULL,
			hidden tinyint(1) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (wp_id)
		) $charset_collate;";
		
		$table_name_queue = $wpdb->prefix . 'actionnetwork_queue';
		$sql_queue = "CREATE TABLE $table_name_queue (
			resource_id bigint(2) NOT NULL AUTO_INCREMENT,
			resource text NOT NULL,
			endpoint varchar(255) DEFAULT '' NOT NULL,
			processed tinyint(1) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (resource_id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		dbDelta( $sql );
		dbDelta( $sql_queue );

		update_option( 'actionnetwork_db_version', $actionnetwork_db_version );

	}
	
	if ( !wp_next_scheduled( 'actionnetwork_cron_daily' ) ) {
		wp_schedule_event( time(), 'daily', 'actionnetwork_cron_daily' );
	}

	update_option('actionnetwork_deferred_admin_notices', $notices);

}
register_activation_hook( __FILE__, 'actionnetwork_install' );

function actionnetwork_update_version_check() {
	global $actionnetwork_version;
	global $actionnetwork_db_version;
	$installed_version = get_option( 'actionnetwork_version' );
	$installed_db_version = get_option( 'actionnetwork_db_version' );
	if ( ($installed_version != $actionnetwork_version) || ($installed_db_version != $actionnetwork_db_version) ) {
		actionnetwork_install();
	}
}
add_action( 'plugins_loaded', 'actionnetwork_update_version_check' );

/**
 * Uninstall
 */
function actionnetwork_uninstall() {

	global $wpdb;

	// remove options
	$actionnetwork_options = array(
		'actionnetwork_version',
		'actionnetwork_db_version',
		'actionnetwork_deferred_admin_notices',
		'actionnetwork_api_key',
		'actionnetwork_cache_timestamp',
		'actionnetwork_queue_status',
		'actionnetwork_cron_token',
	);
	foreach ($actionnetwork_options as $option) {
		delete_option( $option );
	}

	// remove database tables
	$table_name = $wpdb->prefix . 'actionnetwork';
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
	
	$table_name = $wpdb->prefix . 'actionnetwork_queue';
	$wpdb->query("DROP TABLE IF EXISTS $table_name");

}
register_uninstall_hook( __FILE__, 'actionnetwork_uninstall' );

/**
 * Administrative notices
 */
function actionnetwork_admin_notices() {
	if ($notices = get_option( 'actionnetwork_deferred_admin_notices' ) ) {
		foreach ($notices as $notice) {
			echo "<div class=\"updated notice is-dismissible\"><p>$notice</p></div>";
		}
		delete_option( 'actionnetwork_deferred_admin_notices' );
	}
}
add_action( 'admin_notices', 'actionnetwork_admin_notices' );



/**
 * Widgets
 */
if (!class_exists('ActionNetwork_Widgets')) {
	require_once( plugin_dir_path( __FILE__ ) . 'includes/actionnetwork-widgets.class.php' );
}
add_action( 'widgets_init', function(){
	register_widget( 'ActionNetwork_Calendar_Widget' );
});



/**
 * Shortcode for embeds
 * Since the way Action Network's embed codes work
 * does not support multiple embeds on a single page,
 * only allow the first shortcode on a given page load
 */
global $actionnetwork_shortcode_count;
$actionnetwork_shortcode_count = 0;

function actionnetwork_shortcode( $atts ) {
	global $wpdb;
	global $actionnetwork_shortcode_count;

	// only embed a single shortcode on any given page
	if ($actionnetwork_shortcode_count) { return; }

	$id = isset($atts['id']) ? (int) $atts['id'] : null;
	$size = isset($atts['size']) ? $atts['size'] : 'standard';
	$style = isset($atts['style']) ? $atts['style'] : 'layout_only';

	if (!$id) { return; }

	// validate size and style
	if (!in_array($size, array('standard', 'full'))) { $size = 'standard'; }
	if (!in_array($style, array('default', 'layout_only', 'no'))) { $style = 'layout_only'; }
	
	$sql = "SELECT * FROM {$wpdb->prefix}actionnetwork WHERE wp_id=".$id;
	$action = $wpdb->get_row( $sql, ARRAY_A );

	$embed_style = 'embed_'.$size.'_'.$style.'_styles';
	
	$output = _actionnetwork_get_embed_code( $action, $embed_style );
	
	if ($output) {
		$actionnetwork_shortcode_count++;
		return $output;
	}

}
add_shortcode( 'actionnetwork', 'actionnetwork_shortcode' );

/**
 * Shortcode for calendar
 */
function actionnetwork_calendar_shortcode ( $atts, $content = null ) {
	global $wpdb, $wp;
	
	$n = isset($atts['n']) ? (int) $atts['n'] : 0;
	// $page = isset($atts['page']) ? (int) $atts['page'] : 10;
	$date_format = isset($atts['date_format']) ? sanitize_text_field($atts['date_format']) : 'F j, Y';
	$link_format = isset($atts['link_format']) ? sanitize_text_field($atts['link_format']) : '';
	$link_text = isset($atts['link_text']) ? $atts['link_text'] : '{{ event.date }}: {{ event.title }}';
	$container_element = isset($atts['container_element']) ? sanitize_key($atts['container_element']) : 'ul';
	$container_class = isset($atts['container_class']) ? sanitize_html_class($atts['container_class']) : 'actionnetwork-calendar';
	$item_element = isset($atts['item_element']) ? sanitize_key($atts['item_element']) : 'li';
	$item_class = isset($atts['item_class']) ? sanitize_html_class($atts['item_class']) : 'actionnetwork-calendar-item';
	$no_events = isset($atts['no_events']) ? $atts['no_events'] : __( 'No upcoming events', 'actionnetwork' );
	$location = (isset($atts['location']) && $atts['location']) ? '<div class="actionnetwork-calendar-location">{{ event.location }}</div>' : '';
	$description = (isset($atts['description']) && $atts['description']) ? '<div class="actionnetwork-calendar-description">{{ event.description }}</div>' : '';
	
	$embed_style = isset($atts['embed_style']) ? 'embed_'.sanitize_text_field($atts['embed_style']).'_styles' : 'embed_standard_layout_only_styles';
	
	/*
	$embed_fields = array(
		'embed_standard_layout_only_styles',
		'embed_full_layout_only_styles',
		'embed_standard_no_styles',
		'embed_full_no_styles',
		'embed_standard_default_styles',
		'embed_full_default_styles',
	);
	
	// validate embed_style
	if (!in_array( $embed_style, $embed_fields )) { $embed_style = 'embed_standard_layout_only_styles'; }
	*/
	
	// check if we have an id that matches an existing event
	$event_id = ( isset($wp->query_vars['page']) && (!isset($atts['ignore_url_id']) || !$atts['ignore_url_id']) ) ? (int) $wp->query_vars['page'] : 0;
	if ($event_id) {
		$sql = "SELECT * FROM {$wpdb->prefix}actionnetwork WHERE type IN ('event','ticketed_event') AND wp_id=$event_id";
		$sql .= " AND enabled=1 AND hidden=0";
		$sql .= " AND start_date > ".time();
		$event = $wpdb->get_row( $sql, ARRAY_A );
		if (count($event)) {
			return _actionnetwork_get_embed_code( $event, $embed_style );
		}
	}
	
	// template
	if (trim($content)) {
		$content = preg_replace('#</?p>|<br ?/?>#','',$content);
	} else {
		$content = <<<EOHTML
<$container_element class="$container_class">
{% for event in events %}
  <$item_element class="$item_class">
    <a href="$link_format">$link_text</a>
  	$location
  	$description
  </$item_element>
{% else %}
  <$item_element class="$item_class">$no_events</$item_element>
{% endfor %}
</$container_element>
EOHTML;
	}
	
	// parse template into $pre, $row, $else and $post
	list ($pre,$content) = explode('{% for event in events %}', $content);
	list ($row,$content) = explode('{% else %}', $content);
	list ($no_events,$post) = explode('{% endfor %}', $content);
	
	// load events
	$sql = "SELECT * FROM {$wpdb->prefix}actionnetwork WHERE type IN ('event','ticketed_event')";
	$sql .= " AND enabled=1 AND hidden=0";
	$sql .= " AND start_date > ".time();
	$sql .= " ORDER BY start_date ASC";
	if ($n) { $sql .= " LIMIT 0,$n"; }
	$events = $wpdb->get_results( $sql, ARRAY_A );
	
	$output = $pre;
	if (count($events)) {
		foreach ($events as $event) {
			$event_data['id'] = isset($event['wp_id']) ? $event['wp_id'] : 0;
			$event_data['title'] = isset($event['title']) ? $event['title'] : '(Event Title)';
			$event_data['date'] = isset($event['start_date']) ? date($date_format, $event['start_date']) : '(Date)';
			$event_data['link'] = isset($event['browser_url']) ? $event['browser_url'] : site_url();
			$event_data['link'] = $link_format ? _actionnetwork_calendar_render( $link_format, $event_data) : $event_data['link'];
			$event_data['location'] = isset($event['location']) ? _actionnetwork_render_location($event['location']) : '';
			$event_data['description'] = isset($event['description']) ? $event['description'] : '';
			$output .= _actionnetwork_calendar_render( $row, $event_data );
		}
	} else {
		$output .= $no_events;
	}
	$output .= $post;
	
	// $output .= '<pre>' . print_r($wp,1) . '</pre>';
	
	return $output;
	
}
add_shortcode( 'actionnetwork_calendar', 'actionnetwork_calendar_shortcode' );

/**
 * Helper function for calendar shortcode
 * Renders a very simplistic version of twig (http://twig.sensiolabs.org/)
 */
function _actionnetwork_calendar_render( $twig, $event ) {
	$output = $twig;
	foreach ($event as $k => $v) {
		$output = str_replace('{{ event.'.$k.' }}', $v, $output);
	}
	return $output;
}

/**
 * Helper function to render a location hash
 */
function _actionnetwork_render_location( $location_hash ) {
	$location = unserialize( $location_hash );
	if ( isset( $location->html ) && $location->html ) { return wpautop( $location->html ); }
	$location_string = '';
	$location_string .= ( isset( $location->venue ) && $location->venue ) ? $location->venue . "\n" : '';
	$location_string .= ( isset( $location->address_lines ) && is_array( $location->address_lines ) && count ( $location->address_lines ) ) ? $location->address_lines[0] . "\n" : '';
	$location_string .= ( isset( $location->locality ) && $location->locality ) ? $location->locality . ', ' : '';
	$location_string .= isset( $location->region ) ? $location->region . ' ' : '';
	$location_string .= isset( $location->postal_code ) ? $location->postal_code : '';
	return wpautop( $location_string );
}

/**
 * Helper function to get embed code by style
 */
function _actionnetwork_get_embed_code( $action, $embed_style = '', $autop = true ) {
	$embed_fields = array(
		'embed_standard_layout_only_styles',
		'embed_full_layout_only_styles',
		'embed_standard_no_styles',
		'embed_full_no_styles',
		'embed_standard_default_styles',
		'embed_full_default_styles',
	);
	
	$output = null;
	
	// validate embed_style
	if (!in_array( $embed_style, $embed_fields )) { $embed_style = 'embed_standard_layout_only_styles'; }

	if (isset($action[$embed_style]) && $action[$embed_style]) {
		$output = $action[$embed_style];
	} else {
		foreach( $embed_fields as $embed_field_name) {
			if ($action[$embed_field_name]) {
				$output = $action[$embed_field_name];
			}
		}
	}
	
	if ((strpos($output, '<script') === FALSE) && $autop) {
		$output = wpautop($output);
	}
	
	return $output;
}

/**
 * Set up admin menu structure
 * https://developer.wordpress.org/reference/functions/add_menu_page/
 */
function actionnetwork_admin_menu() {
	$actionnetwork_admin_menu_hook = add_menu_page( __('Administer Action Network', 'actionnetwork'), 'Action Network', 'manage_options', 'actionnetwork', 'actionnetwork_admin_page', plugins_url('icon-action-network.png', __FILE__), 21);
	add_action( 'load-' . $actionnetwork_admin_menu_hook, 'actionnetwork_admin_add_help' );
	/*
	// customize the first sub-menu link
	$actionnetwork_admin_menu_hook = add_submenu_page( __('Administer Action Network'), __('Administer'), 'manage_options', 'actionnetwork-menu', 'actionnetwork_admin_page');
	add_action( 'load-' . $actionnetwork_admin_menu_hook, 'actionnetwork_admin_add_help' );
	*/
}
add_action( 'admin_menu', 'actionnetwork_admin_menu' );

/**
 * Update sync daily via cron
 */
function actionnetwork_cron_sync() {

	// initiate a background process by making a call to the "ajax" URL
	$ajax_url = admin_url( 'admin-ajax.php' );

	// since we're making this call from the server, we can't use a nonce
	// because the user id could be different. so create a token
	$timeint = time() / mt_rand( 1, 10 ) * mt_rand( 1, 10 );
	$timestr = (string) $timeint;
	$token = md5( $timestr );
	update_option( 'actionnetwork_ajax_token', $token );

	$body = array(
		'action' => 'actionnetwork_process_queue',
		'queue_action' => 'init',
		'token' => $token,
	);
	$args = array( 'body' => $body, 'timeout' => 1 );
	wp_remote_post( $ajax_url, $args );

}
add_action( 'actionnetwork_cron_daily', 'actionnetwork_cron_sync' );

/**
 * Process ajax requests
 */
function actionnetwork_process_queue(){
	
	// Don't lock up other requests while processing
	session_write_close();

	// check token
	$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : 'no token';
	$stored_token = get_option( 'actionnetwork_ajax_token', '' );
	if ($token != $stored_token) { wp_die(); }
	delete_option( 'actionnetwork_ajax_token' );
	
	$queue_action = isset($_REQUEST['queue_action']) ? $_REQUEST['queue_action'] : '';
	$updated = isset($_REQUEST['updated']) ? $_REQUEST['updated'] : 0;
	$inserted = isset($_REQUEST['inserted']) ? $_REQUEST['inserted'] : 0;
	$new_only = isset($_REQUEST['new_only']) ? $_REQUEST['new_only'] : 0;
	$status = get_option( 'actionnetwork_queue_status', 'empty' );

	// otherwise delete the ajax token
	
	// only do something if status is empty & queue_action is init,
	// or status is processing and queue_action is continue
	if (
			( ($queue_action == 'init') && ($status == 'empty') )
			|| ( ($queue_action == 'continue') && ($status == 'processing') )
		) {
	
		$sync = new Actionnetwork_Sync();
		$sync->updated = $updated;
		$sync->inserted = $inserted;
		$sync->new_only = $new_only;
		if ($queue_action == 'init') { $sync->init(); }
		$sync->processQueue();
	
	}
	
	wp_die();
}
add_action( 'wp_ajax_actionnetwork_process_queue', 'actionnetwork_process_queue' );
add_action( 'wp_ajax_nopriv_actionnetwork_process_queue', 'actionnetwork_process_queue' );

function actionnetwork_get_queue_status(){
	check_ajax_referer( 'actionnetwork_get_queue_status', 'actionnetwork_ajax_nonce' );
	$sync = new Actionnetwork_Sync();
	$status = $sync->getQueueStatus();
	$status['text'] = __('API Sync queue is '.$status['status'].'.', 'actionnetwork');
	if ($status['status'] == 'processing') {
		$status['text'] .= ' ' . __(
			/* translators: first %d is number of items processed, second %d is total number of items in queue */
			sprintf('%d of %d items processed.', $status['processed'], $status['total'])
		);
	}
	
	// if status is "complete" or "empty," check if an admin notice has been set;
	// if it has, return the admin notice as status.text & clear in options
	if ( ($status['status'] == 'complete') || ($status['status'] == 'empty') ) {
		$notices = get_option('actionnetwork_deferred_admin_notices', array());
		if (isset($notices['api_sync_completed'])) {
			$status['text'] = $notices['api_sync_completed'];
			$status['status'] = 'complete';
			// unset($notices['api_sync_completed']);
			// update_option('actionnetwork_deferred_admin_notices', $notices);
		}
	}
	
	wp_send_json($status);
	wp_die();
}
add_action( 'wp_ajax_actionnetwork_get_queue_status', 'actionnetwork_get_queue_status' );

/**
 * Helper function to handle administrative actions
 */
function _actionnetwork_admin_handle_actions(){

	global $wpdb;
	$return = array();
	
	if ( !isset($_REQUEST['actionnetwork_admin_action']) || !check_admin_referer(
		'actionnetwork_'.$_REQUEST['actionnetwork_admin_action'], 'actionnetwork_nonce_field'
		) ) {
			return false;
	}
	
	switch ($_REQUEST['actionnetwork_admin_action']) {
	
		case 'update_api_key':
		
		$debug = "update_api_key case matched\n";
		
		$actionnetwork_api_key = sanitize_key($_REQUEST['actionnetwork_api_key']);
		$debug .= "actionnetwork_api_key: $actionnetwork_api_key\n";
		
		$queue_status = get_option( 'actionnetwork_queue_status', 'empty' );
		$debug .= "queue_status: $queue_status\n";
		// mail('uekissam@gmail.com','debug report 1',$debug,"From: noreply@wp-jkissam.rhcloud.com\r\n");
		
		if (get_option('actionnetwork_api_key', null) !== $actionnetwork_api_key) {
			
			$debug .= "get_option did not match actionnetwork_api_key\n";
			
			// don't allow API Key to be changed if a sync queue is processing
			if ($queue_status != 'empty') {
				$return['notices']['error'] = __( 'Cannot change API key while a sync queue is processing', 'actionnetwork' );
			} else {
				
				$debug .= "trying to change api key\n";
				// mail('uekissam@gmail.com','debug report 2',$debug,"From: noreply@wp-jkissam.rhcloud.com\r\n");
				
				$actionnetwork_api_key_is_valid = false;
				
				// empty API key is "valid"
				if (!$actionnetwork_api_key) {
					$actionnetwork_api_key_is_valid = true;
				} else {
				
					// validate API key
					$ActionNetwork = new ActionNetwork($actionnetwork_api_key);
					$validate = $ActionNetwork->call('petitions');
				
					$debug .= "validation returned:\n\n" . print_r($validate,1) . "\n\n";
					// mail('uekissam@gmail.com','debug report 3',$debug,"From: noreply@wp-jkissam.rhcloud.com\r\n");
					
					if (isset($validate->error)) {
						if (substr($validate->error,0,30) == 'API Key invalid or not present') {
							$return['notices']['error'][] = __( 'Invalid API key:', 'actionnetwork' ).' '.$actionnetwork_api_key;
						} else {
							$return['notices']['error'][] = __( 'Error validating API key:', 'actionnetwork' ).' '.$actionnetwork_api_key;
						}
					} else {
						$actionnetwork_api_key_is_valid = true;
					}
					
				}
				
				$debug .= $actionnetwork_api_key_is_valid ? "actionnetwork_api_key is valid\n" : "actionnetwork_api_key is not valid\n";
				// mail('uekissam@gmail.com','debug report 4',$debug,"From: noreply@wp-jkissam.rhcloud.com\r\n");
				
				if ($actionnetwork_api_key_is_valid) {
			
					update_option('actionnetwork_api_key', $actionnetwork_api_key);
					update_option('actionnetwork_cache_timestamp', 0);
					$deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}actionnetwork WHERE an_id != ''");

					if ($actionnetwork_api_key) {
						
						// initiate a background process by making a call to the "ajax" URL
						$ajax_url = admin_url( 'admin-ajax.php' );

						// since we're making this call from the server, we can't use a nonce
						// because the user id would be different. so create a token
						$timeint = time() / mt_rand( 1, 10 ) * mt_rand( 1, 10 );
						$timestr = (string) $timeint;
						$token = md5( $timestr );
						update_option( 'actionnetwork_ajax_token', $token );

						$body = array(
							'action' => 'actionnetwork_process_queue',
							'queue_action' => 'init',
							'token' => $token,
						);
						$args = array( 'body' => $body, 'timeout' => 1 );
						wp_remote_post( $ajax_url, $args );

						$queue_status = 'processing';
						$return['queue_status'] = $queue_status;
						
						$return['notices']['updated']['sync-started'] = $deleted ? __('API key has been updated, actions synced via previous API key have been removed, and sync with new API key has been started', 'actionnetwork') : __('API key has been updated and sync with new API key has been started', 'actionnetwork');
						
					} else {
						
						$return['notices']['updated'][] = $deleted ? __('API key and actions synced via API have been removed', 'actionnetwork') : __('API key has been removed', 'actionnetwork');
						
					}
				
				}
			
			}
		}
		break;

		case 'update_sync':

			// error_log( 'actionnetwork_admin_action=update_sync called', 0 );
		
			$queue_status = get_option( 'actionnetwork_queue_status', 'empty' );
			if ($queue_status != 'empty') {
				$return['notices']['error'][] = __( 'Sync currently in progress', 'actionnetwork' );
			} else {
		
				// initiate a background process by making a call to the "ajax" URL
				$ajax_url = admin_url( 'admin-ajax.php' );

				// since we're making this call from the server, we can't use a nonce
				// because the user id would be different. so create a token
				$timeint = time() / mt_rand( 1, 10 ) * mt_rand( 1, 10 );
				$timestr = (string) $timeint;
				$token = md5( $timestr );
				update_option( 'actionnetwork_ajax_token', $token );

				$body = array(
					'action' => 'actionnetwork_process_queue',
					'queue_action' => 'init',
					'token' => $token,
				);
				$args = array( 'body' => $body, 'timeout' => 1 );
				wp_remote_post( $ajax_url, $args );
				// error_log( "wp_remote_post url called: $ajax_url, args:\n\n".print_r($args,1), 0 );
				$return['notices']['updated']['sync-started'] = __( 'Sync started', 'actionnetwork' );
				$queue_status = 'processing';
				
			}
			
			$return['queue_status'] = $queue_status;
			
		break;
		
		case 'edit_event':
		$embed_wp_id = isset($_REQUEST['actionnetwork_event_wp_id']) ? (int) $_REQUEST['actionnetwork_event_wp_id'] : 0;
		if ($embed_wp_id) {
			
			$table_name = $wpdb->prefix . 'actionnetwork';
			
			$sql = "SELECT * FROM $table_name WHERE wp_id=".$embed_wp_id;
			$event = $wpdb->get_row( $sql, ARRAY_A );
			
			// only edit if wp_id refers to an existing event or tickted_event which is not API-synced
			if ( ($event == null) || $event['an_id'] ||
					(!in_array($event['type'],array('event','ticketed_event')))) {
				break;
			}
			
			// if we're posting, then get the title, date & code from $_POST
			$update = false;
			if (isset($_POST['postback']) && $_POST['postback']) {
				
				$update = true;
				
				$embed_title = isset($_REQUEST['actionnetwork_add_embed_title']) ? stripslashes($_REQUEST['actionnetwork_add_embed_title']) : '';
				$embed_date_string = isset($_REQUEST['actionnetwork_add_embed_date']) ? stripslashes($_REQUEST['actionnetwork_add_embed_date']) : '';
				$embed_code = isset($_REQUEST['actionnetwork_add_embed_code']) ? stripslashes($_REQUEST['actionnetwork_add_embed_code']) : '';
				$location = isset($_REQUEST['actionnetwork_add_location']) ? stripslashes($_REQUEST['actionnetwork_add_location']) : '';
				
				// make sure title & embed_code are not empty, add error messages;
				if (!$embed_title) {
					$return['notices']['error'][] = __('You must give your action a title', 'actionnetwork');
					$return['errors']['#actionnetwork_add_embed_title'] = true;
					$update = false;
				}
				if (!$embed_code) {
					$return['notices']['error'][] = __('You must enter an embed code or description', 'actionnetwork');
					$return['errors']['#actionnetwork_add_embed_code'] = true;
					$update = false;
				}
				
			} else {
				
				$embed_title = esc_attr($event['title']);
				$embed_date_string = date('Y-m-d', $event['start_date']);
				$embed_code = _actionnetwork_get_embed_code( $event, '', false );
				$location_object = unserialize( $event['location'] );
				$location = isset( $location_object->html ) ? $location_object->html : '';
				
			}
			
			if ($update) {	
				
				$event['title'] = $embed_title;
				$event['start_date'] = strtotime($embed_date_string);
				$event['modified_date'] = time();
				
				// parse embed code
				$embed_style_matched = preg_match_all("/<link href='https:\/\/actionnetwork\.org\/css\/style-embed(-whitelabel)?\.css' rel='stylesheet' type='text\/css' \/>/", $embed_code, $embed_style_matches, PREG_SET_ORDER);
				$embed_script_matched = preg_match_all("|<script src='https://actionnetwork\.org/widgets/v2/([a-z_]+)/([-a-z0-9]+)\?format=js&source=widget(&style=full)?'>|", $embed_code, $embed_script_matches, PREG_SET_ORDER);
				$embed_style = $embed_style_matched ? ( isset($embed_style_matches[0][1]) && $embed_style_matches[0][1] ? 'layout_only' : 'default' ) : 'no';
				$embed_size = isset($embed_script_matches[0][3]) && $embed_script_matches[0][3] ? 'full' : 'standard';
				$embed_field_name = 'embed_'.$embed_size.'_'.$embed_style.'_styles';
				
				// clear out all possible embed codes, in case it has changed
				$event['embed_standard_layout_only_styles'] = '';
				$event['embed_full_layout_only_styles'] = '';
				$event['embed_standard_no_styles'] = '';
				$event['embed_full_no_styles'] = '';
				$event['embed_standard_default_styles'] = '';
				$event['embed_full_default_styles'] = '';
				$event[$embed_field_name] = $embed_code;
				
				// serialize location
				$location_object = new stdClass();
				$location_object->html = $location;
				$event['location'] = serialize($location_object);
				
				$wpdb->update($table_name, $event, array( 'wp_id' => $embed_wp_id ) );
				$return['notices']['updated'][] = sprintf(
					/* translators: %s is title of event */
					__('%s has been updated', 'actionnetwork'), $embed_title
				);
				
			// otherwise, build an edit form	
			} else {
				
				$admin_url = admin_url('admin.php?page=actionnetwork');
				
				$text_actions = __('Actions', 'actionnetwork');
				$text_edit_event = __('Edit Event', 'actionnetwork');
				$text_settings = __('Settings', 'actionnetwork');
				
				$form_action = admin_url('admin.php?page=actionnetwork');
				$nonce_field = wp_nonce_field( 'actionnetwork_edit_event', 'actionnetwork_nonce_field', true, false );
				$text_title = __('Title', 'actionnetwork');
				$text_required = __('This field is required', 'actionnetwork');
				$error_title_required = isset($return['errors']['#actionnetwork_add_embed_title']) && $return['errors']['#actionnetwork_add_embed_title'] ? ' error' : '';
				$text_date = __('Date (if event)', 'actionnetwork');
				
				$text_embed_code = __('Embed Code/Event Description', 'actionnetwork');
				$error_embed_code_required = isset($return['errors']['#actionnetwork_add_embed_code']) && $return['errors']['#actionnetwork_add_embed_code'] ? ' error' : '';
				
				$text_location = __('Event location', 'actionnetwork');
				$text_location_description = __('Event location will only display on the upcoming events list; if you are entering a description above (instead of an embed code), make sure the location is included in the description as well');
				
				$text_update_event = __('Update event', 'actionnetwork');
				
				$return['edit_event_form'] = <<<EOHTML
				
				
			<h2 class="nav-tab-wrapper">
				<a href="$admin_url#actionnetwork-actions" class="nav-tab">
					$text_actions
				</a>
				<span class="nav-tab nav-tab-active">
					$text_edit_event
				</span>
				<a href="$admin_url#actionnetwork-settings" class="nav-tab">
					$text_settings
				</a>
			</h2>
			
				<h2>$text_edit_event</h2>
				<form method="post" action="$form_action">

					$nonce_field

					<input type="hidden" name="actionnetwork_admin_action" value="edit_event" />
					<input type="hidden" name="actionnetwork_event_wp_id" value="$embed_wp_id" />
					<input type="hidden" name="postback" value="1" />
 					<table class="form-table"><tbody>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_add_embed_title">$text_title <span class="required" title="$text_required">*</span></label></th>
							<td>
								<input id="actionnetwork_add_embed_title" name="actionnetwork_add_embed_title" class="required$error_title_required" type="text" value="$embed_title" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_add_embed_date">$text_date</label></th>
							<td>
								<input id="actionnetwork_add_embed_date" name="actionnetwork_add_embed_date" type="date" value="$embed_date_string" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_add_embed_code">$text_embed_code <span class="required" title="$text_required">*</span></label></th>
							<td>
								<textarea id="actionnetwork_add_embed_code" name="actionnetwork_add_embed_code" class="required$error_embed_code_required">$embed_code</textarea>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_add_location">$text_location</label></th>
							<td>
								<textarea id="actionnetwork_add_location" name="actionnetwork_add_location">$location</textarea>
								<p>$text_location_description</p>
							</td>
						</tr>
					</tbody></table>
					<p class="submit"><input type="submit" id="actionnetwork-add-embed-form-submit" class="button-primary" value="$text_update_event" /></p>
				</form>
EOHTML;
			
			}
			
			// update
			
		}
		break;
		
		case 'add_embed':
		$embed_title = isset($_REQUEST['actionnetwork_add_embed_title']) ? stripslashes($_REQUEST['actionnetwork_add_embed_title']) : '';
		$embed_date_string = isset($_REQUEST['actionnetwork_add_embed_date']) ? stripslashes($_REQUEST['actionnetwork_add_embed_date']) : '';
		$embed_code = isset($_REQUEST['actionnetwork_add_embed_code']) ? stripslashes($_REQUEST['actionnetwork_add_embed_code']) : '';
		$location = isset($_REQUEST['actionnetwork_add_location']) ? stripslashes($_REQUEST['actionnetwork_add_location']) : '';
		$embed_wp_id = isset($_REQUEST['actionnetwork_embed_wp_id']) ? (int) $_REQUEST['actionnetwork_embed_wp_id'] : 0;
		
		$embed_valid = true;

		// parse embed code
		$embed_style_matched = preg_match_all("/<link href='https:\/\/actionnetwork\.org\/css\/style-embed(-whitelabel)?\.css' rel='stylesheet' type='text\/css' \/>/", $embed_code, $embed_style_matches, PREG_SET_ORDER);
		$embed_script_matched = preg_match_all("|<script src='https://actionnetwork\.org/widgets/v2/([a-z_]+)/([-a-z0-9]+)\?format=js&source=widget(&style=full)?'>|", $embed_code, $embed_script_matches, PREG_SET_ORDER);

		$embed_style = $embed_style_matched ? ( isset($embed_style_matches[0][1]) && $embed_style_matches[0][1] ? 'layout_only' : 'default' ) : 'no';
		$embed_type = isset($embed_script_matches[0][1]) ? $embed_script_matches[0][1] : '';
		if ($embed_type == 'letter') { $embed_type = 'advocacy_campaign'; }
		if ($embed_type == 'fundraising') { $embed_type = 'fundraising_page'; }
		$embed_size = isset($embed_script_matches[0][3]) && $embed_script_matches[0][3] ? 'full' : 'standard';

		if (!$embed_title) {
			if (isset($embed_script_matches[0][2]) && $embed_script_matches[0][2]) {
				$embed_title = ucwords(str_replace('-',' ',$embed_script_matches[0][2]));
			} else {
				$return['notices']['error'][] = __('You must give your action a title', 'actionnetwork');
				$return['errors']['#actionnetwork_add_embed_title'] = true;
				$return['actionnetwork_add_embed_date'] = $embed_date_string;
				$return['actionnetwork_add_embed_code'] = $embed_code;
				$return['actionnetwork_add_location'] = $location;
				$embed_valid = false;
			}
		}
		if (!$embed_code) {
			// TODO: validate the embed code instead of just checking for it being non-empty
			$return['notices']['error'][] = __('You must enter an embed code or description', 'actionnetwork');
			$return['errors']['#actionnetwork_add_embed_code'] = true;
			$return['actionnetwork_add_embed_date'] = $embed_date_string;
			$return['actionnetwork_add_embed_title'] = $embed_title;
			$return['actionnetwork_add_location'] = $location;
			$embed_valid = false;
		}
		
		// if there is an $embed_date, but no embed type, treat as an event
		if ($embed_date_string && !$embed_type) {
			$embed_type = 'event';
		}
		
		// if there's no valid embed type, then the embed code is not valid
		if (!in_array( $embed_type, array(
				'petition','advocacy_campaign','event','ticketed_event','fundraising_page','form'
		))) {
			$return['notices']['error'][] = __('This does not seem to be a valid Action Network embed code', 'actionnetwork');
			$return['actionnetwork_add_embed_date'] = $embed_date_string;
			$return['actionnetwork_add_embed_title'] = $embed_title;
			$return['actionnetwork_add_embed_code'] = $embed_code;
			$return['actionnetwork_add_location'] = $location;
			$return['errors']['#actionnetwork_add_embed_code'] = true;
			$embed_valid = false;
		}
		
		if ($embed_valid) {
			
			// if the type is event or ticketed_event, give a warning if there is no start_date
			if ( ($embed_type == 'event' || $embed_type == 'ticketed_event') && !$embed_date_string) {
				$return['notices']['updated'][] = __('Notice: if you do not add a start date to your event, it will not display on the calendar widget', 'actionnetwork');
			}
		
			// if the type is *not* event or ticketed_event, and there is a date, give a warning that it won't be used
			if ( ($embed_type != 'event') && ($event_type != 'ticketed_event') && $embed_date_string) {
				$embed_date_string = '';
				$return['notices']['updated'][] = __('Notice: the date entered in the "start date" field is not used for actions that are not events or ticketed events', 'actionnetwork');
			}
			
			// serialize location
			$location_object = new stdClass();
			$location_object->html = $location;
			$location_serialized = serialize($location_object);
			
			// save to action
			$table_name = $wpdb->prefix . 'actionnetwork';
			$embed_field_name = 'embed_'.$embed_size.'_'.$embed_style.'_styles';

			$data = array(
				'type' => $embed_type,
				'title' => $embed_title,
				$embed_field_name => $embed_code,
				'location' => $location_serialized,
				'enabled' => 1,
				'created_date' => time(),
				'modified_date' => time(),
			);
			if ($embed_date_string) { $data['start_date'] = strtotime($embed_date_string); }


			$wpdb->insert($table_name, $data, array ( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' ) );
			
			$__copy = __('Copy', 'actionnetwork');
			$shortcode_copy = <<<EOHTML
<span class="copy-wrapper">
<input type="text" class="copy-text" readonly="readonly" id="shortcode-new-{$wpdb->insert_id}" value="[actionnetwork id={$wpdb->insert_id}]" /><button data-copytarget="#shortcode-new-{$wpdb->insert_id}" class="copy">$__copy</button>
</span>
EOHTML;

			$return['notices']['updated'][] = sprintf(
				/* translators: %s: The shortcode for the saved embed code */
				__('Action saved. Shortcode: %s', 'actionnetwork'),
				$shortcode_copy
			);

			$return['actionnetwork_add_embed_title'] = '';
			$return['actionnetwork_add_embed_code'] = '';

			$return['tab'] = 'actions';
		}
		break;
		
	}
	
	return $return;
}

/**
 * Administrative page
 */
function actionnetwork_admin_page() {

	global $actionnetwork_version;
	
	// defines Actionnetwork_Action_List class, which extends WP_List_Table
	require_once( plugin_dir_path( __FILE__ ) . 'includes/actionnetwork-action-list.class.php' );

	// load scripts and stylesheets
	wp_enqueue_style( 'actionnetwork-admin-css', plugins_url('admin.css', __FILE__) );
	wp_register_script( 'actionnetwork-admin-js', plugins_url('admin.js', __FILE__) );

	// localize script
	$translation_array = array(
		'copied' => __( 'Copied!', 'actionnetwork' ),
		'pressCtrlCToCopy' => __( 'please press Ctrl/Cmd+C to copy', 'actionnetwork' ),
		'clearResults' => __( 'clear results', 'actionnetwork' ),
		'changeAPIKey' => __( 'Change or delete API key', 'actionnetwork' ),
		'confirmChangeAPIKey' => __( 'Are you sure you want to change or delete the API key? Doing so means any actions you have synced via the API will be deleted.', 'actionnetwork' ),
		/* translators: %s: date of last sync */
		'lastSynced' => __( 'Last synced %s', 'actionnetwork' ),
	);
	wp_localize_script( 'actionnetwork-admin-js', 'actionnetworkText', $translation_array );
	wp_enqueue_script( 'actionnetwork-admin-js' );
	
	// This checks which tab we should display
	$tab = isset($_REQUEST['actionnetwork_tab']) ? $_REQUEST['actionnetwork_tab'] : 'actions';

	// This handles form submissions and prints any relevant notices from them
	$notices_html = '';
	$action_returns = array();
	if (isset($_REQUEST['actionnetwork_admin_action'])) {
		$action_returns = _actionnetwork_admin_handle_actions();
		if (isset($action_returns['notices'])) {
			if (isset($action_returns['notices']['error']) && is_array($action_returns['notices']['error'])) {
				foreach ($action_returns['notices']['error'] as $index => $notice) {
					$notices_html .= '<div class="error notice is-dismissible" id="actionnetwork-error-notice-'.$index.'"><p>'.$notice.'</p></div>';
				}
			}
			if (isset($action_returns['notices']['updated']) && is_array($action_returns['notices']['updated'])) {
				foreach ($action_returns['notices']['updated'] as $index => $notice) {
					$notices_html .= '<div class="updated notice is-dismissible" id="actionnetwork-update-notice-'.$index.'"><p>'.$notice.'</p></div>';
				}
			}

		}
		if (isset($action_returns['tab'])) { $tab = $action_returns['tab']; }
	}

	// This prepares this list
	$action_list = new Actionnetwork_Action_List();
	$action_list->prepare_items();
	if (isset($action_list->notices)) {
		if (isset($action_list->notices['error']) && is_array($action_list->notices['error'])) {
			foreach ($action_list->notices['error'] as $index => $notice) {
				$notices_html .= '<div class="error notice is-dismissible" id="actionnetwork-list-error-notice-'.$index.'"><p>'.$notice.'</p></div>';
			}
		}
		if (isset($action_list->notices['updated']) && is_array($action_list->notices['updated'])) {
			foreach ($action_list->notices['updated'] as $index => $notice) {
				$notices_html .= '<div class="updated notice is-dismissible" id="actionnetwork-list-update-notice-'.$index.'"><p>'.$notice.'</p></div>';
			}
		}
	}

	// get API Key
	$actionnetwork_api_key = get_option('actionnetwork_api_key');
	
	// get queue status - allow action_returns to override the option because
	// we've started the queue processing in a separate process, which might not
	// have reset the option yet
	$queue_status = isset($action_returns['queue_status']) ? $action_returns['queue_status'] : get_option('actionnetwork_queue_status', 'empty');

	?>
	
	<div class='wrap'>
		
		<h1><img src="<?php echo plugins_url('logo-action-network.png', __FILE__); ?>" /> Action Network
			<?php if (strpos($actionnetwork_version,'beta')): ?>
				<span class="subtitle">BETA</span>
			<?php endif; ?>
		</h1>
		
		<div class="wrap-inner">

			<?php if ($notices_html) { echo $notices_html; } ?>

				<?php if ($actionnetwork_api_key) : ?>
				<form method="post" action="" id="actionnetwork-update-sync" class="alignright">
					<?php
						// nonce field for form submission
						wp_nonce_field( 'actionnetwork_update_sync', 'actionnetwork_nonce_field' );
						
						// nonce field for ajax requests
						wp_nonce_field( 'actionnetwork_get_queue_status', 'actionnetwork_ajax_nonce', false );
					?>
					<input type="hidden" name="actionnetwork_admin_action" id="actionnetwork-sync-action" value="update_sync" />
					<input type="submit" id="actionnetwork-update-sync-submit" class="button" value="<?php _e('Full API Sync', 'actionnetwork'); ?>" <?php
						// if we're currently processing a queue, disable this button
						if ($queue_status == 'processing') { echo 'disabled="disabled" ';}
					?>/>
					<div class="last-sync"><?php
						$last_updated = get_option('actionnetwork_cache_timestamp', 0);
						if ($queue_status == 'processing') {
							_e('API Sync queue is processing');
						} elseif ($last_updated) {
							printf(
								/* translators: %s: date of last sync */
								__('Last synced %s', 'actionnetwork'),
								date('n/j/Y g:ia', $last_updated)
							);
						} else {
							_e('This API key has not been synced', 'actionnetwork');
						}
					?></div>
				</form>
				<?php endif; ?>
				
			<?php 	// if there is an edit form, just display that and quit
				if (isset($action_returns['edit_event_form']) && $action_returns['edit_event_form']) {
					echo $action_returns['edit_event_form'];
					echo "</div> <!-- /.wrap-inner -->\n";
					jk_branding( 'Action Network', 'https://github.com/jkissam/actionnetwork' );
					echo "</div> <!-- /.wrap -->\n";
					return;
				}
			?>
			
			<h2 class="nav-tab-wrapper">
				<a href="#actionnetwork-actions" class="nav-tab<?php echo ($tab == 'actions') ? ' nav-tab-active' : ''; ?>">
					<?php _e('Actions', 'actionnetwork'); ?>
				</a>
				<a href="#actionnetwork-add" class="nav-tab<?php echo ($tab == 'add') ? ' nav-tab-active' : ''; ?>">
					<?php _e('Add Action', 'actionnetwork'); ?>
				</a>
				<a href="#actionnetwork-settings" class="nav-tab<?php echo ($tab == 'settings') ? ' nav-tab-active' : ''; ?>">
					<?php _e('Settings', 'actionnetwork'); ?>
				</a>
			</h2>
			
			<?php /* list actions */ ?>
			<div class="actionnetwork-admin-tab<?php echo ($tab == 'actions') ? ' actionnetwork-admin-tab-active' : ''; ?>" id="actionnetwork-actions">
				<h2>
					<?php _e('Your Actions', 'actionnetwork'); ?>
					<?php if (isset($_REQUEST['search']) && $_REQUEST['search']) {
						echo '<span class="subtitle search-results-title">';
						/* translators: %s: the term being searched for */
						printf( __('Search results for "%s"', 'actionnetwork'),  $_REQUEST['search'] );
						echo '</span>';
					} ?>
				</h2>

				<?php
					$searchtype = isset($_REQUEST['type']) && isset($action_list->action_type_plurals[$_REQUEST['type']]) ? $action_list->action_type_plurals[$_REQUEST['type']] : __('Actions', 'actionnetwork');
					$searchtext = sprintf(
						/* translators: %s: "actions", or plural of action type, which will be searched) */
						__('Search %s', 'actionnetwork'),
						$searchtype
					);
				?>

				<form id="actionnetwork-actions-filter" method="get">
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
					<p class="search-box">
						<label class="screen-reader-text" for="action-search-input"><?php echo $searchtext; ?>:</label>					
						<input type="search" id="action-search-input" name="search" value="<?php echo isset($_REQUEST['search']) ? $_REQUEST['search'] : '' ?>" placeholder="<?php _e('Search','actionnetwork'); ?>" />
						<input type="submit" id="action-search-submit" class="button" value="<?php echo $searchtext; ?>">
					</p>
					<?php $action_list->display(); ?>
				</form>
				<div id="shortcode-options">
					<p><?php _e('Actionnetwork shortcodes for actions synced via the API can take two additional attributes besides the required <strong>id</strong> attribute:', 'actionnetwork'); ?></p>
					<ul><li><?php _e('The <strong>size</strong> attribute can be set to <strong>full</strong> or <strong>standard</strong> (standard is the default)', 'actionnetwork'); ?></li>
					<li><?php _e('The <strong>style</strong> attribute can be set to <strong>default</strong>, <strong>layout_only</strong>, or <strong>no</strong> (layout_only is the default)', 'actionnetwork'); ?></li></ul>
				</div>
			</div>
		
			<?php /* add action from embed code */ ?>
			<div class="actionnetwork-admin-tab<?php echo ($tab == 'add') ? ' actionnetwork-admin-tab-active' : ''; ?>" id="actionnetwork-add">
				<h2><?php _e('Add action', 'actionnetwork'); ?></h2>
				<form method="post" action="">
					<?php
						$actionnetwork_add_embed_title =
							isset($action_returns['actionnetwork_add_embed_title']) ?
							$action_returns['actionnetwork_add_embed_title'] : '';
						$actionnetwork_add_embed_date =
							isset($action_returns['actionnetwork_add_embed_date']) ?
							$action_returns['actionnetwork_add_embed_date'] : '';
						$actionnetwork_add_embed_code =
							isset($action_returns['actionnetwork_add_embed_code']) ?
							$action_returns['actionnetwork_add_embed_code'] : '';
						$actionnetwork_add_location =
							isset($action_returns['actionnetwork_add_location']) ?
							$action_returns['actionnetwork_add_location'] : '';
						wp_nonce_field( 'actionnetwork_add_embed', 'actionnetwork_nonce_field' );
					?>
					<input type="hidden" name="actionnetwork_admin_action" value="add_embed" />
					<input type="hidden" name="actionnetwork_tab" value="add" />
 					<table class="form-table"><tbody>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_add_embed_title"><?php _e('Title', 'actionnetwork'); ?> <span class="required" title="<?php _e('This field is required', 'actionnetwork'); ?>">*</span></label></th>
							<td>
								<input id="actionnetwork_add_embed_title" name="actionnetwork_add_embed_title" class="required<?php
									echo (isset($action_returns['errors']['#actionnetwork_add_embed_title']) && $action_returns['errors']['#actionnetwork_add_embed_title']) ? ' error' : '';
								?>" type="text" value="<?php esc_attr_e($actionnetwork_add_embed_title); ?>" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_add_embed_date"><?php _e('Date (if event)', 'actionnetwork'); ?></label></th>
							<td>
								<input id="actionnetwork_add_embed_date" name="actionnetwork_add_embed_date" type="date" class="required<?php
									echo (isset($action_returns['errors']['#actionnetwork_add_embed_date']) && $action_returns['errors']['#actionnetwork_add_embed_date']) ? ' error' : '';
								?>" type="text" value="<?php esc_attr_e($actionnetwork_add_embed_date); ?>" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_add_embed_code"><?php _e('Embed Code/Event Description', 'actionnetwork'); ?> <span class="required" title="<?php _e('This field is required', 'actionnetwork'); ?>">*</span></label></th>
							<td>
								<textarea id="actionnetwork_add_embed_code" name="actionnetwork_add_embed_code" class="required<?php
									echo (isset($action_returns['errors']['#actionnetwork_add_embed_code']) && $action_returns['errors']['#actionnetwork_add_embed_code']) ? ' error' : '';
								?>"><?php echo $actionnetwork_add_embed_code; ?></textarea>
							</td>
						</tr>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_add_location"><?php _e('Event location', 'actionnetwork'); ?></label></th>
							<td>
								<textarea id="actionnetwork_add_location" name="actionnetwork_add_location"><?php echo $actionnetwork_add_location; ?></textarea>
								<p><?php _e('Event location will only display on the upcoming events list; if you are entering a description above (instead of an embed code), make sure the location is included in the description as well'); ?></p>
							</td>
						</tr>
					</tbody></table>
					<p class="submit"><input type="submit" id="actionnetwork-add-embed-form-submit" class="button-primary" value="<?php _e('Add Action', 'actionnetwork'); ?>" /></p>
				</form>
			</div>
			
			<?php /* options settings */ ?>
			<div class="actionnetwork-admin-tab<?php echo ($tab == 'settings') ? ' actionnetwork-admin-tab-active' : ''; ?>" id="actionnetwork-settings">
				<h2><?php _e('Plugin Settings', 'actionnetwork'); ?></h2>
				<form method="post" action="">
					<?php
						wp_nonce_field( 'actionnetwork_update_api_key', 'actionnetwork_nonce_field' );
					?>
					<input type="hidden" name="actionnetwork_admin_action" value="update_api_key" />
					<input type="hidden" name="actionnetwork_tab" value="settings" />

					<table class="form-table"><tbody>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_api_key"><?php _e('Action Network API Key', 'actionnetwork'); ?></label></th>
							<td>
								<input id="actionnetwork_api_key" name="actionnetwork_api_key" type="text" value="<?php esc_attr_e($actionnetwork_api_key); ?>" />
							</td>
						</tr>
					</tbody></table>
					<p class="submit"><input type="submit" id="actionnetwork-options-form-submit" class="button-primary" value="<?php _e('Save Settings', 'actionnetwork'); ?>" /></p>
				</form>
			</div>
		
		</div> <!-- /.wrap-inner -->

		<?php jk_branding( 'Action Network', 'https://github.com/jkissam/actionnetwork' ); ?>

	</div> <!-- /.wrap -->
	<?php
}

/**
 * Help for administrative page
 */
function actionnetwork_admin_add_help() {
	$screen = get_current_screen();
	
	$screen->add_help_tab( array(
		'id'       => 'actionnetwork-help-overview',
		'title'    => __( 'Overview', 'actionnetwork' ),
		'content'  => __('
<ul>
<li>Create a Wordpress shortcode from any Action Network embed code by using the "Add New Action" tab.</li>
<li>Manage your saved embed codes using the Wordpress backend. Supports sorting by title, type and last modified date, and provides a search function.</li>
<li>If you are an <a href="https://actionnetwork.org/partnerships">Action Network Partner</a>, use your API key to sync all of your actions from Action Network to Wordpress by entering it in the "Settings" tab.</li>
</ul>
		', 'actionnetwork'),
	));
	
	$screen->add_help_tab( array(
		'id'       => 'actionnetwork-help-shortcodes',
		'title'    => __( 'Shortcode options', 'actionnetwork' ),
		'content'  => '<p>'.__('Actionnetwork shortcodes for actions synced via the API can take two additional attributes besides the required <strong>id</strong> attribute:', 'actionnetwork') . '</p><ul><li>' . __('The <strong>size</strong> attribute can be set to <strong>full</strong> or <strong>standard</strong> (standard is the default)', 'actionnetwork') . '</li><li>' . __('The <strong>style</strong> attribute can be set to <strong>default</strong>, <strong>layout_only</strong>, or <strong>no</strong> (layout_only is the default)', 'actionnetwork') . '</li></ul>',
	));
	
	$screen->add_help_tab( array(
		'id'       => 'actionnetwork-help-calendar-shortcode',
		'title'    => __( 'Calendar shortcode attributes', 'actionnetwork' ),
		'content'  => '<p>'.__('The [actionnetwork_calendar] shortcode will display a list of upcoming events, and can take the following attributes:', 'actionnetwork') . '</p><ul><li>' . __('The <strong>size</strong> attribute can be set to <strong>full</strong> or <strong>standard</strong> (standard is the default)', 'actionnetwork') . '</li><li>' . __('The <strong>style</strong> attribute can be set to <strong>default</strong>, <strong>layout_only</strong>, or <strong>no</strong> (layout_only is the default)', 'actionnetwork') . '</li></ul>',
	));
	
	$screen->add_help_tab( array(
		'id'       => 'actionnetwork-help-calendar-shortcode-template',
		'title'    => __( 'Calendar shortcode template', 'actionnetwork' ),
		'content'  => '<p>'.__('The [actionnetwork_calendar] shortcode can be templated as follows:', 'actionnetwork') . '</p><ul><li>' . __('The <strong>size</strong> attribute can be set to <strong>full</strong> or <strong>standard</strong> (standard is the default)', 'actionnetwork') . '</li><li>' . __('The <strong>style</strong> attribute can be set to <strong>default</strong>, <strong>layout_only</strong>, or <strong>no</strong> (layout_only is the default)', 'actionnetwork') . '</li></ul>',
	));
	
	$screen->add_help_tab( array(
		'id'       => 'actionnetwork-help-ticketed-events',
		'title'    => __( 'Ticketed Events', 'actionnetwork' ),
		'content'  => '<p>' . __('Ticketed events are not currently supported by Action Network\'s API, and so this plugin will not sync them. If you want to use a ticketed event, you will need to add the embed code yourself using the "Add New Action" tab.', 'actionnetwork') . '</p>',
	));
	
}

