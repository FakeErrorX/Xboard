<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected $code; // Error code
    protected $message; // Error message
    protected $errors; // All error information

    public function __construct($message = null, $code = 400, $errors = null)
    {
        $this->message = $message;
        $this->code = $code;
        $this->errors = $errors;
    }
    public function errors(){
        return $this->errors;
    }

}
