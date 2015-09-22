<?php
namespace Acenda;
use Httpful;

class Client
{
    private $client_id;
    private $client_secret;
    private $store_url;
    private $token = ['access_token' => '', 'expires_in' => '', 'token_type' => '', 'scope' => ''];
//    private $ch;
    public $bypass_ssl = false;

    public function __construct($client_id, $client_secret, $store_url, $plugin_name)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->store_url = $store_url;
        $this->plugin_name = $plugin_name;
        $this->initConnection();
    }

    public function initConnection()
    {
        list($http_code, $http_json_response) = $this->performRequest('/oauth/token', 'POST', array('client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'client_credentials'
            )
        );
        $http_response = json_decode($http_json_response, true);
        switch ($http_code) {
            case 200:
                $this->token = $http_response;
                return true;
                break;
            default:
                throw new AcendaException($http_code . ": " . $http_response['error'] . " - " . $http_response['error_description']);
        };
    }

    public function performRequest($route, $type, $data)
    {
        /*
         * So, httpful defaults to strict ssl off - so we would really want to invert this logic here.
         * @todo Talk to Ahmet to see what the use case was for this - and if it needs to remain. I bet it's for local dev testing.
         */
        if ($this->bypass_ssl) {
//            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
//            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $data_json = is_array($data) ? json_encode($data) : $data;
        $url = $this->store_url . (!empty($this->token['access_token']) ? "/api" . $route . "?access_token=" . $this->token['access_token'] : $route);
        switch (strtoupper($type)) {
            case 'GET':
                //Append the query.
                $url .= "&query=" . $data_json;
                $response = Httpful\Request::get($url)->send();
                break;
            case 'PUT':
                $response = Httpful\Request::put($url, $data_json)->sendsJson()->send();
                break;
            case 'POST':
                $response = Httpful\Request::post($url, $data_json)->sendsJson()->send();
                break;
            default:
                throw new AcendaException('Verb ' . $type . ' Not Understood');
        }

//        print_r($response);
        if ($response->code != 200) {
            //This is to catch a blank code.
            $http_code = $response->code?$response->code:400;
            //There be an error!
            $curl_error['error'] = $response->body->status;
            $curl_error['error_description'] = $response->body->error;
            $http_response = json_encode($curl_error);
        } else {
            //Then it worked!
            $http_response = $response->raw_body;
            $http_code = $response->code;
        }
        return array($http_code, $http_response);
    }
}

?>