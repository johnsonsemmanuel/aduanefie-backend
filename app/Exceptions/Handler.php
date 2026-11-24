<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        //
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof TokenMismatchException || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 419)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'CSRF token mismatch.'], 419);
            }

            if ($request->hasSession()) {
                $request->session()->regenerateToken();
            }

            $path = ltrim($request->path(), '/');
            $targetRoute = null;

            if (str_starts_with($path, 'admin-mfa') || str_starts_with($path, 'admin-login') || str_starts_with($path, 'login/admin')) {
                $targetRoute = 'admin.login';
            } elseif (str_starts_with($path, 'vendor-mfa') || str_starts_with($path, 'store-login') || str_starts_with($path, 'login/store')) {
                $targetRoute = 'store.login';
            } elseif (str_starts_with($path, 'marketer-mfa') || str_starts_with($path, 'marketer-login')) {
                $targetRoute = 'marketer.login';
            } elseif (str_starts_with($path, 'rider-login')) {
                $targetRoute = 'rider.login';
            }

            $redirect = $targetRoute ? redirect()->route($targetRoute) : redirect()->back();

            return $redirect
                ->withInput($request->except(['password', 'password_confirmation', '_token', 'code']))
                ->withErrors(['email' => 'Your session expired. Please sign in again.']);
        }

        return parent::render($request, $e);
    }
}
