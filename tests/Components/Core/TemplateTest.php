<?php

namespace FastRaven\Tests\Components\Core;

use PHPUnit\Framework\TestCase;
use FastRaven\Components\Core\Template;
use FastRaven\Components\Data\Map;
use FastRaven\Components\Data\Pair;

class TemplateTest extends TestCase
{
    public function testNewCreatesTemplateWithBasicParameters(): void
    {
        $template = Template::new('main.php', 'My Page', '1.0.0');

        $this->assertEquals('main.php', $template->getFile());
        $this->assertEquals('My Page', $template->getTitle());
        $this->assertEquals('1.0.0', $template->getVersion());
    }

    public function testNewWithEmptyVersion(): void
    {
        $template = Template::new('page.php', 'Page Title');

        $this->assertEquals('page.php', $template->getFile());
        $this->assertEquals('Page Title', $template->getTitle());
        $this->assertEquals('', $template->getVersion());
    }

    public function testSetFileAndGetFile(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');

        $template->setFile('other.php');

        $this->assertEquals('other.php', $template->getFile());
    }

    public function testSetFaviconSetsBothLightAndDark(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');

        $template->setFavicon('icon.png');

        $this->assertEquals('icon.png', $template->getFaviconLight());
        $this->assertEquals('icon.png', $template->getFaviconDark());
    }

    public function testSetFaviconLightAndDarkSeparately(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');

        $template->setFaviconLight('light-icon.png');
        $template->setFaviconDark('dark-icon.png');

        $this->assertEquals('light-icon.png', $template->getFaviconLight());
        $this->assertEquals('dark-icon.png', $template->getFaviconDark());
    }

    public function testAddStyleAppendsToStylesArray(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');

        $template->addStyle('style1.css');
        $template->addStyle('style2.css');

        $this->assertEquals(['style1.css', 'style2.css'], $template->getStyles());
    }

    public function testAddScriptAppendsToScriptsArray(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');

        $template->addScript('script1.js');
        $template->addScript('script2.js');

        $this->assertEquals(['script1.js', 'script2.js'], $template->getScripts());
    }

    public function testAddDataAndGetData(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');

        $template->addData("username", "John");
        $template->addData("email", "john@example.com");

        $this->assertTrue($template->hasData('username'));
        $this->assertTrue($template->hasData('email'));
        $this->assertFalse($template->hasData('nonexistent'));

        $this->assertEquals('John', $template->getData('username'));
        $this->assertEquals('john@example.com', $template->getData('email'));
        $this->assertEquals('', $template->getData('nonexistent'));
    }

    public function testMergeOverwritesTitleVersionFile(): void
    {
        $template1 = Template::new('original.php', 'Original', '1.0');
        $template2 = Template::new('updated.php', 'Updated', '2.0');

        $template1->merge($template2);

        $this->assertEquals('updated.php', $template1->getFile());
        $this->assertEquals('Updated', $template1->getTitle());
        $this->assertEquals('2.0', $template1->getVersion());
    }

    public function testMergeKeepsOriginalIfNewIsEmpty(): void
    {
        $template1 = Template::new('original.php', 'Original', '1.0');
        $template2 = Template::new('', '', '');

        $template1->merge($template2);

        $this->assertEquals('original.php', $template1->getFile());
        $this->assertEquals('Original', $template1->getTitle());
        $this->assertEquals('1.0', $template1->getVersion());
    }

    public function testMergeCombinesStylesAndScripts(): void
    {
        $template1 = Template::new('main.php', 'Page', '1.0');
        $template1->addStyle('style1.css');
        $template1->addScript('script1.js');

        $template2 = Template::new('', '', '');
        $template2->addStyle('style2.css');
        $template2->addScript('script2.js');

        $template1->merge($template2);

        $this->assertEquals(['style1.css', 'style2.css'], $template1->getStyles());
        $this->assertEquals(['script1.js', 'script2.js'], $template1->getScripts());
    }

    public function testMergeCombinesData(): void
    {
        $template1 = Template::new('main.php', 'Page', '1.0');
        $template1->addData("field1", "value1");

        $template2 = Template::new('', '', '');
        $template2->addData("field2", "value2");

        $template1->merge($template2);

        $this->assertTrue($template1->hasData('field1'));
        $this->assertTrue($template1->hasData('field2'));
        $this->assertEquals('value1', $template1->getData('field1'));
        $this->assertEquals('value2', $template1->getData('field2'));
    }

    public function testMergeCombinesFavicons(): void
    {
        $template1 = Template::new('main.php', 'Page', '1.0');
        $template1->setFavicon('original.png');

        $template2 = Template::new('', '', '');
        $template2->setFavicon('updated.png');

        $template1->merge($template2);

        $this->assertEquals('updated.png', $template1->getFaviconLight());
        $this->assertEquals('updated.png', $template1->getFaviconDark());
    }

    public function testGetHtmlTitleGeneratesCorrectTag(): void
    {
        $template = Template::new('main.php', 'My Page Title', '1.0');

        $html = $template->getHtmlTitle();

        $this->assertEquals('<title>My Page Title</title>', $html);
    }

    public function testGetHtmlFaviconGeneratesCorrectTags(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');
        $template->setFavicon('icon.png');

        $html = $template->getHtmlFavicon();

        $this->assertStringContainsString('rel="icon"', $html);
        $this->assertStringContainsString('icon.png', $html);
        $this->assertStringContainsString('prefers-color-scheme: light', $html);
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
    }

    public function testGetHtmlStylesGeneratesLinkTags(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');
        $template->addStyle('main.css');
        $template->addStyle('theme.css');

        $html = $template->getHtmlStyles();

        $this->assertStringContainsString('<link rel="stylesheet" href="/public/assets/css/main.css?v=1.0">', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="/public/assets/css/theme.css?v=1.0">', $html);
    }

    public function testGetHtmlScriptsGeneratesScriptTags(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');
        $template->addScript('app.js');
        $template->addScript('utils.js');

        $html = $template->getHtmlScripts();

        $this->assertStringContainsString('<script src="/public/assets/js/app.js?v=1.0" type="text/javascript" nonce=""></script>', $html);
        $this->assertStringContainsString('<script src="/public/assets/js/utils.js?v=1.0" type="text/javascript" nonce=""></script>', $html);
    }

    public function testVersionInResourceUrls(): void
    {
        $template = Template::new('main.php', 'Test', '3.2.1');
        $template->addStyle('style.css');
        $template->addScript('script.js');

        $styles = $template->getHtmlStyles();
        $scripts = $template->getHtmlScripts();

        $this->assertStringContainsString('?v=3.2.1', $styles);
        $this->assertStringContainsString('?v=3.2.1', $scripts);
    }

    public function testSetBeforeFragmentsAndGetBeforeFragments(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');
        $files = ['header.php', 'nav.php'];

        $template->setBeforeFragments($files);

        $this->assertEquals($files, $template->getBeforeFragments());
    }

    public function testSetAfterFragmentsAndGetAfterFragments(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');
        $files = ['footer.php', 'analytics.php'];

        $template->setAfterFragments($files);

        $this->assertEquals($files, $template->getAfterFragments());
    }

    public function testMethodChaining(): void
    {
        $template = Template::new('main.php', 'Test', '1.0')
            ->setTitle('New Title')
            ->setFile('new.php')
            ->setFavicon('icon.png')
            ->addStyle('style.css')
            ->addScript('script.js')
            ->setBeforeFragments(['header.php'])
            ->setAfterFragments(['footer.php'])
            ->addData("key", "value");

        $this->assertEquals('New Title', $template->getTitle());
        $this->assertEquals('new.php', $template->getFile());
        $this->assertEquals('icon.png', $template->getFaviconLight());
        $this->assertEquals(['style.css'], $template->getStyles());
        $this->assertEquals(['script.js'], $template->getScripts());
        $this->assertEquals(['header.php'], $template->getBeforeFragments());
        $this->assertEquals(['footer.php'], $template->getAfterFragments());
        $this->assertEquals('value', $template->getData('key'));
    }

    public function testAddHeadAndAddMeta(): void
    {
        $template = Template::new('main.php', 'Test', '1.0');
        $template->addHead('<link rel="canonical" href="https://example.com/">');
        $template->addMeta('description', 'A "quoted" <desc>');

        $this->assertCount(2, $template->getHead());
        $this->assertEquals(
            '<link rel="canonical" href="https://example.com/"><meta name="description" content="A &quot;quoted&quot; &lt;desc&gt;">',
            $template->getHtmlHead()
        );
    }

    public function testMergeCombinesHeadAndLang(): void
    {
        $template1 = Template::new('main.php', 'Page', '1.0')->addHead('<meta name="a" content="1">');
        $template2 = Template::new('', '', '')->addHead('<meta name="b" content="2">')->setLang('es');

        $template1->merge($template2);

        $this->assertEquals(['<meta name="a" content="1">', '<meta name="b" content="2">'], $template1->getHead());
        $this->assertEquals('es', $template1->getLang());
    }

    public function testGetLangFallsBackToDefaultLang(): void
    {
        $template = Template::new('main.php', 'Test')->setDefaultLang('fr');

        $this->assertEquals('fr', $template->getLang());
        $this->assertEquals('de', $template->setLang('de')->getLang());
    }

    public function testResolveLangKeepsExplicitLang(): void
    {
        $template = Template::new('main.php', 'Test')->setLang('de');

        $template->resolveLang(['es', 'en']);

        $this->assertEquals('de', $template->getLang());
    }

    public function testResolveLangFallsBackToDefaultWhenNothingAvailable(): void
    {
        // No SITE_PATH language file exists in tests, so no language is available.
        if (!defined('SITE_PATH')) define('SITE_PATH', sys_get_temp_dir() . '/');
        $template = Template::new('main.php', 'Test')->setDefaultLang('en');

        $template->resolveLang(['es']);

        $this->assertEquals('en', $template->getLang());
        $this->assertEquals('MISSING_KEY', $template->lang('MISSING_KEY'));
    }
}
