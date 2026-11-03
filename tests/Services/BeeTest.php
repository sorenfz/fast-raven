<?php

namespace FastRaven\Tests\Services;

use PHPUnit\Framework\TestCase;
use FastRaven\Bee;

class BeeTest extends TestCase
{
    private array $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        // Store original environment variables
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        // Restore original environment variables
        $_ENV = $this->originalEnv;
        parent::tearDown();
    }

    public function testEnvReturnsEnvironmentVariableValue(): void
    {
        $_ENV['TEST_VAR'] = 'test_value';

        $result = Bee::env('TEST_VAR');

        $this->assertEquals('test_value', $result);
    }

    public function testEnvReturnsDefaultWhenVariableDoesNotExist(): void
    {
        unset($_ENV['NON_EXISTENT_VAR']);

        $result = Bee::env('NON_EXISTENT_VAR', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function testEnvReturnsEmptyStringWhenVariableDoesNotExistAndNoDefault(): void
    {
        unset($_ENV['NON_EXISTENT_VAR']);

        $result = Bee::env('NON_EXISTENT_VAR');

        $this->assertEquals('', $result);
    }

    public function testIsDevReturnsTrueWhenStateIsDev(): void
    {
        $_ENV['STATE'] = 'dev';

        $result = Bee::isDev();

        $this->assertTrue($result);
    }

    public function testIsDevReturnsFalseWhenStateIsNotDev(): void
    {
        $_ENV['STATE'] = 'prod';

        $result = Bee::isDev();

        $this->assertFalse($result);
    }

    public function testIsDevReturnsFalseWhenStateIsNotSet(): void
    {
        unset($_ENV['STATE']);

        $result = Bee::isDev();

        $this->assertFalse($result);
    }

    /**
     * @dataProvider normalizePathProvider
     */
    public function testNormalizePathRemovesLeadingAndTrailingSlashes(string $input, string $expected): void
    {
        $result = Bee::normalizePath($input);

        $this->assertEquals($expected, $result);
    }

    public static function normalizePathProvider(): array
    {
        return [
            // Basic normalization
            'leading slash' => ['/path/to/file', 'path/to/file'],
            'trailing slash' => ['path/to/file/', 'path/to/file'],
            'both slashes' => ['/path/to/file/', 'path/to/file'],
            'no slashes' => ['path/to/file', 'path/to/file'],
            'multiple leading slashes' => ['///path/to/file', 'path/to/file'],
            'multiple trailing slashes' => ['path/to/file///', 'path/to/file'],
            'multiple consecutive slashes' => ['path//to///file', 'path/to/file'],

            // Backslash handling
            'backslashes' => ['\\path\\to\\file\\', 'path/to/file'],
            'mixed slashes' => ['/path\\to/file\\', 'path/to/file'],
            'leading backslash' => ['\\path/to/file', 'path/to/file'],
            'trailing backslash' => ['path/to/file\\', 'path/to/file'],

            // Security: Path traversal prevention
            'parent directory traversal' => ['path/../to/file', 'path/to/file'],
            'multiple parent traversal' => ['path/../../to/file', 'path/to/file'],
            'parent at start' => ['../path/to/file', 'path/to/file'],
            'parent at end' => ['path/to/file/..', 'path/to/file'],
            'current directory' => ['path/./to/./file', 'path/to/file'],
            'mixed traversal' => ['path/../to/./file', 'path/to/file'],
            'complex traversal' => ['/path/../.././to/../file', 'path/to/file'],

            // Security: Null byte injection prevention
            'null byte in path' => ["path\0/to/file", 'path/to/file'],
            'null byte at end' => ["path/to/file\0", 'path/to/file'],

            // Edge cases
            'empty string' => ['', ''],
            'single slash' => ['/', ''],
            'single backslash' => ['\\', ''],
            'only dots' => ['...', '...'],
            'dots in filename' => ['path/to/file.txt', 'path/to/file.txt'],
        ];
    }

    public function testGetBaseDomainReturnsCorrectBaseDomain(): void
    {
        $_ENV['SITE_ADDRESS'] = 'sub.example.com';
        $this->assertEquals('example.com', Bee::getBaseDomain());

        $_ENV['SITE_ADDRESS'] = 'example.com';
        $this->assertEquals('example.com', Bee::getBaseDomain());

        $_ENV['SITE_ADDRESS'] = 'localhost';
        $this->assertEquals('localhost', Bee::getBaseDomain());

        $_ENV['SITE_ADDRESS'] = 'deep.subdomain.example.co.uk';
        $this->assertEquals('example.co.uk', Bee::getBaseDomain());
        
        unset($_ENV['SITE_ADDRESS']);
    }

    public function testGetBaseDomainReturnsLocalhostWhenNotSet(): void
    {
        unset($_ENV['SITE_ADDRESS']);

        $result = Bee::getBaseDomain();

        $this->assertEquals('localhost', $result);
    }

    public function testGetBuiltDomainReturnsCorrectBuiltDomain(): void
    {
        $_ENV['SITE_ADDRESS'] = 'example.com';

        // Test with subdomain
        $resultWithSubdomain = Bee::getBuiltDomain('sub');
        $this->assertEquals('sub.example.com', $resultWithSubdomain);

        // Test without subdomain
        $resultWithoutSubdomain = Bee::getBuiltDomain();
        $this->assertEquals('example.com', $resultWithoutSubdomain);
        
        // Clean up
        unset($_ENV['SITE_ADDRESS']);
    }

    public function testHashPasswordReturnsNonEmptyString(): void
    {
        $password = 'mySecurePassword123';
        
        $hash = Bee::hashPassword($password);
        
        $this->assertNotEmpty($hash);
        $this->assertIsString($hash);
    }

    public function testHashPasswordGeneratesDifferentHashesForSamePassword(): void
    {
        $password = 'mySecurePassword123';
        
        $hash1 = Bee::hashPassword($password);
        $hash2 = Bee::hashPassword($password);
        
        // Each hash should be unique due to random salt
        $this->assertNotEquals($hash1, $hash2);
    }

    public function testHashPasswordCanBeVerifiedWithPasswordVerify(): void
    {
        $password = 'mySecurePassword123';
        
        $hash = Bee::hashPassword($password);
        
        // Verify the password matches the hash
        $this->assertTrue(password_verify($password, $hash));
        
        // Verify wrong password doesn't match
        $this->assertFalse(password_verify('wrongPassword', $hash));
    }

    public function testHashPasswordUsesArgon2IDAlgorithm(): void
    {
        $password = 'mySecurePassword123';
        
        $hash = Bee::hashPassword($password);
        
        // Argon2ID hashes start with $argon2id$
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function testHashPasswordHandlesEmptyPassword(): void
    {
        $password = '';
        
        $hash = Bee::hashPassword($password);
        
        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testHashPasswordHandlesSpecialCharacters(): void
    {
        $password = '!@#$%^&*()_+-=[]{}|;:\'",.<>?/~`';
        
        $hash = Bee::hashPassword($password);
        
        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testHashPasswordHandlesUnicodeCharacters(): void
    {
        $password = 'пароль密码🔒';
        
        $hash = Bee::hashPassword($password);
        
        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testHashPasswordHandlesLongPassword(): void
    {
        // Create a very long password (1000 characters)
        $password = str_repeat('a', 1000);
        
        $hash = Bee::hashPassword($password);
        
        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    // =====================================================================
    // getFileMimeType() tests
    // =====================================================================

    public function testGetFileMimeTypeReturnsOctetStreamForNonExistentFile(): void
    {
        $result = Bee::getFileMimeType('/nonexistent/file.txt');

        $this->assertEquals('application/octet-stream', $result);
    }

    public function testGetFileMimeTypeReturnsCorrectMimeForTextFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.txt';
        file_put_contents($tmpFile, 'Hello, World!');

        $result = Bee::getFileMimeType($tmpFile);

        $this->assertEquals('text/plain', $result);
        unlink($tmpFile);
    }

    public function testGetFileMimeTypeReturnsCorrectMimeForJsonFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.json';
        file_put_contents($tmpFile, '{"key": "value"}');

        $result = Bee::getFileMimeType($tmpFile);

        // JSON is detected as text/plain or application/json depending on finfo version
        $this->assertTrue(in_array($result, ['text/plain', 'application/json']), "Expected text/plain or application/json, got: $result");
        unlink($tmpFile);
    }

    public function testGetFileMimeTypeReturnsCorrectMimeForPngFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.png';
        // Minimal valid PNG: signature + IHDR chunk + IEND chunk
        $png = hex2bin('89504E470D0A1A0A')  // PNG signature
             . hex2bin('0000000D')           // IHDR length
             . 'IHDR'
             . hex2bin('00000001')           // width: 1
             . hex2bin('00000001')           // height: 1
             . hex2bin('08')                 // bit depth: 8
             . hex2bin('02')                 // color type: RGB
             . hex2bin('000000')             // compression, filter, interlace
             . hex2bin('907753DE')           // CRC
             . hex2bin('00000000')           // IEND length
             . 'IEND'
             . hex2bin('AE426082');          // IEND CRC
        file_put_contents($tmpFile, $png);

        $result = Bee::getFileMimeType($tmpFile);

        $this->assertEquals('image/png', $result);
        unlink($tmpFile);
    }

    public function testGetFileMimeTypeReturnsCorrectMimeForJpegFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.jpg';
        // JPEG magic bytes: FF D8 FF
        file_put_contents($tmpFile, hex2bin('FFD8FFE0') . str_repeat("\0", 100));

        $result = Bee::getFileMimeType($tmpFile);

        $this->assertEquals('image/jpeg', $result);
        unlink($tmpFile);
    }

    public function testGetFileMimeTypeReturnsCorrectMimeForPdfFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.pdf';
        // PDF magic bytes: %PDF
        file_put_contents($tmpFile, '%PDF-1.4' . str_repeat("\0", 100));

        $result = Bee::getFileMimeType($tmpFile);

        $this->assertEquals('application/pdf', $result);
        unlink($tmpFile);
    }

    public function testGetFileMimeTypeDetectsByContentNotExtension(): void
    {
        // Create a file with .txt extension but PDF content
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.txt';
        file_put_contents($tmpFile, '%PDF-1.4' . str_repeat("\0", 100));

        $result = Bee::getFileMimeType($tmpFile);

        // Should detect as PDF by magic bytes, not text by extension
        $this->assertEquals('application/pdf', $result);
        unlink($tmpFile);
    }

    public function testGetFileMimeTypeReturnsCorrectMimeForPhpFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.php';
        file_put_contents($tmpFile, '<?php echo "Hello"; ?>');

        $result = Bee::getFileMimeType($tmpFile);

        // PHP files are detected as text/x-php or text/plain
        $this->assertTrue(in_array($result, ['text/x-php', 'text/plain']), "Expected text/x-php or text/plain, got: $result");
        unlink($tmpFile);
    }

    public function testGetFileMimeTypeReturnsCorrectMimeForExeFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.exe';
        // Windows EXE magic bytes: MZ (4D 5A)
        file_put_contents($tmpFile, hex2bin('4D5A') . str_repeat("\0", 100));

        $result = Bee::getFileMimeType($tmpFile);

        // EXE files are detected as application/x-dosexec or application/x-executable or application/octet-stream
        $allowed = ['application/x-dosexec', 'application/x-executable', 'application/octet-stream', 'application/x-ms-dos-executable'];
        $this->assertTrue(in_array($result, $allowed), "Expected EXE MIME type, got: $result");
        unlink($tmpFile);
    }

    public function testGetFileMimeTypeReturnsCorrectMimeForElfFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.bin';
        // ELF (Linux executable) magic bytes: 7F 45 4C 46 (.ELF)
        file_put_contents($tmpFile, hex2bin('7F454C46') . str_repeat("\0", 100));

        $result = Bee::getFileMimeType($tmpFile);

        // ELF files are detected as application/x-executable or application/x-sharedlib or application/octet-stream
        // Note: Minimal ELF magic bytes may be detected as text/plain on some systems
        $allowed = ['application/x-executable', 'application/x-sharedlib', 'application/x-elf', 'application/octet-stream', 'text/plain'];
        $this->assertTrue(in_array($result, $allowed), "Expected ELF MIME type, got: $result");
        unlink($tmpFile);
    }

    public function testGetFileMimeTypeDetectsPhpInDisguise(): void
    {
        // Security test: PHP code hidden in a .jpg file
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.jpg';
        file_put_contents($tmpFile, '<?php phpinfo(); ?>');

        $result = Bee::getFileMimeType($tmpFile);

        // Despite .jpg extension, finfo should detect it as text/x-php or text/plain
        $this->assertTrue(in_array($result, ['text/x-php', 'text/plain']), "Expected text/x-php or text/plain, got: $result");
        unlink($tmpFile);
    }

    // =====================================================================
    // buildProjectPath() tests
    // =====================================================================

    protected function setUpSitePath(): void
    {
        if (!defined('SITE_PATH')) {
            define('SITE_PATH', DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR);
        }
    }

    public function testBuildProjectPathReturnsCorrectPath(): void
    {
        $this->setUpSitePath();

        $result = Bee::buildProjectPath(\FastRaven\Types\ProjectFolderType::CONFIG, 'config.php');

        $this->assertStringContainsString('config', $result);
        $this->assertStringEndsWith('config.php', $result);
    }

    public function testBuildProjectPathWithEmptyFile(): void
    {
        $this->setUpSitePath();

        $result = Bee::buildProjectPath(\FastRaven\Types\ProjectFolderType::STORAGE_CACHE);

        $this->assertStringContainsString('storage', $result);
        $this->assertStringContainsString('cache', $result);
    }

    public function testBuildProjectPathNormalizesFilePath(): void
    {
        $this->setUpSitePath();

        $result = Bee::buildProjectPath(\FastRaven\Types\ProjectFolderType::SRC_API, '../../../etc/passwd');

        // Path traversal (..) should be stripped, but the remaining path stays
        // Result: /test/site/src/api/etc/passwd (not /etc/passwd)
        $this->assertStringNotContainsString('..', $result);
        $this->assertStringContainsString('src', $result);  // Still within project
        $this->assertStringStartsWith(SITE_PATH, $result);  // Confined to SITE_PATH
    }

    public function testBuildProjectPathHandlesNullBytes(): void
    {
        $this->setUpSitePath();

        $result = Bee::buildProjectPath(\FastRaven\Types\ProjectFolderType::SRC_API, "test\0.php");

        // Null bytes should be stripped
        $this->assertStringNotContainsString("\0", $result);
    }

    // =====================================================================
    // validateCallable() tests
    // =====================================================================

    public function testValidateCallableReturnsTrueForValidCallable(): void
    {
        $callable = function(string $param1, int $param2): bool {
            return true;
        };

        $result = Bee::validateCallable($callable, ['string', 'int']);

        $this->assertTrue($result);
    }

    public function testValidateCallableReturnsFalseForInvalidSignature(): void
    {
        $callable = function(string $param1): bool {
            return true;
        };

        $result = Bee::validateCallable($callable, ['int']);

        $this->assertFalse($result);
    }

    public function testValidateCallableReturnsFalseForNull(): void
    {
        $result = Bee::validateCallable(null, []);

        $this->assertFalse($result);
    }

    public function testValidateCallableReturnsTrueForEmptyParams(): void
    {
        $callable = function(): bool {
            return true;
        };

        $result = Bee::validateCallable($callable, []);

        $this->assertTrue($result);
    }

    public function testValidateCallableWithUntypedParameters(): void
    {
        $callable = function($param1, $param2): bool {
            return true;
        };

        // Untyped parameters should pass any expected type
        $result = Bee::validateCallable($callable, ['string', 'int']);

        $this->assertTrue($result);
    }

    public function testValidateCallableWithClassTypes(): void
    {
        $callable = function(\FastRaven\Components\Http\Request $request): bool {
            return true;
        };

        $result = Bee::validateCallable($callable, ['FastRaven\Components\Http\Request']);

        $this->assertTrue($result);
    }

    public function testValidateCallableWithMismatchedClassTypes(): void
    {
        $callable = function(\FastRaven\Components\Http\Response $response): bool {
            return true;
        };

        $result = Bee::validateCallable($callable, ['FastRaven\Components\Http\Request']);

        $this->assertFalse($result);
    }

    public function testGetBaseDomainHandlesCommonPublicSuffixes(): void
    {
        foreach (["shop.example.com.au" => "example.com.au", "a.b.example.ac.uk" => "example.ac.uk", "app.google.de" => "google.de", "www.example.io" => "example.io", "fastraven.loc" => "fastraven.loc"] as $host => $expected) {
            $_ENV['SITE_ADDRESS'] = $host;
            $this->assertEquals($expected, Bee::getBaseDomain(), $host);
        }
    }
}
