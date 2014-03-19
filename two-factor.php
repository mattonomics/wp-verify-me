<?php

/*
	Plugin Name: Two Factor Authentication
	Version: 0.1-alpha
	Description: Simple two factor authentication using Twilio.
	Author: Matt Gross
	Author URI: http://mattonomics.com
	Text Domain: two_factor_auth
	Domain Path: /languages
*/

add_action( 'init', 'instance_two_factor_authentication' );
function instance_two_factor_authentication() {
	global $pagenow;
	if ( ( is_admin() || $pagenow == 'wp-login.php' ) && !class_exists( 'two_factor_authentication' ) ) {
		require_once( 'class-two-factor-authentication-twilio.php' );
		require_once( 'class-two-factor-authentication.php' );
		new two_factor_authentication;
	}
}

add_action( 'plugins_loaded', 'two_factor_authentication_textdomain' );
function two_factor_authentication_textdomain() {
	load_plugin_textdomain( 'two_factor_auth', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}