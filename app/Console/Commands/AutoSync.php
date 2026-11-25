<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Activity;
use App\Services\FactusolCatalog;
use App\Services\FactusolClient;
use App\Services\FactusolDocs;
use App\Services\FactusolSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Sincronización automática con FactuSOL (la lanza el cron del servidor cada pocos minutos).
 *  - documentos del ejercicio actual: cada pasada del cron (~5 min); clientes: cada 15 minutos
 *  - artículos, stock, tarifas y proveedores: cada 6 horas
 */
class AutoSync extends Command
{
    protected $signature = 'parsur:auto-sync {--force}';

    protected $description = 'Sincronización periódica con FactuSOL';

    public function handle(): int
    {
        // Parsur Flota: las fotos y PDF de facturas de otros proveedores se borran a los 3 días (una pasada por hora).
        if (Cache::add('portal:purge-files', 1, 3600)) {
            try {
                \App\Models\MachineExpense::purgeOldFiles();
            } catch (\Throwable $e) {
            }
        }
        // Cada mañana laborable (desde las 8:00) deja en «Avisos y tareas» los presupuestos que toca reclamar.
        try {
            self::dailyFollowTask();
        } catch (\Throwable $e) {
        }
        if (! FactusolClient::configured()) {
            return self::SUCCESS;
        }
        if (Setting::get('auto_sync', '1') !== '1' && ! $this->option('force')) {
            return self::SUCCESS;
        }
        $lock = Cache::lock('parsur:auto-sync', 600);
        if (! $lock->get()) {
            return self::SUCCESS; // ya hay una en marcha
        }
        try {
            $now = now();
            $last = Setting::get('auto_sync_last');
            $lastCat = Setting::get('auto_sync_catalog_last');
            $log = [];
            // Documentos (pedidos, albaranes, facturas…): en cada pasada del cron (~5 min).
            if ($this->option('force') || ! $last || \Carbon\Carbon::parse($last)->diffInMinutes($now, true) >= 3) {
                try {
                    $r = (new FactusolDocs)->run((int) date('Y'), 110);
                    $log[] = 'documentos: '.implode('; ', $r['steps'] ?: ['sin cambios']);
                } catch (\Throwable $e) {
                    $log[] = 'documentos ERROR '.$e->getMessage();
                }
                Setting::put('auto_sync_last', $now->toIso8601String());
            }
            // Clientes: cada 15 min (es lo más pesado y cambia poco).
            $lastCli = Setting::get('auto_sync_clients_last');
            if ($this->option('force') || ! $lastCli || \Carbon\Carbon::parse($lastCli)->diffInMinutes($now, true) >= 14) {
                try {
                    $n = (new FactusolSync)->runAll();
                    $log[] = "clientes $n";
                } catch (\Throwable $e) {
                    $log[] = 'clientes ERROR '.$e->getMessage();
                }
                Setting::put('auto_sync_clients_last', $now->toIso8601String());
            }
            if ($this->option('force') || ! $lastCat || \Carbon\Carbon::parse($lastCat)->diffInHours($now, true) >= 6) {
                try {
                    $r = (new FactusolCatalog)->run();
                    $log[] = "catálogo {$r['articles']} artículos";
                } catch (\Throwable $e) {
                    $log[] = 'catálogo ERROR '.$e->getMessage();
                }
                Setting::put('auto_sync_catalog_last', $now->toIso8601String());
            }
            try {
                if ($n = \App\Services\QuoteSync::retryPending()) {
                    $log[] = "presupuestos pendientes reintentados: $n";
                }
            } catch (\Throwable $e) {
            }
            // Lo cobrado por la web (enlaces de pago de presupuestos y pedidos de la tienda) pasa a pedido en FactuSOL.
            try {
                $log = array_merge($log, (new \App\Services\PaidOrders)->run());
            } catch (\Throwable $e) {
                $log[] = 'cobros web ERROR '.$e->getMessage();
            }
            if ($log) {
                Setting::put('auto_sync_report', $now->timezone('Europe/Madrid')->format('d/m/Y H:i').' · '.implode(' · ', $log));
                Activity::log('auto_sync', mb_substr(implode(' · ', $log), 0, 250), null);
                $this->info(implode("\n", $log));
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /** Un aviso al día (lunes a viernes, desde las 8:00) con los presupuestos sin respuesta para reclamar. */
    public static function dailyFollowTask(): void
    {
        $now = now('Europe/Madrid');
        if ($now->isWeekend() || $now->hour < 8 || Setting::get('follow_task_day') === $now->toDateString()) {
            return;
        }
        Setting::put('follow_task_day', $now->toDateString());
        // El aviso de días anteriores que siga abierto se cierra: el de hoy ya trae la lista al día.
        \App\Models\Task::pending()->where('title', 'like', 'Reclamar % presupuesto%')->update(['done_at' => now()]);
        $quotes = \App\Models\Quote::with('customer')->toFollow()->orderByDesc('date')->get();
        if ($quotes->isEmpty()) {
            return;
        }
        $total = $quotes->sum('total');
        $lines = $quotes->take(25)->map(fn ($q) => '· '.$q->number.' · '.($q->customer?->displayName() ?? $q->customer_name ?? 'Sin ficha').' · '.eur($q->total).' · hace '.(int) $q->date?->diffInDays(now()).' días')->implode("\n");
        $juan = \App\Models\User::where('role', 'admin')->where('name', 'like', 'Juan%')->value('id');
        \App\Models\Task::create([
            'title' => 'Reclamar '.$quotes->count().' presupuesto'.($quotes->count() === 1 ? '' : 's').' sin respuesta ('.eur($total).')',
            'notes' => "Ábrelos en Presupuestos → «Por reclamar»: cada uno tiene su botón de WhatsApp ya escrito.\n".$lines.($quotes->count() > 25 ? "\n… y ".($quotes->count() - 25).' más.' : ''),
            'due_date' => $now->toDateString(),
            'assigned_to' => $juan,
        ]);
    }
}
