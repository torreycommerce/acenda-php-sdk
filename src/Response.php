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