<?php

namespace App\Services;

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

    private function store(array $r): void
    {
        $s = fn (string $k) => ($v = trim((string) ($r[$k] ?? ''))) === '' ? null : $v;
        $notes = collect([$s('OBSCLI'), $s('MEMCLI')])->filter()->unique()->implode("\n\n");
        $created = $s('FALCLI');

        Customer::updateOrCreate(['codcli' => (int) $r['CODCLI']], [
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
            'factusol_created_at' => $created && strncmp($created, '1900', 4) !== 0 ? $created : null,
            'synced_at' => now(),
        ]);
    }
}
