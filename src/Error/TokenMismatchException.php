<?php

namespace Rammewerk\Component\Request\Error;

use Exception;
use Throwable;

class TokenMismatchException extends Exception {

    public function __construct(
        string    $message = "Unable to validate request",
        int       $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct( $message, $code, $previous );
    }

}