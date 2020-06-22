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
    private $store_url;
    private $httpful;
    private $throttle_iteration = 1;
    private $authentication;


    /**
     * Client constructor.
     * @param $client_id
     * @param $client_secret
     * @param $store_name
     * @param bool $bypass_ssl
     * @throws \Exception
     */
    public function __construct($client_id, $client_secret, $store_name, $bypass_ssl = false)
    {
        $this->httpful = Httpful\Request::init();

        if (!$bypass_ssl) {
            $this->httpful = $this->httpful->withStrictSSL();
        }
        $this->authentication = new Authentication($client_id, $client_secret);
        $this->generateStoreUrl($store_name);
    }

    /**
     * @param $name
     * @return bool
     */
    private function generateStoreUrl($name)
    {
        switch ((isset($_SERVER['ACENDA_MODE']) ? $_SERVER['ACENDA_MODE'] : null)) {
            case "acendavm":
                $this->store_url = "http://admin.acendev/preview/" . md5($name) . "/api";
                break;
            case "development":
                $this->store_url = "https://admin.acenda.devserver/preview/" . md5($name) . "/api";
                break;
            default:
                $this->store_url = "https://admin.acenda.com/preview/" . md5($name) . "/api";
                break;
        }

        return true;
    }

    /**
     * @param $uri
     * @param array $params
     * @return string
     */
    private function generate_query($uri, $params = [])
    {
        $params = array_merge(['access_token' => $this->authentication->getToken()], $params);

        $parameters = "";
        $index = 0;
        foreach ($params as $k => $v) {
            if ($index >= 1) {
                $parameters .= "&";
            }

            if (is_array($v)) {
                $parameters .= ($k . "=" . urlencode(json_encode($v)));
            } else {
                $parameters .= ($k . "=" . urlencode($v));
            }
            $index++;
        }

        $route = $this->store_url;
        $route .= ($uri[0] == '/') ? $uri : '/' . $uri;
        $route .= (strpos($uri, '?') == false ? '?' : '&') . $parameters;

        return $route;
    }

    /**
     * @param string $route Route used to query. ie: /order.
     * @param array $data Query attributes. ie: ["query" => "*", "limit" => 1].
     * @return Response
     * @throws Httpful\Exception\ConnectionErrorException
     */
    public function get($route, $data = [])
    {
        return $this->performRequest($route, 'GET', $data);
    }

    /**
     * @param string $route Route used to query. ie: /order.
     * @param array $data Query attributes. ie: ["query" => "*", "limit" => 1].
     * @param array $files
     * @return Response
     * @throws Httpful\Exception\ConnectionErrorException
     */
    public function post($route, $data = [], $files = [])
    {
        return $result = $this->performRequest($route, 'POST', $data, $files);
    }

    /**
     * @param string $route Route used to query. ie: /order.
     * @param array $data Query attributes. ie: ["query" => "*", "limit" => 1].
     * @return Response
     * @throws Httpful\Exception\ConnectionErrorException
     */
    public function put($route, $data = [])
    {
        return $this->performRequest($route, 'PUT', $data);
    }

    /**
     * @param string $route Route used to query. ie: /order.
     * @param array $data Query attributes. ie: ["query" => "*", "limit" => 1].
     * @return Response
     * @throws Httpful\Exception\ConnectionErrorException
     */
    public function delete($route, $data = [])
    {
        return $this->performRequest($route, 'DELETE', $data);
    }


    /**
     * @param string $route
     * @param string $verb
     * @param array $data
     * @param array $files
     * @param bool $handle_throttle
     * @return Response
     * @throws Httpful\Exception\ConnectionErrorException
     */
    private function performRequest($route, $verb = 'GET', $data = [], $files = [], $handle_throttle = true)
    {
        if (!is_array($data)) {
            throw new \Exception('Wrong parameters provided');
        }

        switch (strtoupper($verb)) {
            case 'GET':
                $url = $this->generate_query($route, $data);
                $response = $this->httpful->get($url)->addHeaders(['AUTHORIZATION'=>'Bearer '.Authentication::getToken()])->send();
                break;
            case 'PUT':
                $url = $this->generate_query($route);
                $response = $this->httpful->put($url, json_encode($data))->sendsJson()->addHeaders(['AUTHORIZATION'=>'Bearer '.Authentication::getToken()])->send();
                break;
            case 'POST':
                $url = $this->generate_query($route);
                if(count($files)) {
                    $response = $this->httpful->post($url)->body($data)->sendsType(Httpful\Mime::FORM)->attach($files)->addHeaders(['AUTHORIZATION'=>'Bearer '.Authentication::getToken()])->send();
                } else {
                    $response = $this->httpful->post($url, json_encode($data))->sendsJson()->addHeaders(['AUTHORIZATION'=>'Bearer '.Authentication::getToken()])->send();
                }
                break;
            case 'DELETE':
                $url = $this->generate_query($route, $data);
                $response = $this->httpful->delete($url)->sendsJson()->addHeaders(['AUTHORIZATION'=>'Bearer '.Authentication::getToken()])->send();
                break;
            default:
                throw new \Exception('Verb not recognized yet');
        }

        //Default in this switch is failure. All failures should fall through to default.
        switch ($response->code) {
            case 200:
            case 201:
                // Why are we resetting throttle iteration here? The throttle in this thing is whack.
                $this->throttle_iteration = 1;
                return new Response($response);
                break;
            case 429:
                if ($handle_throttle) {
                    $this->throttle();
                    $this->throttle_iteration++;
                    return $this->performRequest($route, $verb, $data, $files, $handle_throttle);
                } else {
                    return new Response($response);
                }
            default:
                return new Response($response);
        }
    }

    private function throttle()
    {
        sleep(pow(2, $this->throttle_iteration));

    }
}
