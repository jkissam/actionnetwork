<?php
/*
 * @package ActionNetwork
 * @version 1.0-beta
 *
 * Plugin Name: Action Network
 * Description: Integrates with Action Network (actionnetwork.org)'s API to provide action embed codes as shortcodes
 * Author: Jonathan Kissam
 * Text Domain: actionnetwork
 * Domain Path: /languages
 * Version: 1.0-beta
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
$actionnetwork_version = '1.0-beta';
global $actionnetwork_db_version;
$actionnetwork_db_version = '1.0.4';

function actionnetwork_install() {

	global $wpdb;
	global $actionnetwork_version;
	global $actionnetwork_db_version;
	$installed_version = get_option( 'actionnetwork_version' );
	$installed_db_version = get_option( 'actionnetwork_db_version' );

	$notices = get_option('actionnetwork_deferred_admin_notices', array());

	if ($installed_version != $actionnetwork_version) {

		// test for particular updates here

		// on first installation
		if (!$installed_version) {
			$install_notice = jk_branding( 'Action Network', 'https://github.com/jkissam/actionnetwork' , true, false);
			// jk_branding will not return the notice if plugin was deleted and then re-installed
			if ($install_notice) {
				$notices[] = $install_notice;
			}
		}

		update_option( 'actionnetwork_version', $actionnetwork_version );
	}

	if ($installed_db_version != $actionnetwork_db_version) {
		
		// test for particular updates
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
			enabled tinyint(1) DEFAULT 0 NOT NULL,
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

	// remove database table
	$table_name = $wpdb->prefix . 'actionnetwork';
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
 * Shortcode
 * Since the way Action Network's embed codes work
 * does not support multiple embeds on a single page,
 * only allow the first shortcode on a given page load
 */
global $actionnetwork_shortcode_count;
$actionnetwork_shortcode_count = 0;

function actionnetwork_shortcode( $atts ) {
	global $wpdb;
	global $actionnetwork_shortcode_count;

	if ($actionnetwork_shortcode_count) { return; }

	$id = isset($atts['id']) ? (int) $atts['id'] : null;
	$size = isset($atts['size']) ? $atts['size'] : 'standard';
	$style = isset($atts['style']) ? $atts['style'] : 'default';

	if (!$id) { return; }

	// validate size and style
	if (!in_array($size, array('standard', 'full'))) { $size = 'standard'; }
	if (!in_array($style, array('default', 'layout_only', 'no'))) { $style = 'default'; }
	
	$sql = "SELECT * FROM {$wpdb->prefix}actionnetwork WHERE wp_id=".$id;
	$action = $wpdb->get_row( $sql, ARRAY_A );

	$embed_field_name = 'embed_'.$size.'_'.$style.'_styles';
	if ($action[$embed_field_name]) { return $action[$embed_field_name]; }

	$embed_fields = array(
		'embed_standard_layout_only_styles',
		'embed_full_layout_only_styles',
		'embed_standard_no_styles',
		'embed_full_no_styles',
		'embed_standard_default_styles',
		'embed_full_default_styles',
	);

	foreach( $embed_fields as $embed_field_name) {
		if ($action[$embed_field_name]) {
			$actionnetwork_shortcode_count++;
			return $action[$embed_field_name];
		}
	}

}
add_shortcode( 'actionnetwork', 'actionnetwork_shortcode' );

/**
 * Set up admin menu structure
 * https://developer.wordpress.org/reference/functions/add_menu_page/
 */
function actionnetwork_admin_menu() {
	$actionnetwork_admin_menu_hook = add_menu_page( __('Administer Action Network', 'actionnetwork'), 'Action Network', 'manage_options', 'actionnetwork', 'actionnetwork_admin_page', plugins_url('icon-action-network.png', __FILE__), 21);
	// add_action( 'load-' . $actionnetwork_admin_menu_hook, 'actionnetwork_admin_add_help' );
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
	
	if ( !isset($_REQUEST['actionnetwork_admin_action']) || !check_admin_referer(
		'actionnetwork_'.$_REQUEST['actionnetwork_admin_action'], 'actionnetwork_nonce_field'
		) ) {
			return false;
	}
	
	switch ($_REQUEST['actionnetwork_admin_action']) {
	
		case 'update_api_key':
		
		// TODO: clean this value!
		$actionnetwork_api_key = $_REQUEST['actionnetwork_api_key'];
		$queue_status = get_option( 'actionnetwork_queue_status', 'empty' );
		
		if (get_option('actionnetwork_api_key', null) !== $actionnetwork_api_key) {
			
			// don't allow API Key to be changed if a sync queue is processing
			if ($queue_status != 'empty') {
				$return['notices']['error'] = __( 'Cannot change API key while a sync queue is processing', 'actionnetwork' );
			} else {
			
				update_option('actionnetwork_api_key', $actionnetwork_api_key);
				update_option('actionnetwork_cache_timestamp', 0);
				$deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}actionnetwork WHERE an_id != ''");

				if ($actionnetwork_api_key) {
					$return['notices']['updated'][] = $deleted ? __('API key has been updated and actions synced via previous API key have been removed', 'actionnetwork') : __('API key has been updated', 'actionnetwork');
				} else {
					$return['notices']['updated'][] = $deleted ? __('API key and actions synced via API have been removed', 'actionnetwork') : __('API key has been removed', 'actionnetwork');
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
		
		case 'add_embed':
		$embed_title = isset($_REQUEST['actionnetwork_add_embed_title']) ? stripslashes($_REQUEST['actionnetwork_add_embed_title']) : '';
		$embed_code = isset($_REQUEST['actionnetwork_add_embed_code']) ? stripslashes($_REQUEST['actionnetwork_add_embed_code']) : '';

		// parse embed code
		$embed_style_matched = preg_match_all("/<link href='https:\/\/actionnetwork\.org\/css\/style-embed(-whitelabel)?\.css' rel='stylesheet' type='text\/css' \/>/", $embed_code, $embed_style_matches, PREG_SET_ORDER);
		$embed_script_matched = preg_match_all("|<script src='https://actionnetwork\.org/widgets/v2/([a-z_]+)/([-a-z0-9]+)\?format=js&source=widget(&style=full)?'>|", $embed_code, $embed_script_matches, PREG_SET_ORDER);

		$embed_style = $embed_style_matched ? ( isset($embed_style_matches[0][1]) && $embed_style_matches[0][1] ? 'layout_only' : 'default' ) : 'no';
		$embed_type = isset($embed_script_matches[0][1]) ? $embed_script_matches[0][1] : '';
		if ($embed_type == 'letter') { $embed_type = 'advocacy_campaign'; }
		$embed_size = isset($embed_script_matches[0][3]) && $embed_script_matches[0][3] ? 'full' : 'standard';

		if (!$embed_title) {
			if (isset($embed_script_matches[0][2]) && $embed_script_matches[0][2]) {
				$embed_title = ucwords(str_replace('-',' ',$embed_script_matches[0][2]));
			} else {
				$return['notices']['error'][] = __('You must give your action a title', 'actionnetwork');
				$return['errors']['#actionnetwork_add_embed_title'] = true;
				$return['actionnetwork_add_embed_code'] = $embed_code;
			}
		}
		if (!$embed_code) {
			// TODO: validate the embed code instead of just checking for it being non-empty
			$return['notices']['error'][] = __('You must enter an embed code', 'actionnetwork');
			$return['errors']['#actionnetwork_add_embed_code'] = true;
			$return['actionnetwork_add_embed_title'] = $embed_title;
		}
		if ($embed_title && $embed_code) {
			// save to action
			$table_name = $wpdb->prefix . 'actionnetwork';
			$embed_field_name = 'embed_'.$embed_size.'_'.$embed_style.'_styles';

			$data = array(
				'type' => $embed_type,
				'title' => $embed_title,
				$embed_field_name => $embed_code,
				'enabled' => 1,
				'created_date' => time(),
				'modified_date' => time(),
			);

			$wpdb->insert($table_name, $data, array ( '%s', '%s', '%s', '%d', '%d', '%d' ) );
			$__copy = __('Copy', 'actionnetwork');
			$shortcode_copy = <<<EOHTML
<span class="copy-wrapper">
<input type="text" class="copy-text" readonly="readonly" id="shortcode-new-{$wpdb->insert_id}" value="[actionnetwork id={$wpdb->insert_id}]" /><button data-copytarget="#shortcode-new-{$wpdb->insert_id}" class="copy">$__copy</button>
</span>
EOHTML;

			$return['notices']['updated'][] = sprintf(
				/* translators: %s: The shortcode for the saved embed code */
				__('Embed code saved to your actions. Shortcode: %s', 'actionnetwork'),
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
		foreach ($action_list->notices['error'] as $index->$notice) {
			$notices_html .= '<div class="error notice is-dismissible" id="actionnetwork-list-error-notice-'.$index.'"><p>'.$notice.'</p></div>';
		}
		foreach ($action_list->notices['updated'] as $index->$notice) {
			$notices_html .= '<div class="updated notice is-dismissible" id="actionnetwork-list-update-notice-'.$index.'"><p>'.$notice.'</p></div>';
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
					<input type="hidden" name="actionnetwork_admin_action" value="update_sync" />
					<input type="submit" id="actionnetwork-update-sync-submit" class="button" value="<?php _e('Update API Sync', 'actionnetwork'); ?>" <?php
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
			
			<h2 class="nav-tab-wrapper">
				<a href="#actionnetwork-actions" class="nav-tab<?php echo ($tab == 'actions') ? ' nav-tab-active' : ''; ?>">
					<?php _e('Actions', 'actionnetwork'); ?>
				</a>
				<a href="#actionnetwork-add" class="nav-tab<?php echo ($tab == 'add') ? ' nav-tab-active' : ''; ?>">
					<?php _e('Add New Action', 'actionnetwork'); ?>
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
			</div>
		
			<?php /* add action from embed code */ ?>
			<div class="actionnetwork-admin-tab<?php echo ($tab == 'add') ? ' actionnetwork-admin-tab-active' : ''; ?>" id="actionnetwork-add">
				<h2><?php _e('Add action from embed code', 'actionnetwork'); ?></h2>
				<form method="post" action="">
					<?php
						$actionnetwork_add_embed_title =
							isset($action_returns['actionnetwork_add_embed_title']) ?
							$action_returns['actionnetwork_add_embed_title'] : '';
						$actionnetwork_add_embed_code =
							isset($action_returns['actionnetwork_add_embed_code']) ?
							$action_returns['actionnetwork_add_embed_code'] : '';
						wp_nonce_field( 'actionnetwork_add_embed', 'actionnetwork_nonce_field' );
					?>
					<input type="hidden" name="actionnetwork_admin_action" value="add_embed" />
					<input type="hidden" name="actionnetwork_tab" value="add" />
					<table class="form-table"><tbody>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_add_embed_title"><?php _e('Action Title'); ?> <span class="required" title="<?php _e('This field is required', 'actionnetwork'); ?>">*</span></label></th>
							<td>
								<input id="actionnetwork_add_embed_title" name="actionnetwork_add_embed_title" class="required<?php
									echo (isset($action_returns['errors']['#actionnetwork_add_embed_title']) && $action_returns['errors']['#actionnetwork_add_embed_title']) ? ' error' : '';
								?>" type="text" value="<?php esc_attr_e($actionnetwork_add_embed_title); ?>" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="actionnetwork_add_embed_code"><?php _e('Action Embed Code'); ?> <span class="required" title="<?php _e('This field is required', 'actionnetwork'); ?>">*</span></label></th>
							<td>
								<textarea id="actionnetwork_add_embed_code" name="actionnetwork_add_embed_code" class="required<?php
									echo (isset($action_returns['errors']['#actionnetwork_add_embed_code']) && $action_returns['errors']['#actionnetwork_add_embed_code']) ? ' error' : '';
								?>"><?php echo $actionnetwork_add_embed_code; ?></textarea>
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
<p>Placeholder for documentation</p>
		', 'actionnetwork'),
	));
}

/**
 * Sync actions from API - should be able to remove everything below here if the Actionnetwork_Sync class works
 */
global $actionnetwork_sync_report;
$actionnetwork_sync_report = array(
	'updated' => 0,
	'inserted' => 0,
	'deleted' => 0,
);
function _actionnetwork_sync_actions(){

	global $wpdb;
	global $actionnetwork_sync_report;

	if ($actionnetwork_api_key = get_option('actionnetwork_api_key')) {

		$actionnetwork_sync_report = array(
			'updated' => 0,
			'inserted' => 0,
			'deleted' => 0,
		);

		// load all the content into simple id => title arrays
		$actionnetwork = new ActionNetwork($actionnetwork_api_key);

		// mark all existing API-synced actions for deletion (any that are still synced will be un-marked)
		$wpdb->query("UPDATE {$wpdb->prefix}actionnetwork SET enabled=-1 WHERE an_id != ''");

		$endpoints = array( 'petitions', 'events', 'fundraising_pages', 'advocacy_campaigns', 'forms' );
		foreach ($endpoints as $endpoint) {
			$actionnetwork->traverseFullCollection( $endpoint, '_actionnetwork_process_api_response' );
		}
		// TODO: initiate batch processor here...

		// now remove all API-synced action that are still marked for deletion
		$actionnetwork_sync_report['deleted'] = $wpdb->query("DELETE FROM {$wpdb->prefix}actionnetwork WHERE an_id != '' AND enabled=-1");

		// update synced timestamp
		update_option('actionnetwork_cache_timestamp', time());

		// return a notice that sync has been completed, report number of updated/inserted/deleted actions
		return sprintf(
			/* translators: 1: Number of updated actions, 2: Number of inserted actions, 3: Number of deleted actions */
			__('API sync has been completed. Updated: %1$d. Inserted: %2$d. Deleted: %3$d.', 'actionnetwork'),
			$actionnetwork_sync_report['updated'],
			$actionnetwork_sync_report['inserted'],
			$actionnetwork_sync_report['deleted']
		);
	}
}

// TODO: have this just add the info to a batch processor
function _actionnetwork_process_api_response($resource, $endpoint, $actionnetwork, $index, $total) {
	
	global $wpdb;
	global $actionnetwork_sync_report;
	
	$data = array();
	
	// load an_id, created_date, modified_date, name, title, start_date into $data
	$data['an_id'] = $actionnetwork->getResourceId($resource);
	$data['created_date'] = isset($resource->created_date) ? strtotime($resource->created_date) : null;
	$data['modified_date'] = isset($resource->modified_date) ? strtotime($resource->modified_date) : null;
	$data['start_date'] = isset($resource->start_date) ? strtotime($resource->start_date) : null;
	$data['browser_url'] = isset($resource->browser_url) ? $resource->browser_url : '';
	$data['title'] = isset($resource->title) ? $resource->title : '';
	$data['name'] = isset($resource->name) ? $resource->name : '';
	
	// set $data['enabled'] to 0 if:
	// * action_network:hidden is true
	// * status is "cancelled"
	// * event has a start_date that is past
	$data['enabled'] = 1;
	if (isset($resource->{'action_network:hidden'}) && ($resource->{'action_network:hidden'} == true)) {
		$data['enabled'] = 0;
	}
	if (isset($resource->status) && ($resource->status == 'cancelled')) {
		$data['enabled'] = 0;
	}
	if ($data['start_date'] && ($data['start_date'] < time())) {
		$data['enabled'] = 0;
	}
	
	// use endpoint (minus pluralizing s) to set $data['type']
	$data['type'] = substr($endpoint,0,strlen($endpoint) - 1);

	// check if action exists in database
	$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}actionnetwork WHERE an_id='{$data['an_id']}'";
	$count = $wpdb->get_var( $sql );
	if ($count) {
		// if modified_date is more recent than latest api sync, get embed codes, update
		$last_updated = get_option('actionnetwork_cache_timestamp', 0);
		if ($last_updated < $data['modified_date']) {
			// $embed_codes = _actionnetwork_get_embed_codes($resource, $endpoint, $actionnetwork, $data['an_id']);
			$embed_codes = $actionnetwork->getEmbedCodes($resource, true);
			$data = array_merge($data, _actionnetwork_clean_embed_codes($embed_codes));
			$wpdb->update(
				$wpdb->prefix.'actionnetwork',
				$data,
				array( 'an_id' => $data['an_id'] )
			);
			$actionnetwork_sync_report['updated']++;
			
		// otherwise just reset the 'enabled' field (to prevent deletion, and hide events whose start date has passed)
		} else {
			$wpdb->update(
				$wpdb->prefix.'actionnetwork',
				array( 'enabled' => $data['enabled'] ),
				array( 'an_id' => $data['an_id'] )
			);
		}

	} else {
		// if action *doesn't* exist in the database, get embed codes, insert
		// $embed_codes = _actionnetwork_get_embed_codes($resource, $endpoint, $actionnetwork, $data['an_id']);
		$embed_codes = $actionnetwork->getEmbedCodes($resource, true);
		$data = array_merge($data, _actionnetwork_clean_embed_codes($embed_codes));
		$wpdb->insert(
			$wpdb->prefix.'actionnetwork',
			$data
		);
		$actionnetwork_sync_report['inserted']++;
	}
}

function _actionnetwork_clean_embed_codes($embed_codes_raw) {
	$embed_fields = array(
		'embed_standard_layout_only_styles',
		'embed_full_layout_only_styles',
		'embed_standard_no_styles',
		'embed_full_no_styles',
		'embed_standard_default_styles',
		'embed_full_default_styles',
	);
	foreach ($embed_fields as $embed_field) {
		$embed_codes[$embed_field] = isset($embed_codes_raw[$embed_field]) ? $embed_codes_raw[$embed_field] : '';
	}
	return $embed_codes;
}

// shouldn't be needed
/*
function _actionnetwork_get_embed_codes($resource, $endpoint, $actionnetwork, $id){
	$embed_endpoint = isset($resource->_links->{'action_network:embed'}->href) ? $resource->_links->{'action_network:embed'}->href : '';
	if ($embed_endpoint) {
		$embed_endpoint = str_replace('https://actionnetwork.org/api/v2/','',$embed_endpoint);
	} else {
		$embed_endpoint = $endpoint.'/'.$id.'/embed';
	}
	$embed_codes = array();
	$embeds = $actionnetwork->call($embed_endpoint);
	$embed_fields = array(
		'embed_standard_layout_only_styles',
		'embed_full_layout_only_styles',
		'embed_standard_no_styles',
		'embed_full_no_styles',
		'embed_standard_default_styles',
		'embed_full_default_styles',
	);
	foreach ($embed_fields as $embed_field) {
		$embed_codes[$embed_field] = isset($embeds->$embed_field) ? $embeds->$embed_field : '';
	}
	return $embed_codes;
}
*/

