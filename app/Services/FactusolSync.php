<?php

namespace App\Services;

use App\Services\Activity;

use App\Models\Customer;
use App\Models\Setting;

/**
 * Sincroniza clientes de FactuSOL (F_CLI) a la tabla local, por tandas.
 * La API devuelve un registro por llamada, así que se recorre CODCLI en orden
 * y se recuerda por dónde se va. Termina tras una racha de códigos vacíos.
 */
class FactusolSync
{
    private const MAX_MISSES = 150;

    /** Trae todos los clientes en una sola consulta (CargaTabla). */
    public function runAll(): int
    {
        $api = new FactusolClient;
        foreach ($api->query((int) date('Y'), 'SELECT CODFPA, DESFPA, DIA1FPA FROM F_FPA') as $f) {
            \Illuminate\Support\Facades\DB::table('payment_terms')->updateOrInsert(['code' => (int) $f['CODFPA']], ['name' => trim((string) $f['DESFPA']), 'days' => (int) ($f['DIA1FPA'] ?? 0)]);
        }
        $rows = $api->table((int) date('Y'), 'F_CLI', 'CODCLI > 0 ORDER BY CODCLI');
        $n = 0;
        foreach ($rows as $r) {
            if ((int) ($r['CODCLI'] ?? 0) > 0) {
                $this->store($r);
                $n++;
            }
        }
        Setting::put('fs_last_sync', now()->toIso8601String());

        return $n;
    }

    /** @return array{done: int, found: int, cursor: int, finished: bool} */
    public function run(int $seconds = 40): array
    {
        $api = new FactusolClient;
        $cursor = max(1, (int) Setting::get('fs_cursor', '1'));
        $misses = 0;
        $found = 0;
        $done = 0;
        $start = microtime(true);
        $year = (int) date('Y');

        while (microtime(true) - $start < $seconds) {
            $row = $api->record($year, 'F_CLI', 'CODCLI = '.$cursor);
            $done++;
            if ($row === null) {
                $misses++;
                $cursor++;
                if ($misses >= self::MAX_MISSES) {
                    Setting::put('fs_cursor', (string) max(1, $cursor - self::MAX_MISSES));
                    Setting::put('fs_last_sync', now()->toIso8601String());

                    return ['done' => $done, 'found' => $found, 'cursor' => $cursor, 'finished' => true];
                }
                continue;
            }
            $misses = 0;
            $this->store($row);
            $found++;
            $cursor++;
        }
        Setting::put('fs_cursor', (string) $cursor);

        return ['done' => $done, 'found' => $found, 'cursor' => $cursor, 'finished' => false];
    }

    /** Vuelve a leer un solo cliente de FactuSOL (y rehace su flota si cambiaron las observaciones). */
    public function syncOne(int $codcli): bool
    {
        $rows = (new FactusolClient)->query((int) date('Y'), "SELECT * FROM F_CLI WHERE CODCLI = $codcli");
        if (! $rows) {
            return false;
        }
        $this->store($rows[0]);

        return true;
    }

    private function store(array $r): void
    {
        $s = fn (string $k) => ($v = trim((string) ($r[$k] ?? ''))) === '' ? null : $v;
        $notes = collect([$s('OBSCLI'), $s('MEMCLI')])->filter()->unique()->implode("\n\n");
        $created = $s('FALCLI');

        $prev = Customer::where('codcli', (int) $r['CODCLI'])->value('factusol_notes');
        $customer = Customer::updateOrCreate(['codcli' => (int) $r['CODCLI']], [
            'name' => $s('NOFCLI') ?? $s('NOCCLI') ?? 'Cliente '.$r['CODCLI'],
            'trade_name' => $s('NOCCLI'),
            'nif' => $s('NIFCLI'),
            'phone' => $s('TELCLI'),
            'mobile' => $s('MOVCLI'),
            'email' => $s('EMACLI'),
            'address' => $s('DOMCLI'),
            'city' => $s('POBCLI'),
            'postcode' => $s('CPOCLI'),
            'province' => $s('PROCLI'),
            'factusol_notes' => $notes !== '' ? $notes : null,
            'tariff' => (int) ($r['TARCLI'] ?? 0) ?: null,
            'payment_code' => (int) ($r['FPACLI'] ?? 0) ?: null,
            'factusol_created_at' => $created && strncmp($created, '1900', 4) !== 0 ? $created : null,
            'synced_at' => now(),
        ]);
        // Si han cambiado las observaciones en FactuSOL, se rehace la flota y los avisos de ese cliente conservando lo editado a mano.
        if (trim((string) $prev) !== trim((string) ($notes !== '' ? $notes : '')) && $customer->fleet_imported_at) {
            try {
                FleetImporter::reimport($customer->fresh());
            } catch (\Throwable $e) {
                Activity::log('flota_reimport_error', $customer->codcli.': '.mb_substr($e->getMessage(), 0, 200), null);
            }
        }
    }
}
