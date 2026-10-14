<?php

use App\Http\Middleware\EnsureActiveTournamentGame;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active_tournament_game' => EnsureActiveTournamentGame::class,
        ]);

        RedirectIfAuthenticated::redirectUsing(function ($request) {
            return $request->user()?->landingPath() ?? '/login';
        });

        Authenticate::redirectUsing(fn () => '/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
