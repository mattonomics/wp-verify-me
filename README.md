# wpVerifyMe

This simple implementation of two factor authorization uses [Twilio](https://www.twilio.com/try-twilio) to send a 5 digit code to your cell phone.

Twilio allows a single user to register for their service for free. If you intend to use this service for more than one user, you will need to purchase a paid account.

## Setup

After signing up for Twilio:
- Paste your Account SID, Token and Twilio phone number in the appropriate fields found in Settings > wpVerifyMe.
- Navigate to Users > Your Profile and enter a number that can receive SMS. If you're using the free version of Twilio, this number _must_ match the number you used to sign up.