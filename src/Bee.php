<?php

namespace FastRaven;

use FastRaven\Services\CacheService;

use FastRaven\Types\DataType;
use FastRaven\Types\ProjectFolderType;

final class Bee {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private static ?\finfo $finfoInstance = null;

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT



    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS



    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS

    /**
     * Defines an environment variable
     * 
     * @param string $key the key of the environment variable
     * @param string $value the value of the environment variable
     */
    public static function defineEnv(string $key, string $value): void {
        $_ENV[$key] = $value;
    }

    /**
     * Gets the value of the environment variable
     * 
     * @param string $key the key of the environment variable
     * @return string the value of the environment variable or empty string if not found
     */
    public static function env(string $key, string $default = ""): string {
        return $_ENV[$key] ?? $default;
    }
    /**
     * Checks if the application is running in a development environment
     * 
     * @return bool true if the application is running in a development environment, false otherwise
     */
    public static function isDev() : bool {
        return Bee::env("STATE") === "dev";
    }
    
   /**
     * Normalize a path by removing redundant slashes and trimming it
     * 
     * @param string $path the path to normalize
     * @return string the normalized path (e.g., "path/to/endpoint")
     */
    public static function normalizePath(string $path): string {
        if(!$path) return "";

        $path = str_replace("\0", "", $path);
        $path = preg_replace("#[\\\\/]+#", "/", $path);
        $segments = array_filter(
            explode("/", $path),
            fn($s) => $s !== "" && $s !== "." && $s !== ".."
        );
        return implode("/", $segments);
    }

    /**
     * Returns the base domain of the site from the SITE_ADDRESS environment variable ("localhost" if not set).
     * Subdomains are stripped: "sub.example.com" gives "example.com" and "sub.example.co.uk" gives "example.co.uk"
     * (a two-letter TLD preceded by a label of up to three letters is treated as a public suffix).
     *
     * @return string the base domain of the site
     */
    public static function getBaseDomain(): string {
        $parts = explode(".", Bee::env("SITE_ADDRESS", "localhost"));
        $keep = count($parts) >= 3 && strlen(array_last($parts)) === 2 && strlen($parts[count($parts) - 2]) <= 3 ? 3 : 2;

        return implode(".", array_slice($parts, -$keep));
    }

    /**
     * Returns the built domain of the site based on the SITE_ADDRESS environment variable and the $subdomain parameter.
     * If the $subdomain parameter is empty, returns the base domain of the site.
     * If the $subdomain parameter is not empty, returns the built domain by concatenating the $subdomain parameter with the base domain of the site.
     *
     * @param string $subdomain the subdomain to use for the built domain
     * 
     * @return string the built domain of the site
     */
    public static function getBuiltDomain(string $subdomain = ""): string {
        $baseDomain = Bee::getBaseDomain();
        if ($subdomain === "") return $baseDomain;
        else return $subdomain . "." . $baseDomain;
    }

    /**
     * Hashes a password using the Argon2ID algorithm with a memory cost of 2^16, a time cost of 4 and 2 threads.
     * 
     * @param string $password the password to hash
     * 
     * @return string the hashed password
     */
    #[\NoDiscard]
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 1 << 16, 'time_cost' => 4, 'threads' => 2]);
    }

    /**
     * Returns the MIME type of a file.
     * 
     * @param string $file the path to the file
     * @param bool $returnType whether to return the MIME type as a DataType enum value
     * 
     * @return string|DataType the MIME type of the file. If the file does not exist or cannot be read, returns "application/octet-stream".
     */
    public static function getFileMimeType(string $file, bool $returnType = false): string|DataType {
        if(!is_file($file)) return $returnType ? DataType::BINARY : "application/octet-stream";

        if(self::$finfoInstance === null) self::$finfoInstance = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = self::$finfoInstance->file($file);

        if($mimeType === false) return $returnType ? DataType::BINARY : "application/octet-stream";

        if($returnType) {
            try { return DataType::from($mimeType); }
            catch (\ValueError $e) { return DataType::BINARY; }
        }
        
        return $mimeType;
    }

    public static function buildProjectPath(ProjectFolderType $folderType, string $file = ""): string {
        return SITE_PATH . $folderType->value . Bee::normalizePath($file);
    }

    /**
     * Validates the callable signature.
     *
     * @return bool true if the callable signature is valid, false otherwise
     */
    public static function validateCallable(?callable $callable, array $params = []): bool {
        if($callable === null || !is_callable($callable)) return false;

        $reflection = new \ReflectionFunction(\Closure::fromCallable($callable));
        $reflectionParams = $reflection->getParameters();
        $paramCount = count($params);
        
        for($i = 0; $i < $paramCount; $i++) {
            $type = $reflectionParams[$i]?->getType();
            if($type instanceof \ReflectionNamedType && $type->getName() !== $params[$i]) return false;
        }

        return true;
    }

    /**
     * Parses a CSV file and returns an array that represents the CSV file.
     *
     * @param string $csvPath the path to the CSV file absolute.
     *
     * @return array an array that represents the CSV file
     */
    public static function parseCSV(string $csvPath): array {
        if (!file_exists($csvPath)) return [];
        
        $handle = fopen($csvPath, "r");
        if (!$handle) return [];
        
        $result = [];
        $cols = fgetcsv($handle);

        if($cols === false || $cols[0] !== "key") {
            fclose($handle);
            return [];
        }
        
        $cols = array_slice($cols, 1);
        foreach($cols as $col) {
            $result[$col] = [];
        }

        while(($row = fgetcsv($handle)) !== false) {
            foreach($cols as $key => $col) {
                $result[$col][$row[0]] = $row[$key+1];
            }
        }
        
        fclose($handle);
        return $result;
    }

    /**
     * Returns the parsed language file, cached for 24 hours (never cached on dev).
     *
     * @param string $file The language file name without extension, relative to src/web/assets/lang/.
     *
     * @return array The translations indexed by language then key (e.g., ["en" => ["KEY" => "value"]]).
     */
    public static function langData(string $file = "global"): array {
        $cacheKey = Bee::getCacheKey("lang", $file);

        if(Bee::isDev()) CacheService::remove($cacheKey);
        $data = CacheService::read($cacheKey);
        if($data === null) {
            $data = Bee::parseCSV(Bee::buildProjectPath(ProjectFolderType::SRC_WEB_ASSETS_LANG, "$file.csv"));
            CacheService::write($cacheKey, $data, 86400);
        }

        return $data;
    }

    /**
     * Translates a key into the given language. Falls back to the first language column, then to the key itself.
     *
     * @param string $key The translation key.
     * @param string $lang The language code (CSV column).
     * @param string $file The language file name without extension.
     *
     * @return string The translated text.
     */
    #[\NoDiscard]
    public static function lang(string $key, string $lang, string $file = "global"): string {
        $data = Bee::langData($file);
        $text = $data[$lang][$key] ?? "";
        return $text !== "" ? $text : (array_first($data)[$key] ?? $key);
    }

    /**
     * Returns a standardized cache key based on the type and key.
     *
     * @param string $type the type of the cache key
     * @param string $key the key of the cache key
     *
     * @return string the cache key
     */
    public static function getCacheKey(string $type, string $key): string {
        return "fastraven:" . Bee::getBaseDomain() . ":" . $type . ":" . Bee::env("VERSION", "0.0.1") . ":" . hash("xxh3", $key);
    }

    #/ METHODS
    #----------------------------------------------------------------------
}