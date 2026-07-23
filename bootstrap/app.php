<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->render(function (Throwable $e, Request $request) {

            if ($request->expectsJson()) {
                return null;
            }

            if ($e instanceof AuthenticationException || $e instanceof ValidationException) {
                return null;
            }

            $status = 500;

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
            }

            return response()->view('errors.generic', [
                'code' => $status,
                'message' => match ($status) {
                    401 => 'Vous devez être connecté pour accéder à cette page.',
                    403 => 'Vous n’avez pas les droits nécessaires.',
                    404 => 'La page demandée est introuvable.',
                    419 => 'Votre session a expiré.',
                    429 => 'Trop de requêtes. Veuillez patienter.',
                    500 => 'Une erreur interne est survenue.',
                    503 => 'Le service est momentanément indisponible.',
                    default => 'Une erreur est survenue.',
                },
            ], $status);

        });
    })->create();
