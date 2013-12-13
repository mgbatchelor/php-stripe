<?php
// Codeigniter access check, remove it for direct use
if( !defined( 'BASEPATH' ) ) exit( 'No direct script access allowed' );

// Set the server api endpoint and http methods as constants
define( 'STRIPE_API_ENDPOINT', 'https://api.stripe.com/v1/' );
define( 'STRIPE_METHOD_POST', 'post' );
define( 'STRIPE_METHOD_DELETE', 'delete' );

/**
 * A simple to use library to access the stripe.com services
 * 
 * @copyright   Copyright (c) 2011 Pixative Solutions
 * @author      Ben Cessa <ben@pixative.com>
 * @author_url  http://www.pixative.com
 */
class Stripe {

	const TEST_MODE = TRUE;

	/**
	 * Holder for the initial configuration parameters 
	 * 
	 * @var     resource
	 * @access  private
	 */
	private $_conf = NULL;


	/**
	 * Constructor method
	 * 
	 * @param  array         Configuration parameters for the library
	 */
	public function __construct($param = NULL) {
		if( !$config || !is_array($param) ) {
			// Store the config values
			// Configuration options
			$param['stripe_key_test_public']         = 'pk_test_';
			$param['stripe_key_test_secret']         = 'sk_test_';
			$param['stripe_key_live_public']         = 'pk_live_';
			$param['stripe_key_live_secret']         = 'sk_live_';
			$param['stripe_test_mode']               = Stripe::TEST_MODE;
			$param['stripe_verify_ssl']              = FALSE;
		}
		$this->_conf = $param;
	}

	/**
	 * Create and apply a charge to an existent user based on it's customer_id
	 * 
	 * @param  int           The amount to charge in cents ( USD ) 
	 * @param  string        The customer id of the charge subject
	 * @param  string        A free form reference for the charge
	 */
	public function charge_customer( $amount, $customer_id, $desc ) {
		$params = array(
			'amount' => $amount,
			'currency' => 'usd',
			'customer' => $customer_id,
			'description' => $desc,
			'capture' => 'false'
		);

		return $this->_send_request( 'charges', $params, STRIPE_METHOD_POST );
	}

	/**
	 * Create and apply a charge based on credit card information
	 * 
	 * @param  int           The amount to charge in cents ( USD )
	 * @param  mixed         This can be a card token generated with stripe.js ( recommended ) or
	 *                       an array with the card information: number, exp_month, exp_year, cvc, name
	 * @param  string        A free form reference for the charge
	 */
	public function charge_card( $amount, $card, $desc ) {
		$params = array(
			'amount' => $amount,
			'currency' => 'usd',
			'card' => $card,
			'description' => $desc
		);

		return $this->_send_request( 'charges', $params, STRIPE_METHOD_POST );
	}

	/**
	 * Retrieve information about a specific charge
	 * 
	 * @param string         The charge ID to query
	 */
	public function charge_info( $charge_id ) {
		return $this->_send_request( 'charges/'.$charge_id );
	}

	/**
	 * Refund a charge
	 * 
	 * @param  string        The charge ID to refund
	 * @param  int           The amount to refund, defaults to the total amount charged
	 */
	public function charge_refund( $charge_id, $amount = FALSE ) {
		$amount ? $params = array( 'amount' => $amount ) : $params = array();
		return $this->_send_request( 'charges/'.$charge_id.'/refund', $params, STRIPE_METHOD_POST );
	}

	/**
	 * Get a list of charges, either general or for a certain customer
	 * 
	 * @param  int           The number of charges to return, default 10, max 100
	 * @param  int           Offset to apply to the list, default 0
	 * @param  string        A customer ID to return only charges for that customer
	 */
	public function charge_list( $count = 10, $offset = 0, $customer_id = FALSE ) {
		$params['count'] = $count;
		$params['offset'] = $offset;
		if( $customer_id )
			$params['customer'] = $customer_id;
		$vars = http_build_query( $params, NULL, '&' );

		return $this->_send_request( 'charges?'.$vars );
	}

	/**
	 * Register a new customer on system
	 * 
	 * @param  mixed         This can be a card token generated with stripe.js ( recommended ) or
	 *                       an array with the card information: number, exp_month, exp_year, cvc, name
	 * @param  string        The customer email address, useful as reference
	 * @param  string        A free form reference for the customer record
	 * @param  string        A subscription plan identifier to add the customer to it
	 */
	public function customer_create( $card, $email, $desc = NULL, $plan = NULL ) {
		$params = array(
			'card' => $card,
			'email' => $email
		);
		if( $desc )
			$params['description'] = $desc;
		if( $plan )
			$params['plan'] = $plan;

		return $this->_send_request( 'customers', $params, STRIPE_METHOD_POST );
	}

	/**
	 * Retrieve information for a given customer
	 * 
	 * @param  string        The customer ID to get information about
	 */
	public function customer_info( $customer_id ) {
		return $this->_send_request( 'customers/'.$customer_id );
	}

	/**
	 * Update an existing customer record
	 * 
	 * @param  string        The customer ID for the record to update
	 * @param  array         An array containing the new data for the user, you may use the
	 *                       following keys: card, email, description
	 */
	public function customer_update( $customer_id, $newdata ) {
		return $this->_send_request( 'customers/'.$customer_id, $newdata, STRIPE_METHOD_POST );
	}
	
	/**
	 * Apply a coupon code to a customer.
	 * 
	 * @param	string		The customer ID for the record to update
	 * @param	string		The coupon code value
	 * 
	 */
	public function apply_coupon( $customer_id, $coupon ) {
		$params = array(
			'coupon' => $coupon
		);
		return $this->customer_update( $customer_id, $params );
	}
	
	public function remove_coupon( $customer_id ) {
		return $this->_send_request( 'customers/'.$customer_id.'/discount', array(), STRIPE_METHOD_DELETE );
	}
	
	/**
	 * Delete an existing customer record
	 * 
	 * @param  string        The customer ID of the record to delete
	 */
	public function customer_delete( $customer_id ) {
		return $this->_send_request( 'customers/'.$customer_id, array(), STRIPE_METHOD_DELETE );
	}

	/**
	 * Get a list of customers record ordered by creation date
	 * 
	 * @param  int           The number of customers to return, default 10, max 100
	 * @param  int           Offset to apply to the list, default 0
	 */
	public function customer_list( $count = 10, $offset = 0 ) {
		$params['count'] = $count;
		$params['offset'] = $offset;
		$vars = http_build_query( $params, NULL, '&' );

		return $this->_send_request( 'customers?'.$vars );
	}

	/**
	 * Subscribe a customer to a plan
	 * 
	 * @param  string        The customer ID
	 * @param  string        The plan identifier
	 * @param  array         Configuration options for the subscription: prorate, coupon, trial_end(stamp)
	 */
	public function customer_subscribe( $customer_id, $plan_id, $options = array() ) {
		$options['plan'] = $plan_id;

		return $this->_send_request( 'customers/'.$customer_id.'/subscription', $options, STRIPE_METHOD_POST );
	}
	
	/**
	 * Create a card for the customer
	 *
	 * @param	string		The customer ID
	 * @param	string		The card token generated by stripe.js
	 */
	public function card_create( $customer_id, $card ) {
		$params = array(
			'card' => $card
		);
		
		return $this->_send_request( 'customers/'.$customer_id.'/cards', $params, STRIPE_METHOD_POST );
	}
	
	/**
	 * Retrieve information for a given customer
	 * 
	 * @param	string		The customer ID that owns the card
	 * @param	string		The card ID to get the details about
	 */
	public function card_info($customer_id, $card_id) {
		return $this->_send_request( 'customers/'.$customer_id.'/cards/'.$card_id);
	}
	
	/**
	 * Register a new recipient to the system
	 *
	 * @param	string		The recipient's name
	 * @param	string		Ths recipient's email address
	 * @param	string		A free form reference for the recipient record
	 * @param	string		Type of the recipient: either individual or corporation.
	 * @param	string		The recipient's tax ID, as a string. For type individual, the full SSN; for type corporation, the full EIN.
	 * @param	array		Requires fields 
	 *							'country'			The country the bank account is in. Currently, only US is supported.
	 * 							'routing_number'	The routing number for the bank account in string form. This should be the ACH routing number, not the wire routing number.
	 *						and 'account_number'	The account number for the bank account in string form. Must be a checking account.
	 * 
	 */
	public function recipient_create($name, $email, $routing_number, $account_number, $type = 'individual', $tax_id = NULL, $description = NULL ) {
		$params = array(
			'name' => $name,
			'email' => $email,
			'type' => $type,
			'bank_account' => array(
				'country' => 'US',
				'routing_number' => $routing_number,
				'account_number' => $account_number
			)
		);
			
		if( $description )
			$params['description'] = $description;
		
		if( $tax_id )
			$params['tax_id'] = $tax_id;
		
		return $this->_send_request( 'recipients', $params, STRIPE_METHOD_POST );
	}
	
	/**
	 * Retrieve information for a given recipient
	 * 
	 * @param  string        The recipient ID to get information about
	 */
	public function recipient_info($recipient_id) {
		return $this->_send_request( 'recipients/'.$recipient_id );
	}
	
	/**
	 * Update an existing recipient record
	 * 
	 * @param  string        The recipient ID for the record to update
	 * @param  array         An array containing the new data for the user, you may use the
	 *                       following keys: name, tax_id, bank_account, email, description
	 */
	public function recipient_update($recipient_id, $newdata) {
		return $this->_send_request( 'recipients/'.$recipient_id, $newdata, STRIPE_METHOD_POST );
	}
	
	
	/**
	 * Delete an existing recipient record
	 * 
	 * @param  string        The recipient ID of the record to delete
	 */
	public function recipient_delete($recipient_id) {
		return $this->_send_request( 'recipients/'.$recipient_id, STRIPE_METHOD_DELETE );
	}
	
	/**
	 * Get a list of customers record ordered by creation date
	 * 
	 * @param  int          The number of customers to return, default 10, max 100
	 * @param  int          Offset to apply to the list, default 0
	 * @param  boolean		Only return recipients that are verified or unverified.
	 */
	public function recipient_list( $count = 10, $offset = 0, $verified = FALSE ) {
		$params['count'] = $count;
		$params['offset'] = $offset;
		$params['verified'] = $verified ? 'true' : 'false';
		$vars = http_build_query( $params, NULL, '&' );

		return $this->_send_request( 'recipients?'.$vars );
	}

	/**
	 * Transfer the amount to the recipient
	 *
	 * @param  int			The amount in USD to transfer
	 * @param  string		The customer ID
	 * @param  string		Statement descriptor to show of the payment
	 * @param  string		Description to show for the transfer on the stripe dashboard
	 */
	public function transfer_create($amount, $recipient_id, $descriptor = '', $description = NULL ) {
		$params = array (
			'amount' => $amount * 100,
			'currency' => 'usd',
			'recipient' => $recipient_id,
			'statement_descriptor' => $descriptor
		);
		
		if( $description )
			$params['description'] = $description;
		
		return $this->_send_request( 'transfers', $params, STRIPE_METHOD_POST );
	}
	
	/**
	 * Get the information a transfer
	 *
	 * @param  string		The transfer ID
	 */
	public function transfer_info( $transfer_id ) {
		return $this->_send_request( 'transfers/'.$transfer_id );
	}
	
	/**
	 * Update the transfer description
	 *
	 * @param  string		The transfer ID
	 * @param  string		Description to show for the transfer on the stripe dashboard
	 */
	public function transfer_update( $transfer_id, $description ) {
		$newdata = array( 'description' => $description );
		$this->_send_request( 'transfers/'.$transfer_id, $newdata, STRIPE_METHOD_POST );
	}

	/**
	 * Cancel the pending transfer
	 * 
	 * @param  string		The transfer ID
	 */
	public function transfer_cancel( $transfer_id ) {
		$this->_send_request( 'transfers/'.$transfer_id.'/cancel', STRIPE_METHOD_POST );
	}

	/**
	 * Get a list of transfers
	 * 
	 * @param  int          The number of transfers to return, default 10, max 100
	 * @param  int          Offset to apply to the list, default 0
	 * @param  string		Only return transfers for the recipient provided.
	 * @param  string		Status code to filter the transfers to return
	 */
	public function transfer_list( $count = 10 , $offset = 0, $recipient_id = NULL, $status = NULL ) {
		$params = array( 
			'count' => $count,
			'offset' => $offset	
		);
		
		if( $recipient_id )
			$params['recipient'] = $recipient_id;
			
		if( $status )
			$params['status'] = $status;

		$vars = http_build_query( $params, NULL, '&' );
		$this->_send_request( 'transfers?'.$vars );
	}

	/**
	 * Cancel a customer's subscription
	 * 
	 * @param  string        The customer ID
	 * @param  boolean       Cancel the subscription immediately( FALSE ) or at the end of the current period( TRUE )
	 */
	public function customer_unsubscribe( $customer_id, $at_period_end = TRUE ) {
		$at_period_end ? $pend = 'true' : $pend = 'false';
		$url = 'customers/'.$customer_id.'/subscription?at_period_end='.$pend;

		return $this->_send_request( $url, array(), STRIPE_METHOD_DELETE );
	}

	/**
	 * Get the next upcoming invoice for a given customer
	 * 
	 * @param  string        Customer ID to get the invoice from
	 */
	public function customer_upcoming_invoice( $customer_id ) {
		return $this->_send_request( 'invoices/upcoming?customer='.$customer_id );
	}

	/**
	 * Generate a new single-use stripe card token
	 * 
	 * @param  array         An array containing the credit card data, with the following keys:
	 *                       number, cvc, exp_month, exp_year, name
	 * @param  int           If the token will be used on a charge, this is the amount to charge for
	 */
	public function card_token_create( $card_data, $amount ) {
		$params = array(
			'card' => $card_data,
			'amount' => $amount,
			'currency' => 'usd'
		);

		return $this->_send_request( 'tokens', $params, STRIPE_METHOD_POST );
	}

	/**
	 * Get information about a card token
	 * 
	 * @param  string        The card token ID to get the information
	 */
	public function card_token_info( $token_id ) {
		return $this->_send_request( 'tokens/'.$token_id );
	}

	/**
	 * Create a new subscription plan on the system
	 * 
	 * @param  string        The plan identifier, this will be used when subscribing customers to it
	 * @param  int           The amount in cents to charge for each period
	 * @param  string        The plan name, will be displayed in invoices and the web interface
	 * @param  string        The interval to apply on the plan, could be 'month' or 'year'
	 * @param  int           Number of days for the trial period, if any
	 */
	public function plan_create( $plan_id, $amount, $name, $interval, $trial_days  = FALSE ) {
		$params = array(
			'id' => $plan_id,
			'amount' => $amount,
			'name' => $name,
			'currency' => 'usd',
			'interval' => $interval
		);
		if( $trial_days )
			$params['trial_period_days'] = $trial_days;

		return $this->_send_request( 'plans', $params, STRIPE_METHOD_POST );
	}

	/**
	 * Retrieve information about a given plan
	 * 
	 * @param  string        The plan identifier you wish to get info about
	 */
	public function plan_info( $plan_id ) {
		return $this->_send_request( 'plans/'.$plan_id );
	}

	/**
	 * Delete a plan from the system
	 * 
	 * @param  string        The identifier of the plan you want to delete
	 */
	public function plan_delete( $plan_id ) {
		return $this->_send_request( 'plans/'.$plan_id, array(), STRIPE_METHOD_DELETE );
	}

	/**
	 * Retrieve a list of the plans in the system
	 */
	public function plan_list( $count = 10, $offset = 0 ) {
		$params['count'] = $count;
		$params['offset'] = $offset;
		$vars = http_build_query( $params, NULL, '&' );

		return $this->_send_request( 'plans?'.$vars );
	}

	/**
	 * Get infomation about a specific invoice
	 * 
	 * @param  string        The invoice ID
	 */
	public function invoice_info( $invoice_id ) {
		return $this->_send_request( 'invoices/'.$invoice_id );
	}

	/**
	 * Get a list of invoices on the system
	 * 
	 * @param  string        Customer ID to retrieve invoices only for a given customer
	 * @param  int           Number of invoices to retrieve, default 10, max 100
	 * @param  int           Offset to start the list from, default 0
	 */
	public function invoice_list( $customer_id = NULL, $count = 10, $offset = 0 ) {
		$params['count'] = $count;
		$params['offset'] = $offset;
		if( $customer_id )
			$params['customer'] = $customer_id;
		$vars = http_build_query( $params, NULL, '&' );

		return $this->_send_request( 'invoices?'.$vars );
	}

	/**
	 * Register a new invoice item to the upcoming invoice for a given customer
	 * 
	 * @param  string        The customer ID
	 * @param  int           The amount to charge in cents
	 * @param  string        A free form description explaining the charge
	 */
	public function invoiceitem_create( $customer_id, $amount, $desc ) {
		$params = array(
			'customer' => $customer_id,
			'amount' => $amount,
			'currency' => 'usd',
			'description' => $desc
		);

		return $this->_send_request( 'invoiceitems', $params, STRIPE_METHOD_POST );
	}

	/**
	 * Get information about a specific invoice item
	 * 
	 * @param  string        The invoice item ID
	 */
	public function invoiceitem_info( $invoiceitem_id ) {
		return $this->_send_request( 'invoiceitems/'.$invoiceitem_id );
	}

	/**
	 * Update an invoice item before is actually charged
	 * 
	 * @param  string        The invoice item ID
	 * @param  int           The amount for the item in cents
	 * @param  string        A free form string describing the charge
	 */
	public function invoiceitem_update( $invoiceitem_id, $amount, $desc = FALSE ) {
		$params['amount'] = $amount;
		$params['currency'] = 'usd';
		if( $desc ) $params['description'] = $desc;

		return $this->_send_request( 'invoiceitems/'.$invoiceitem_id, $params, STRIPE_METHOD_POST );
	}

	/**
	 * Delete a specific invoice item
	 * 
	 * @param  string        The invoice item identifier
	 */
	public function invoiceitem_delete( $invoiceitem_id ) {
		return $this->_send_request( 'invoiceitems/'.$invoiceitem_id, array(), STRIPE_METHOD_DELETE );
	}

	/**
	 * Get a list of invoice items
	 * 
	 * @param  string        Customer ID to retrieve invoices only for a given customer
	 * @param  int           Number of invoices to retrieve, default 10, max 100
	 * @param  int           Offset to start the list from, default 0
	 */
	public function invoiceitem_list( $customer_id = FALSE, $count = 10, $offset = 0 ) {
		$params['count'] = $count;
		$params['offset'] = $offset;
		if( $customer_id )
			$params['customer'] = $customer_id;
		$vars = http_build_query( $params, NULL, '&' );

		return $this->_send_request( 'invoiceitems?'.$vars );
	}

	/**
	 * Private utility function that prepare and send the request to the API servers
	 * 
	 * @param  string        The URL segments to use to complete the http request
	 * @param  array         The parameters for the request, if any
	 * @param  srting        Either 'post','get' or 'delete' to determine the request method, 'get' is default
	 */
	private function _send_request( $url_segs, $params = array(), $http_method = 'get' ) {
		if( $this->_conf['stripe_test_mode'] )
			$key = $this->_conf['stripe_key_test_secret'];
		else
			$key = $this->_conf['stripe_key_live_secret'];

		// Initializ and configure the request
		$req = curl_init( 'https://api.stripe.com/v1/'.$url_segs );
		curl_setopt( $req, CURLOPT_SSL_VERIFYPEER, $this->_conf['stripe_verify_ssl'] );
		curl_setopt( $req, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
		curl_setopt( $req, CURLOPT_USERPWD, $key.':' );
		curl_setopt( $req, CURLOPT_RETURNTRANSFER, TRUE );

		// Are we using POST? Adjust the request properly
		if( $http_method == STRIPE_METHOD_POST ) {
			curl_setopt( $req, CURLOPT_POST, TRUE );
			curl_setopt( $req, CURLOPT_POSTFIELDS, http_build_query( $params, NULL, '&' ) );
		}

		if( $http_method == STRIPE_METHOD_DELETE ) {
			curl_setopt( $req, CURLOPT_CUSTOMREQUEST, "DELETE" );
			curl_setopt( $req, CURLOPT_POSTFIELDS, http_build_query( $params, NULL, '&' ) );
		}

		// Get the response, clean the request and return the data
		$response = curl_exec( $req );
		curl_close( $req );
		return json_decode($response, true);
	}
}
// END Stripe Class

/* End of file Stripe.php */
/* Location: ./{APPLICATION}/libraries/Stripe.php */