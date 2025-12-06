<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
        
        // Manejar errores de validación para respuestas JSON
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->ajax()) {
                $errors = $e->errors();
                $firstError = collect($errors)->flatten()->first();
                
                // Log para debug - encontrar origen de validación
                \Log::warning('ValidationException capturada', [
                    'url' => $request->url(),
                    'method' => $request->method(),
                    'errors' => $errors,
                    'input' => $request->except(['password', 'password_confirmation'])
                ]);
                
                return response()->json([
                    'estado' => false,
                    'msj' => 'Error de validación: ' . $firstError,
                    'errors' => $errors
                ], 422);
            }
        });
    }
}
