<?php

namespace FastRaven\Services;

use FastRaven\Internals\Engines\CacheEngine;
use FastRaven\Types\CacheType;

final class CacheService {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private static bool $ready = false;
    private static CacheEngine $engine;

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    public static function __getToWork(CacheEngine &$engine): void {
        if(!self::$ready) {
            self::$ready = true;
            self::$engine = $engine;
        }
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
     * Returns the current cache backend type.
     * 
     * @return CacheType The cache type (APCU, SHARED, or FILE).
     */
    public static function getUsedType(): CacheType {
        if(self::$ready) {
            return self::$engine->getType();
        }

        return CacheType::FILE;
    }

    /**
     * Checks if a cache item exists and is not expired.
     * 
     * @param string $key The cache key.
     * @return bool True if the item exists.
     */
    public static function exists(string $key): bool {
        if(self::$ready) {
            $key = self::$engine->prefix($key);
            return match(self::$engine->getType()) {
                CacheType::APCU => self::$engine->apcuExists($key),
                CacheType::SHARED => self::$engine->shmopExists($key),
                CacheType::FILE => self::$engine->fileExists($key),
            };
        }

        return false;
    }

    /**
     * Retrieves a cache value.
     * 
     * @param string $key The cache key.
     * 
     * @return mixed The cached value, or null if not found/expired.
     */
    public static function read(string $key): mixed {
        if(self::$ready) {
            $key = self::$engine->prefix($key);
            return match(self::$engine->getType()) {
                CacheType::APCU => self::$engine->apcuRead($key)["value"] ?? null,
                CacheType::SHARED => self::$engine->shmopRead($key)["value"] ?? null,
                CacheType::FILE => self::$engine->fileRead($key)["value"] ?? null,
            };
        }

        return null;
    }

    /**
     * Retrieves a cache item with metadata (value and expiry).
     * 
     * @param string $key The cache key.
     * @return ?array ["value" => mixed, "expires" => int] or null if not found.
     */
    public static function readWithMeta(string $key): ?array {
        if(self::$ready) {
            $key = self::$engine->prefix($key);
            return match(self::$engine->getType()) {
                CacheType::APCU => self::$engine->apcuRead($key),
                CacheType::SHARED => self::$engine->shmopRead($key),
                CacheType::FILE => self::$engine->fileRead($key),
            };
        }

        return null;
    }

    /**
     * Stores a value in cache.
     * 
     * @param string $key The cache key.
     * @param mixed $value The value to cache.
     * @param int $expires Time-to-live in seconds.
     * @return bool True on success.
     */
    public static function write(string $key, mixed $value, int $expires): bool {
        if(self::$ready) {
            $key = self::$engine->prefix($key);
            return match(self::$engine->getType()) {
                CacheType::APCU => self::$engine->apcuWrite($key, $value, $expires),
                CacheType::SHARED => self::$engine->shmopWrite($key, $value, $expires),
                CacheType::FILE => self::$engine->fileWrite($key, $value, $expires),
            };
        }

        return false;
    }

    /**
     * Increments an integer cache value atomically.
     * 
     * @param string $key The cache key (must contain an integer value).
     * @param int $step The amount to increment by (default: 1).
     * @return int The incremented value or 0 on failure.
     */
    public static function increment(string $key, int $step = 1): int {
        if(self::$ready) {
            $key = self::$engine->prefix($key);
            return match(self::$engine->getType()) {
                CacheType::APCU => self::$engine->apcuIncrement($key, $step),
                CacheType::SHARED => self::$engine->shmopIncrement($key, $step),
                CacheType::FILE => self::$engine->fileIncrement($key, $step),
            };
        }

        return 0;
    }

    /**
     * Decrements an integer cache value atomically.
     * 
     * @param string $key The cache key (must contain an integer value).
     * @param int $step The amount to decrement by (default: 1).
     * @return int The decremented value or 0 on failure.
     */
    public static function decrement(string $key, int $step = 1): int {
        if(self::$ready) {
            $key = self::$engine->prefix($key);
            return match(self::$engine->getType()) {
                CacheType::APCU => self::$engine->apcuIncrement($key, -$step),
                CacheType::SHARED => self::$engine->shmopIncrement($key, -$step),
                CacheType::FILE => self::$engine->fileIncrement($key, -$step),
            };
        }

        return 0;
    }

    /**
     * Removes a cache item.
     * 
     * @param string $key The cache key.
     * @return bool True if removed successfully.
     */
    public static function remove(string $key): bool {
        if(self::$ready) {
            $key = self::$engine->prefix($key);
            return match(self::$engine->getType()) {
                CacheType::APCU => self::$engine->apcuRemove($key),
                CacheType::SHARED => self::$engine->shmopRemove($key),
                CacheType::FILE => self::$engine->fileRemove($key),
            };
        }

        return false;
    }

    /**
     * Clears all cache entries.
     * Note: For shmop backend, this only returns false (cannot enumerate keys).
     * 
     * @return bool True on success.
     */
    public static function empty(): bool {
        if(self::$ready) {
            return match(self::$engine->getType()) {
                CacheType::APCU => self::$engine->apcuEmpty(),
                CacheType::SHARED => false,
                CacheType::FILE => self::$engine->fileEmpty(),
            };
        }

        return false;
    }

    /**
     * Runs garbage collection on file-based cache.
     * Only has effect when using FILE backend.
     * 
     * @param int $power Number of cache files to check for expiry.
     */
    public static function runGarbageCollector(int $power): void {
        if(self::$ready && self::$engine->getType() === CacheType::FILE) {
            self::$engine->runGarbageCollector($power);
        }
    }

    #/ METHODS
    #----------------------------------------------------------------------
}
