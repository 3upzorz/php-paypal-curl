<?php namespace Trevor;

class PayPalCurl{

	//REST CREDENTIALS

	//place paypal client id here
	private static $client_id = "";
	//place paypal client secret here
	private static $client_secret = "";
	//place base uri here for testing/not testing
	//sandbox = https://api.sandbox.paypal.com/v1
	//live    = https://api.paypal.com/v1
	private static $base_uri = "https://api.sandbox.paypal.com/v1";

	//CLASSIC CREDENTIALS
	//TEST
	// private static $base_uri_classic = "https://api-3t.sandbox.paypal.com/nvp";
	// private static $ppUsername = "";
	// private static $ppPassword = "";
	// private static $ppSignature = "";
	// public static $redirectUrl = "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
	

	//PRODUCTION
	private static $base_uri_classic = "https://api-3t.paypal.com/nvp";
	private static $ppUsername = "";
	private static $ppPassword = "";
	private static $ppSignature = "";
	private static $returnUrl = "";
	private static $cancelUrl = "";
	private static $version = 115;

	public static $redirectUrl = "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
	public static $currencyCode = "USD";

	/**
	 * cURL the data for the classic NVP api
	 * @param {Array} data : nvp array
	 * @return {Array} arr : the nvp string passed back by paypal turned into an array
	 */
	private static function curlClassic($data){

		$query = http_build_query($data);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, self::$base_uri_classic);
		curl_setopt($ch, CURLOPT_HEADER, false); //no special headers
		curl_setopt($ch, CURLOPT_POST, true); //send via post
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  //curl returns something
		curl_setopt($ch, CURLOPT_POSTFIELDS, $query); //data to send

		$result = curl_exec($ch);
		curl_close($ch);
		$arr;
		parse_str($result,$arr);
		return $arr;
	}

	/**
	 * Creates a payment using the Classic NVP api
	 * @param {Array} paymentRequest : contains details about the payment
	 *				=> {String} currency : 3 letter currency code
	 * @param {Array} items : an array of all the items
	 *				n => {Array} item : 
	 *					=> {String} name : name of item
	 *					=> {String} amt : amount of item, must be formatted to exactly 0 or 2 decimal places
	 *					=> {String} desc : description of item
	 *					=> {int} qty : quantity of item
	 * @return {Array} result : the nvp string passed back by paypal turned into an array
	 */
	public static function setExpressCheckout($paymentRequest, $items){

		$data = array(
			"USER" => self::$ppUsername,
			"PWD" => self::$ppPassword,
			"SIGNATURE" => self::$ppSignature,
			"VERSION" => self::$version,
			"METHOD" => "SetExpressCheckout",
			"RETURNURL" => self::$returnUrl,
			"CANCELURL" => self::$cancelUrl,
			"PAYMENTREQUEST_0_PAYMENTACTION" => "Sale",
			"PAYMENTREQUEST_0_CURRENCYCODE" => $paymentRequest['currency']
		);

		$total = 0.00;
		for($i = 0; $i < count($items); $i++){
			$total += $items[$i]["amt"];
			$baseStr = "L_PAYMENTREQUEST_0_";
			$data[$baseStr . "NAME" . $i] = $items[$i]["name"];
			$data[$baseStr . "AMT" . $i] = $items[$i]["amt"];
			$data[$baseStr . "DESC" . $i] = $items[$i]["desc"];
			$data[$baseStr . "QTY" . $i] = $items[$i]["qty"];
		}
		$data["PAYMENTREQUEST_0_ITEMAMT"] = number_format($total,2,'.','');
		$data["PAYMENTREQUEST_0_AMT"] = number_format($total,2,'.','');

		$result = self::curlClassic($data);

		return $result;
	}

	/**
	 * Gets payment details after creating a payment
	 * @param {String} token : token obtained from setExpressCheckout
	 * @return {Array} result : the nvp string passed back by paypal turned into an array
	 */
	public static function getExpressCheckoutDetails($token){

		$data = array(
			"USER" => self::$ppUsername,
			"PWD" => self::$ppPassword,
			"SIGNATURE" => self::$ppSignature,
			"VERSION" => self::$version,
			"METHOD" => "GetExpressCheckoutDetails",
			"TOKEN" => $token
		);

		$result = self::curlClassic($data);

		return $result;
	}

	/**
	 * Completes express checkout payment, send updated tax and shipping amount
	 * @param {String} token : token obtained from getExpressCheckoutDetails
	 * @param {String} payerId : id of the payer obtained from getExpressCheckoutDetails
	 * @param {String} itemAmount : the total amount of the items in the order
	 * @param {String} taxAmount : the amount of tax to apply to the order
	 * @param {String} shippingAmount : the amount of shipping to apply to the order
	 * @return {Array} result : the result of the finished payment
	 */
	public static function doExpressCheckoutPayment($token, $payerId, $itemAmount, $taxAmount, $shippingAmount){

		$amt = $itemAmount + $taxAmount + $shippingAmount;
		$data = array(
			"USER" => self::$ppUsername,
			"PWD" => self::$ppPassword,
			"SIGNATURE" => self::$ppSignature,
			"VERSION" => self::$version,
			"METHOD" => "DoExpressCheckoutPayment",
			"TOKEN" => $token,
			"PAYERID" => $payerId,
			"PAYMENTREQUEST_0_AMT" => number_format($amt,2,'.',''),
			"PAYMENTREQUEST_0_CURRENCYCODE" => self::$currencyCode,
			"PAYMENTREQUEST_0_ITEMAMT" => number_format($itemAmount,2,'.',''),
			"PAYMENTREQUEST_0_SHIPPINGAMT" => $shippingAmount,
			"PAYMENTREQUEST_0_TAXAMT" => $taxAmount
		);
		
		$result = self::curlClassic($data);

		return $result;
	}


	//TODO REST add checking to see if token was retreived successfully on methods that use getToken
	/**
	 * Obtains the object containing the oath token or false if failure
	 * return properties:
	 * 	String scope
	 *  String access_token
	 *	String token_type
	 *	int expires_in
	 */
	public static function getToken(){

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, self::$base_uri  . "/oauth2/token"); //url to send to
		curl_setopt($ch, CURLOPT_HEADER, false); //no special headers
		// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, true); //send via post
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  //curl returns something
		curl_setopt($ch, CURLOPT_USERPWD, self::$client_id.":".self::$client_secret); //info to authenticate with
		curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials"); //data to post

		$result = curl_exec($ch); //execute curl

		curl_close($ch); //close curl

		if(empty($result)){

			return false; //not completed successfully
		}
		else
		{
		    return json_decode($result);
		}
	}

	/**
	 * Creates a paypal payment payment
	 * See https://developer.paypal.com/webapps/developer/docs/api/ for structure of objects
	 * @param {String} $payment_method (accepts 'paypal' or 'credit_card')
	 * @param {Array} transaction
	 *					=>{Array} amount
	 *					=>{String} description (127 char max)
	 *					=>{Array} item_list
 	 *						=>{Array} items
 	 *							=>{Array} item
	 *					=>{Array} (optional) related_resources (array of sale, authorization, capture, or refund, objects)
	 * @param {Array} (required if payment method 'credit_card') $credit_card
	 *					=> number
	 *					=> type
	 *					=> expire_month
	 *					=> expire_year
	 *					=> cvv2
	 *					=> first_name
	 *					=> last_name
	 * @param {Array} (required if payment method 'credit_card') $address
	 *					=>line1
	 *					=>(optional)line2
	 *					=>city
	 *					=>state
	 *					=>postal_code
	 *					=>country_code
	 */
	public static function createPayment($payment_method, $transaction, $credit_card = null, $address = null){

		$accepted_payment_methods = array('credit_card', 'paypal');
		if(!in_array($payment_method, $accepted_payment_methods)){
			throw new \Exception("invalid payment method");
		}

		$details = array(
			"subtotal" => $transaction['amount']['details']['subtotal']//num format
		);

		if(isset($transaction['amount']['details']['tax'])){
			$details["tax"] = $transaction['amount']['details']['tax'];//num format
		}
		if(isset($transaction['amount']['details']['tax'])){
			$details["shipping"] = $transaction['amount']['details']['shipping'];//num format
		}

		$amount = array(
			"total" => $transaction['amount']['total'], //paypal expects no decimals or 2 decimals, nothing in between
			"currency" => $transaction['amount']['currency'],
			"details" => $details
		);

		$transactions = array(
			array(
				'amount' => $amount,
				'description' => $transaction['description'],
				'item_list' => $transaction['item_list']
			)
		);

		$payer = array(
			'payment_method' => $payment_method
		);

		if($payment_method == 'credit_card'){
			//credit_card and address cannot be empty
			if(!self::isArrayGood($credit_card)){
				throw new \Exception('invalid credit_card array');
			}
			if(!self::isArrayGood($address)){
				throw new \Exception('invalid address array');
			}
			$billing_address = array(
				'line1' => $address['line1'],
				'city' => $address['city'],
				'state' => $address['state'],
				'postal_code' => $address['postal_code'],
				'country_code' => $address['country_code'],
			);
			if(isset($address['line2']) && $address['line2']){
				$billing_address['line2'] = $address['line2'];
			}

			$credit_card = array(
				"number" => $credit_card['number'],
				"type" => $credit_card['type'],
				"expire_month" => $credit_card['expire_month'],
				"expire_year" => $credit_card['expire_year'],
				"cvv2" => $credit_card['cvv2'],
				"first_name" => $credit_card['first_name'],
				"last_name" => $credit_card['last_name'],
				'billing_address' => $billing_address
			);

			$funding_instruments = array(
				array(
					"credit_card" => $credit_card
				)
			);

			$payer['funding_instruments'] = $funding_instruments;
		}

		$data = array(
			'intent' => 'sale',
			'payer' => $payer,
			'transactions' => $transactions
		);

		if($payment_method == 'paypal'){

			$data['redirect_urls'] = array(
				'return_url' => 'http://192.168.1.21/house-plan-hunters/public/paypal/confirm', //TODO
				'cancel_url' => 'http://192.168.1.21/house-plan-hunters/public/paypal/cancel'
			);
		}

		$token = self::getToken()->access_token;

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, self::$base_uri . "/payments/payment"); //uri to send to
		curl_setopt($ch, CURLOPT_POST, true); //send post data
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //curl will return
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); //data to send
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type:application/json",	         //Sending json
			"Authorization: Bearer $token")	         //oath token to send
		);

		$result = curl_exec($ch); //execute curl

		curl_close($ch); //close curl

	    return json_decode($result);
	}

	/**
	 * Retrieves payment information for given payment id
	 * @param {String} payment_id
	 */
	public static function getPayment($payment_id){

		$token = self::getToken();

		if(!$token){

			//TODO handle token failure
		}

		$token = $token->access_token;

		$ch = curl_init();

		//TODO return only the correct payment, not an array of payments

		curl_setopt($ch, CURLOPT_URL, self::$base_uri . "/payments/payment/$payment_id");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type:application/json",	         //Sending json
		  	"Authorization: Bearer $token"	         	 //oath token to send
		));

		$result = curl_exec($ch);
		curl_close($ch);

		return json_decode($result);
	}

	/**
	 * Executes a payment
	 * @param {String} $payment_id
	 * @param {String} $payer_id
	 * @param {Array} (optional)$transactionAmount
	 *				=> {String} currency
	 *				=> {String} total
	 *				=> {Array} details
	 *					=> {String} shipping
	 *					=> {String} subtotal
	 *					=> {String} tax
	 *					=> {String} fee
	 */
	public static function executePayment($payment_id, $payer_id, $transactionAmount = null){

		$token = self::getToken();

		if(!$token){

			//TODO handle token failure
		}

		$token = $token->access_token;

		$ch = curl_init();

		$data = array(
			'payer_id' => $payer_id
		);

		if($transactionAmount){

			$transaction = $transactionAmount;

			$transactions = array(
				$transaction
			);

			$data['transactions'] = $transactions;
		}

		curl_setopt($ch, CURLOPT_URL, self::$base_uri . "/payments/payment/$payment_id/execute");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type:application/json",	         //Sending json
		  	"Authorization: Bearer $token"	         	 //oath token to send
		));
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); //data to send

		$result = curl_exec($ch);
		curl_close($ch);

		return json_decode($result);
		// return $data;
	}

	/**
	 * Get the info for a completed sale from paypal
	 */
	public static function getSale($token, $sale_id){

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, self::$base_uri . "/payments/sale/$sale_id");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type:application/json",	         //Sending json
		  	"Authorization: Bearer $token"	         	 //oath token to send
		));

		$result = curl_exec($ch);
		curl_close($ch);

		return json_decode($result);
	}

	/**
	 * Creates legible error messages that can be sent to front end
	 */
	public static function generateErrorMessages($error){

		$return_errors = array();

		// if($error->name == 'INTERNAL_SERVICE_ERROR'){
			// //internal service, retry at another time
			// $return_errors[] = $error->message . ' please try again in a few minutes';
 		// }

 		if($error->name == 'VALIDATION_ERROR'){

 			foreach($error->details as $detail){

				$arr = explode('.', str_replace('_', ' ', $detail->field));
				$error = '';
				for($i = 2; $i < count($arr); $i++){

					$error .= $arr[$i] . ' ';
				}
				$return_errors[] .= $detail->issue;
			}
 		}
 		else{

 			$return_errors[] = $error->message;
 		}
 		
 		return $return_errors;
	}

	/**
	 * Checks if an array and all of its first level indexes are not empty
	 * @param {Array} $to_check
	 * @param {Array} $not_required
	 * @return {Boolean} empty
	 */
	private static function isArrayGood($to_check, $not_required = null){

		if(!isset($to_check) && empty($to_check)){
			return false;
		}

		if($not_required){
			//there are some indexes that are allowed to be empty
			foreach ($to_check as $key => $value) {
				if(!in_array($key, $not_required)){
					if(!isset($value) && !$value){
						//a required index is empty, return false
						return false;
					}
				}
			}
		}else{
			//every index is required
			foreach ($to_check as $key => $value) {
				if(!isset($value) && !$value){
					//a required index is empty, return false
					return false;
				}
			}
		}
		//all required indexes are not empty
		return true;
	}
}