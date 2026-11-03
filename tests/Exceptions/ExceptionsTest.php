<?php

namespace FastRaven\Tests\Exceptions;

use PHPUnit\Framework\TestCase;
use FastRaven\Exceptions\UnhandledException;
use FastRaven\Exceptions\BadImplementationException;
use FastRaven\Exceptions\BadMiddlewareException;
use FastRaven\Exceptions\MiddlewareDeniedException;
use FastRaven\Exceptions\EndpointFileNotFoundException;
use FastRaven\Exceptions\NotAuthorizedException;
use FastRaven\Exceptions\NotFoundException;
use FastRaven\Exceptions\SmartException;
use FastRaven\Exceptions\DeveloperException;

class ExceptionsTest extends TestCase
{
    // BadImplementationException Tests
    public function testBadImplementationExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(BadImplementationException::class);
        $this->expectExceptionMessage("Endpoint does not return a valid Response object. (/path/to/endpoint.php)");

        throw new BadImplementationException("/path/to/endpoint.php");
    }

    public function testBadImplementationExceptionWithCustomType(): void
    {
        $this->expectException(BadImplementationException::class);
        $this->expectExceptionMessage("Endpoint does not return a valid Template object. (/path/to/view.php)");

        throw new BadImplementationException("/path/to/view.php", "Template");
    }

    public function testBadImplementationExceptionExtendsException(): void
    {
        $exception = new BadImplementationException("/test/path.php");
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testBadImplementationExceptionHasCorrectStatusCode(): void
    {
        $exception = new BadImplementationException("/test/path.php");
        $this->assertEquals(500, $exception->getStatusCode());
    }

    public function testBadImplementationExceptionHasCorrectPublicMessage(): void
    {
        $exception = new BadImplementationException("/test/path.php");
        $this->assertEquals("This resource is not available at this time.", $exception->getPublicMessage());
    }

    // BadMiddlewareException Tests
    public function testBadMiddlewareExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(BadMiddlewareException::class);
        $this->expectExceptionMessage("Middleware does not have the correct signature. (requireAdmin)");

        throw new BadMiddlewareException("requireAdmin");
    }

    public function testBadMiddlewareExceptionExtendsException(): void
    {
        $exception = new BadMiddlewareException("testMiddleware");
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testBadMiddlewareExceptionHasCorrectStatusCode(): void
    {
        $exception = new BadMiddlewareException("testMiddleware");
        $this->assertEquals(500, $exception->getStatusCode());
    }

    public function testBadMiddlewareExceptionHasCorrectPublicMessage(): void
    {
        $exception = new BadMiddlewareException("testMiddleware");
        $this->assertEquals("This resource is not available at this time.", $exception->getPublicMessage());
    }

    // MiddlewareDeniedException Tests
    public function testMiddlewareDeniedExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(MiddlewareDeniedException::class);
        $this->expectExceptionMessage("Middleware denied access to a resource.");

        throw new MiddlewareDeniedException();
    }

    public function testMiddlewareDeniedExceptionExtendsException(): void
    {
        $exception = new MiddlewareDeniedException();
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testMiddlewareDeniedExceptionHasCorrectStatusCode(): void
    {
        $exception = new MiddlewareDeniedException();
        $this->assertEquals(400, $exception->getStatusCode());
    }

    public function testMiddlewareDeniedExceptionHasCorrectPublicMessage(): void
    {
        $exception = new MiddlewareDeniedException();
        $this->assertEquals("Request does not meet the requirements.", $exception->getPublicMessage());
    }

    public function testMiddlewareDeniedExceptionWithCustomCodeAndMessage(): void
    {
        $exception = new MiddlewareDeniedException(403, "Access denied.");
        $this->assertEquals(403, $exception->getStatusCode());
        $this->assertEquals("Access denied.", $exception->getPublicMessage());
    }

    // EndpointFileNotFoundException Tests
    public function testEndpointFileNotFoundExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(EndpointFileNotFoundException::class);
        $this->expectExceptionMessage("Endpoint file does not exist! (/missing/file.php)");

        throw new EndpointFileNotFoundException("/missing/file.php");
    }

    public function testEndpointFileNotFoundExceptionExtendsException(): void
    {
        $exception = new EndpointFileNotFoundException("/test/path.php");
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testEndpointFileNotFoundExceptionHasCorrectStatusCode(): void
    {
        $exception = new EndpointFileNotFoundException("/test/path.php");
        $this->assertEquals(500, $exception->getStatusCode());
    }

    public function testEndpointFileNotFoundExceptionHasCorrectPublicMessage(): void
    {
        $exception = new EndpointFileNotFoundException("/test/path.php");
        $this->assertEquals("This resource is not available at this time.", $exception->getPublicMessage());
    }

    // NotAuthorizedException Tests (Resource Level)
    public function testNotAuthorizedExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(NotAuthorizedException::class);
        $this->expectExceptionMessage("Unauthorized user tried to access private resource.");

        throw new NotAuthorizedException(false);
    }

    public function testNotAuthorizedExceptionExtendsException(): void
    {
        $exception = new NotAuthorizedException(false);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testNotAuthorizedExceptionResourceLevelHasCorrectStatusCode(): void
    {
        $exception = new NotAuthorizedException(false);
        $this->assertEquals(401, $exception->getStatusCode());
    }

    public function testNotAuthorizedExceptionResourceLevelHasCorrectPublicMessage(): void
    {
        $exception = new NotAuthorizedException(false);
        $this->assertEquals("Authorization required.", $exception->getPublicMessage());
    }

    public function testNotAuthorizedExceptionResourceLevelIsDomainLevelReturnsFalse(): void
    {
        $exception = new NotAuthorizedException(false);
        $this->assertFalse($exception->isDomainLevel());
    }

    // NotAuthorizedException Tests (Domain Level)
    public function testNotAuthorizedExceptionDomainLevelCanBeThrownAndCaught(): void
    {
        $this->expectException(NotAuthorizedException::class);
        $this->expectExceptionMessage("Unauthorized user tried to access private subdomain.");

        throw new NotAuthorizedException(true);
    }

    public function testNotAuthorizedExceptionDomainLevelHasCorrectStatusCode(): void
    {
        $exception = new NotAuthorizedException(true);
        $this->assertEquals(401, $exception->getStatusCode());
    }

    public function testNotAuthorizedExceptionDomainLevelHasCorrectPublicMessage(): void
    {
        $exception = new NotAuthorizedException(true);
        $this->assertEquals("Authorization required.", $exception->getPublicMessage());
    }

    public function testNotAuthorizedExceptionDomainLevelIsDomainLevelReturnsTrue(): void
    {
        $exception = new NotAuthorizedException(true);
        $this->assertTrue($exception->isDomainLevel());
    }

    // NotFoundException Tests
    public function testNotFoundExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("No matching route found for request.");

        throw new NotFoundException();
    }

    public function testNotFoundExceptionExtendsException(): void
    {
        $exception = new NotFoundException();
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testNotFoundExceptionHasCorrectStatusCode(): void
    {
        $exception = new NotFoundException();
        $this->assertEquals(404, $exception->getStatusCode());
    }

    public function testNotFoundExceptionHasCorrectPublicMessage(): void
    {
        $exception = new NotFoundException();
        $this->assertEquals("Resource not found.", $exception->getPublicMessage());
    }

    // DeveloperException Tests
    public function testDeveloperExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(DeveloperException::class);
        $this->expectExceptionMessage("{test.php} Error message");

        throw new DeveloperException("test.php", "Error message");
    }

    public function testDeveloperExceptionExtendsSmartException(): void
    {
        $exception = new DeveloperException("test.php", "message");
        $this->assertInstanceOf(SmartException::class, $exception);
    }

    public function testDeveloperExceptionHasCorrectStatusCode(): void
    {
        $exception = new DeveloperException("test.php", "message");
        $this->assertEquals(500, $exception->getStatusCode());
    }

    public function testDeveloperExceptionHasCorrectPublicMessage(): void
    {
        $exception = new DeveloperException("test.php", "message");
        $this->assertEquals("This resource is not available at this time.", $exception->getPublicMessage());
    }

    // SmartException Tests
    public function testSmartExceptionGetExceptionName(): void
    {
        $exception = new NotFoundException();
        $this->assertEquals("NotFoundException", $exception->getExceptionName());

        $devException = new DeveloperException("file", "msg");
        $this->assertEquals("DeveloperException", $devException->getExceptionName());
    }

    public function testUnhandledExceptionWrapsThrowable(): void
    {
        $e = new UnhandledException(new \TypeError("boom"));

        $this->assertEquals(500, $e->getStatusCode());
        $this->assertStringStartsWith("TypeError: boom (", $e->getMessage());
        $this->assertEquals("This resource is not available at this time.", $e->getPublicMessage());
        $this->assertEquals("UnhandledException", $e->getExceptionName());
    }
}
