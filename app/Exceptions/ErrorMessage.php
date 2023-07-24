<?php

namespace App\Exceptions;

use Illuminate\Http\Request;

class ErrorMessage {
    public $error_source;
    public $error_id;
    public $error_description;

    public function __construct($error_source, string $error_id, string $error_description) {
        $this->error_source = $error_source;
        $this->error_id = $error_id;
        $this->error_description = $error_description;
    }

    public function add_description_context(string $description_prefix) {
        $this->error_description = $description_prefix . $this->error_description;
    }

    public function get_view(Request $request, bool $hide_parameter_values) {
        if($hide_parameter_values) {
            return view('error', [
                "error_source" => $this->error_source,
                "error_id" => $this->error_id,
                "error_description" => $this->error_description,

                "hidden_parameter_string" => "?".implode("&", array_keys($request->all()))]);
        }
        return view('error', [
            "error_source" => $this->error_source,
            "error_id" => $this->error_id,
            "error_description" => $this->error_description]);
    }

    public function get_json() {
        return [
            "error" => [
                "source" => $this->error_source,
                "id" => $this->error_id,
                "description" => $this->error_description
            ]
        ];
    }
}
