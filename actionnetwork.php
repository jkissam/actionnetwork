<?php
/*
 * @package ActionNetwork
 * @version 1.0-alpha2
 *
 * Plugin Name: Action Network
 * Description: Integrations with Action Network (actionnetwork.org)'s API to provide action embed codes as shortcodes
 * Author: Jonathan Kissam
 * Text Domain: heckle-window
 * Domain Path: /languages
 * Version: 1.0-alpha2
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
$actionnetwork_version = '1.0-alpha2';
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
 */
function actionnetwork_shortcode( $atts ) {
	$id = isset($atts['id']) ? $atts['id'] : null;
	$size = isset($atts['size']) ? $atts['size'] : 'standard';
	$style = isset($atts['style']) ? $atts['style'] : 'default';

	if (!$id) { return; }

	// validate size and style
	if (!in_array($size, array('standard', 'full'))) { $size = 'standard'; }
	if (!in_array($style, array('default', 'layout_only', 'no'))) { $style = 'default'; }
	
	// query embed_code and embed_code_$size_$style columns from database
	// if embed_code_$size_$style is null or empty, use embed_code
	// if code is not empty or null, return it
}
add_shortcode( 'actionnetwork', 'actionnetwork_shortcode' );

/**
 * Set up admin menu structure
 * https://developer.wordpress.org/reference/functions/add_menu_page/
 */
function actionnetwork_admin_menu() {
	$actionnetwork_admin_menu_hook = add_menu_page( __('Administer Action Network', 'actionnetwork'), 'Action Network', 'manage_options', 'actionnetwork', 'actionnetwork_admin_page', 'dashicons-megaphone', 61);
	add_action( 'load-' . $actionnetwork_admin_menu_hook, 'actionnetwork_admin_add_help' );
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
			$return['notices']['updated'][] = __('API key has been updated', 'actionnetwork');
		}
		break;
		
		case 'add_embed':
		$embed_title = isset($_REQUEST['actionnetwork_add_embed_title']) ? $_REQUEST['actionnetwork_add_embed_title'] : '';
		$embed_code = isset($_REQUEST['actionnetwork_add_embed_code']) ? $_REQUEST['actionnetwork_add_embed_code'] : '';
		if (!$embed_title) {
			// TODO: get title from embed code
			$return['notices']['error'][] = __('You must give your action a title', 'actionnetwork');
			$return['errors']['#actionnetwork_add_embed_title'] = true;
			$return['actionnetwork_add_embed_code'] = $embed_code;
		}
		if (!$embed_code) {
			// TODO: validate the embed code instead of just checking for it being non-empty
			$return['notices']['error'][] = __('You must enter an embed code', 'actionnetwork');
			$return['errors']['#actionnetwork_add_embed_code'] = true;
			$return['actionnetwork_add_embed_title'] = $embed_title;
		}
		if ($embed_title && $embed_code) {
			// save to action
			$return['notices']['updated'][] = __('Embed code saved to your actions', 'actionnetwork');
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
	wp_enqueue_style('actionnetwork-admin-css', plugins_url('admin.css', __FILE__));
	wp_enqueue_script('actionnetwork-admin-js', plugins_url('admin.js', __FILE__));
	
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

	?>
	
	<div class='wrap'>
		
		<h1>Action Network <a href="#actionnetwork-add" class="page-title-action"><?php _e('Add New Action', 'actionnetwork'); ?></a></h1>
		<?php if ($notices_html) { echo $notices_html; } ?>
		
		<div class="wrap-inner">
			
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
