<?php

namespace FastRaven\Internals\Engines;

use FastRaven\Services\MailService;
use FastRaven\Services\LogService;

use FastRaven\Components\Core\Mail;
use FastRaven\Components\Data\Map;
use FastRaven\Components\Data\Pair;

use FastRaven\Types\ProjectFolderType;

use FastRaven\Bee;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * @internal
 */
final class MailEngine {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private static bool $ready = false;

    private ?PHPMailer $mailer = null;
    private array $deferredMails = [];

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    /**
     * Initializes the MailEngine if it is not already busy.
     * 
     * This function will create a new MailEngine if it is not already busy.
     * It will then call MailService::__getToWork() and pass the new MailEngine object.
     * The new MailEngine object will be returned.
     * 
     * @return ?MailEngine The MailEngine object if it was successfully created, null otherwise.
     */
    public static function zap(): ?MailEngine {
        if(!self::$ready) {
            self::$ready = true;
            $inst = new MailEngine();
            MailService::__getToWork($inst);

            return $inst;
        }
        
        return null;
    }

    private function __construct() {

    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS

    /**
     * Retrieves the email template content from a file in the views directory.
     *
     * @param string $file The path to the template file (relative to src/views/).
     *
     * @return ?string The template content if the file exists, null otherwise.
     */
    private function getMailTemplate(string $file): ?string {
        $path = realpath(Bee::buildProjectPath(ProjectFolderType::SRC_WEB_TEMPLATES_MAILS, $file));
        
        if($path !== false) return file_get_contents($path);
        else return null;
    }

    /**
     * Configures the basic SMTP settings for the PHPMailer instance.
     *
     * @param int $timeout The timeout in milliseconds for the SMTP connection (default: 3000).
     */
    private function setMailerBasic(int $timeout = 3000): void {
        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->clearCustomHeaders();
        $this->mailer->clearReplyTos();

        $this->mailer->isSMTP();
        $this->mailer->SMTPAuth = true;
        $this->mailer->SMTPSecure = Bee::env("SMTP_ENCRYPTION", PHPMailer::ENCRYPTION_STARTTLS);
        $this->mailer->Host = Bee::env("SMTP_HOST", "smtp.notvalid.com");
        $this->mailer->Username = Bee::env("SMTP_USER", "notvalid");
        $this->mailer->Password = Bee::env("SMTP_PASS", "notvalid");
        $this->mailer->Port = Bee::env("SMTP_PORT", 587);
        $this->mailer->Timeout = $timeout;
        $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;
    }

    /**
     * Sets the sender, recipient, and BCC addresses for the PHPMailer instance.
     *
     * @param Pair $origin The sender's email information (key: name, value: email address).
     * @param Pair $destination The recipient's email information (key: name, value: email address).
     * @param ?Map $bccMails Optional collection of BCC email addresses (key: name, value: email address).
     */
    private function setMailerAddress(Pair $origin, Pair $destination, ?Map $bccMails): void {
        $this->mailer->setFrom($origin->getValue(), $origin->getKey());
        $this->mailer->addAddress($destination->getValue(), $destination->getKey());

        if($bccMails) {
            foreach($bccMails->getRawData() as $bcc) {
                $this->mailer->addBCC($bcc->getValue(), $bcc->getKey());
            }
        }
    }

    /**
     * Sets the email subject and body for the PHPMailer instance, applying replace values and translations.
     *
     * @param string $template The HTML template content.
     * @param Mail $mail The mail holding the subject, replace values and language.
     */
    private function setMailerBody(string $template, Mail $mail): void {
        $subject = $mail->getSubject();
        $template = str_replace($mail->getReplaceValues()->getAllKeys(), $mail->getReplaceValues()->getAllValues(), $template);

        if($mail->getLang() !== "") {
            $translate = fn($m) => Bee::lang($m[1], $mail->getLang(), $mail->getLangFile());
            $subject = preg_replace_callback('/\{\{([A-Za-z0-9_]+)\}\}/', $translate, $subject);
            $template = preg_replace_callback('/\{\{([A-Za-z0-9_]+)\}\}/', $translate, $template);
        }

        $this->mailer->isHTML(true);
        $this->mailer->Subject = $subject;
        $this->mailer->Body = $template;
    }

    /**
     * Adds attachments to the PHPMailer instance from the storage/uploads directory.
     *
     * @param ?Map $attachments Optional collection of attachments (key: display name, value: file path relative to src/assets/).
     */
    private function setMailerAttachments(?Map $attachments): void {
        if($attachments) {
            foreach($attachments->getRawData() as $attachment) {
                $path = realpath(Bee::buildProjectPath(ProjectFolderType::STORAGE_UPLOADS, $attachment->getValue()));
                
                if($path !== false) $this->mailer->addAttachment($path, $attachment->getKey());
                else LogService::error("Attachment not found: " . $attachment->getValue());
            }
        }
    }

    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS

    /**
     * Sends an email using the provided Mail configuration.
     *
     * This function retrieves the email template, configures PHPMailer with the Mail settings,
     * and attempts to send the email. Any errors are logged via LogService.
     *
     * @param Mail $mail The Mail instance containing email configuration.
     *
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function send(Mail $mail): bool {
        $template = $this->getMailTemplate($mail->getBodyFile());
        if(!$template) return false;
        
        if($this->mailer === null) $this->mailer = new PHPMailer(true);

        try {
            $this->setMailerBasic($mail->getTimeout());
            $this->setMailerAddress($mail->getOrigin(), $mail->getDestination(), $mail->getBccMails());
            $this->setMailerBody($template, $mail);
            $this->setMailerAttachments($mail->getAttachments());

            $res = $this->mailer->send();

            if(!$res) LogService::error("PHPMailer Error: " . $this->mailer->ErrorInfo);
            return $res;

        } catch (\Exception $e) {
            LogService::error("PHPMailer Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queues an email to be sent after the response is sent to the client.
     * This method does NOT block the request.
     *
     * @param Mail $mail The Mail instance to queue.
     *
     * @return bool True if the email was queued successfully, false otherwise.
     */
    public function fireAndForget(Mail $mail): bool {
        $template = $this->getMailTemplate($mail->getBodyFile());
        if(!$template) return false;
        
        $this->deferredMails[] = [
            "template" => $template,
            "mail" => $mail
        ];

        return true;
    }

    /**
     * Processes all queued fire-and-forget emails.
     * 
     * This method should be called after fastcgi_finish_request() to ensure
     * that emails are sent in the background without blocking the response.
     *
     */
    public function processDeferredMails(): void {
        if(empty($this->deferredMails)) return;

        foreach($this->deferredMails as $item) {
            $mail = $item["mail"];
            $template = $item["template"];
            
            if($this->mailer === null) $this->mailer = new PHPMailer(false);

            $this->setMailerBasic($mail->getTimeout());
            $this->setMailerAddress($mail->getOrigin(), $mail->getDestination(), $mail->getBccMails());
            $this->setMailerBody($template, $mail);
            $this->setMailerAttachments($mail->getAttachments());

            if(!$this->mailer->send()) {
                LogService::error("Deferred PHPMailer Error: " . $this->mailer->ErrorInfo);
            }
        }

        $this->deferredMails = [];
    }

    #/ METHODS
    #----------------------------------------------------------------------
}