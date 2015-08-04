<?php
namespace Acenda;

class Client {
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
    }

    public function __destruct() {
        $this->closeCurl();
    }

    public function initConnection() {
        list($http_code, $http_json_response) = $this->performRequest('/oauth/token', 'POST', array(    'client_id' => $this->client_id,
                                                                'client_secret' => $this->client_secret, 
                                                                'grant_type' => 'client_credentials' 
                                                            )
                            );
        $http_response = json_decode($http_json_response,true);
        switch ($http_code) {
            case 200:
                $this->token = $http_response;
                return true;
                break;
            default:
                throw new AcendaException($http_code.": ".$http_response['error']." - ".$http_response['error_description']);
                break;
        };
    }
    
    public function performRequest($route, $type, $data,$bypass_ssl=false) {
        $data_json = is_array($data) ? json_encode($data) : $data;

        if ($type == 'GET') {
            $url = $this->store_url.(!empty($this->token['access_token']) ? "/api".$route."?access_token=".$this->token['access_token'] : $route )."&query=".$data_json;
            curl_setopt($this->ch, CURLOPT_URL, $url);
        }else if($type == 'POST') {
            $url = $this->store_url.(!empty($this->token['access_token']) ? "/api".$route."?access_token=".$this->token['access_token'] : $route ); 
            curl_setopt($this->ch, CURLOPT_URL, $url);
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data_json);
        }
        
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_json))
        );

        if($bypass_ssl){
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $http_response = curl_exec($this->ch);
        $http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        if (curl_errno($this->ch)) { 
            $http_code = "400";
            $curl_error['error'] = curl_errno($this->ch);
            $curl_error['error_description'] = curl_error($this->ch); 
            $http_response = json_encode($curl_error);
        } 
        return array($http_code, $http_response);
    }

    public function initCurl() {
        $this->ch = curl_init();
    }

    public function closeCurl() {
        curl_close($this->ch);
    }
}

?>