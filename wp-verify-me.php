<?php

/*
	Plugin Name: wpVerify.me
	Version: 0.1
	Description: Simple two factor authentication using Twilio.
	Author: Matt Gross
	Author URI: http://mattonomics.com
	Text Domain: wp_verify_me
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

add_action( 'init', 'instance_wp_verify_me' );
function instance_wp_verify_me() {
	global $pagenow;
	if ( ( is_admin() || $pagenow == 'wp-login.php' ) && !class_exists( 'wp_verify_me' ) ) {
		require_once( 'class-wp-verify-me-twilio.php' );
		require_once( 'class-wp-verify-me.php' );
		new wp_verify_me;
	}
}

add_action( 'plugins_loaded', 'wp_verify_me_textdomain' );
function wp_verify_me_textdomain() {
	load_plugin_textdomain( 'wp_verify_me', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}