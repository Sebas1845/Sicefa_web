<?php
namespace Modules\CAFETO\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

class SkipCsrfForFormulations
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('cafeto/cashier/formulations')) {
            return $next($request); // Skip CSRF check
        }

        try {
            $this->validateCsrfToken($request);
        } catch (TokenMismatchException $e) {
            abort(419, 'Page Expired');
        }

        return $next($request);
    }

    protected function validateCsrfToken(Request $request)
    {
        if (
            $request->method() === 'POST' ||
            $request->method() === 'PUT' ||
            $request->method() === 'DELETE'
        ) {
            if (! $request->hasValidCsrfToken()) {
                throw new TokenMismatchException('CSRF token mismatch.');
            }
        }
    }
}