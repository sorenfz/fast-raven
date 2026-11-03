<?php

namespace FastRaven;

use FastRaven\Exceptions\BadProjectSkeletonException;
use FastRaven\Exceptions\MiddlewareDeniedException;
use FastRaven\Exceptions\NotFoundException;
use FastRaven\Exceptions\NotAuthorizedException;
use FastRaven\Exceptions\RateLimitExceededException;
use FastRaven\Exceptions\SmartException;
use FastRaven\Exceptions\UnhandledException;

use FastRaven\Internals\Kernel;

use FastRaven\Components\Core\Config;
use FastRaven\Components\Core\Template;
use FastRaven\Components\Routing\Router;
use FastRaven\Components\Http\Response;
use FastRaven\Components\Routing\Middleware;

use FastRaven\Services\LogService;
use FastRaven\Services\HeaderService;

use FastRaven\Bee;

use FastRaven\Types\ProjectFolderType;

final class Server {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private Kernel $kernel;
    private bool $ready = false;

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    public static function getConfiguration(): Config {
        return require_once Bee::buildProjectPath(ProjectFolderType::CONFIG, "config.php");
    }

    public static function getTemplate(): Template {
        return require_once Bee::buildProjectPath(ProjectFolderType::CONFIG, "template.php");
    }

    public static function getMiddleware(): Middleware {
        return require_once Bee::buildProjectPath(ProjectFolderType::CONFIG, "middleware.php");
    }

    public static function getViewRouter(): Router {
        return require_once Bee::buildProjectPath(ProjectFolderType::CONFIG_ROUTER, "views.php");
    }

    public static function getApiRouter(): Router {
        return require_once Bee::buildProjectPath(ProjectFolderType::CONFIG_ROUTER, "api.php");
    }

    public static function getCdnRouter(): Router {
        return require_once Bee::buildProjectPath(ProjectFolderType::CONFIG_ROUTER, "cdn.php");
    }

    /**
     * Initializes the server.
     *
     * @param string $sitePath The local path of the site. Use __DIR__ unless you know what you are doing.
     * 
     * @return Server
     */
    public static function initialize(string $sitePath): Server {
        define("SITE_PATH", DIRECTORY_SEPARATOR . Bee::normalizePath($sitePath) . DIRECTORY_SEPARATOR);

        if(Bee::isDev()) {
            foreach(ProjectFolderType::cases() as $folder)
                if(!is_dir(Bee::buildProjectPath($folder))) throw new BadProjectSkeletonException($folder);
        }

        require_once Bee::buildProjectPath(ProjectFolderType::CONFIG_ENV, "env.php");

        // Site\ namespace maps to the site's src/lib/ folder (per-site shared logic).
        spl_autoload_register(function(string $class): void {
            if(!str_starts_with($class, "Site\\")) return;
            $file = Bee::buildProjectPath(ProjectFolderType::SRC_LIB, str_replace("\\", "/", substr($class, 5)) . ".php");
            if(is_file($file)) require $file;
        });

        return new Server();
    }

    private function __construct() {

    }

    /**
     * Configures the server.
     *
     * @param Config $config The configuration to use.
     * @param Template $template The default template for all views.
     * @param Middleware $middleware The middleware to use.
     * @param Router $viewRouter The View Router to use.
     * @param Router $apiRouter The API Router to use.
     * @param Router $cdnRouter The CDN Router to use.
     */
    public function configure(Config $config, Template $template, Middleware $middleware, Router $viewRouter, Router $apiRouter, Router $cdnRouter): Server {
        $this->kernel = new Kernel($config, $template, $middleware, $viewRouter, $apiRouter, $cdnRouter);
        $this->ready = true;
        return $this;
    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS

    private function handleException(SmartException $e): Response|Template {
        $response = Response::new(false, $e->getStatusCode(), $e->getPublicMessage());
        LogService::error($e->getExceptionName() . ": " . $e->getMessage());

        if($e instanceof RateLimitExceededException || is_subclass_of($e, RateLimitExceededException::class)) {
            HeaderService::addHeader("Retry-After", $e->getTimeLeft());
        }

        if($this->kernel->isViewRequest()) {
            $status = $e->getStatusCode();
            $config = $this->kernel->getConfig();
            $location = null;
            if($e instanceof NotFoundException) {
                $location = $config->getDefaultNotFoundPathRedirect();
            } else if($e instanceof MiddlewareDeniedException) {
                $location = "/";
            } else if($e instanceof NotAuthorizedException) {
                if($e->isDomainLevel() && $config->getDefaultUnauthorizedSubdomainRedirect() !== null) $location = "https://".Bee::getBuiltDomain($config->getDefaultUnauthorizedSubdomainRedirect());
                else $location = $config->getDefaultUnauthorizedPathRedirect();
            }

            // Never redirect to the page that just failed: show the error page instead of looping.
            if($location !== null && rtrim($location, "/") !== rtrim($this->kernel->getRequest()->getPath(), "/")) {
                HeaderService::addHeader("Location", $location);
                $status = 302;
            }

            $template = $this->kernel->getTemplate();
            $response = $template
                ->setFile($template->getErrorFile($status))
                ->setTitle($template->getTitle() . " - Error")
                ->addData("errorCode", $status)
                ->addData("errorMessage", $e->getPublicMessage());
        }

        return $response;
    }

    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS

    /**
     * Starts the server and handles incoming requests.
     *
     * If the server has not been configured, it will return a 500 status code.
     *
     * Handles NotFoundException, BadImplementationException, EndpointFileNotFoundException, NotAuthorizedException,
     * RateLimitExceededException, BadMiddlewareException, MiddlewareDeniedException, UploadedFileNotFoundException.
     * Any other Throwable is wrapped into an UnhandledException.
     */
    public function run(): void {
        if ($this->ready) {
            $response = null;
            try {
                $this->kernel->open(); // Services/Engines initialization
                $response = $this->kernel->process(); // Request processing
            } catch(SmartException $e) {
                $response = $this->handleException($e); // Exception handling
            } catch(\Throwable $e) {
                $response = $this->handleException(new UnhandledException($e)); // Anything else becomes a logged 500
            }
            $this->kernel->close($response); // Response processing and sending
        } else {
            http_response_code(500);
        }
        exit(0);
    }

    #/ METHODS
    #----------------------------------------------------------------------
}