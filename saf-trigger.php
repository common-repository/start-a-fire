<?php
/**
 * @package Start_A_Fire
 * @version 1.0.2
 */
/*
Plugin Name: Start A Fire
Plugin URI: http://startafire.com/tools/wordpress/download/
Description: Grow and expand your audience by recommending your content within any link you share.
Author: Tomodo Ltd.
Version: 1.0.2
Author URI: http://startafire.com/
*/

/* **********************
** CHECK FOR REQUIREMENTS
** *********************/

function saf_show_php_error() {
	?>
		<div class="notice notice-error">
			<p><b>PHP Requirement Error</b></p>
			<p>Start A Fire Wordpress plugin requires PHP version 5.3 or greater to work on your installation. Emails us for support: <b>hello@startafire.com</b></p>
		</div>
	<?php
	// unset variable to not show standard WP installation notice
	if (isset($_GET['activate'])) {
		unset( $_GET['activate'] );
	}
}

if (version_compare(PHP_VERSION, '5.3', '<')) {
	// show error message
	add_action( 'admin_notices', 'saf_show_php_error');

	// uninstall plugin automatically
	function pluginname_deactivate_self() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
	}

	add_action( 'admin_init', 'pluginname_deactivate_self' );
	return;
} else {

	include 'saf-main.php';

	/* ******************
	** INSTALL, UNINSTALL
	** *****************/

	// on activation, add options stored in .../options.php table
	function saf_plugin_activate() {
		add_option('saf_token');
		add_option('saf_first_activation');
		add_option('saf_links_changed_in_posts');
	}
	
	register_activation_hook( __FILE__, 'saf_plugin_activate');

	// on deactivation, remove options stored in table
	function saf_plugin_deactivate() {
		delete_option('saf_token');
		delete_option('saf_first_activation');
		delete_option('saf_links_changed_in_posts');
	}

	register_deactivation_hook( __FILE__, 'saf_plugin_deactivate' );

}

?>