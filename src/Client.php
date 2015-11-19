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
    private $store_name;
    private $store_url;
    private $acenda_url;
    private $httpful;


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

        if (!$bypass_ssl) {
            $this->httpful = $this->httpful->withStrictSSL();
        }

        Authentication::init($client_id, $client_secret, $this->httpful);

        $this->generateStoreUrl($store_name);
    }

    /**
     * @return bool
     */
    private function generateStoreUrl($name){
        switch((isset($_SERVER['ACENDA_MODE']) ? $_SERVER['ACENDA_MODE'] : null)){
            case "acendavm":
                $this->store_url = "http://admin.acendev/preview/".md5($name)."/api";
                break;
            case "development":
                $this->store_url = "https://admin.acenda.devserver/preview/".md5($name)."/api";
                break;
            default:
                $this->store_url = "https://admin.acenda.com/preview/".md5($name)."/api";
                break;
        }

        return (true);
    }

    /**
     * @return bool
     * @throws AcendaException
     */
    private function generate_query($uri, $params=[]){
        $params = array_merge(['access_token' => Authentication::getToken()], $params);

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
    * @param $route Route used to query. ie: /order.
    * @param $data Query attributes. ie: ["query" => "*", "limit" => 1].
    * @return Acenda\Response
    */
    public function get($route, $data=[]){
        return $this->performRequest($route, 'GET', $data);
    }

    /**
    * @param $route Route used to query. ie: /order.
    * @param $data Query attributes. ie: ["query" => "*", "limit" => 1].
    * @return Acenda\Response
    */
    public function post($route, $data=[]){
        return $result = $this->performRequest($route, 'POST', $data);
    }

    /**
    * @param $route Route used to query. ie: /order.
    * @param $data Query attributes. ie: ["query" => "*", "limit" => 1].
    * @return Acenda\Response
    */
    public function put($route, $data=[]){
        return $this->performRequest($route, 'PUT', $data);
    }

    /**
    * @param $route Route used to query. ie: /order.
    * @param $data Query attributes. ie: ["query" => "*", "limit" => 1].
    * @return Acenda\Response
    */
    public function delete($route, $data=[]){
        return $this->performRequest($route, 'DELETE', $data);
    }

    /**
     * @param $route
     * @param $type
     * @param $data
     * @return array
     * @throws \Exception
     * @throws Httpful\Exception\ConnectionErrorException
     * @throws \Exception
     */
    private function performRequest($route, $type, $data=[])
    {
        if (!is_array($data)){ throw new \Exception('Wrong parameters provided'); }

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
                throw new \Exception('Verb not recognized yet');
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
