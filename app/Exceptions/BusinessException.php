<?php

namespace App\Exceptions;

use Exception;

class BusinessException extends Exception
{
    /**
     * Business exception constructor
     * @param array $codeResponse Status code
     * @param string $info Custom return information, when not empty it will replace the message text information in codeResponse
     */
    public function __construct(array $codeResponse, $info = '')
    {
        [$code, $message] = $codeResponse;
        parent::__construct($info ?: $message, $code);
    }
}