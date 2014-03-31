<?php

defined( 'ABSPATH' ) || die;

final class wp_verify_me {
	
	private $twilio;
	private $option;
	private $user = array(
		'number' => false,
		'lock_code' => false,
		'timestamp' => false,
		'id' => false
	);
	
	public function __construct() {
		global $pagenow;
		$this->option = wp_parse_args( get_option('wp_verify_me'), array( 
			'sid'	=> '',
			'token'	=> '',
			'phone'	=> ''
		));
		$this->initialize();
	}
	
	private function initialize() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'menu_page' ) );
		add_action( 'show_user_profile', array( $this, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'profile_save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'profile_save' ) );
		
		// do we have all three necessary items?
		if ( count( array_filter( $this->option ) ) === 3 ) {
			add_action( 'login_form_wp_verify_me', array( $this, 'login_form' ) );
			add_action( 'admin_post_wp_verify_me_unlock', array( $this, 'maybe_unlock' ) );

			add_filter( 'login_redirect', array( $this, 'handle_login' ), 1, 3 );
		}
	}
	
	public function set_props( $user_id = false ) {
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		$user_id = absint( $user_id );
		$this->user = array(
			'number' => get_user_meta( $user_id, 'wp_verify_me_user_phone', true ),
			'lock_code' => get_user_meta( $user_id, 'wp_verify_me_lock_code', true ),
			'timestamp' => get_user_meta( $user_id, 'wp_verify_me_timestamp', true ),
			'id' => $user_id
		);
		$this->twilio = new wp_verify_me_twilio( $this->option, $this->user['number'] );
	}
	
	public function admin_init() {
		$this->set_props();
		$this->settings();
		$this->check_user_lock();
	}
	
	private function settings() {
		register_setting( 'wp_verify_me_group', 'wp_verify_me', array( $this, 'sanitize_settings' ) );
		add_settings_section( 'wp-verify-me', __( 'Enter Twilio Account Info', 'wp_verify_me' ), array( $this, 'explain_twilio' ), 'wp-verify-me' );
		add_settings_field( 'wp-verify-me-sid', __( 'Twilio Account SID', 'wp_verify_me' ), array( $this, 'sid_callback' ), 'wp-verify-me', 'wp-verify-me' );
		add_settings_field( 'wp-verify-me-token', __( 'Twilio Auth Token', 'wp_verify_me' ), array( $this, 'token_callback' ), 'wp-verify-me', 'wp-verify-me' );
		add_settings_field( 'wp-verify-me-phone', __( 'Twilio Phone Number', 'wp_verify_me' ), array( $this, 'phone_callback' ), 'wp-verify-me', 'wp-verify-me' );
	}
	
	public function menu_page() {
		add_options_page( __( 'wpVerifyMe', 'wp_verify_me' ), __( 'wpVerifyMe', 'wp_verify_me' ), 'manage_options', 'wp-verify-me', array( $this, 'options_page' ) );
	}
	
	public function options_page() {
		if ( !empty( $_GET['action'] ) && $_GET['action'] == 'lock' ) {
			$reauth = $redir = '';
			if ( isset( $_GET['reauth'] ) ) {
				$reauth = '<p>' . __( 'Your code has expired. A new authorization code has been sent.', 'wp_verify_me' ) . '</p>';
			}
			if ( !empty( $_GET['redir'] ) ) {
				$redir = '<input type="hidden" name="redir" value="' . esc_attr( $_GET['redir'] ) . '" />';
			}
			?><div class="wrap">
			        <h2><?php _e( 'Enter Authorization Code', 'wp_verify_me' ); ?></h2>
			        <form action="<?php echo admin_url('admin-post.php?action=wp_verify_me_unlock'); ?>" method="POST">
						<table class="form-table">
							<tr>
								<th scope="row"><?php _e( 'Authorization Code', 'wp_verify_me' ); ?></th>
								<td>
									<input type="text" name="unlock_code" value="" <?php echo isset( $_GET['error'] ) ? 'style="background:#F7D7D7;" ' : ''; ?>/>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Unlock', 'wp_verify_me' ) ); ?>
						<?php wp_nonce_field( 'wp_verify_me_unlock' ); ?>
						<?php echo $reauth; ?>
						<?php echo $redir; ?>
			        </form>
			    </div><?php
		} else {
			?><div class="wrap">
			        <h2><?php _e( 'wpVerifyMe: Twilio Account Info', 'wp_verify_me' ); ?></h2>
			        <form action="options.php" method="POST">
			            <?php settings_fields( 'wp_verify_me_group' ); ?>
			            <?php do_settings_sections( 'wp-verify-me' ); ?>
			            <?php submit_button(); ?>
			        </form>
			    </div><?php
		}
	}
	
	public function explain_twilio() {
		echo '<p>', sprintf( __( 'If you do not have a Twilio account, <a href="%1$s" target="_blank">sign up</a>.', 'wp_verify_me' ), 'https://www.twilio.com/try-twilio' ), '</p>';
	}
	
	public function sid_callback() {
		echo '<input type="text" name="wp_verify_me[sid]" value="', esc_attr( $this->option['sid'] ), '" />';
	}
	
	public function token_callback() {
		echo '<input type="text" name="wp_verify_me[token]" value="', esc_attr( $this->option['token'] ), '" />';
	}
	
	public function phone_callback() {
		echo '<input type="text" name="wp_verify_me[phone]" value="', esc_attr( $this->option['phone'] ), '" />'.
			 '<br />'.
			 '<span class="description">'. __( 'Be sure to include the country code. US numbers will be in the form <code>+1 555-123-1234</code>', 'wp_verify_me' ). '</span>';
	}
	
	public function sanitize_settings( $options ) {
		$save = array();
		foreach ( $options as $name => $option ) {
			if ( in_array( $name, array( 'sid', 'token', 'phone' ) ) ) {
				$save[$name] = preg_replace( '/\W| |_/', '', trim( $option ) );
			}
		}
		return $save;
	}
	
	public function profile_fields( $user ) {
		$value = esc_attr( get_the_author_meta( 'wp_verify_me_user_phone', $user->ID ) );
		?>
		<h3><?php _e( 'wpVerifyMe', 'wp_verify_me' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="wp_verify_me_user_phone"><?php _e( 'Your Phone Number', 'wp_verify_me' ); ?></label></th>
				<td>
					<input type="text" name="wp_verify_me_user_phone" id="wp_verify_me_user_phone" value="<?php echo $value; ?>" />
					<br />
					<span class="description"><?php _e( 'Enter a phone number where you can receive SMS. <u>Please include a country code</u>.', 'wp_verify_me' ); ?></span>
				</td>
			</tr>
		</table>
		<?php
	}
	
	public function profile_save( $user_id ) {
		if ( !current_user_can( 'edit_user', $user_id ) )
			return;
		if ( !empty( $_POST['wp_verify_me_user_phone'] ) ) {
			$raw = $_POST['wp_verify_me_user_phone'];
			$sanitized = preg_replace( '/\D/', '', $raw );
		} else {
			$sanitized = '';
		}
		update_user_meta( $user_id, 'wp_verify_me_user_phone', $sanitized );
	}
	
	public function handle_login( $to, $requested, $user ) {
		if ( is_a( $user, 'WP_User' ) && property_exists( $user, 'ID' ) && !empty( $user->ID ) ) {
			/*
				User has been verified. Now we will add user meta with the auth code,
				send the code and display a form for the user to confirm.
			*/
			$this->set_props( $user->ID );
			$this->create_lock( $this->create_code() );
			$to = add_query_arg( array( 'page' => 'wp-verify-me', 'action' => 'lock', 'redir' => urlencode( $to ) ), admin_url( 'options-general.php' ) );
		}
		return $to;
	}
	
	public function check_user_lock() {
		global $pagenow;
		if ( in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php' ) ) ) {
			return;
		} elseif ( !empty( $this->user['lock_code'] ) ) {
			// a lock is in place and the user will need to confrim before continuing.
			if ( empty( $_GET['page'] ) || $_GET['page'] != 'wp-verify-me' || $pagenow != 'options-general.php' || empty( $_GET['action'] ) || $_GET['action'] != 'lock' ) {
				// user is not on the auth page, so redirect 'em
				wp_safe_redirect( admin_url( 'options-general.php?page=wp-verify-me&action=lock' ) );
				exit;
			}
		}
	}
	
	public function maybe_unlock() {
		check_admin_referer( 'wp_verify_me_unlock' );
		$difference = ( time() - $this->user['timestamp'] ) / 60;
		$lock_time = apply_filters( 'wp_verify_me_lock_time', 5 );
		if ( !empty( $_POST['unlock_code'] ) && $this->user['lock_code'] == trim( $_POST['unlock_code'] ) && $difference <= $lock_time ) {
			delete_user_meta( get_current_user_id(), 'wp_verify_me_lock_code' );
			delete_user_meta( get_current_user_id(), 'wp_verify_me_timestamp' );
			wp_safe_redirect( !empty( $_POST['redir'] ) ? esc_url_raw( $_POST['redir'] ) : admin_url() );
			exit;
		} 	elseif ( $difference > $lock_time && $this->create_lock( $this->create_code() ) ) {
			// code expired. resend a new code, send to the auth page and notify the user that they're slow
			wp_safe_redirect( admin_url( 'options-general.php?page=wp-verify-me&action=lock&reauth' ) );
			exit;
		} elseif ( empty( $_POST['unlock_code'] ) || $this->user['lock_code'] != trim( $_POST['unlock_code'] ) ) {
			// code is incorrect
			wp_safe_redirect( admin_url( 'options-general.php?page=wp-verify-me&action=lock&error' ) );
			exit;
		} 
	}
	
	private function create_lock( $code ) {
		// delete any current data
		delete_user_meta( $this->user['id'], 'wp_verify_me_lock_code' );
		// delete the timestamp
		delete_user_meta( $this->user['id'], 'wp_verify_me_timestamp' );
		if ( $this->twilio->send( $code ) ) {
			// add them both back in
			add_user_meta( $this->user['id'], 'wp_verify_me_lock_code', $this->esc_alphanumeric( $code ) );
			add_user_meta( $this->user['id'], 'wp_verify_me_timestamp', time() );
			return true;
		} else {
			return false;
		}
	}
	
	private function create_code() {
		return substr( wp_hash( microtime() ), rand( 0, 27 ), 5 );
	}
	
	public function esc_alphanumeric( $text ) {
		return preg_replace( '/\W|\s|_/', '', $text );
	}
}
