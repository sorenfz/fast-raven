<?php

namespace FastRaven\Internals\Engines;

use FastRaven\Services\CacheService;
use FastRaven\Bee;

use FastRaven\Types\CacheType;
use FastRaven\Types\ProjectFolderType;
use FastRaven\Services\LogService;

/**
 * @internal
 */
final class CacheEngine {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private static bool $ready = false;
    private CacheType $type = CacheType::FILE;
    private string $prefix = "";
        public function prefix(string $key): string { return $this->prefix . $key; }

    private const int SHMOP_SEGMENT_SIZE = 1024;
    private const int SHMOP_MODE = 0644;

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    /**
     * Initializes the CacheEngine if it is not already busy.
     * Auto-detects the best available backend: APCu > shmop > File.
     * 
     * @param string $prefix Prefix prepended to every key, isolating the site from others sharing the backend.
     * 
     * @return ?CacheEngine The CacheEngine object if it was successfully created, null otherwise.
     */
    public static function zap(string $prefix = ""): ?CacheEngine {
        if(!self::$ready) {
            self::$ready = true;
            $inst = new CacheEngine();
            $inst->prefix = $prefix;
            
            if (function_exists("apcu_enabled") && apcu_enabled()) $inst->type = CacheType::APCU;
            elseif (function_exists("shmop_open")) $inst->type = CacheType::SHARED;
            else $inst->type = CacheType::FILE;

            CacheService::__getToWork($inst);
            return $inst;
        }

        return null;
    }

    private function __construct() {

    }

    /**
     * Returns the current cache backend type.
     * 
     * @return CacheType The cache backend type (APCU, SHARED, or FILE).
     */
    public function getType(): CacheType {
        return $this->type;
    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ LOCK

    /**
     * Acquires a file-based lock for a key, used for atomic increments on every backend and all shmop operations.
     * 
     * @return mixed File handle or false on failure.
     */
    private function lock(string $key): mixed {
        $lockFile = Bee::buildProjectPath(ProjectFolderType::STORAGE_CACHE, md5($key) . ".lock");
        $fp = @fopen($lockFile, "c");
        
        if($fp) {
            if(flock($fp, LOCK_EX)) return $fp;
            else fclose($fp);
        }
        
        return false;
    }

    /**
     * Releases a lock and removes the lock file.
     */
    private function unlock(mixed $lock): void {
        if ($lock) {
            $path = stream_get_meta_data($lock)['uri'];
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($path);
        }
    }

    #/ LOCK
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ APCu BACKEND

    /**
     * Checks if a key exists in the APCu cache.
     * 
     * @param string $key The key to check.
     * 
     * @return bool True if the key exists, false otherwise.
     */
    public function apcuExists(string $key): bool {
        return apcu_exists($key);
    }

    /**
     * Reads a value from the APCu cache.
     * 
     * @param string $key The key to read.
     * 
     * @return ?array The cached item, or null if not found or expired.
     */
    public function apcuRead(string $key): ?array {
        $value = apcu_fetch($key);
        
        if (is_array($value) && isset($value["expires"])) {
            if ($value["expires"] > time()) return $value;
            else apcu_delete($key);
        }
        
        return null;
    }

    /**
     * Writes a value to the APCu cache.
     * 
     * @param string $key The key to write.
     * @param mixed $value The value to cache.
     * @param int $expires The expiration time in seconds.
     * 
     * @return bool True if successful, false otherwise.
     */
    public function apcuWrite(string $key, mixed $value, int $expires): bool {
        $item = [
            "expires" => time() + $expires,
            "value" => $value
        ];
        return apcu_store($key, $item, $expires);
    }

    /**
     * Increments a value in the APCu cache.
     * 
     * @param string $key The key to increment.
     * @param int $step The amount to increment by.
     * 
     * @return int The new value, or 0 if failed.
     */
    public function apcuIncrement(string $key, int $step = 1): int {
        $lock = $this->lock($key);
        if (!$lock) return 0;

        try {
            $item = $this->apcuRead($key);
            
            if ($item && is_int($item["value"])) {
                $item["value"] += $step;
                $expires = max(0, $item["expires"] - time());
                
                if ($this->apcuWrite($key, $item["value"], $expires)) return $item["value"];
            }
            
            return 0;
        } finally {
            $this->unlock($lock);
        }
    }

    /**
     * Removes a value from the APCu cache.
     * 
     * @param string $key The key to remove.
     * 
     * @return bool True if successful, false otherwise.
     */
    public function apcuRemove(string $key): bool {
        return apcu_delete($key);
    }

    /**
     * Clears every APCu entry belonging to this site (prefix).
     * 
     * @return bool True if successful, false otherwise.
     */
    public function apcuEmpty(): bool {
        if($this->prefix === "") return apcu_clear_cache();
        return apcu_delete(new \APCUIterator("/^" . preg_quote($this->prefix, "/") . "/")) !== false;
    }

    #/ APCu BACKEND
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ SHMOP BACKEND

    /**
     * Converts a string key to an integer key for shmop.
     * 
     * @param string $key The string key.
     * 
     * @return int The integer key (positive 31-bit).
     */
    private function shmopKey(string $key): int {
        return hexdec(hash("xxh32", $key)) & 0x7FFFFFFF;
    }

    /**
     * Checks if a key exists in shmop.
     * 
     * @param string $key The key to check.
     * 
     * @return bool True if the key exists, false otherwise.
     */
    public function shmopExists(string $key): bool {
        $shm = @shmop_open($this->shmopKey($key), "a", 0, 0);
        
        if ($shm) {
            shmop_close($shm);
            return true;
        }
        
        return false;
    }

    /**
     * Reads a value from shmop.
     * 
     * @param string $key The key to read.
     * 
     * @return ?array The cached item, or null if not found or expired.
     */
    public function shmopRead(string $key): ?array {
        $lock = $this->lock($key);
        if (!$lock) return null;
        
        try {
            $shmKey = $this->shmopKey($key);
            $shm = @shmop_open($shmKey, "a", 0, 0);
            
            if ($shm) {
                $data = shmop_read($shm, 0, shmop_size($shm));
                shmop_close($shm);

                if ($data !== false) {
                    $item = @unserialize(rtrim($data, "\0"), ["allowed_classes" => false]);

                    if (is_array($item) && isset($item["expires"])) {
                        if ($item["expires"] > time()) return $item;
                        else $this->shmopRemoveInternal($shmKey);
                    }
                }
            }
            
            return null;
        } finally {
            $this->unlock($lock);
        }
    }

    /**
     * Writes a value to shmop.
     * 
     * @param string $key The key to write.
     * @param mixed $value The value to cache.
     * @param int $expires The expiration time in seconds.
     * 
     * @return bool True if successful, false otherwise.
     */
    public function shmopWrite(string $key, mixed $value, int $expires): bool {
        $lock = $this->lock($key);
        if (!$lock) return false;
        
        try {
            $item = [
                "expires" => time() + $expires,
                "value" => $value
            ];
            
            $data = serialize($item);
            $requiredSize = (int) ceil(strlen($data) / self::SHMOP_SEGMENT_SIZE) * self::SHMOP_SEGMENT_SIZE;
            
            if($requiredSize <= Bee::env("SHMOP_MAX_SIZE", 1024) * 1024) {
                $shmKey = $this->shmopKey($key);
                $this->shmopRemoveInternal($shmKey);
                $shm = @shmop_open($shmKey, "c", self::SHMOP_MODE, $requiredSize);
                
                if ($shm) {
                    $res = shmop_write($shm, str_pad($data, $requiredSize, "\0"), 0);
                    shmop_close($shm);
                    return $res !== false;
                }
            } else {
                LogService::warning("Could not write cache key '{$key}'. Data exceeds maximum configured shmop size.");
            }
            
            return false;
        } finally {
            $this->unlock($lock);
        }
    }

    /**
     * Increments a value in shmop.
     * 
     * @param string $key The key to increment.
     * @param int $step The amount to increment by.
     * 
     * @return int The new value, or 0 if failed.
     */
    public function shmopIncrement(string $key, int $step = 1): int {
        $lock = $this->lock($key);
        if (!$lock) return 0;
        
        try {
            $shmKey = $this->shmopKey($key);
            $shm = @shmop_open($shmKey, "a", 0, 0);
            
            if ($shm) {
                $data = shmop_read($shm, 0, shmop_size($shm));
                shmop_close($shm);

                if ($data !== false) {
                    $item = @unserialize(rtrim($data, "\0"), ["allowed_classes" => false]);

                    if (is_array($item) && isset($item["value"]) && is_int($item["value"])) {
                        if ($item["expires"] > time()) {
                            $item["value"] += $step;
                            
                            $shm = @shmop_open($shmKey, "w", 0, 0);

                            if ($shm) {
                                shmop_write($shm, str_pad(serialize($item), self::SHMOP_SEGMENT_SIZE, "\0"), 0);
                                shmop_close($shm);
                                
                                return $item["value"];
                            }
                        } else {
                            $this->shmopRemoveInternal($shmKey);
                        }
                    }
                }
            }
            
            return 0;
        } finally {
            $this->unlock($lock);
        }
    }

    /**
     * Removes a value from shmop.
     * 
     * @param string $key The key to remove.
     * 
     * @return bool True if successful, false otherwise.
     */
    public function shmopRemove(string $key): bool {
        $lock = $this->lock($key);
        if (!$lock) return false;
        
        try {
            return $this->shmopRemoveInternal($this->shmopKey($key));
        } finally {
            $this->unlock($lock);
        }
    }

    /**
     * Internal method to remove a shmop segment by integer key.
     * 
     * @param int $shmKey The integer key.
     * 
     * @return bool True if successful, false otherwise.
     */
    private function shmopRemoveInternal(int $shmKey): bool {
        $shm = @shmop_open($shmKey, "a", 0, 0);
        
        if ($shm) {
            $res = shmop_delete($shm);
            shmop_close($shm);

            return $res;
        }
        
        return false;
    }

    #/ SHMOP BACKEND
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ FILE BACKEND

    /**
     * Gets the file path for a given key.
     * 
     * @param string $key The key of the file.
     * 
     * @return string The file path.
     */
    private function getFilePath(string $key): string {
        return Bee::buildProjectPath(ProjectFolderType::STORAGE_CACHE, $key.".cache");
    }

    /**
     * Checks if a file exists in the cache directory.
     * 
     * @param string $key The key of the file to check.
     * 
     * @return bool True if the file exists, false otherwise.
     */
    public function fileExists(string $key): bool {
        return file_exists($this->getFilePath($key));
    }

    /**
     * Reads a file from the cache directory.
     * 
     * @param string $key The key of the file to read.
     * 
     * @return ?array The contents of the file, or null if the file does not exist or is expired.
     */
    public function fileRead(string $key): ?array {
        $path = $this->getFilePath($key);
        
        $content = @file_get_contents($path);
        if ($content !== false) {
            $item = @json_decode($content, true);

            if (is_array($item) && isset($item["expires"])) {
                if ($item["expires"] > time()) return $item;
                else @unlink($path);
            }
        }
        
        return null;
    }

    /**
     * Writes a file to the cache directory.
     * 
     * @param string $key The key of the file to write.
     * @param mixed $value The value to write to the file.
     * @param int $expires The expiration time for the file.
     * 
     * @return bool True if the file was successfully written, false otherwise.
     */
    public function fileWrite(string $key, mixed $value, int $expires): bool {
        $path = $this->getFilePath($key);
        $item = [
            "expires" => time() + $expires,
            "value" => $value
        ];
        
        return file_put_contents($path, json_encode($item), LOCK_EX) !== false;
    }

    /**
     * Increments the value of a file in the cache directory.
     * 
     * @param string $key The key of the file to increment.
     * @param int $step The amount to increment the value by.
     * 
     * @return int The new value of the file, or 0 if the file does not exist or is not an integer.
     */
    public function fileIncrement(string $key, int $step = 1): int {
        $lock = $this->lock($key);
        if (!$lock) return 0;

        try {
            $item = $this->fileRead($key);
            
            if ($item && is_int($item["value"])) {
                $item["value"] += $step;
                
                $res = file_put_contents($this->getFilePath($key), json_encode($item), LOCK_EX);
                if ($res !== false) return $item["value"];
            }
            
            return 0;
        } finally {
            $this->unlock($lock);
        }
    }

    /**
     * Removes a file from the cache directory.
     * 
     * @param string $key The key of the file to remove.
     * 
     * @return bool True if the file was successfully removed, false otherwise.
     */
    public function fileRemove(string $key): bool {
        $path = $this->getFilePath($key);

        if (file_exists($path)) return @unlink($path);
        else return false;
    }

    /**
     * Removes all files from the cache directory.
     * 
     * @return bool True if all files were successfully removed, false otherwise.
     */
    public function fileEmpty(): bool {
        $files = glob(Bee::buildProjectPath(ProjectFolderType::STORAGE_CACHE) . "*.cache", GLOB_NOSORT);
        if ($files !== false && !empty($files)) {
            foreach ($files as $file) {
                if (!@unlink($file)) return false;
            }
        }
        
        return true;
    }

    /**
     * Runs garbage collection on file-based cache.
     * Only applicable for FILE backend.
     * 
     * @param int $power Number of files to check.
     */
    public function runGarbageCollector(int $power): void {
        if ($this->type !== CacheType::FILE || $power <= 0) return;
        
        $files = glob(Bee::buildProjectPath(ProjectFolderType::STORAGE_CACHE) . "*.cache", GLOB_NOSORT);
        if ($files !== false && !empty($files)) {
            $total = count($files);

            for ($i = 0; $i < min($power, $total); $i++) {
                $ri = random_int($i, $total - 1);
                if ($i !== $ri) [$files[$i], $files[$ri]] = [$files[$ri], $files[$i]];
                
                $key = pathinfo($files[$i], PATHINFO_FILENAME);
                $this->fileRead($key);
            }
        }
    }

    #/ FILE BACKEND
    #----------------------------------------------------------------------
}