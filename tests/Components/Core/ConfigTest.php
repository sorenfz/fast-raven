<?php

namespace FastRaven\Tests\Components\Core;

use PHPUnit\Framework\TestCase;
use FastRaven\Components\Core\Config;

class ConfigTest extends TestCase
{
    public function testNewCreatesInstanceWithCorrectValues(): void
    {
        $config = Config::new('main', true);

        $this->assertEquals('main', $config->getSiteName());
        $this->assertTrue($config->isRestricted());
    }

    public function testNewCreatesNonRestrictedSite(): void
    {
        $config = Config::new('public', false);

        $this->assertEquals('public', $config->getSiteName());
        $this->assertFalse($config->isRestricted());
    }





    public function testDefaultAuthSessionName(): void
    {
        $config = Config::new('test', false);

        $this->assertEquals('PHPSESSID', $config->getAuthSessionName());
    }

    public function testDefaultAuthLifetime(): void
    {
        $config = Config::new('test', false);

        // Default is 7 days = 7 * 24 * 60 * 60 = 604800 seconds
        $this->assertEquals(604800, $config->getAuthLifetime());
    }

    public function testDefaultAuthGlobalIsFalse(): void
    {
        $config = Config::new('test', false);

        $this->assertFalse($config->isAuthGlobal());
    }

    public function testConfigureAuthorizationSetsGlobalAuthTrue(): void
    {
        $config = Config::new('test', false);

        $config->configureAuthorization('SESSION', 7, true);

        $this->assertTrue($config->isAuthGlobal());
    }

    public function testConfigureAuthorizationSetsGlobalAuthFalse(): void
    {
        $config = Config::new('test', false);

        $config->configureAuthorization('SESSION', 7, false);

        $this->assertFalse($config->isAuthGlobal());
    }

    public function testConfigureAuthorizationSetsSessionName(): void
    {
        $config = Config::new('test', false);

        $config->configureAuthorization('CUSTOM_SESSION', 30, false);

        $this->assertEquals('CUSTOM_SESSION', $config->getAuthSessionName());
    }

    public function testConfigureAuthorizationCalculatesLifetime(): void
    {
        $config = Config::new('test', false);

        $config->configureAuthorization('SESSION', 1, false);

        // 1 day = 86400 seconds
        $this->assertEquals(86400, $config->getAuthLifetime());
    }

    public function testConfigureAuthorizationCalculatesLifetimeForMultipleDays(): void
    {
        $config = Config::new('test', false);

        $config->configureAuthorization('SESSION', 14, false);

        // 14 days = 1209600 seconds
        $this->assertEquals(1209600, $config->getAuthLifetime());
    }

    public function testDefaultNotFoundPathRedirect(): void
    {
        $config = Config::new('test', false);

        $this->assertNull($config->getDefaultNotFoundPathRedirect());
    }

    public function testConfigureRedirectsSetsNotFoundPath(): void
    {
        $config = Config::new('test', false);

        $config->configureRedirects('/404', '/login');

        $this->assertEquals('/404', $config->getDefaultNotFoundPathRedirect());
    }

    public function testDefaultUnauthorizedPathRedirect(): void
    {
        $config = Config::new('test', false);

        $this->assertNull($config->getDefaultUnauthorizedPathRedirect());
    }

    public function testDefaultUnauthorizedSubdomainRedirect(): void
    {
        $config = Config::new('test', false);

        $this->assertNull($config->getDefaultUnauthorizedSubdomainRedirect());
    }

    public function testConfigureRedirectsSetsUnauthorizedPath(): void
    {
        $config = Config::new('test', false);

        $config->configureRedirects('/', '/auth');

        $this->assertEquals('/auth', $config->getDefaultUnauthorizedPathRedirect());
    }

    public function testConfigureRedirectsSetsSubdomain(): void
    {
        $config = Config::new('test', false);

        $config->configureRedirects('/', '/login', 'auth.example.com');

        $this->assertEquals('/login', $config->getDefaultUnauthorizedPathRedirect());
        $this->assertEquals('auth.example.com', $config->getDefaultUnauthorizedSubdomainRedirect());
    }

    public function testConfigureRedirectsWithEmptySubdomain(): void
    {
        $config = Config::new('test', false);

        $config->configureRedirects('/', '/login', '');

        $this->assertEquals('', $config->getDefaultUnauthorizedSubdomainRedirect());
    }

    public function testMultipleConfigurationCalls(): void
    {
        $config = Config::new('test', true);

        $config->configureAuthorization('MY_SESSION', 10, true);
        $config->configureRedirects('/not-found', '/unauthorized', 'auth.mydomain.com');

        $this->assertEquals('MY_SESSION', $config->getAuthSessionName());
        $this->assertEquals(864000, $config->getAuthLifetime()); // 10 days
        $this->assertTrue($config->isAuthGlobal());
        $this->assertEquals('/not-found', $config->getDefaultNotFoundPathRedirect());
        $this->assertEquals('/unauthorized', $config->getDefaultUnauthorizedPathRedirect());
        $this->assertEquals('auth.mydomain.com', $config->getDefaultUnauthorizedSubdomainRedirect());
    }

    public function testDefaultCacheFileGCProbability(): void
    {
        $config = Config::new('test', false);

        $this->assertEquals(0, $config->getCacheFileGCProbability());
    }

    public function testDefaultCacheFileGCPower(): void
    {
        $config = Config::new('test', false);

        $this->assertEquals(50, $config->getCacheFileGCPower());
    }

    public function testConfigureCacheSetsProbability(): void
    {
        $config = Config::new('test', false);

        $config->configureCache(5, 100);

        $this->assertEquals(5, $config->getCacheFileGCProbability());
    }

    public function testConfigureCacheSetsPower(): void
    {
        $config = Config::new('test', false);

        $config->configureCache(5, 100);

        $this->assertEquals(100, $config->getCacheFileGCPower());
    }

    public function testConfigureCacheDisabledByDefault(): void
    {
        $config = Config::new('test', false);

        // Probability 0 means GC is disabled
        $this->assertEquals(0, $config->getCacheFileGCProbability());
    }

    public function testProxyNotTrustedByDefault(): void
    {
        $this->assertFalse(Config::new("main", false)->isProxyTrusted());
    }

    public function testConfigureProxy(): void
    {
        $this->assertTrue(Config::new("main", false)->configureProxy(true)->isProxyTrusted());
    }
}
