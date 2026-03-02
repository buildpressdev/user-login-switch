<?php
/**
 * Plugin Name: User Login Switch
 * Plugin URI:  https://github.com/buildpressdev/user-login-switch
 * Description: Administrator-only one-click user switching for testing and support.
 * Version:     0.1.0
 * Author:      buildpressdev
 * License:     GPL-2.0-or-later
 * Text Domain: user-login-switch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ULS_VERSION', '0.1.0' );
define( 'ULS_FILE', __FILE__ );
define( 'ULS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ULS_URL', plugin_dir_url( __FILE__ ) );

require_once ULS_DIR . 'includes/class-uls-plugin.php';

register_activation_hook( ULS_FILE, array( 'ULS_Plugin', 'activate' ) );
register_deactivation_hook( ULS_FILE, array( 'ULS_Plugin', 'deactivate' ) );

ULS_Plugin::instance();
