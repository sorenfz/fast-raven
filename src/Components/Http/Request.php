<?php

namespace FastRaven\Components\Http;

use FastRaven\Components\Core\File;

use FastRaven\Bee;

use FastRaven\Types\EndpointType;
use FastRaven\Types\SanitizeType;

final class Request {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private EndpointType $type;
        public function getType(): EndpointType { return $this->type; }
    private string $internalID;
        public function getInternalID(): string { return $this->internalID; }
    private array $query = [];
    private array $data = [];
    private array $files = [];
    private string $method;
        public function getMethod(): string { return $this->method; }
    private string $path;
        public function getPath(): string { return $this->path; }
    private string $complexPath;
        public function getComplexPath(): string { return $this->complexPath; }
    private string $remoteAddress;
        public function getRemoteAddress(): string { return $this->remoteAddress; }
    private array $preferredLanguages = [];
        public function getPreferredLanguages(): array { return $this->preferredLanguages; }

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    /**
     * Initializes a new Request instance.
     *
     * @param string $uri              The original URI of the request.
     * @param string $method           The HTTP method of the request (e.g. GET, POST, PUT, DELETE).
     * @param string $dataStream       The data stream of the request.
     * @param array $fileStream        The file stream of the request.
     * @param string $remoteAddress    The remote address of the request.
     * @param string $acceptLanguage   The Accept-Language header of the request.
     * @param string $langCookie       The language explicitly chosen by the client ("lang" cookie).
     */
    public function __construct(string $uri, string $method, string $dataStream, array $fileStream, string $remoteAddress, string $acceptLanguage = "", string $langCookie = "") {
        $this->internalID = bin2hex(random_bytes(4));
        $this->remoteAddress = $remoteAddress;
        $this->preferredLanguages = $this->parseLanguages($acceptLanguage, $langCookie);
       
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? "", $this->query);
        $decoded = json_decode($dataStream, true);
        $this->data = is_array($decoded) ? $decoded : ($_POST ?? []);
        $this->files = [];
        foreach ($fileStream as $name => $data) {
            if (isset($data["tmp_name"]) && $data["tmp_name"]) {
                $this->files[$name] = File::new($data["name"], $data["tmp_name"]);
            }
        }
        
        $this->method = strtoupper($method);
        $this->path = "/".Bee::normalizePath(parse_url($uri, PHP_URL_PATH) ?? "");
        if($this->path !== "/") $this->path .= "/";
        $this->complexPath = $this->path."#".$this->method;

        $this->type = EndpointType::VIEW;
        if(str_starts_with($this->path, "/api/")) $this->type = EndpointType::API;
        elseif(str_starts_with($this->path, "/cdn/")) $this->type = EndpointType::CDN;
    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS

    /**
     * Parses the client language preferences: the cookie first, then Accept-Language primary tags ordered by quality.
     * 
     * @return string[] Lowercase language codes without duplicates (e.g., ["es", "en"]).
     */
    private function parseLanguages(string $header, string $cookie): array {
        $weights = [];
        foreach(explode(",", $header) as $part) {
            [$tag, $q] = array_pad(preg_split('/\s*;\s*q=/', trim($part), 2), 2, "1");
            if($tag !== "") $weights[strtolower(explode("-", $tag)[0])] ??= (float)$q;
        }
        arsort($weights);

        return [strtolower($cookie), ...array_keys($weights)]
            |> array_filter(...)
            |> array_unique(...)
            |> array_values(...);
    }
    
    /**
     * Sanitizes a value from an array based on the specified sanitization level.
     * 
     * @param array $array The array to sanitize.
     * @param string $key The key to sanitize.
     * @param SanitizeType $sanitizeType The sanitization level to apply (default: RAW).
     * 
     * @return mixed The sanitized value, or null if the key does not exist.
     */
    private function sanitizeDataItem(array $array, string $key, SanitizeType $sanitizeType = SanitizeType::RAW): mixed {
        $value = $array[$key] ?? null;
        
        if($value === null) return null;
        if(!is_string($value)) return $value;

        if($sanitizeType === SanitizeType::RAW) return $value;
        
        $value = preg_replace('/\x00|%00/i', "", $value);
        $value = preg_replace('/<\?(?:php|=)?[\s\S]*?\?>|<\?(?:php|=)?[\s\S]*/i', "", $value);
        if($sanitizeType === SanitizeType::SAFE) return $value;
        
        if($sanitizeType === SanitizeType::ENCODED) return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
        
        $value = trim(strip_tags($value));
        if($sanitizeType === SanitizeType::SANITIZED) return $value;
        
        return preg_replace('/[^a-zA-Z0-9\s]/', "", $value);
    }

    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS

    /**
     * Retrieves a value from the URI query string with optional sanitization.
     * 
     * Sanitization is cumulative. Each level includes all transformations from previous levels:
     * 
     * Note: ENCODED and SANITIZED both extend SAFE but are mutually exclusive branches.
     *
     * @param string $key The key to retrieve from the URI query string.
     * @param SanitizeType $sanitizeType The sanitization level to apply (default: RAW).
     * 
     * @return mixed The sanitized value, or null if the key does not exist.
     */
    #[\NoDiscard]
    public function get(string $key, SanitizeType $sanitizeType = SanitizeType::RAW): mixed {
        return $this->sanitizeDataItem($this->query, $key, $sanitizeType);
    }

    /**
     * Retrieves a value from the request data with optional sanitization.
     * 
     * Sanitization is cumulative. Each level includes all transformations from previous levels:
     * 
     * Note: ENCODED and SANITIZED both extend SAFE but are mutually exclusive branches.
     *
     * @param string $key The key to retrieve from request data.
     * @param SanitizeType $sanitizeType The sanitization level to apply (default: RAW).
     * 
     * @return mixed The sanitized value, or null if the key does not exist.
     */
    #[\NoDiscard]
    public function post(string $key, SanitizeType $sanitizeType = SanitizeType::RAW): mixed {
        return $this->sanitizeDataItem($this->data, $key, $sanitizeType);
    }

    /**
     * Retrieves an uploaded file's temporary path by field name.
     *
     * @param string $name The field name from the form/FormData.
     * 
     * @return ?File The temporary file path, or null if not found.
     */
    #[\NoDiscard]
    public function file(string $name): ?File {
        return $this->files[$name] ?? null;
    }

    #/ METHODS
    #----------------------------------------------------------------------  
}