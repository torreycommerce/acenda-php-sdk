<?php
namespace Acenda;

use Httpful;

class Authentication{
    private static $instances;

    private $access_token;
    private $expires;
    private $scope;
    private $token_type;

    private $client_id;
    private $client_secret;
    private $httpful;

    /**
     * @return bool
     * @throws AcendaException
     */
    private function generation(){
        //Give us 10 seconds of padding, which should be plenty. This method is called every request, so the token
        //should be used within microseconds of this method.
        if (empty($this->expires) || $this->expires <= time() + 10){
            $this->generateToken();
        }

        return (true);
    }

    /**
     * @return string
     * @throws AcendaException
     */
    public function getToken(){
        $this->generation();
        return $this->access_token;
    }

    /**
     * @return string
     * @throws AcendaException
     */
    public function getType(){
        $this->generation();
        return $this->token_type;
    }

    /**
     * @return array
     * @throws AcendaException
     */
    public function getScope(){
        $this->generation();
        return explode('|', $this->scope);
    }

    /**
     * @return integer
     * @throws AcendaException
     */
    public function getExpiration(){
        $this->generation();
        return $this->expires;
    }

    /**
     * @param \StdClass $data StdClass of the request received by token generation
     */
    private function handleSuccess(\StdClass $data){
        $this->access_token = $data->access_token;
        $this->expires = (time() + $data->expires_in);
        $this->scope = $data->scope;
        $this->token_type = $data->token_type;
    }

    /**
     * @return string
     */
    private function getUrl(){
        switch(isset($_SERVER['ACENDA_MODE']) ? $_SERVER['ACENDA_MODE'] : null){
            case "acendavm":
                return "http://acenda.acendev";
                break;
            case "development":
                return "https://acenda.acenda.devserver";
                break;
            default:
                return "https://acenda.com";
                break;
        }
    }

    /**
    * @throws AcendaException
    * @return boolean
    */
    public function refresh(){
        $this->generation();
        $this->generateToken();

        return true;
    }

    /**
    * @throws AcendaException
    */
    private function generateToken(){
        $response = $this->httpful->post($this->getUrl().'/oauth/token', json_encode([
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials'
        ]))->sendsJson()->send();

        switch ($response->code){
            case 200:
                $this->handleSuccess($response->body);
                break;
            default:
                throw new AcendaException($response->code, $response->body);
        }
        return true;
    }

    public function __construct($client_id, $client_secret){
        if (empty($client_id) || empty($client_secret)){
            throw new \Exception("Please provide client_id and client_secret");
        }else{
            $this->client_id = $client_id;
            $this->client_secret = $client_secret;
            $this->httpful = Httpful\Request::init();
            $this->httpful->additional_curl_opts = [
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => function () {}
            ];
            $this->generateToken();
        }
    }
}
