<?php

namespace App\Services;

/**
 * Trabajo que se hace justo después de responder al navegador (guardar en FactuSOL, refrescar datos de la tienda…),
 * para que la pantalla no espere. SecurityHeaders cierra la respuesta con su longitud exacta cuando hay algo pendiente.
 */
class AfterResponse
{
    private static bool $pending = false;

    public static function pending(): bool
    {
        return self::$pending;
    }

    public static function run(callable $job): void
    {
        if (app()->runningInConsole()) {
            $job();

            return;
        }
        self::$pending = true;
        app()->terminating(function () use ($job) {
            @set_time_limit(150);
            @ignore_user_abort(true);
            try {
                $job();
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
