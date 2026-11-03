<?php

namespace FastRaven\Internals;

use FastRaven\Exceptions\DeveloperException;
use FastRaven\Services\AuthService;
use FastRaven\Services\FileService;
use FastRaven\Services\HeaderService;
use FastRaven\Services\CacheService;
use FastRaven\Bee;

use FastRaven\Internals\Engines\LogEngine;
use FastRaven\Internals\Engines\HeaderEngine;
use FastRaven\Internals\Engines\AuthEngine;
use FastRaven\Internals\Engines\DataEngine;
use FastRaven\Internals\Engines\ValidationEngine;
use FastRaven\Internals\Engines\MailEngine;
use FastRaven\Internals\Engines\FileEngine;
use FastRaven\Internals\Engines\CacheEngine;

use FastRaven\Components\Core\Config;
use FastRaven\Components\Http\Response;
use FastRaven\Components\Http\Request;
use FastRaven\Components\Core\Template;
use FastRaven\Components\Core\File;
use FastRaven\Components\Routing\Router;
use FastRaven\Components\Routing\Endpoint;
use FastRaven\Components\Routing\Middleware;

use FastRaven\Exceptions\BadImplementationException;
use FastRaven\Exceptions\EndpointFileNotFoundException;
use FastRaven\Exceptions\NotAuthorizedException;
use FastRaven\Exceptions\BadMiddlewareException;
use FastRaven\Exceptions\MiddlewareDeniedException;
use FastRaven\Exceptions\NotFoundException;
use FastRaven\Exceptions\PayloadTooLargeException;
use FastRaven\Exceptions\RateLimitExceededException;
use FastRaven\Exceptions\UploadedFileNotFoundException;

use FastRaven\Types\EndpointType;
use FastRaven\Types\ProjectFolderType;

/**
 * @internal
 */
final class Kernel {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private Config $config;
        public function getConfig(): Config { return $this->config; }
    private Request $request;
        public function getRequest(): Request { return $this->request; }

    private Template $template;
        public function getTemplate(): Template { return $this->template; }
    private Middleware $middleware;
    private Router $viewRouter;
    private Router $apiRouter;
    private Router $cdnRouter;

    private ?LogEngine $logEngine = null;
    private ?HeaderEngine $headerEngine = null;
    private ?AuthEngine $authEngine = null;
    private ?DataEngine $dataEngine = null;
    private ?ValidationEngine $validationEngine = null;
    private ?MailEngine $mailEngine = null;
    private ?FileEngine $fileEngine = null;
    private ?CacheEngine $cacheEngine = null;

    private int $rateLimitRemaining = 0;
    private int $rateLimitTimeRemaining = 0;
    private bool $rateLimitApplied = false;
    private string $nonce;

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    public function  __construct(Config $config, Template $template, Middleware $middleware, Router $viewRouter, Router $apiRouter, Router $cdnRouter) {
        $this->config = $config;
        $this->template = $template;
        $this->middleware = $middleware;
        $this->viewRouter = $viewRouter;
        $this->apiRouter = $apiRouter;
        $this->cdnRouter = $cdnRouter;
    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS

    /**
     * Handles routing for the request.
     *
     * @param Router $router The router to use.
     * 
     * @return Endpoint|null The matched endpoint, or null if no match is found.
     */
    private function handleRouting(Router $router): ?Endpoint {
        foreach($router->getSubrouterList() as $ep) {
            if(str_starts_with($this->request->getPath(), $ep->getPath())) {
                $this->enforceAccess($ep);
                $nestedRouter = require Bee::buildProjectPath(ProjectFolderType::CONFIG_ROUTER, $ep->getFile());
                if($nestedRouter instanceof Router) return $this->handleRouting($nestedRouter);
            }
        }
        
        if(isset($router->getEndpointList()[$this->request->getComplexPath()]))
            return $router->getEndpointList()[$this->request->getComplexPath()];

        return null;
    }

    /**
     * Enforces the restricted flag and the middleware of an endpoint or nested router.
     *
     * @throws NotAuthorizedException If the endpoint is restricted and the request is not authorized.
     * @throws BadMiddlewareException If the middleware has an invalid signature.
     * @throws MiddlewareDeniedException If the middleware denies the request.
     */
    private function enforceAccess(Endpoint $endpoint): void {
        if($endpoint->getRestricted() && !AuthService::isAuthorized($this->request)) throw new NotAuthorizedException();

        if($endpoint->getMiddlewareId() !== "") {
            $middleware = $this->middleware->get($endpoint->getMiddlewareId());
            if(!Bee::validateCallable($middleware, [Request::class])) throw new BadMiddlewareException($endpoint->getFile());
            if($middleware($this->request) === false) throw new MiddlewareDeniedException();
        }
    }

    /**
     * Handles rate limiting for the request. Limits apply per authorized user only.
     *
     * @param int $limit The configured rate limit for the request.
     * @param string $scope Counter scope, so config and router limits keep separate counters per endpoint type.
     * 
     * @return bool True if the user does not exceed its rate limit, false otherwise.
     */
    private function handleRateLimit(int $limit, string $scope): bool {
        $uid = $limit > 0 ? AuthService::getAuthorizedUserId() : null;
        if ($uid === null) return true;

        $rateLimitID = Bee::getCacheKey("ratelimit", "$uid:$scope");
        $cacheItem = CacheService::readWithMeta($rateLimitID);
        $count = $cacheItem ? CacheService::increment($rateLimitID) : 0;
        $expires = $cacheItem["expires"] ?? time() + 60;

        // A missing or just-expired window starts a new one.
        if($count === 0) {
            $count = 1;
            $expires = time() + 60;
            CacheService::write($rateLimitID, $count, 60);
        }

        $this->rateLimitApplied = true;
        $this->rateLimitRemaining = $limit - $count;
        $this->rateLimitTimeRemaining = max(0, $expires - time());

        return $this->rateLimitRemaining >= 0 || $this->rateLimitTimeRemaining <= 0;
    }

    /**
     * Throws if the given rate limit is exceeded, writing the rate limit headers otherwise.
     *
     * @throws RateLimitExceededException If the authorized user exceeds its rate limit.
     */
    private function enforceRateLimit(int $limit, string $scope): void {
        $allowed = $this->handleRateLimit($limit, $scope);
        if($this->rateLimitApplied) $this->headerEngine->writeRateLimitHeaders($limit, $this->rateLimitRemaining, $this->rateLimitTimeRemaining);
        if(!$allowed) throw new RateLimitExceededException("User " . AuthService::getAuthorizedUserId(), $this->rateLimitRemaining, $this->rateLimitTimeRemaining);
    }

    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS
    
    public function isViewRequest(): bool { return isset($this->request) && $this->request->getType() === EndpointType::VIEW; }
    public function isApiRequest(): bool { return isset($this->request) && $this->request->getType() === EndpointType::API; }
    public function isCdnRequest(): bool { return isset($this->request) && $this->request->getType() === EndpointType::CDN; }

    /**
     * This function initializes the kernel and prepares it for processing the request.
     *
     * It builds the Request, starts the engines, writes the headers, resolves the page language,
     * applies the per-user rate limit and the site-level authorization.
     *
     * It should be called at the beginning of every request.
     * 
     * @throws PayloadTooLargeException If the request body exceeds the configured input length limit.
     * @throws NotAuthorizedException If the endpoint is restricted and the request is not authorized.
     * @throws RateLimitExceededException If the authorized user exceeds its rate limit.
     */
    public function open(): void {
        $this->nonce = bin2hex(random_bytes(16));

        // Behind a trusted reverse proxy the client IP and protocol come from the forwarded headers.
        $proxy = $this->config->isProxyTrusted();
        $remoteAddress = $proxy && isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]) : $_SERVER["REMOTE_ADDR"];
        $https = $proxy && isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) ? ($_SERVER["HTTP_X_FORWARDED_PROTO"] === "https" ? "on" : "") : ($_SERVER["HTTPS"] ?? "");

        $inputLengthLimit = $this->config->getLengthLimitInput() >= 0 ? $this->config->getLengthLimitInput() : null;
        $this->request = new Request(
            $_SERVER["REQUEST_URI"],
            $_SERVER["REQUEST_METHOD"],
            file_get_contents("php://input", false, null, 0, $inputLengthLimit),
            $_FILES,
            $this->config->isPrivacyRegisterOrigin() ? $remoteAddress : "HOST",
            $_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? "",
            $_COOKIE["lang"] ?? ""
        );
        
        if($this->config->isPrivacyRegisterLogs()) {
            $this->logEngine = LogEngine::zap($this->request->getInternalID());
            $this->logEngine->writeOpenLogs($this->request);
        }

        $this->headerEngine = HeaderEngine::zap();
        $this->headerEngine->writeSecurityHeaders($https, $this->nonce);

        // Multipart bodies are consumed by PHP into $_FILES and bounded by the file upload limit instead.
        $isMultipart = str_starts_with($_SERVER["CONTENT_TYPE"] ?? "", "multipart/form-data");
        if($inputLengthLimit !== null && !$isMultipart && (int)($_SERVER["CONTENT_LENGTH"] ?? 0) > $inputLengthLimit) throw new PayloadTooLargeException($inputLengthLimit);

        $this->cacheEngine = CacheEngine::zap(Bee::getBaseDomain() . ":" . $this->config->getSiteName() . ":");

        $this->authEngine = AuthEngine::zap();
        $this->authEngine->initializeSessionCookie($this->config->getAuthSessionName(), $this->config->getAuthLifetime(), $this->config->isAuthGlobal());

        $hasSession = isset($_COOKIE[$this->config->getAuthSessionName()]) && AuthService::isAuthorized();
        $this->headerEngine->writeUtilityHeaders($this->request->getType() === EndpointType::API || $hasSession);

        $this->template->resolveLang($this->request->getPreferredLanguages());

        $this->enforceRateLimit($this->config->getRateLimit($this->request->getType()), $this->request->getType()->value);

        if($this->config->isRestricted()) {
            if(!AuthService::isAuthorized($this->request)) throw new NotAuthorizedException(true);
        }

        $this->dataEngine = DataEngine::zap();

        $this->validationEngine = ValidationEngine::zap();

        $this->mailEngine = MailEngine::zap();

        $this->fileEngine = FileEngine::zap($this->config->getLengthLimitFileUpload());
    }

    /**
     * Processes the request and returns a response.
     *
     * This function will try to match the request with an endpoint in the router and process it.
     * If the request is a View request, it will render the template from the endpoint file and expect a Template object to be returned.
     * If the request is an API request, it will call the function from the endpoint file and expect a Response object to be returned.
     * If the request is a CDN request, it will retrieve the file from the endpoint file and expect a File object to be returned.
     *
     * @return Template|Response|File The response to output or process.
     * 
     * @throws NotFoundException If no matching route is found for the request.
     * @throws NotAuthorizedException If the endpoint is restricted and the request is not authorized.
     * @throws EndpointFileNotFoundException If the endpoint file does not exist.
     * @throws BadImplementationException If the API function does not return a Response object.
     * @throws DeveloperException If the developer makes a mistake on View/API/CDN endpoints.
     */
    public function process(): Template|Response|File {
        [$router, $folder] = match ($this->request->getType()) {
            EndpointType::VIEW => [$this->viewRouter, ProjectFolderType::SRC_VIEWS],
            EndpointType::API => [$this->apiRouter, ProjectFolderType::SRC_API],
            EndpointType::CDN => [$this->cdnRouter, ProjectFolderType::SRC_CDN],
        };

        $this->enforceRateLimit($router->getLimitPerMinute(), $this->request->getType()->value . ":router");

        $endpoint = $this->handleRouting($router);
        if(!$endpoint) throw new NotFoundException();
        $this->enforceAccess($endpoint);

        $filePath = Bee::buildProjectPath($folder, $endpoint->getFile());
        if(!file_exists($filePath)) throw new EndpointFileNotFoundException($filePath);

        $fn = require_once $filePath;
        $response = null;

        if($this->request->getType() === EndpointType::VIEW) {
            $this->template->setNonce($this->nonce);
            if(Bee::validateCallable($fn, [Request::class, Template::class])) {
                try {
                    $response = $fn($this->request, $this->template);
                } catch (\Throwable $e) {
                    throw new DeveloperException($endpoint->getFile(), $e->getMessage());
                }
            }
            if($response === null || !$response instanceof Template) throw new BadImplementationException($endpoint->getFile(), "Template");

            if($this->template !== $response) $response = $this->template->merge($response);
        } else {
            if(Bee::validateCallable($fn, [Request::class])) {
                try {
                    $response = $fn($this->request);
                } catch (\Throwable $e) {
                    throw new DeveloperException($endpoint->getFile(), $e->getMessage());
                }
            }
            if($response === null || !$response instanceof Response) throw new BadImplementationException($endpoint->getFile(), "Response");

            if($this->request->getType() === EndpointType::CDN) {
                $cdnFilePath = $response->getFrameworkMetadata()["path"];
                if(!FileService::exists($cdnFilePath)) throw new UploadedFileNotFoundException(FileService::getUploadFilePath($cdnFilePath));
                $response = File::new("cdn_file", FileService::getUploadFilePath($cdnFilePath));
            }
        }

        return $response;
    }

    /**
     * This function is called at the end of every request and is responsible for outputting the response,
     * writing the close logs, and dumping the log stash into a file.
     *
     * @param Template|Response|File $response The response to output or process
     */
    public function close(Template|Response|File $response): void {
        $statusCode = match(get_class($response)) {
            Template::class => $response->hasData("errorCode") ? intval($response->getData("errorCode")) : 200,
            Response::class => $response->getCode(),
            File::class => 200,
        };
        http_response_code($statusCode);

        if($response instanceof Template) {
            HeaderService::addHeader("Content-Type", "text/html; charset=utf-8");
            $template = $response;
            require_once __DIR__ . DIRECTORY_SEPARATOR . "View" . DIRECTORY_SEPARATOR . "main.php";
        } else if ($response instanceof Response) {
            HeaderService::addHeader("Content-Type", "application/json; charset=utf-8");
            echo json_encode([
                "success" => $response->getSuccess(),
                "msg" => $response->getMessage(),
                "data" => $response->getData()
            ]);
        } elseif($response instanceof File) {
            HeaderService::addHeader("Content-Type", $response->getType()->value);
            HeaderService::addHeader("Content-Length", filesize($response->getPath()));
            readfile($response->getPath());
        }
        
        if(session_status() === PHP_SESSION_ACTIVE) session_write_close();

        if (function_exists("fastcgi_finish_request")) fastcgi_finish_request();

        $elapsedTime = round((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000);

        $this->mailEngine?->processDeferredMails();

        if($this->logEngine) {
            $this->logEngine->writeCloseLogs($elapsedTime, $statusCode);
            $this->logEngine->dumpLogsIntoFile();
        }
        
        if (random_int(0, 100) < $this->config->getCacheFileGCProbability()) { 
            CacheService::runGarbageCollector($this->config->getCacheFileGCPower());
        }
    }

    #/ METHODS
    #----------------------------------------------------------------------  
}