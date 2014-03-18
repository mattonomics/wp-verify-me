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