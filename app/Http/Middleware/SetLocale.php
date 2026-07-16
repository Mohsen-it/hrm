<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Set the application locale from session or default to Arabic.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Session::get('locale', config('app.locale', 'ar'));

        if (! in_array($locale, ['ar', 'en'])) {
            $locale = 'ar';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
