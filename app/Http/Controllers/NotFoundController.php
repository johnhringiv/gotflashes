<?php

namespace App\Http\Controllers;

class NotFoundController extends Controller
{
    /**
     * Handle 404 errors through the web middleware stack.
     * This ensures proper session, auth, and CSRF handling.
     */
    public function __invoke()
    {
        return response()->view('errors.404', [], 404);
    }
}
