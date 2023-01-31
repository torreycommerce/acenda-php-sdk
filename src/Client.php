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
    private $acenda_mode = null;
    private $store_url;
    /**
     * @var Httpful\Request
     */
    private $httpful;
    private $throttle_iteration = 1;
    private $authentication;
    /*
     * Retry attempt tracking
     */
    private $retry_count = 0;
    private $max_retries;


    /**
     * Client constructor.
     * @param $client_id
     * @param $client_secret
     * @param $store_name
     * @param bool $bypass_ssl
     * @param int $max_retries
     * @throws \Exception
     */
    public function __construct($client_id, $client_secret, $store_name, $bypass_ssl = false, $max_retries = 5, $acenda_mode = null)
    {
        $this->httpful = Httpful\Request::init();
        $this->httpful->additional_curl_opts = [
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function () {}
        ];

        if (!$bypass_ssl) {
            $this->httpful = $this->httpful->withStrictSSL();
        }
        $this->acenda_mode = $acenda_mode;
        $this->authentication = new Authentication($client_id, $client_secret, $acenda_mode);
        $this->generateStoreUrl($store_name);
        $this->max_retries = $max_retries;
    }

    /**
     * @param $name
     * @return bool
     */
    private function generateStoreUrl($name)
    {
        $server_mode = $this->acenda_mode;
        if(!$server_mode){
            $server_mode = $_SERVER['ACENDA_MODE'] ?? null;
        }
        switch ($server_mode) {
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
     * @param array $headers
     * @return Response
     * @throws AcendaException
     */
    public function get($route, $data = [], $headers = [])
    {
        return $this->performRequest($route, 'GET', $data, [], $headers);
    }

    /**
     * @param string $route Route used to query. ie: /order.
     * @param array $data Query attributes. ie: ["query" => "*", "limit" => 1].
     * @param array $files
     * @param array $headers
     * @return Response
     * @throws AcendaException
     */
    public function post($route, $data = [], $files = [], $headers = [])
    {
        return $result = $this->performRequest($route, 'POST', $data, $files, $headers);
    }

    /**
     * @param string $route Route used to query. ie: /order.
     * @param array $data Query attributes. ie: ["query" => "*", "limit" => 1].
     * @param array $headers
     * @return Response
     * @throws AcendaException
     */
    public function put($route, $data = [], $headers = [])
    {
        return $this->performRequest($route, 'PUT', $data, [], $headers);
    }

    /**
     * @param string $route Route used to query. ie: /order.
     * @param array $data Query attributes. ie: ["query" => "*", "limit" => 1].
     * @return Response
     * @throws AcendaException
     */
    public function delete($route, $data = [], $headers = [])
    {
        return $this->performRequest($route, 'DELETE', $data, [], $headers);
    }

    public function getCurrentToken(){
        return $this->authentication->getToken();
    }


    /**
     * @param string $route
     * @param string $verb
     * @param array $data
     * @param array $files
     * @param array $additional_headers
     * @param bool $handle_throttle
     * @return Response
     * @throws AcendaException
     */
    private function performRequest($route, $verb = 'GET', $data = [], $files = [], $additional_headers = [], $handle_throttle = true)
    {
        if (!is_array($data)) {
            throw new \Exception('Wrong parameters provided');
        }

        switch (strtoupper($verb)) {
            case 'GET':
                $url = $this->generate_query($route, $data);
                $request = $this->httpful->get($url);
                break;
            case 'PUT':
                $url = $this->generate_query($route);
                $request = $this->httpful->put($url, json_encode($data))->sendsJson();
                break;
            case 'POST':
                $url = $this->generate_query($route);
                if (count($files)) {
                    $request = $this->httpful->post($url)->body($data)->sendsType(Httpful\Mime::FORM)->attach($files);
                } else {
                    $request = $this->httpful->post($url, json_encode($data))->sendsJson();
                }
                break;
            case 'DELETE':
                $url = $this->generate_query($route, $data);
                $request = $this->httpful->delete($url)->sendsJson();
                break;
            default:
                throw new \Exception('Verb not recognized yet');
        }
        if($additional_headers){
            $request->addHeaders($additional_headers);
        }
        $request->addHeaders(['AUTHORIZATION' => 'Bearer ' . $this->authentication->getToken()]);
        try {
            $response = $request->send();
        } catch (Httpful\Exception\ConnectionErrorException $e) {
            if ($this->retry_count >= $this->max_retries) {
                throw new AcendaException(500, "Connection Error Exception, out of retries: " . $e->getMessage());
            }
            // Sleep 5 seconds
            sleep(5);
            $this->retry_count++;
            return $this->performRequest($route, $verb, $data, $files, $additional_headers, $handle_throttle);
        }
        $this->retry_count = 0;

        //Default in this switch is failure. All failures should fall through to default.
        switch ($response->code) {
            case 200:
            case 201:
                // Reset throttle iterations since we got through
                $this->throttle_iteration = 1;
                return new Response($response);
                break;
            case 429:
                if ($handle_throttle) {
                    $this->throttle();
                    $this->throttle_iteration++;
                    return $this->performRequest($route, $verb, $data, $files, $additional_headers, $handle_throttle);
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
