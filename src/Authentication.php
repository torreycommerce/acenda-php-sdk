<?php
namespace Acenda;

use Httpful;

class Authentication{
    private static $instance;

    private $access_token;
    private $expires;
    private $scope;
    private $token_type;

    private static $client_id;
    private static $client_secret;
    private static $httpful;

    /**
     * @return bool
     */
    private static function generation(){
        //Give us 10 seconds of padding, which should be plenty. This method is called every request, so the token
        //should be used within microseconds of this method.
        if (empty(static::$instance) || static::$instance->expires <= time() + 10){
            static::$instance = new Authentication();
        }

        return (true);
    }

    /**
     * @param $client_id Client ID mandatory for
     * @param $client_secret
     * @param $httpful
     * @return bool
     */
    public static function init($client_id, $client_secret, Httpful\Request $httpful){
        static::$client_id = $client_id;
        static::$client_secret = $client_secret;
        static::$httpful = $httpful;

        return (true);
    }

    /**
     * @return token
     */
    public static function getInstance(){
        static::generation();
        return static::$instance;
    }

    /**
     * @return token
     */
    public static function getToken(){
        static::generation();
        return static::$instance->access_token;
    }

    /**
     * @return token type
     */
    public static function getType(){
        static::generation();
        return static::$instance->token_type;
    }

    /**
     * @return scopes
     */
    public static function getScope(){
        static::generation();
        return split('|', static::$instance->scope);
    }

    /**
     * @return expiration time in timestamp
     */
    public static function getExpiration(){
        static::generation();
        return static::$instance->expires;
    }

    /**
     * @param $data StdClass of the request received by token generation
     */
    private function handleSuccess(\StdClass $data){
        $this->access_token = $data->access_token;
        $this->expires = (date("U") + $data->expires_in);
        $this->scope = $data->scope;
        $this->token_type = $data->token_type;
    }

    /**
     * @return URL
     */
    private function getUrl(){
        $acenda_mode = isset($_SERVER['ACENDA_MODE']) ? $_SERVER['ACENDA_MODE'] : null;
        if(empty($acenda_mode)){
            $acenda_mode = getenv('ACENDA_MODE');
        }
        switch($acenda_mode){
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

        return (null);
    }

    /**
    * @throws AcendaException
    * @return boolean
    */
    public static function refresh(){
        static::generation();
        static::generateToken();

        return true;
    }

    /**
    * @throws AcendaException
    */
    private function generateToken(){
        $response = static::$httpful->post($this->getUrl().'/oauth/token', json_encode([
            'client_id' => static::$client_id,
            'client_secret' => static::$client_secret,
            'grant_type' => 'client_credentials'
        ]))->sendsJson()->send();

        switch ($response->code){
            case 200:
                $this->handleSuccess($response->body);
                return true;
                break;
            default:
                throw new AcendaException($response->code, $response->body);
        }
    }

    protected function __construct(){
        if (empty(static::$client_id) || empty(static::$client_secret) || empty(static::$httpful)){
            throw new \Exception("The Authentication class must be initialized before instanciation.");
        }else{
            $this->generateToken();
        }
    }
}
