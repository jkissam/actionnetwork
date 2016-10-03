<?php
	
define('jk_BRANDING_VERSION','1.0');

if (!function_exists('jk_branding')) :
function jk_branding($product_name, $product_url = '', $notice = false, $echo = true, $plugin_or_theme = 'plugin', $donation_url = 'http://jonathankissam.com/support') {
	
	if ($plugin_or_theme == 'plugin') {
		$plugin_path = dirname(__FILE__);
		$plugin_path_array = explode('/',$plugin_path);
		array_pop($plugin_path_array); // remove jk_wp_branding
		$plugin_name = array_pop($plugin_path_array);
		if (isset($_REQUEST['hide_jk_branding']) && $_REQUEST['hide_jk_branding']) {
			update_option($plugin_name.'_hide_jk_branding',true);
		}
		if (get_option($plugin_name.'_hide_jk_branding',false)) {
			return;
		}
	}
	
	$product_link = $product_url ? "<a href=\"$product_url\">$product_name</a>" : $product_name;
	$product_thankyou = ($plugin_or_theme == 'plugin') ? sprintf(
		/* translators: %s: Name of the plugin */
		__('Hi! Thank you for installing my %s plugin.', 'jk_branding'),
		$product_link
	) : sprintf(
		/* translators: %s: Name of the theme */
		__('Hi! Thank you for installing my %s theme.', 'jk_branding'),
		$product_link
	);
	$donation_link = '<a href="' . $donation_url . '">' . __('Support my work' , 'jk_branding') . '</a>';
	$hireme_link = '<a href="http://jonathankissam.com/about">' . __('Hire me for custom Wordpress development' , 'jk_branding') . '</a>';
	$getupdates_link = '<a href="http://eepurl.com/cabLYT">'. __('Get updates about this plugin', 'jk_branding') . '</a>';
	if ($notice) {
		$branding = $product_thankyou . ' ' . $donation_link . ' | ' . $hireme_link . ' &mdash; <a href="http://jonathankissam.com">Jonathan Kissam</a>';
	} else {
		wp_enqueue_style('jk-branding', plugins_url('jk_branding.css', __FILE__));
		$screen = get_current_screen();
		$hide_branding_link = '<a href="admin.php?page='.$screen->parent_file.'&hide_jk_branding=true">' . __("Don't show this notice", 'jk_branding') . '</a>';
		$avatar_src = "https://www.gravatar.com/avatar/".md5('uekissam@gmail.com');
		$branding = <<<EOHTML
		<div class="jk-branding postbox">
		<a href="http://jonathankissam.com" class="jk-avatar" title="Jonathan Kissam, Wordpress developer"><img src="$avatar_src" /></a>
		<p class="jk-thankyou">$product_thankyou</p>
		<p class="jk-donate"><span class="dashicons dashicons-thumbs-up"></span> $donation_link</p>
		<p class="jk-hireme"><span class="dashicons dashicons-hammer"></span> $hireme_link</p>
		<p class="jk-getupdated"><span class="dashicons dashicons-email"></span> $getupdates_link</p>
		<p class="jk-hide"><span class="dashicons dashicons-dismiss"></span> $hide_branding_link</p>
		</div>
EOHTML;
	}
	if ($echo) { echo $branding; } else { return $branding; }
}
endif;
