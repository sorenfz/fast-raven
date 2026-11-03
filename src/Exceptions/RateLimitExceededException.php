<?php

namespace FastRaven\Exceptions;

class RateLimitExceededException extends SmartException
{
    private int $timeLeft;
    public function getTimeLeft(): int { return $this->timeLeft; }
    
    /**
     * Initializes the exception.
     *
     * This exception is thrown when an authorized user exceeds its rate limit.
     */
    public function __construct(string $who, int $requestLeft, int $timeLeft = 60) {
        $this->timeLeft = $timeLeft;
        parent::__construct("$who reached its rate limit. ($requestLeft requests left)", "Please wait before making another request.", 429);
    }
}