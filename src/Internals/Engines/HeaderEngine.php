<?php

namespace FastRaven\Internals\Engines;

use FastRaven\Services\HeaderService;

/**
 * @internal
 */
final class HeaderEngine {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private static bool $ready = false;

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    /**
     * Initializes the HeaderEngine if it is not already busy.
     * 
     * This function will create a new HeaderEngine if it is not already busy.
     * It will then call HeaderService::__getToWork() and pass the new HeaderEngine object.
     * The new HeaderEngine object will be returned.
     * 
     * @return ?HeaderEngine The HeaderEngine object if it was successfully created, null otherwise.
     */
    public static function zap(): ?HeaderEngine {
        if(!self::$ready) {
            self::$ready = true;
            $inst = new HeaderEngine();
            HeaderService::__getToWork($inst);

            return $inst;
        }

        return null;
    }

    private function __construct() {

    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS



    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS

    /**
     * Adds a header to the response.
     * 
     * @param string $key The key of the header.
     * @param string $value The value of the header.
     */
    public function addHeader(string $key, string $value): void {
        header("$key: $value");
    }

    /**
     * Removes a header from the response.
     * 
     * @param string $key The key of the header to remove.
     */
    public function removeHeader(string $key): void {
        header_remove($key);
    }

    
    /**
     * Writes security headers to the response.
     * 
     * @param string $https The value of the HTTPS header.
     * @param string $nonce The nonce to use for the Content-Security-Policy header.
     */
    public function writeSecurityHeaders(string $https, string $nonce): void {
        HeaderService::removeHeader("X-Powered-By");
        HeaderService::removeHeader("Server");

        HeaderService::addHeader("X-Content-Type-Options", "nosniff");
        HeaderService::addHeader("Referrer-Policy", "strict-origin-when-cross-origin");
        HeaderService::addHeader("Cross-Origin-Resource-Policy", "same-origin");
        HeaderService::addHeader("X-Frame-Options", "DENY");
        HeaderService::addHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
        HeaderService::addHeader("Access-Control-Allow-Headers", "Content-Type");

        HeaderService::addHeader("Content-Security-Policy",
            "default-src 'self'; " .
            "script-src 'self' 'nonce-$nonce'; " .
            "script-src-elem 'self' 'nonce-$nonce'; " .
            "script-src-attr 'self' 'unsafe-inline'; " .
            "style-src 'self' 'unsafe-inline' https:; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' data: https:; " .
            "connect-src 'self' https:; " .
            "frame-ancestors 'none'; " .
            "base-uri 'none'; " .
            "form-action 'self'; " .
            "object-src 'none'"
        );

        if (!empty($https) && $https !== 'off') {
            HeaderService::addHeader("Strict-Transport-Security", "max-age=31536000; includeSubDomains; preload");
        }
    }

    /**
     * Writes utility headers to the response.
     *
     * @param bool $noStore Whether the response must never be stored (API responses and anything served to an authorized session).
     */
    public function writeUtilityHeaders(bool $noStore): void {
        if($noStore) HeaderService::addHeader("Cache-Control", "private, no-store, must-revalidate");
        else HeaderService::addHeader("Cache-Control", "private, max-age=600, stale-while-revalidate=30");
    }

    /**
     * Writes rate limit headers to the response.
     *
     * @param int $configuredRateLimit The configured rate limit.
     * @param int $rateLimitRemaining The remaining requests before being blocked.
     * @param int $rateLimitTimeRemaining The time remaining until the rate limit is reset.
     */
    public function writeRateLimitHeaders(int $configuredRateLimit, int $rateLimitRemaining, int $rateLimitTimeRemaining): void {
        if($configuredRateLimit >= 0) {
            HeaderService::addHeader("RateLimit-Limit", $configuredRateLimit);
            HeaderService::addHeader("RateLimit-Remaining", max(0, $rateLimitRemaining));
            HeaderService::addHeader("RateLimit-Reset", time() + $rateLimitTimeRemaining);
        }
    }

    #/ METHODS
    #----------------------------------------------------------------------
}