<?php

namespace FastRaven\Exceptions;

class PayloadTooLargeException extends SmartException
{
    /**
     * Initializes the exception.
     *
     * This exception is thrown when the request body exceeds the configured input length limit.
     *
     * @param int $limit The configured limit in bytes.
     */
    public function __construct(int $limit) {
        parent::__construct("Request body exceeds the configured input limit ($limit bytes).", "Request body too large.", 413);
    }
}