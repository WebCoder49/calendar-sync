<?php

namespace App;

use Illuminate\Support\Facades\Route;

/* Route pages "seamlessly", so their content is loaded by AJAX
from "/_pages{path}", and "{path}" only loads an empty page with
JavaScript to load the content. */

class SeamlessRouter {
    /**
     * Get empty page to load on all UI requests before loading page content
     */
    public static function getSeamlessPage() {
        return view('seamless');
    }

    /**
     * Register a seamless GET request route
     */
    public static function get($path, $callback) {
        Route::get($path, function () {
            return view('seamless');
            return SeamlessRouter::getSeamlessPage();
        }); // Empty page
        return Route::get("/_seamless" . $path, $callback); // Main page
    }
}
