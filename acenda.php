<?php

class Acenda {
	private $client_id;
	private $client_secret;
	private $store_url;

	private $token = ['access_token' => '', 'expires_in' => '', 'token_type' => '', 'scope' => ''];

	private $ch;

	public function __construct($client_id, $client_secret, $store_url,$plugin_name) {
		$this->client_id=$client_id;
		$this->client_secret=$client_secret;
		$this->store_url=$store_url;
		$this->plugin_name=$plugin_name;

		$this->initCurl();

		$this->initConnection();
		//$acenda
	}

	public function __destructor() {
		$this->closeCurl();
	}

	public function initConnection() {
		list($httpCode, $httpJsonResponse) = $this->performRequest('/oauth/token', 'POST', array(	'client_id' => $this->client_id,
			             										'client_secret' => $this->client_secret, 
			             										'grant_type' => 'client_credentials' 
			             									)
			            	);
		$httpResponse = json_decode($httpJsonResponse,true);
		switch ($httpCode) {
			case 200:
				$this->token = $httpResponse;
				return true;
				break;
			default:
				throw new Exception($httpCode.": ".$httpResponse['error']." - ".$httpResponse['error_description']);
				break;
		};
	}
	
	public function performRequest($route, $type, $data) {
		$data_json = json_encode($data);

		$url = $this->store_url.(!empty($this->token['access_token']) ? "/api".$route."?access_token=".$this->token['access_token'] : $route );

		curl_setopt($this->ch, CURLOPT_URL, $url);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $type);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data_json);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(                                                                          
		    'Content-Type: application/json',
		    'Content-Length: ' . strlen($data_json))
		);
		$httpResponse = curl_exec($this->ch);
		$httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		echo "\n";
		echo "Url: ".$url."\n";
		echo "Code: ".$httpCode."\n";
		echo "Response: ".$httpResponse."\n";
		return array($httpCode, $httpResponse);
	}

	public function initCurl() {
		$this->ch = curl_init();
	}

	public function closeCurl() {
		curl_close($this->ch);
	}
}
/*
	This Doesn't work cause file get content doesn't know how to deal with conenction errors.
	It gives you a warning on error. (No Exception)
	
	public function initConnection() {
		$result = file_get_contents(
			$this->store_url.'/oauth/token', 
			false, 
			stream_context_create(array(
				'http' => array(
			     	'method' => 'POST',
			        'header' => 'Content-type: application/json',
			        'content' => json_encode(
			            					array(	'client_id' => $this->client_id,
			             							'client_secret' => $this->client_secret, 
			             							'grant_type' => 'client_credentials' 
			             						)
			            					)		 
			    )
			))
		);
		var_dump($result);
	}

*/

?>