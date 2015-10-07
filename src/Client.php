<?php
namespace Acenda;

date_default_timezone_set('GMT');

use Httpful;

/**
 * Class Response
 * primary type of response for Acenda SDK queries.
 * @package Acenda
 */
class Response{
    public $code;
    public $body;
    
    /**
     * @param Httpful\Response $response
     */
    public function __construct(Httpful\Response $response){
        $this->code = $response->code;
        $this->body = $response->body;
    }
}

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
    private $store_name;
    private $store_url;
    private $acenda_url;
    private $httpful;
    private $access_token;
    private $expires;
    private $scope;
    private $token_type;

    /**
     * @param $client_id Developer ID, usually in form of user@domain.com
     * @param $client_secret Developer key provided by Acenda.
     * @param $store_url The URL of the store we are working with.
     * @param $plugin_name Friendly name for localeconv(oid)gs etc. I don't think this is implemented.
     * @param $bypass_ssl Rather the SSL verification should be strict or not.
     * @throws AcendaException
     */
    public function __construct($client_id, $client_secret, $store_name, $bypass_ssl = false)
    {
        $this->httpful = Httpful\Request::init();
        
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;

        if (!$bypass_ssl) {
            $this->httpful = $this->httpful->withStrictSSL();
        }

        $this->generateStoreUrl($store_name);
    }

    private function generateStoreUrl($name){
        $_SERVER['ACENDA_MODE'] = "acendavm";
        switch((isset($_SERVER['ACENDA_MODE']) ? $_SERVER['ACENDA_MODE'] : null)){
            case "acendavm":
                $this->store_url = "http://admin.acendev/preview/".md5($name)."/api";
                $this->acenda_url = "http://acenda.acendev";
                break;
            case "development":
                $this->store_url = "https://admin.acenda.devserver/preview/".md5($name)."/api";
                $this->acenda_url = "https://acenda.acenda.devserver";
                break;
            default:
                $this->store_url = "https://admin.acenda.com/preview/".md5($name)."/api";
                $this->acenda_url = "https://acenda.com";
                break;
        }

        return (true);
    }

    private function handleSuccessToken($data){
        $this->access_token = $data['access_token'];
        $this->expires = (date("U") + $data['expires_in']);
        $this->scope = $data['scope'];
        $this->token_type = $data['token_type'];
    }

    public function initConnection()
    {
        $response = $this->httpful->post($this->acenda_url.'/oauth/token', json_encode([
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials'
        ]))->sendsJson()->send();

        switch ($response->code) {
            case 200:
                $this->handleSuccessToken(json_decode($response->raw_body, true));
                return true;
                break;
            default:
                throw new AcendaException($response->code, $response->body);
        };
    }

    /**
     * @return bool
     * @throws AcendaException
     */
    private function generate_query($uri, $params=[]){
        $params = array_merge(['access_token' => $this->access_token], $params);

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

    public function get($route, $data){
        return $this->performRequest($route, 'GET', $data);
    }

    public function post($route, $data){
        return $result = $this->performRequest($route, 'POST', $data);
    }

    public function put($route, $data){
        return $this->performRequest($route, 'PUT', $data);
    }

    public function delete($route, $data){
        return $this->performRequest($route, 'DELETE', $data);
    }

    /**
     * @param $route
     * @param $type
     * @param $data
     * @return array
     * @throws Exception
     * @throws Httpful\Exception\ConnectionErrorException
     */
    private function performRequest($route, $type, $data=[])
    {
        if (!$this->expires || $this->expires <= date("U") || !$this->token){
            $this->initConnection();
        }
        
        if (!is_array($data)){ throw new Exception('Wrong parameters provided'); }

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
                throw new Exception('Verb not recognized yet');
        }

        //Default in this switch is failure. All failures should fall through to default.
        switch ($response->code) {
            case 200:
            case 201:
                return new Response($response);
            default:
                return new Response($response);
        }
    }
}
