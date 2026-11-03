<?php

namespace FastRaven\Tests\Components\Core;

use PHPUnit\Framework\TestCase;
use FastRaven\Components\Core\Mail;
use FastRaven\Components\Data\Pair;
use FastRaven\Components\Data\Map;

class MailTest extends TestCase
{
    private Pair $origin;
    private Pair $destination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->origin = Pair::new("Sender Name", "sender@example.com");
        $this->destination = Pair::new("Recipient Name", "recipient@example.com");
    }

    public function testNewCreatesMailInstance(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "test/template.html"
        );

        $this->assertInstanceOf(Mail::class, $mail);
    }

    public function testGetOriginReturnsCorrectValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "test/template.html"
        );

        $this->assertEquals($this->origin, $mail->getOrigin());
    }

    public function testSetOriginUpdatesValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "test/template.html"
        );

        $newOrigin = Pair::new("New Sender", "newsender@example.com");
        $mail->setOrigin($newOrigin);

        $this->assertEquals($newOrigin, $mail->getOrigin());
    }

    public function testGetDestinationReturnsCorrectValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "test/template.html"
        );

        $this->assertEquals($this->destination, $mail->getDestination());
    }

    public function testSetDestinationUpdatesValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "test/template.html"
        );

        $newDestination = Pair::new("New Recipient", "newrecipient@example.com");
        $mail->setDestination($newDestination);

        $this->assertEquals($newDestination, $mail->getDestination());
    }

    public function testGetSubjectReturnsCorrectValue(): void
    {
        $subject = "Test Subject Line";
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            $subject,
            "test/template.html"
        );

        $this->assertEquals($subject, $mail->getSubject());
    }

    public function testSetSubjectUpdatesValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Original Subject",
            "test/template.html"
        );

        $newSubject = "Updated Subject";
        $mail->setSubject($newSubject);

        $this->assertEquals($newSubject, $mail->getSubject());
    }

    public function testGetBodyFileReturnsCorrectValue(): void
    {
        $bodyFile = "emails/welcome.html";
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            $bodyFile
        );

        $this->assertEquals($bodyFile, $mail->getBodyFile());
    }

    public function testSetBodyFileUpdatesValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "original/template.html"
        );

        $newBodyFile = "updated/template.html";
        $mail->setBodyFile($newBodyFile);

        $this->assertEquals($newBodyFile, $mail->getBodyFile());
    }

    public function testSetBccMailsUpdatesValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "test/template.html"
        );

        $bccList = Map::new([
            "BCC User 1" => "bcc1@example.com",
            "BCC User 2" => "bcc2@example.com"
        ]);

        $mail->setBccMails($bccList);

        $this->assertEquals($bccList, $mail->getBccMails());
        $this->assertCount(2, $mail->getBccMails()->getAllKeys());
    }

    public function testSetReplaceValuesUpdatesValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "test/template.html"
        );

        $replacements = Map::new([
            "{{NAME}}" => "John",
            "{{LINK}}" => "https://example.com"
        ]);

        $mail->setReplaceValues($replacements);

        $this->assertEquals($replacements, $mail->getReplaceValues());
        $this->assertCount(2, $mail->getReplaceValues()->getAllKeys());
    }

    public function testSetAttachmentsUpdatesValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "test/template.html"
        );

        $attachments = Map::new([
            "document.pdf" => "files/document.pdf",
            "image.jpg" => "images/image.jpg"
        ]);

        $mail->setAttachments($attachments);

        $this->assertEquals($attachments, $mail->getAttachments());
        $this->assertCount(2, $mail->getAttachments()->getAllKeys());
    }

    public function testGetTimeoutReturnsDefaultValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "test/template.html"
        );

        $this->assertEquals(3000, $mail->getTimeout());
    }

    public function testSetTimeoutUpdatesValue(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "Test Subject",
            "test/template.html"
        );

        $newTimeout = 5000;
        $mail->setTimeout($newTimeout);

        $this->assertEquals($newTimeout, $mail->getTimeout());
    }

    public function testMailHandlesEmptySubject(): void
    {
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            "",
            "test/template.html"
        );

        $this->assertEquals("", $mail->getSubject());
    }

    public function testMailHandlesSpecialCharactersInSubject(): void
    {
        $subject = "Test &lt;> Subject with 'special' \"characters\"";
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            $subject,
            "test/template.html"
        );

        $this->assertEquals($subject, $mail->getSubject());
    }

    public function testMailHandlesUnicodeCharactersInSubject(): void
    {
        $subject = "Test Subject 中文 العربية 🔥";
        $mail = Mail::new(
            $this->origin,
            $this->destination,
            $subject,
            "test/template.html"
        );

        $this->assertEquals($subject, $mail->getSubject());
    }

    public function testSetLangStoresLanguageAndFile(): void
    {
        $mail = Mail::new(Pair::mail('A', 'a@x.com'), Pair::mail('B', 'b@x.com'), 'Subject', 'welcome.php');

        $this->assertEquals('', $mail->getLang());
        $this->assertEquals('global', $mail->getLangFile());

        $mail->setLang('es', 'mails');

        $this->assertEquals('es', $mail->getLang());
        $this->assertEquals('mails', $mail->getLangFile());
    }
}
