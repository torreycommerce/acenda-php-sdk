<?php

namespace Acenda;
use Exception;


class AcendaException extends Exception{
    public $code;
    public $body;

    public function __construct($code, $body){
        $this->code = $code;
        $this->body = $body;

        parent::__construct($code);
    }
}
