<?php

namespace FastRaven\Components\Core;

use FastRaven\Components\Data\Map;
use FastRaven\Components\Data\Pair;

final class Mail {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private Pair $origin;
        public function getOrigin(): Pair { return $this->origin; }
        public function setOrigin(Pair $origin): Mail { $this->origin = $origin; return $this; }
    private Pair $destination;
        public function getDestination(): Pair { return $this->destination; }
        public function setDestination(Pair $destination): Mail { $this->destination = $destination; return $this; }
    private Map $bccMails;
        public function getBccMails(): Map { return $this->bccMails; }
        public function setBccMails(Map $bccMails): Mail { $this->bccMails = $bccMails; return $this; }
    private string $subject = "";
        public function getSubject(): string { return $this->subject; }
        public function setSubject(string $subject): Mail { $this->subject = $subject; return $this; }
    private string $bodyFile = "";
        public function getBodyFile(): string { return $this->bodyFile; }
        public function setBodyFile(string $bodyFile): Mail { $this->bodyFile = $bodyFile; return $this; }
    private Map $replaceValues;
        public function getReplaceValues(): Map { return $this->replaceValues; }
        public function setReplaceValues(Map $replaceValues): Mail { $this->replaceValues = $replaceValues; return $this; }
    private Map $attachments;
        public function getAttachments(): Map { return $this->attachments; }
        public function setAttachments(Map $attachments): Mail { $this->attachments = $attachments; return $this; }
    private int $timeout = 3000;
        public function getTimeout(): int { return $this->timeout; }
        public function setTimeout(int $timeout): Mail { $this->timeout = $timeout; return $this; }
    private string $lang = "";
        public function getLang(): string { return $this->lang; }
    private string $langFile = "global";
        public function getLangFile(): string { return $this->langFile; }
    

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    /**
     * Creates a new Mail instance with the specified origin, destination, subject and body template file.
     *
     * @param Pair $origin The sender's email information (key: name, value: email address).
     * @param Pair $destination The recipient's email information (key: name, value: email address).
     * @param string $subject The subject line of the email.
     * @param string $bodyFile The path to the HTML template file (relative to src/web/mail/).
     *
     * @return Mail A new Mail instance configured with the provided parameters.
     */
    public static function new(Pair $origin, Pair $destination, string $subject, string $bodyFile): Mail {
        return new Mail($origin, $destination, $subject, $bodyFile);
    }

    private function __construct(Pair $origin, Pair $destination, string $subject, string $bodyFile) {
        $this->origin = $origin;
        $this->destination = $destination;
        $this->subject = $subject;
        $this->bodyFile = $bodyFile;

        $this->bccMails = Map::new();
        $this->replaceValues = Map::new();
        $this->attachments = Map::new();
    }

    #/ INIT
    #----------------------------------------------------------------------
    
    #----------------------------------------------------------------------
    #\ PRIVATE FUNCTIONS



    #/ PRIVATE FUNCTIONS
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ METHODS

    /**
     * Translates the mail: every {{KEY}} left in the subject and body after the replace values is looked up in the language file.
     *
     * @param string $lang The language code (CSV column).
     * @param string $langFile The language file name without extension.
     */
    public function setLang(string $lang, string $langFile = "global"): Mail {
        $this->lang = $lang;
        $this->langFile = $langFile;
        return $this;
    }

    #/ METHODS
    #----------------------------------------------------------------------
}