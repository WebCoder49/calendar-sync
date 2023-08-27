<?php

namespace App\Exceptions;

use Illuminate\Http\Request;

/**
 * Handles errors that will eventually be shown to the client.
 */
class ErrorMessage {
    public $errorSource;
    public $errorID;
    public $errorDescription;

    /**
     * Creates an ErrorMessage
     * @param string $errorSource Where the error comes from, "" if not from an external API.
     * @param string $errorID A camelCase string standardising what the error is.
     * @param string $errorDescription A description of the error / data with it that is human readable.
     */
    public function __construct(string|null $errorSource, string $errorID, string $errorDescription) {
        $this->errorSource = $errorSource;
        $this->errorID = $errorID;
        $this->errorDescription = $errorDescription;
    }

    /**
     * Adds a prefix to the errorDescription.
     * @param string $descriptionPrefix e.g. "Error while refreshing access token: "
     */
    public function addDescriptionContext(string $descriptionPrefix) {
        $this->errorDescription = $descriptionPrefix . $this->errorDescription;
    }

    public function getView(Request $request, bool $hideParameterValues) {
        if($hideParameterValues) {
            return view('error', [
                "errorSource" => $this->errorSource,
                "errorID" => $this->errorID,
                "errorDescription" => $this->errorDescription,

                "hiddenParameterString" => "?".implode("&", array_keys($request->all()))]);
        }
        return view('error', [
            "errorSource" => $this->errorSource,
            "errorID" => $this->errorID,
            "errorDescription" => $this->errorDescription]);
    }

    public function getJSON() {
        return [
            "error" => [
                "source" => $this->errorSource,
                "id" => $this->errorID,
                "description" => $this->errorDescription
            ]
        ];
    }
}
