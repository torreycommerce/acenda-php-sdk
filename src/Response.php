<?php
namespace Acenda;

use Httpful;

/**
 * Class Response
 * primary type of response for Acenda SDK queries.
 * @package Acenda
 */
class Response{
    public $code;
    public $body;
    public $headers;

    /**
     * @param Httpful\Response $response
     */
    public function __construct(Httpful\Response $response){
        $this->code = $response->code;
        $this->headers = $response->headers;
        $this->body = $response->body;
    }
}
