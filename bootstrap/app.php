<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Webfox\Xero\Exceptions\OAuthException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle when the user clicks cancel on the Xero authorization screen
        $exceptions->render(function (OAuthException $e, Request $request) {
            return redirect('/my/xero/connect/page')->with('errorMessage', $e->getMessage());
        });
    })->create();

