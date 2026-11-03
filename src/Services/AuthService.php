<?php

namespace FastRaven\Services;

use FastRaven\Internals\Engines\AuthEngine;
use FastRaven\Components\Http\Request;

final class AuthService {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private static bool $ready = false;
    private static AuthEngine $engine;

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    public static function __getToWork(AuthEngine &$engine): void {
        if(!self::$ready) {
            self::$ready = true;
            self::$engine = $engine;
        }
    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS

    private static function ensureSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
    }

    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS

    /**
     * Creates an authorized session for the given user ID.
     *
     * This function will create an authorized session for the given user ID.
     * It will then call AuthEngine::createAuthorizedSession() and pass the user ID, custom data, and a randomly generated CSRF token.
     * It will then log a message indicating that an authorized session was created for the user.
     *
     * @param int $id The ID of the user to authorize or any other unique identifier.
     * @param array $customData Custom data to store in the session.
     */
    public static function createAuthorization(int $id, array $customData = []): void {
        if(self::$ready) {
            self::ensureSession();
            self::$engine->createAuthorizedSession($id, $customData, bin2hex(random_bytes(32)));
            LogService::debug("Authorized session created for user {$id}.");
        }
    }

    /**
     * Destroys the authorized session.
     *
     * This function will destroy the authorized session if it exists.
     * It will then log a message indicating that the authorized session was destroyed.
     */
    public static function destroyAuthorization(): void {
        if(self::$ready) {
            self::ensureSession();
            self::$engine->destroyAuthorizedSession();
            LogService::debug("Authorized session destroyed.");
        }
    }

    /**
     * Verifies if the user is authorized and has a valid csrf_token.
     *
     * This function will check if the user is authorized and has a valid csrf_token.
     * If the user is authorized and has a valid csrf_token, it will log a message indicating that the authorization was verified.
     * If the user is authorized but does not have a valid csrf_token, it will log a warning message indicating that the csrf_token was invalid.
     * If the user is not authorized, it will return false.
     *
     * @param ?Request $request The request object.
     *
     * @return bool True if the user is authorized and has a valid csrf_token, false otherwise.
     */
    public static function isAuthorized(?Request $request = null): bool {
        if(self::$ready) {
            self::ensureSession();
            if(self::$engine->validateSession()) {
                if($request && in_array($request->getMethod(), ["POST", "PUT", "DELETE", "PATCH"], true)) {
                    if(!self::$engine->validateCSRF($_SESSION["sgas_csrf"], $_SERVER["HTTP_X_CSRF_TOKEN"] ?? null)) {
                        LogService::warning("Restricted action for authenticated user was called without a valid csrf_token.");
                        return false;
                    }
                }
                LogService::debug("Verified authorization for user " . self::getAuthorizedUserId() . ".");
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves the ID of the authorized user if an authorized session exists.
     *
     * This function will return the ID of the authorized user if an authorized session exists.
     * If no authorized session exists, it will return null.
     *
     * @return ?int The ID of the authorized user if an authorized session exists, null otherwise.
     */
    public static function getAuthorizedUserId(): ?int {
        if(self::$ready) {
            self::ensureSession();
            if(self::$engine->validateSession()) {
                return $_SESSION["sgas_uid"];
            }
        }

        return null;
    }

    /**
     * Retrieves the custom data of the authorized user if an authorized session exists.
     *
     * This function will return the custom data of the authorized user if an authorized session exists.
     * If no authorized session exists, it will return null.
     *
     * @return ?array The custom data of the authorized user if an authorized session exists, null otherwise.
     */
    public static function getAuthorizedUserCustomData(): ?array {
        if(self::$ready) {
            self::ensureSession();
            if(self::$engine->validateSession()) {
                return $_SESSION["sgas_custom"];
            }
        }

        return null;
    }

    /**
     * Replaces the custom data of the authorized user if an authorized session exists.
     *
     * @param array $customData The new custom data to store in the session.
     *
     * @return bool True if the custom data was stored, false if no authorized session exists.
     */
    public static function setAuthorizedUserCustomData(array $customData): bool {
        if(self::$ready) {
            self::ensureSession();
            if(self::$engine->validateSession()) {
                self::$engine->setCustomData($customData);
                return true;
            }
        }

        return false;
    }

    /**
     * Automatically logs in a user via their username and password, and creates an authorized session if the login is successful.
     *
     * @param ?string $user The username of the user to login.
     * @param ?string $pass The password of the user to login.
     * @param string $dbTable The name of the database table to query for login data.
     * @param string $dbIdCol The name of the column in the database table that contains the user's ID.
     * @param string $dbNameCol The name of the column in the database table that contains the user's name.
     * @param string $dbPassCol The name of the column in the database table that contains the user's password.
     *
     * @return bool True if the login is successful, false otherwise.
     */
    public static function autologin(?string $user, ?string $pass, string $dbTable = "users", string $dbIdCol = "id", string $dbNameCol = "name", string $dbPassCol = "password"): bool {
        if(self::$ready) {
            self::ensureSession();
            if(!$user || !$pass) return false;

            $id = self::$engine->checkCredentials($user, $pass, $dbTable, $dbIdCol, $dbNameCol, $dbPassCol);
            
            if($id) {
                LogService::debug("User {$user} logged in via autologin.");
                AuthService::createAuthorization($id);
                return true;
            } else {
                LogService::warning("Failed autologin attempt for user {$user}.");
                AuthService::destroyAuthorization();
                return false;
            }
        }

        return false;
    }

    /**
     * Regenerates the CSRF token for the current authorized session.
     *
     * This function will regenerate the CSRF token for the current authorized session if an authorized session exists.
     * If no authorized session exists, it will return null.
     * Note: This will invalidate CSRF tokens in other open tabs.
     *
     * @return ?string The new CSRF token if the authorized session exists, null otherwise.
     */
    #[\NoDiscard]
    public static function regenerateCSRF(): ?string {
        if(self::$ready) {
            self::ensureSession();
            if(self::$engine->validateSession()) {
                LogService::debug("CSRF token has been regenerated for user " . self::getAuthorizedUserId());
                return self::$engine->regenerateCSRF();
            }
        }

        return null;
    }

    #/ METHODS
    #----------------------------------------------------------------------
}