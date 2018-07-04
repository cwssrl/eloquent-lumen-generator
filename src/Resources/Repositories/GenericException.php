<?php

namespace App\Exceptions;

class GenericException extends \Exception
{
    protected $statusCode;

    public function __construct($message, $statusCode = 400)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}