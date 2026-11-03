<?php

namespace FastRaven\Components\Http;

use FastRaven\Bee;

use FastRaven\Types\DataType;

final class Response {
    #----------------------------------------------------------------------
    #\ VARIABLES

    private bool $success;
        public function getSuccess(): bool { return $this->success; }
    private int $code;
        public function getCode(): int { return $this->code; }
    private string $message = "";
        public function getMessage(): string { return $this->message; }
        public function setMessage(string $message): Response { $this->message = $message; return $this; }
    private string|array $data = [];
        public function getData(): string|array { return $this->data; }
        public function setData(string|array $data): Response { $this->data = $data; return $this; }
    
    private array $frameworkMetadata = [];
        public function getFrameworkMetadata(): array { return $this->frameworkMetadata; }

    #/ VARIABLES
    #----------------------------------------------------------------------

    #----------------------------------------------------------------------
    #\ INIT

    /**
     * Creates a new Response instance.
     *
     * @param bool $success Whether the response is a success or not.
     * @param int $code The HTTP status code of the response.
     * @param string $message [optional] The message to set on the response.
     * @param string|array $data [optional] The data to set on the response.
     * 
     * @return Response The new Response instance.
     */
    #[\NoDiscard]
    public static function new(bool $success, int $code, string $message = "", string|array|null $data = []): Response {
        return (new Response($success, $code))->setData($data ?? [])->setMessage($message);
    }

    /**
     * Creates a new Response instance with a file as the data.
     *
     * @param bool $success Whether the response is a success or not.
     * @param string $path The path to the file to be sent relative to storage/uploads.
     * 
     * @return Response The new Response instance.
     */
    #[\NoDiscard]
    public static function file(bool $success, string $path): Response {
        return new Response($success, $success ? 200 : 500, ["path" => $path]);
    }

    
    private function __construct(bool $success, int $code, array $frameworkMetadata = []) {
        $this->success = $success;
        $this->code = $code;
        $this->frameworkMetadata = $frameworkMetadata;
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
     * Sets the body of the response.
     *
     * @param string $message [optional] The message to set on the response.
     * @param string|array $data [optional] The data to set on the response.
     * 
     * @return Response The response instance.
     */
    public function setBody(string $message = "", string|array $data = []): Response {
        $this->message = $message;
        $this->data = $data;
        return $this;
    }

    #/ METHODS
    #----------------------------------------------------------------------
}