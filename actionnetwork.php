<?php
/*
 * @package ActionNetwork
 * @version 1.0-alpha3
 *
 * Plugin Name: Action Network
 * Description: Integrations with Action Network (actionnetwork.org)'s API to provide action embed codes as shortcodes
 * Author: Jonathan Kissam
 * Text Domain: heckle-window
 * Domain Path: /languages
 * Version: 1.0-alpha3
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

/**
 * Set up options
 */
add_option( 'actionnetwork_api_key', null );

/**
 * Installation, database setup
 */
global $actionnetwork_version;
$actionnetwork_version = '1.0-alpha3';
global $actionnetwork_db_version;
$actionnetwork_db_version = '1.0';

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

		$table_name = $wpdb->prefix . 'actionnetwork';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			wp_id mediumint(9) NOT NULL AUTO_INCREMENT,
			an_id varchar(64) DEFAULT '' NOT NULL,
			type varchar(16) DEFAULT '' NOT NULL, 
			name varchar(255) DEFAULT '' NOT NULL,
			title varchar (255) DEFAULT '' NOT NULL,
			embed_standard_default_styles text NOT NULL,
			embed_standard_layout_only_styles text NOT NULL,
			embed_standard_no_styles text NOT NULL,
			embed_full_default_styles text NOT NULL,
			embed_full_layout_only_styles text NOT NULL,
			embed_full_no_styles text NOT NULL,
			enabled tinyint(1) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (wp_id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		update_option( 'actionnetwork_db_version', $actionnetwork_db_version );

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
 * Since Action Network's embed codes share a unique ID,
 * only allow the first shortcode on a given page
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
	$actionnetwork_admin_menu_hook = add_menu_page( __('Administer Action Network', 'actionnetwork'), 'Action Network', 'manage_options', 'actionnetwork', 'actionnetwork_admin_page', 'dashicons-megaphone', 21);
	// add_action( 'load-' . $actionnetwork_admin_menu_hook, 'actionnetwork_admin_add_help' );
	/*
	// customize the first sub-menu link
	$actionnetwork_admin_menu_hook = add_submenu_page( __('Administer Action Network'), __('Administer'), 'manage_options', 'actionnetwork-menu', 'actionnetwork_admin_page');
	add_action( 'load-' . $actionnetwork_admin_menu_hook, 'actionnetwork_admin_add_help' );
	*/
}
add_action( 'admin_menu', 'actionnetwork_admin_menu' );

/**
 * Handle administrative actions
 */
function actionnetwork_admin_handle_actions(){

	global $wpdb;
	
	if ( !isset($_REQUEST['actionnetwork_admin_action']) || !check_admin_referer(
		'actionnetwork_'.$_REQUEST['actionnetwork_admin_action'], 'actionnetwork_nonce_field'
		) ) {
			return false;
	}
	
	switch ($_REQUEST['actionnetwork_admin_action']) {
	
		case 'update_api_key':
		$actionnetwork_api_key = $_REQUEST['actionnetwork_api_key'];
		if (get_option('actionnetwork_api_key', null) !== $actionnetwork_api_key) {
			update_option('actionnetwork_api_key', $actionnetwork_api_key);

			$deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}actionnetwork WHERE an_id != ''");

			$return['notices']['updated'][] = $deleted ? __('API key has been updated and actions synced via API have been removed', 'actionnetwork') : __('API key has been updated', 'actionnetwork');

			// TODO: sync new actions
		}
		break;
		
		case 'add_embed':
		$embed_title = isset($_REQUEST['actionnetwork_add_embed_title']) ? stripslashes($_REQUEST['actionnetwork_add_embed_title']) : '';
		$embed_code = isset($_REQUEST['actionnetwork_add_embed_code']) ? stripslashes($_REQUEST['actionnetwork_add_embed_code']) : '';

		// parse embed code
		$embed_style_matched = preg_match_all("/<link href='https:\/\/actionnetwork\.org\/css\/style-embed(-whitelabel)?\.css' rel='stylesheet' type='text\/css' \/>/", $embed_code, $embed_style_matches, PREG_SET_ORDER);
		$embed_script_matched = preg_match_all("|<script src='https://actionnetwork\.org/widgets/v2/([a-z_]+)/([-a-z0-9]+)\?format=js&source=widget(&style=full)?'>|", $embed_code, $embed_script_matches, PREG_SET_ORDER);

		$embed_style = $embed_style_matched ? ( isset($embed_style_matches[0][1]) && $embed_style_matches[0][1] ? 'layout_only' : 'default' ) : 'no';
		$embed_type = isset($embed_script_matches[0][1]) ? $embed_script_matches[0][1] : '';
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

			$wpdb->insert($table_name, array(
				'type' => $embed_type,
				'name' => $embed_title,
				'title' => $embed_title,
				$embed_field_name => $embed_code,
				'enabled' => 1,
			), array ( '%s', '%s', '%s', '%s', '%d' ) );
			$shortcode = "[actionnetwork id=".$wpdb->insert_id."]";

			$return['notices']['updated'][] = sprintf(
				/* translators: %s: The shortcode for the saved embed code */
				__('Embed code saved to your actions. Shortcode: %s', 'actionnetwork'),
				'<code>'.$shortcode.'</code>'
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

	// load scripts and stylesheets
	wp_enqueue_style( 'actionnetwork-admin-css', plugins_url('admin.css', __FILE__) );
	wp_register_script( 'actionnetwork-admin-js', plugins_url('admin.js', __FILE__) );

	// localize script
	$translation_array = array(
		'copied' => __( 'Copied!', 'actionnetwork' ),
		'pressCtrlCToCopy' => __( 'please press Ctrl/Cmd+C to copy', 'actionnetwork' ),
		'changeAPIKey' => __( 'Change or delete API key', 'actionnetwork' ),
		'confirmChangeAPIKey' => __( 'Are you sure you want to change or delete the API key? Doing so means any actions you have synced via the API will be deleted.', 'actionnetwork' ),
	);
	wp_localize_script( 'actionnetwork-admin-js', 'actionnetworkText', $translation_array );
	wp_enqueue_script( 'actionnetwork-admin-js' );
	
	// This checks which tab we should display
	$tab = isset($_REQUEST['actionnetwork_tab']) ? $_REQUEST['actionnetwork_tab'] : 'actions';

	// This handles form submissions and prints any relevant notices from them
	$notices_html = '';
	if (isset($_REQUEST['actionnetwork_admin_action'])) {
		$action_returns = actionnetwork_admin_handle_actions();
		if (isset($action_returns['notices'])) {
			foreach ($action_returns['notices']['error'] as $notice) {
				$notices_html .= '<div class="error notice is-dismissible"><p>'.$notice.'</p></div>';
			}
			foreach ($action_returns['notices']['updated'] as $notice) {
				$notices_html .= '<div class="updated notice is-dismissible"><p>'.$notice.'</p></div>';
			}

		}
		if (isset($action_returns['tab'])) { $tab = $action_returns['tab']; }
	}

	// This handles this list
	$action_list = new Actionnetwork_Action_List();
	$action_list->prepare_items();
	if (isset($action_list->notices)) {
		foreach ($action_list->notices['error'] as $notice) {
			$notices_html .= '<div class="error notice is-dismissible"><p>'.$notice.'</p></div>';
		}
		foreach ($action_list->notices['updated'] as $notice) {
			$notices_html .= '<div class="updated notice is-dismissible"><p>'.$notice.'</p></div>';
		}
	}
	

	?>
	
	<div class='wrap'>
		
		<h1>Action Network</h1>
		
		<div class="wrap-inner">

			<?php if ($notices_html) { echo $notices_html; } ?>
			
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
				<h2><?php _e('Your Actions', 'actionnetwork'); ?></h2>
				<form id="actionnetwork-actions-filter" method="get">
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
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
						$actionnetwork_api_key = get_option('actionnetwork_api_key');
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
		'id'       => 'actionnetwork-help-1',
		'title'    => __( 'Help Tab 1', 'actionnetwork' ),
		'content'  => __('
<p>Placeholder for documentation</p>
		', 'actionnetwork'),
	));
}

/**
 * Load actions from API
 */
function _actionnetwork_load_actions(){
	if ($actionnetwork_api_key = get_option('actionnetwork_api_key')) {
		// make call
		if ($success) {
			// delete all database entries with an_id not null or empty
			// then load into database
			// and update cache timestamp
			if (get_option('actionnetwork_cache_timestamp')) {
				update_option('actionnetwork_cache_timestamp', time());
			} else {
				add_option('actionnetwork_cache_timestamp', time(), '', 'no'); // TODO: look at add_option documentation to figure out last two values
			}
		}
	}
}

/**
 * Create WP_List_Table for actions
 */
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class Actionnetwork_Action_List extends WP_List_Table {

	// Notices
	public $notices = array();
	public $action_types = array();
	public $action_type_plurals = array();

	// Class constructor
	function __construct() {

		parent::__construct( array(
			'singular' => __( 'Action', 'actionnetwork' ),
			'plural' => __( 'Actions', 'actionnetwork' ),
			'ajax' => false,
		) );

		$this->notices = array(
			'error' => array(),
			'updated' => array(),
		);

		$this->action_types = array(
			'petition' => __( 'Petition', 'actionnetwork' ),
			'advocacy_campaign' => __( 'Advocacy Campaign', 'actionnetwork' ),
			'event' => __( 'Event', 'actionnetwork' ),
			'ticketed_event' => __( 'Ticketed Event', 'actionnetwork' ),
			'fundraising_page' => __( 'Fundraising Page', 'actionnetwork' ),
			'form' => __( 'Form', 'actionnetwork' ),
		);
		$this->action_type_plurals = array(
			'petition' => __( 'Petitions', 'actionnetwork' ),
			'advocacy_campaign' => __( 'Advocacy Campaigns', 'actionnetwork' ),
			'event' => __( 'Events', 'actionnetwork' ),
			'ticketed_event' => __( 'Ticketed Events', 'actionnetwork' ),
			'fundraising_page' => __( 'Fundraising Pages', 'actionnetwork' ),
			'form' => __( 'Forms', 'actionnetwork' ),
		);

	}

    function column_default($item, $column_name){
        switch($column_name){

			case 'type':
                return $this->action_types[$item[$column_name]];
			break;

			case 'source':
				return $item['an_id'] ? 'API' : __('User','actionnetwork');
			break;

            default:
                return print_r($item,true); //Show the whole array for troubleshooting purposes
			break;
        }
    }

	function column_title($item) {
		$title = $item['title'];
		if ($item['name'] && ($item['name'] != $title)) {
			$title .= '<span class="admin-name">('.$item['name'].')</span>';
		}
		return $title;
	}

	function column_shortcode($item) {
		$shortcode = "[actionnetwork id={$item['wp_id']}]";
		$__copy = __( 'Copy', 'actionnetwork' );
		return <<<EOHTML
<div class="copy-wrapper">
<input type="text" class="copy-text" readonly="readonly" id="shortcode-{$item['wp_id']}" value="$shortcode" /><button data-copytarget="#shortcode-{$item['wp_id']}" class="copy">$__copy</button>
</div>
EOHTML;
	}

    function column_cb($item){
		// we disable this for items which have an action network ID,
		// because they are controlled by the Action Network backend
		// and synced via API
        return sprintf(
            '<input type="checkbox" name="bulk-action[]" value="%1$s" %2$s/>',
            $item['wp_id'],
			$item['an_id'] ? 'disabled="disabled" title="'.__( 'Cannot perform bulk actions on actions synced via API', 'actionnetwork' ).'" ' : ''
        );
    }

	function get_actions( $per_page = 20, $page_number = 1 ) {

		global $wpdb;
		$sql = "SELECT * FROM {$wpdb->prefix}actionnetwork";

		if ( !empty( $_REQUEST['type'] ) ) {
			$type = $_REQUEST['type'];
			
			if ( array_key_exists( $type, $this->action_types ) ) {
				$sql .= " WHERE type = '".$type."'";
			}
		}

		if ( !empty( $_REQUEST['source'] ) ) {
			
			if ( $_REQUEST['source'] == 'user' ) {
				$sql .= " WHERE an_id = ''";
			} elseif ( $_REQUEST['source'] == 'api' ) {
				$sql .= " WHERE an_id != ''";
			}
		}

		if ( !empty( $_REQUEST['orderby'] ) ) {
			$sql .= " ORDER BY " . esc_sql( $_REQUEST['orderby'] );
			$sql .= !empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ($page_number - 1) * $per_page;

		$result = $wpdb->get_results( $sql, 'ARRAY_A' );
		return $result;
	}

	function record_count() {
		global $wpdb;
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}actionnetwork";
		return $wpdb->get_var( $sql );
	}

	function delete_action( $wp_id ) {
		global $wpdb;
		$wpdb->delete(
			"{$wpdb->prefix}actionnetwork",
			array( 'wp_id' => $wp_id ),
			array( '%d' )
		);
	}

	function no_items() {
		_e( 'No actions available', 'actionnetwork' );
	}

	function get_columns() {

		$columns = array(
			'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
			'title' => __( 'Title', 'actionnetwork' ),
			'type' => __( 'Type', 'actionnetwork' ),
			'shortcode' => __( 'Shortcode', 'actionnetwork' ),
		);

		if ( get_option( 'actionnetwork_api_key', null ) ) {
			$columns['source'] = __( 'Source', 'actionnetwork' );;
		}

		return $columns;
	}

    function get_sortable_columns() {
   	    $sortable_columns = array(
   	        'title'     => array('title',false),
   	        'type'     => array('type',false),
		);
        return $sortable_columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'delete'    => __( 'Delete', 'actionnetwork' ),
        );
        return $actions;
    }

    function process_bulk_action() {
        
        //Detect when a bulk action is being triggered...
        if( 'delete'===$this->current_action() ) {
			$delete_wp_ids = esc_sql( $_REQUEST['bulk-action'] );
			mail( 'uekissam@gmail.com' , 'delete_wp_ids', print_r($delete_wp_ids, 1), "From: noreply@wp-jkissam.rhcloud.com\r\n" );
			foreach ( $delete_wp_ids as $wp_id ) {
				self::delete_action( $wp_id );
			}
			$this->notices['updated'][] = __('Actions deleted', 'actionnetwork');
        }
        
    }

	function extra_tablenav( $which ) {

		global $wpdb;

		if ( $which == 'top' ) {

			$type_options = '';
			foreach ($this->action_type_plurals as $key => $plural) {
				$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}actionnetwork WHERE type='$key'";
				$count = $wpdb->get_var( $sql );
				$type_options .= "<option value=\"$key\"";
				$type_options .= selected( $_REQUEST['type'], $key, false );
				$type_options .= disabled( ($count == 0), true, false );
				$type_options .= ">$plural</option>\n";
			}

			?>
			<div class="alignleft actions">
				<label for="actionnetwork-filter-by-type" class="screen-reader-text"><?php _e('Filter by type', 'actionnetwork'); ?></label>
				<select name="type" id="actionnetwork-filter-by-type">
					<option value=""><?php _e('All Types', 'actionnetwork'); ?></option>
					<?php echo $type_options; ?>
				</select>
				<?php if ( get_option( 'actionnetwork_api_key', null ) ): ?>
				<label for="actionnetwork-filter-by-source" class="screen-reader-text"><?php _e('Filter by source', 'actionnetwork'); ?></label>
				<select name="source" id="actionnetwork-filter-by-source">
					<option value=""><?php _e('All Sources', 'actionnetwork'); ?></option>
					<option value="user"<?php selected( $_REQUEST['source'], 'user' ); ?>><?php _e('User', 'actionnetwork'); ?></option>
					<option value="api"<?php selected( $_REQUEST['source'], 'api' ); ?>>API</option>
				</select>
				<?php endif; ?>
				<input type="submit" name="filter_action" id="actionnetwork-filter-submit" class="button" value="<?php _e('Filter', 'actionnetwork'); ?>">
			</div>
			<?php

		}

	}

	function prepare_items() {

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

		// process bulk action
		$this->process_bulk_action();

		$per_page = $this->get_items_per_page( 'actions_per_page', 20 );
		$current_page = $this->get_pagenum();
		$total_items = self::record_count();

		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page' => $per_page,
		));

		$this->items = self::get_actions( $per_page, $current_page );
	}

}
