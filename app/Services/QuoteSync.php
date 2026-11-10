<?php

namespace App\Services;

use App\Models\Quote;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Guarda en FactuSOL los cambios de un presupuesto del panel.
 * La pantalla responde al momento y la escritura en FactuSOL se hace justo después (FactuSOL tarda varios segundos);
 * un candado por presupuesto evita que dos guardados se pisen. Si falla, queda apuntado en el presupuesto y se ve en pantalla.
 */
class QuoteSync
{
    private static array $queued = [];

    public static function async(): bool
    {
        return ! app()->runningInConsole();
    }

    /** ¿Hay un guardado en FactuSOL preparado para después de responder? (para cerrar ya la respuesta al navegador) */
    public static function pending(): bool
    {
        return (bool) self::$queued;
    }

    public static function eligible(Quote $q): bool
    {
        return (bool) $q->customer?->codcli && ! $q->isDraftDoc();
    }

    /** Marca el presupuesto como pendiente y lo guarda (después de responder, si el servidor lo permite). Devuelve el error si se guardó en el momento y falló. */
    public static function save(Quote $q): ?string
    {
        if (! self::eligible($q)) {
            return null;
        }
        DB::table('quotes')->where('id', $q->id)->update(['fs_dirty' => 1]);
        if ($q->fs_code && self::async()) {
            if (! isset(self::$queued[$q->id])) {
                self::$queued[$q->id] = true;
                $id = $q->id;
                app()->terminating(function () use ($id) {
                    @set_time_limit(150);
                    @ignore_user_abort(true);
                    self::run($id, 60);
                });
            }

            return null;
        }

        return self::run($q->id);
    }

    /** Guarda ahora lo pendiente. Devuelve el mensaje de error, si lo hay. */
    public static function run(int $id, int $wait = 25): ?string
    {
        $lock = Cache::lock('quote-push:'.$id, 120);
        try {
            $lock->block($wait);
        } catch (LockTimeoutException $e) {
            return 'FactuSOL sigue guardando un cambio anterior de este presupuesto; se reintenta solo en unos minutos.';
        }
        $err = null;
        try {
            for ($i = 0; $i < 4; $i++) {
                $q = Quote::find($id);
                if (! $q || ! $q->fs_dirty) {
                    break;
                }
                // 1 = pendiente, 2 = guardándose ahora. Si mientras se guarda llega otro cambio vuelve a 1 y se repite.
                DB::table('quotes')->where('id', $id)->update(['fs_dirty' => 2]);
                try {
                    (new QuoteExporter)->push($q);
                    DB::table('quotes')->where('id', $id)->where('fs_dirty', 2)->update(['fs_dirty' => 0]);
                    DB::table('quotes')->where('id', $id)->update(['fs_error' => null]);
                    $err = null;
                } catch (\Throwable $e) {
                    $err = $e->getMessage();
                    DB::table('quotes')->where('id', $id)->update(['fs_dirty' => 0, 'fs_error' => mb_substr($err, 0, 500)]);
                    Activity::log('error_guardar_presupuesto', $q->number.': '.$err);
                    break;
                }
            }
        } finally {
            $lock->release();
        }

        return $err ? 'Guardado en el panel, pero no en FactuSOL: '.$err : null;
    }

    /** Antes de una operación que lee el presupuesto de FactuSOL: asegura que no queda nada pendiente. */
    public static function flush(Quote $q): void
    {
        if (DB::table('quotes')->where('id', $q->id)->value('fs_dirty')) {
            $e = self::run($q->id, 60);
            if ($e) {
                throw new RuntimeException($e);
            }
            if (DB::table('quotes')->where('id', $q->id)->value('fs_dirty')) {
                throw new RuntimeException('Todavía se está guardando un cambio de este presupuesto en FactuSOL. Prueba otra vez en unos segundos.');
            }
        }
    }

    /** Reintenta (desde el cron) lo que se quedó pendiente, p. ej. si el servidor cortó la petición. */
    public static function retryPending(): int
    {
        $n = 0;
        foreach (DB::table('quotes')->where('fs_dirty', '>', 0)->where('updated_at', '<', now()->subMinutes(3))->limit(10)->pluck('id') as $id) {
            self::run((int) $id, 5);
            $n++;
        }

        return $n;
    }
}
