<?php

/*
	Plugin Name: Two Factor Authentication
	Version: 0.1-alpha
	Description: Simple two factor authentication using Twilio.
	Author: Matt Gross
	Author URI: http://mattonomics.com
	Text Domain: two_factor_auth
	Domain Path: /languages
	License: GPLv2
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/*  Copyright 2014  Matt Gross

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

defined( 'ABSPATH' ) || die;

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