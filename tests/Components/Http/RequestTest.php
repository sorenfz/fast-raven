<?php

namespace FastRaven\Tests\Components\Http;

use PHPUnit\Framework\TestCase;
use FastRaven\Components\Http\Request;
use FastRaven\Types\SanitizeType;
use FastRaven\Types\EndpointType;

class RequestTest extends TestCase
{
    public function testConstructorParsesSimpleUri(): void
    {
        $request = new Request('/home', 'GET', '', [], '127.0.0.1');

        $this->assertEquals('/home/', $request->getPath());
        $this->assertEquals('GET', $request->getMethod());
    }

    public function testConstructorParsesRootPath(): void
    {
        $request = new Request('/', 'GET', '', [], '127.0.0.1');

        $this->assertEquals('/', $request->getPath());
    }

    public function testConstructorKeepsTrailingSlashInPath(): void
    {
        $request = new Request('/about/', 'GET', '', [], '127.0.0.1');

        // Path always has trailing slash
        $this->assertEquals('/about/', $request->getPath());
        $this->assertEquals('/about/#GET', $request->getComplexPath());
    }

    public function testConstructorKeepsRootSlash(): void
    {
        $request = new Request('/', 'GET', '', [], '127.0.0.1');

        $this->assertEquals('/', $request->getPath());
    }

    public function testConstructorParsesUriWithQueryString(): void
    {
        $request = new Request('/search?q=test&page=1', 'GET', '', [], '127.0.0.1');

        $this->assertEquals('/search/', $request->getPath());
    }

    public function testComplexPathGenerationForGetRequest(): void
    {
        $request = new Request('/home', 'GET', '', [], '127.0.0.1');

        $this->assertEquals('/home/#GET', $request->getComplexPath());
    }

    public function testComplexPathGenerationForPostRequest(): void
    {
        $request = new Request('/submit', 'POST', '', [], '127.0.0.1');

        $this->assertEquals('/submit/#POST', $request->getComplexPath());
    }

    public function testComplexPathGenerationForRootPath(): void
    {
        $request = new Request('/', 'GET', '', [], '127.0.0.1');

        $this->assertEquals('/#GET', $request->getComplexPath());
    }

    public function testGetTypeReturnsApiForApiPaths(): void
    {
        $request = new Request('/api/users', 'GET', '', [], '127.0.0.1');

        $this->assertEquals(EndpointType::API, $request->getType());
    }

    public function testGetTypeReturnsViewForNonApiPaths(): void
    {
        $request = new Request('/home', 'GET', '', [], '127.0.0.1');

        $this->assertEquals(EndpointType::VIEW, $request->getType());
    }

    public function testGetTypeReturnsApiForNestedApiPaths(): void
    {
        $request = new Request('/api/v1/users/123', 'GET', '', [], '127.0.0.1');

        $this->assertEquals(EndpointType::API, $request->getType());
    }

    public function testGetTypeReturnsCdnForCdnPaths(): void
    {
        $request = new Request('/cdn/images/logo.png', 'GET', '', [], '127.0.0.1');

        $this->assertEquals(EndpointType::CDN, $request->getType());
    }

    // =====================================================================
    // get() method tests - Query string parameters (URL)
    // =====================================================================

    public function testGetReturnsQueryParamValue(): void
    {
        $request = new Request('/search?username=testuser&email=test@example.com', 'GET', '', [], '127.0.0.1');

        $this->assertEquals('testuser', $request->get('username'));
        $this->assertEquals('test@example.com', $request->get('email'));
    }

    public function testGetReturnsNullForNonExistentQueryParam(): void
    {
        $request = new Request('/search?username=testuser', 'GET', '', [], '127.0.0.1');

        $this->assertNull($request->get('nonexistent'));
    }

    public function testGetRawPreservesHtmlTagsInQuery(): void
    {
        $request = new Request('/search?content=' . urlencode('<script>alert("xss")</script>Hello'), 'GET', '', [], '127.0.0.1');

        $result = $request->get('content', SanitizeType::RAW);
        $this->assertEquals('<script>alert("xss")</script>Hello', $result);
    }

    public function testGetSafeStripsPhpTagsInQuery(): void
    {
        $request = new Request('/search?code=' . urlencode('<?php echo "hack"; ?>safe'), 'GET', '', [], '127.0.0.1');

        $result = $request->get('code', SanitizeType::SAFE);
        $this->assertEquals('safe', $result);
    }

    public function testGetEncodedEncodesHtmlInQuery(): void
    {
        $request = new Request('/search?content=' . urlencode('<b>bold</b>'), 'GET', '', [], '127.0.0.1');

        $result = $request->get('content', SanitizeType::ENCODED);
        $this->assertEquals('&lt;b&gt;bold&lt;/b&gt;', $result);
    }

    public function testGetSanitizedStripsHtmlInQuery(): void
    {
        $request = new Request('/search?content=' . urlencode('<b>bold</b>text'), 'GET', '', [], '127.0.0.1');

        $result = $request->get('content', SanitizeType::SANITIZED);
        $this->assertEquals('boldtext', $result);
    }

    public function testGetOnlyAlphaFiltersQuery(): void
    {
        $request = new Request('/search?username=john_doe@123!', 'GET', '', [], '127.0.0.1');

        $result = $request->get('username', SanitizeType::ONLY_ALPHA);
        $this->assertEquals('johndoe123', $result);
    }

    public function testGetHandlesEmptyQueryString(): void
    {
        $request = new Request('/search', 'GET', '', [], '127.0.0.1');

        $this->assertNull($request->get('any_key'));
    }

    // =====================================================================
    // post() method tests - Body data (JSON)
    // =====================================================================

    public function testPostReturnsValueForExistingKey(): void
    {
        $jsonData = json_encode(['username' => 'testuser', 'email' => 'test@example.com']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $this->assertEquals('testuser', $request->post('username'));
        $this->assertEquals('test@example.com', $request->post('email'));
    }

    public function testPostReturnsNullForNonExistentKey(): void
    {
        $jsonData = json_encode(['username' => 'testuser']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $this->assertNull($request->post('nonexistent'));
    }

    public function testPostRawPreservesHtmlTags(): void
    {
        $jsonData = json_encode(['content' => '<script>alert("xss")</script>Hello']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('content', SanitizeType::RAW);
        $this->assertEquals('<script>alert("xss")</script>Hello', $result);
    }

    public function testPostRawPreservesPhpTags(): void
    {
        $jsonData = json_encode(['code' => '<?php echo "test"; ?>']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('code', SanitizeType::RAW);
        $this->assertEquals('<?php echo "test"; ?>', $result);
    }

    public function testPostSafeStripsPhpTags(): void
    {
        $jsonData = json_encode(['code' => '<?php echo "hack"; ?>safe content']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('code', SanitizeType::SAFE);
        $this->assertEquals('safe content', $result);
    }

    public function testPostSafeStripsShortEchoTags(): void
    {
        $jsonData = json_encode(['code' => '<?= $var ?>visible']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('code', SanitizeType::SAFE);
        $this->assertEquals('visible', $result);
    }

    public function testPostSafeStripsNullBytes(): void
    {
        $jsonData = json_encode(['data' => "hello\x00world"]);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('data', SanitizeType::SAFE);
        $this->assertEquals('helloworld', $result);
    }

    public function testPostSafeStripsUrlEncodedNullBytes(): void
    {
        $jsonData = json_encode(['data' => 'hello%00world']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('data', SanitizeType::SAFE);
        $this->assertEquals('helloworld', $result);
    }

    public function testPostSafePreservesHtmlTags(): void
    {
        $jsonData = json_encode(['content' => '<b>bold</b>']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('content', SanitizeType::SAFE);
        $this->assertEquals('<b>bold</b>', $result);
    }

    public function testPostEncodedEncodesHtmlEntities(): void
    {
        $jsonData = json_encode(['content' => '<script>alert("xss")</script>']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('content', SanitizeType::ENCODED);
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result);
    }

    public function testPostEncodedEncodesQuotes(): void
    {
        $jsonData = json_encode(['content' => "It's a \"test\""]);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('content', SanitizeType::ENCODED);
        $this->assertEquals("It&#039;s a &quot;test&quot;", $result);
    }

    public function testPostEncodedInheritsSafeFiltering(): void
    {
        $jsonData = json_encode(['code' => '<?php echo "hack"; ?><b>bold</b>']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('code', SanitizeType::ENCODED);
        $this->assertStringNotContainsString('<?php', $result);
        $this->assertEquals('&lt;b&gt;bold&lt;/b&gt;', $result);
    }

    public function testPostSanitizedStripsHtmlTags(): void
    {
        $jsonData = json_encode(['content' => '<script>alert("xss")</script>Hello']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('content', SanitizeType::SANITIZED);
        $this->assertEquals('alert("xss")Hello', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testPostSanitizedTrimsWhitespace(): void
    {
        $jsonData = json_encode(['name' => '  John Doe  ']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('name', SanitizeType::SANITIZED);
        $this->assertEquals('John Doe', $result);
    }

    public function testPostSanitizedInheritsSafeFiltering(): void
    {
        $jsonData = json_encode(['code' => '<?php echo "hack"; ?><b>text</b>']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('code', SanitizeType::SANITIZED);
        $this->assertStringNotContainsString('<?php', $result);
        $this->assertStringNotContainsString('<b>', $result);
        $this->assertEquals('text', $result);
    }

    public function testPostOnlyAlphaRemovesSpecialCharacters(): void
    {
        $jsonData = json_encode(['username' => 'john_doe@123!']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('username', SanitizeType::ONLY_ALPHA);
        $this->assertEquals('johndoe123', $result);
    }

    public function testPostOnlyAlphaPreservesSpaces(): void
    {
        $jsonData = json_encode(['name' => 'John Doe 123']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('name', SanitizeType::ONLY_ALPHA);
        $this->assertEquals('John Doe 123', $result);
    }

    public function testPostOnlyAlphaInheritsSanitizedFiltering(): void
    {
        $jsonData = json_encode(['data' => '<?php ?><b>Hello!</b> World@2024']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $result = $request->post('data', SanitizeType::ONLY_ALPHA);
        $this->assertEquals('Hello World2024', $result);
    }

    // =====================================================================
    // post() method tests - Non-string values
    // =====================================================================

    public function testPostWithBooleanValue(): void
    {
        $jsonData = json_encode(['active' => true, 'deleted' => false]);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $this->assertTrue($request->post('active', SanitizeType::SANITIZED));
        $this->assertFalse($request->post('deleted', SanitizeType::SANITIZED));
    }

    public function testPostWithIntegerValue(): void
    {
        $jsonData = json_encode(['count' => 42, 'zero' => 0]);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $this->assertSame(42, $request->post('count', SanitizeType::ONLY_ALPHA));
        $this->assertSame(0, $request->post('zero', SanitizeType::ONLY_ALPHA));
    }

    public function testPostWithFloatValue(): void
    {
        $jsonData = json_encode(['price' => 45.67]);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $this->assertSame(45.67, $request->post('price', SanitizeType::SANITIZED));
    }

    // =====================================================================
    // Other method tests
    // =====================================================================

    public function testConstructorHandlesInvalidJsonData(): void
    {
        $request = new Request('/submit', 'POST', 'invalid json', [], '127.0.0.1');

        $this->assertNull($request->post('any_key'));
    }

    public function testConstructorHandlesEmptyJsonData(): void
    {
        $request = new Request('/submit', 'POST', '', [], '127.0.0.1');

        $this->assertNull($request->post('any_key'));
    }

    public function testGetRemoteAddressReturnsIpString(): void
    {
        $request = new Request('/home', 'GET', '', [], '192.168.1.100');

        $remoteAddress = $request->getRemoteAddress();

        $this->assertIsString($remoteAddress);
        $this->assertEquals('192.168.1.100', $remoteAddress);
    }

    public function testGetInternalIdIsHexString(): void
    {
        $request = new Request('/home', 'GET', '', [], '127.0.0.1');

        $internalId = $request->getInternalID();

        $this->assertIsString($internalId);
        $this->assertEquals(8, mb_strlen($internalId));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}$/', $internalId);
    }

    public function testGetInternalIdIsUnique(): void
    {
        $request1 = new Request('/home', 'GET', '', [], '127.0.0.1');
        $request2 = new Request('/home', 'GET', '', [], '127.0.0.1');

        $this->assertNotEquals($request1->getInternalID(), $request2->getInternalID());
    }

    public function testConstructorHandlesVariousHttpMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];

        foreach ($methods as $method) {
            $request = new Request('/endpoint', $method, '', [], '127.0.0.1');
            $this->assertEquals($method, $request->getMethod());
        }
    }

    public function testGetDefaultSanitizationIsRaw(): void
    {
        $request = new Request('/search?html=' . urlencode('<b>bold</b>'), 'GET', '', [], '127.0.0.1');

        $this->assertEquals('<b>bold</b>', $request->get('html'));
    }

    public function testPostDefaultSanitizationIsRaw(): void
    {
        $jsonData = json_encode(['html' => '<b>bold</b>']);
        $request = new Request('/submit', 'POST', $jsonData, [], '127.0.0.1');

        $this->assertEquals('<b>bold</b>', $request->post('html'));
    }

    public function testGetAndPostCanBeUsedTogether(): void
    {
        $jsonData = json_encode(['body_param' => 'from_body']);
        $request = new Request('/submit?url_param=from_url', 'POST', $jsonData, [], '127.0.0.1');

        $this->assertEquals('from_url', $request->get('url_param'));
        $this->assertEquals('from_body', $request->post('body_param'));
        $this->assertNull($request->get('body_param'));
        $this->assertNull($request->post('url_param'));
    }

    // =====================================================================
    // file() method tests - Uploaded files
    // =====================================================================

    public function testFileReturnsNullWhenNoFilesUploaded(): void
    {
        $request = new Request('/upload', 'POST', '', [], '127.0.0.1');

        $this->assertNull($request->file('avatar'));
    }

    public function testFileReturnsTmpNameForUploadedFile(): void
    {
        $files = [
            'avatar' => ['name' => 'photo.jpg', 'tmp_name' => '/tmp/phpABC123', 'type' => 'image/jpeg', 'size' => 12345, 'error' => 0],
        ];
        $request = new Request('/upload', 'POST', '', $files, '127.0.0.1');

        $file = $request->file('avatar');
        $this->assertNotNull($file);
        $this->assertEquals('/tmp/phpABC123', $file->getPath());
        $this->assertEquals('photo.jpg', $file->getName());
    }

    public function testFileReturnsNullForNonExistentFieldName(): void
    {
        $files = [
            'avatar' => ['name' => 'photo.jpg', 'tmp_name' => '/tmp/phpABC123', 'type' => 'image/jpeg', 'size' => 12345, 'error' => 0],
        ];
        $request = new Request('/upload', 'POST', '', $files, '127.0.0.1');

        $this->assertNull($request->file('document'));
    }

    public function testFileHandlesMultipleUploadedFiles(): void
    {
        $files = [
            'avatar' => ['name' => 'photo.jpg', 'tmp_name' => '/tmp/phpABC123', 'type' => 'image/jpeg', 'size' => 12345, 'error' => 0],
            'document' => ['name' => 'doc.pdf', 'tmp_name' => '/tmp/phpDEF456', 'type' => 'application/pdf', 'size' => 54321, 'error' => 0],
        ];
        $request = new Request('/upload', 'POST', '', $files, '127.0.0.1');

        $avatar = $request->file('avatar');
        $document = $request->file('document');
        $this->assertNotNull($avatar);
        $this->assertNotNull($document);
        $this->assertEquals('/tmp/phpABC123', $avatar->getPath());
        $this->assertEquals('/tmp/phpDEF456', $document->getPath());
    }

    // =====================================================================
    // Language preferences
    // =====================================================================

    public function testPreferredLanguagesOrderedByQuality(): void
    {
        $request = new Request('/', 'GET', '', [], '127.0.0.1', 'fr-CH, fr;q=0.9, en;q=0.8, de;q=0.7, *;q=0.5');

        $this->assertEquals(['fr', 'en', 'de', '*'], $request->getPreferredLanguages());
    }

    public function testPreferredLanguagesCookieComesFirst(): void
    {
        $request = new Request('/', 'GET', '', [], '127.0.0.1', 'en-US,en;q=0.9', 'ES');

        $this->assertEquals(['es', 'en'], $request->getPreferredLanguages());
    }

    public function testPreferredLanguagesEmptyWhenNothingProvided(): void
    {
        $request = new Request('/', 'GET', '', [], '127.0.0.1');

        $this->assertEquals([], $request->getPreferredLanguages());
    }

    public function testPostIgnoresNonArrayJsonBody(): void
    {
        $request = new Request('/submit', 'POST', '"just a string"', [], '127.0.0.1');
        $this->assertNull($request->post('anything'));

        $request = new Request('/submit', 'POST', '42', [], '127.0.0.1');
        $this->assertNull($request->post('anything'));
    }
}
