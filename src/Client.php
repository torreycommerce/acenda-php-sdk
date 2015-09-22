<?php
namespace Acenda;

use Httpful;

class Client
{
    private $client_id;
    private $client_secret;
    private $store_url;
    private $token = ['access_token' => '', 'expires_in' => '', 'token_type' => '', 'scope' => ''];
    public $bypass_ssl = false;

    /**
     * @param $client_id
     * @param $client_secret
     * @param $store_url
     * @param $plugin_name
     * @throws AcendaException
     */
    public function __construct($client_id, $client_secret, $store_url, $plugin_name)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->store_url = $store_url;
        $this->plugin_name = $plugin_name;
        $this->initConnection();
    }

    /**
     * @return bool
     * @throws AcendaException
     */
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
        $httpful = Httpful\Request::init();
        if (!$this->bypass_ssl) {
            $httpful = $httpful->withStrictSSL();
        }
        $data_json = is_array($data) ? json_encode($data) : $data;
        $url = $this->store_url . (!empty($this->token['access_token']) ? "/api" . $route . "?access_token=" . $this->token['access_token'] : $route);
        switch (strtoupper($type)) {
            case 'GET':
                //Append the query.
                $url .= "&query=" . $data_json;
                $response = $httpful->get($url)->send();
                break;
            case 'PUT':
                $response = $httpful->put($url, $data_json)->sendsJson()->send();
                break;
            case 'POST':
                $response = $httpful->post($url, $data_json)->sendsJson()->send();
                break;
            default:
                throw new AcendaException('Verb ' . $type . ' Not Understood');
        }

        if ($response->code != 200) {
            //This is to catch a blank code.
            $http_code = $response->code ? $response->code : 400;
            //There be an error!
            if ($response->body) {
                $curl_error['error'] = isset($response->body->error) ? $response->body->error : $response->code;
                $curl_error['error_description'] = isset($response->body->error_description) ? $response->body->error_description : 'There was an unknown error making the request.';
            }
            $http_response = json_encode($curl_error);
        } else {
            //Then it worked! We aren't using any of the httpful response magicalness, so return it raw.
            /*
             * If someone is interested in said magicalness, it can basically type juggle the response into a stdClass,
             * So if you have JSON coming back - it comes back json_decoded. If you have XML, it also comes back as stdClass.
             */
            $http_response = $response->raw_body;
            $http_code = $response->code;
        }
        return array($http_code, $http_response);
    }
}