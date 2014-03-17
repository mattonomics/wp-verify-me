<?php

class two_factor_authentication_twilio {
	
	private $twilio_number;
	private $user_number;
	
	public function __construct( $option, $user_number ) {
		$this->twilio_number = '+' . preg_replace( '/\D/', '', $option['phone'] );
		$this->user_number = '+' . preg_replace( '/\D/', '', $user_number );
		require_once 'twilio-php/Twilio.php';
		$this->client = new Services_Twilio( $option['sid'], $option['token'] );
	}
	
	public function send( $code ) {
		$success = true;
		$text = sprintf( __( "This message was sent by: %s\nThe authorization code is: %d", 'two_factor_auth' ), site_url(), absint( $code ) );
		try {
			$message = $this->client->account->messages->sendMessage( $this->twilio_number, $this->user_number, $text );
		} catch ( Services_Twilio_RestException $e ) {
			// need to add in error handling. if the text fails, no lock code will be generated.
			$success = false;
		}
		// doesn't matter what the error is, just don't do anything if there is one
		return $success;
	}
	
}