<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Xử lý request — đặt locale từ header Accept-Language
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(['vi', 'en']);

        app()->setLocale($locale);

        return $next($request);
    }
}
