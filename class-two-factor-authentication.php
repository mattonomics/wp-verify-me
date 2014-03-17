<?php

final class two_factor_authentication {
	
	private $twilio;
	private $option;
	private $user = array(
		'number' => false,
		'lock_code' => false,
		'timestamp' => false,
		'id' => false
	);
	
	public function __construct() {
		$this->option = wp_parse_args( get_option('two_factor_auth'), array( 
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
		add_action( 'login_form_two_factor_auth', array( $this, 'login_form' ) );
		add_action( 'admin_post_two_factor_auth_unlock', array( $this, 'maybe_unlock' ) );
		
		add_filter( 'login_redirect', array( $this, 'handle_login' ), 1, 3 );
	}
	
	public function set_props( $user_id = false ) {
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		$user_id = absint( $user_id );
		$this->user = array(
			'number' => get_user_meta( $user_id, 'two_factor_auth_user_phone', true ),
			'lock_code' => get_user_meta( $user_id, 'two_factor_auth_lock_code', true ),
			'timestamp' => get_user_meta( $user_id, 'two_factor_auth_timestamp', true ),
			'id' => $user_id
		);
		$this->twilio = new two_factor_authentication_twilio( $this->option, $this->user['number'] );
	}
	
	public function admin_init() {
		$this->set_props();
		$this->settings();
		$this->check_user_lock();
	}
	
	private function settings() {
		register_setting( 'two_factor_auth_group', 'two_factor_auth', array( $this, 'sanitize_settings' ) );
		add_settings_section( 'two-factor-auth', __( 'Enter Twilio Account Info' ), array( $this, 'explain_twilio' ), 'two-factor-auth' );
		add_settings_field( 'two-factor-auth-sid', __( 'Twilio Account SID', 'two_factor_auth' ), array( $this, 'sid_callback' ), 'two-factor-auth', 'two-factor-auth' );
		add_settings_field( 'two-factor-auth-token', __( 'Twilio Auth Token', 'two_factor_auth' ), array( $this, 'token_callback' ), 'two-factor-auth', 'two-factor-auth' );
		add_settings_field( 'two-factor-auth-phone', __( 'Twilio Phone Number', 'two_factor_auth' ), array( $this, 'phone_callback' ), 'two-factor-auth', 'two-factor-auth' );
	}
	
	public function menu_page() {
		add_options_page( __( 'Two Factor Authentication', 'two_factor_auth' ), __( 'Two Factor Authentication', 'two_factor_auth' ), 'manage_options', 'two-factor-auth', array( $this, 'options_page' ) );
	}
	
	public function options_page() {
		if ( !empty( $_GET['action'] ) && $_GET['action'] == 'lock' ) {
			$reauth = $redir = '';
			if ( isset( $_GET['reauth'] ) ) {
				$reauth = '<p>' . __( 'Your code has expired. A new authorization code has been sent.', 'two_factor_auth' ) . '</p>';
			}
			if ( !empty( $_GET['redir'] ) ) {
				$redir = '<input type="hidden" name="redir" value="' . esc_attr( $_GET['redir'] ) . '" />';
			}
			?><div class="wrap">
			        <h2><?php _e( 'Enter Authorization Code', 'two_factor_auth' ); ?></h2>
			        <form action="<?php echo admin_url('admin-post.php?action=two_factor_auth_unlock'); ?>" method="POST">
						<table class="form-table">
							<tr>
								<th scope="row"><?php _e( 'Authorization Code', 'two_factor_auth' ); ?></th>
								<td>
									<input type="text" name="unlock_code" value="" <?php echo isset( $_GET['error'] ) ? 'style="background:#F7D7D7;" ' : ''; ?>/>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Unlock', 'two_factor_auth' ) ); ?>
						<?php wp_nonce_field( 'two_factor_auth_unlock' ); ?>
						<?php echo $reauth; ?>
						<?php echo $redir; ?>
			        </form>
			    </div><?php
		} else {
			?><div class="wrap">
			        <h2><?php _e( 'Two Factor Authentication: Twilio Account Info', 'two_factor_auth' ); ?></h2>
			        <form action="options.php" method="POST">
			            <?php settings_fields( 'two_factor_auth_group' ); ?>
			            <?php do_settings_sections( 'two-factor-auth' ); ?>
			            <?php submit_button(); ?>
			        </form>
			    </div><?php
		}
	}
	
	public function explain_twilio() {
		echo '<p>', sprintf( __( 'If you do not have a <a href="%1$s">Twilio</a> account, <a href="%1$s">sign up</a>.', 'two_factor_auth' ), 'https://www.twilio.com/try-twilio' ), '</p>';
	}
	
	public function sid_callback() {
		echo '<input type="text" name="two_factor_auth[sid]" value="', esc_attr( $this->option['sid'] ), '" />';
	}
	
	public function token_callback() {
		echo '<input type="text" name="two_factor_auth[token]" value="', esc_attr( $this->option['token'] ), '" />';
	}
	
	public function phone_callback() {
		echo '<input type="text" name="two_factor_auth[phone]" value="', esc_attr( $this->option['phone'] ), '" />'.
			 '<br />'.
			 '<span class="description">'. __( 'Be sure to include the country code. US numbers will be in the form <code>+1 555-123-1234</code>', 'two_factor_auth' ). '</span>';
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
		$value = esc_attr( get_the_author_meta( 'two_factor_auth_user_phone', $user->ID ) );
		?>
		<h3><?php _e( 'Two Factor Authentication', 'two_factor_auth' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="two_factor_auth_user_phone"><?php _e( 'Your Phone Number', 'two_factor_auth' ); ?></label></th>
				<td>
					<input type="text" name="two_factor_auth_user_phone" id="two_factor_auth_user_phone" value="<?php echo $value; ?>" />
					<br />
					<span class="description"><?php _e( 'Enter a phone number where you can receive SMS. <u>Please include a country code</u>.', 'two_factor_auth' ); ?></span>
				</td>
			</tr>
		</table>
		<?php
	}
	
	public function profile_save( $user_id ) {
		if ( !current_user_can( 'edit_user', $user_id ) )
			return;
		if ( !empty( $_POST['two_factor_auth_user_phone'] ) ) {
			$raw = $_POST['two_factor_auth_user_phone'];
			$sanitized = preg_replace( '/\D/', '', $raw );
		} else {
			$sanitized = '';
		}
		update_user_meta( $user_id, 'two_factor_auth_user_phone', $sanitized );
	}
	
	public function handle_login( $to, $requested, $user ) {
		if ( is_a( $user, 'WP_User' ) && property_exists( $user, 'ID' ) && !empty( $user->ID ) ) {
			/*
				User has been verified. Now we will add user meta with the auth code,
				send the code and display a form for the user to confirm.
			*/
			$this->set_props( $user->ID );
			$this->create_lock( $this->create_code() );
			$to = add_query_arg( array( 'page' => 'two-factor-auth', 'action' => 'lock', 'redir' => urlencode( $to ) ), admin_url( 'options-general.php' ) );
		}
		return $to;
	}
	
	public function check_user_lock() {
		global $pagenow;
		if ( in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php' ) ) ) {
			return;
		} elseif ( !empty( $this->user['lock_code'] ) ) {
			// a lock is in place and the user will need to confrim before continuing.
			if ( empty( $_GET['page'] ) || $_GET['page'] != 'two-factor-auth' || $pagenow != 'options-general.php' || empty( $_GET['action'] ) || $_GET['action'] != 'lock' ) {
				// user is not on the auth page, so redirect 'em
				wp_safe_redirect( admin_url( 'options-general.php?page=two-factor-auth&action=lock' ) );
				exit;
			}
		}
	}
	
	public function maybe_unlock() {
		check_admin_referer( 'two_factor_auth_unlock' );
		$difference = ( time() - $this->user['timestamp'] ) / 60;
		if ( !empty( $_POST['unlock_code'] ) && $this->user['lock_code'] == trim( $_POST['unlock_code'] ) && $difference <= 20 ) {
			delete_user_meta( get_current_user_id(), 'two_factor_auth_lock_code' );
			delete_user_meta( get_current_user_id(), 'two_factor_auth_timestamp' );
			wp_safe_redirect( !empty( $_POST['redir'] ) ? esc_url_raw( $_POST['redir'] ) : admin_url() );
			exit;
		} 	elseif ( $difference > 20 && $this->create_lock( $this->create_code() ) ) {
			// code expired. resend a new code, send to the auth page and notify the user that they're slow
			wp_safe_redirect( admin_url( 'options-general.php?page=two-factor-auth&action=lock&reauth' ) );
			exit;
		} elseif ( empty( $_POST['unlock_code'] ) || $this->user['lock_code'] != trim( $_POST['unlock_code'] ) ) {
			// code is incorrect
			wp_safe_redirect( admin_url( 'options-general.php?page=two-factor-auth&action=lock&error' ) );
			exit;
		} 
	}
	
	private function create_lock( $code ) {
		// delete any current data
		delete_user_meta( $this->user['id'], 'two_factor_auth_lock_code' );
		// delete the timestamp
		delete_user_meta( $this->user['id'], 'two_factor_auth_timestamp' );
		if ( $this->twilio->send( $code ) ) {
			// add them both back in
			add_user_meta( $this->user['id'], 'two_factor_auth_lock_code', absint( $code ) );
			add_user_meta( $this->user['id'], 'two_factor_auth_timestamp', time() );
			return true;
		} else {
			return false;
		}
	}
	
	private function create_code() {
		return substr( str_replace( '.', '', microtime( true ) ), -5 );
	}
}