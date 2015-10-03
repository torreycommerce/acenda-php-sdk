<?php
namespace Acenda;

use Httpful;

/**
 * Class Client
 * Primary client for interacting with the Acenda SDK from PHP.
 * @package Acenda
 */
class Client
{
    private $route;
    private $client_id;
    private $client_secret;
    private $store_url;
    private $acenda_api_url;
    private $httpful;
    private $token = ['access_token' => '', 'expires_in' => '', 'token_type' => '', 'scope' => ''];

    /**
     * @param $client_id Developer ID, usually in form of user@domain.com
     * @param $client_secret Developer key provided by Acenda.
     * @param $store_url The URL of the store we are working with.
     * @param $plugin_name Friendly name for logs etc. I don't think this is implemented.
     * @param $bypass_ssl Rather the SSL verification should be strict or not.
     * @throws AcendaException
     */
    public function __construct($client_id, $client_secret, $store_url, $plugin_name, $bypass_ssl = false)
    {
        $this->httpful = Httpful\Request::init();

        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->store_url = $store_url.($store_url[strlen($store_url)-1] == '/' ? 'api' : '/api');
        $this->acenda_api_url = $store_url;
        $this->plugin_name = $plugin_name;

        if (!$bypass_ssl) {
            $this->httpful = $this->httpful->withStrictSSL();
        }

        $this->initConnection();
    }

    public function getToken(){
        return $this->token;
    }

    /**
     * @return bool
     * @throws AcendaException
     */
    public function initConnection()
    {
        $response = $this->httpful->post($this->acenda_api_url.'/oauth/token', json_encode([
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials'
        ]))->sendsJson()->send();

        switch ($response->code) {
            case 200:
                $this->token = json_decode($response->raw_body, true);
                return true;
                break;
            default:
                throw new AcendaException($http_code . ": " . $http_response['error'] . " - " . $http_response['error_description']);
        };
    }

    private function generate_query($uri, $params=[]){
        $params = array_merge(['access_token' => $this->token['access_token']], $params);

        $parameters = "";
        $index = 0;
        foreach($params as $k => $v){
            if ($index >= 1){ $parameters .= "&"; }
            
            if (is_array($v)){ $parameters .= ($k."=".urlencode(json_encode($v))); }
            else{ $parameters .= ($k."=".urlencode($v)); } 
            $index++;
        }

        $route = $this->store_url;
        $route .= ($uri[0] == '/') ? $uri : '/'.$uri;
        $route .= (strpos($uri, '?') == false ? '?' : '&').$parameters;
        
        return $route;
    }

    /**
    * @param Httpful\Response $response
    * @return array
    */
    protected function requestSuccess(Httpful\Response $response)
    {
        $http_response = $response->raw_body;
        $http_code = $response->code;
        return array($http_code, $http_response);
    }

    /**
    * @param Httpful\Response $response
    * @return array
    */
    protected function requestFailure(Httpful\Response $response)
    {
        $http_code = $response->code ? $response->code : 400;
        $curl_error = [];
        if ($response->body) {
            $curl_error['error'] = isset($response->body->error) ? $response->body->error : $http_code;
            $curl_error['error_description'] = isset($response->body->error_description) ? $response->body->error_description : 'There was an unknown error making the request.';
        }
        $http_response = json_encode($curl_error);
        return array($http_code, $http_response);
    }

    /**
     * @param $route
     * @param $type
     * @param $data
     * @return array
     * @throws AcendaException
     * @throws Httpful\Exception\ConnectionErrorException
     */
    public function performRequest($route, $type, $data)
    {
        if (!is_array($data)){ throw new AcendaException('Wrong parameters provided'); }

        switch (strtoupper($type)) {
            case 'GET':
                $url = $this->generate_query($route, $data);
                $response = $this->httpful->get($url)->send();
                break;
            case 'PUT':
                $url = $this->generate_query($route);
                $response = $this->httpful->put($url, json_encode($data))->sendsJson()->send();
                break;
            case 'POST':
                $url = $this->generate_query($route);
                $response = $this->httpful->post($url, json_encode($data))->sendsJson()->send();
                break;
            case 'DELETE':
                $url = $this->generate_query($route, $data);
                $response = $this->httpful->delete($url)->sendsJson()->send();
                break;
            default:
                throw new AcendaException('Verb not recognized yet');
        }

        //Default in this switch is failure. All failures should fall through to default.
        switch ($response->code) {
            case 200:
            case 201:
                return $this->requestSuccess($response);
            default:
                return $this->requestFailure($response);
        }
    }
}
