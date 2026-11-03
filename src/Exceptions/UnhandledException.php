<?php

namespace FastRaven\Exceptions;

class UnhandledException extends SmartException
{
    /**
     * Initializes the exception.
     *
     * This exception wraps any non-framework Throwable escaping the request lifecycle so it is logged and answered as a 500.
     *
     * @param \Throwable $e The original throwable.
     */
    public function __construct(\Throwable $e) {
        parent::__construct(get_class($e) . ": " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")", "This resource is not available at this time.", 500);
    }
}