<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Quote;
use RuntimeException;

/**
 * Envía un presupuesto del panel a FactuSOL (F_PRE + F_LPS).
 * Parte de un presupuesto real ya existente como plantilla (así se respetan todas las columnas obligatorias
 * de tu FactuSOL) y sustituye cliente, fecha, número, líneas e importes.
 */
class QuoteExporter
{
    public function export(Quote $quote): array
    {
        $quote->load(['customer', 'lines']);
        if ($quote->fs_code) {
            throw new RuntimeException("Este presupuesto ya está en FactuSOL (presupuesto {$quote->fs_code}).");
        }
        if ($quote->lines->isEmpty()) {
            throw new RuntimeException('El presupuesto no tiene líneas.');
        }
        $codcli = (int) $quote->customer->codcli;
        if ($codcli <= 0) {
            throw new RuntimeException('El cliente no existe en FactuSOL (es un cliente solo del panel).');
        }

        $api = new FactusolClient;
        $year = (int) date('Y');

        // Plantilla: último presupuesto del ejercicio y su primera línea
        $max = $api->query($year, 'SELECT MAX(CODPRE) AS M FROM F_PRE');
        $last = (int) ($max[0]['M'] ?? 0);
        if ($last <= 0) {
            throw new RuntimeException("No hay ningún presupuesto en FactuSOL en $year que sirva de plantilla. Crea uno a mano y reintenta.");
        }
        $tpl = ($api->table($year, 'F_PRE', "CODPRE = $last")[0] ?? null);
        $tplLine = ($api->table($year, 'F_LPS', "CODLPS = $last")[0] ?? null);
        if (! $tpl || ! $tplLine) {
            throw new RuntimeException("No se ha podido leer el presupuesto $last de FactuSOL como plantilla (cabecera o líneas).");
        }
        $code = $last + 1;

        $cli = $api->record($year, 'F_CLI', "CODCLI = $codcli") ?? [];
        $date = ($quote->date ?? now('Europe/Madrid'))->format('Y-m-d');
        $setDate = fn ($old) => preg_match('/\d{4}-\d\d-\d\d/', (string) $old) ? preg_replace('/\d{4}-\d\d-\d\d/', $date, (string) $old, 1) : $date;

        $base = 0.0;
        foreach ($quote->lines as $l) {
            $base += round($l->qty * $l->price * (1 - $l->discount / 100), 2);
        }
        $pct = (float) ($quote->iva_pct ?? 21);
        $iva = round($base * $pct / 100, 2);
        $total = round($base + $iva, 2);

        // Cabecera
        $h = $tpl;
        foreach (['CNO', 'CDO', 'CPO', 'CCP', 'CPR', 'CNI', 'REF', 'OB1', 'OB2', 'OB3', 'OB4'] as $p) {
            if (array_key_exists($p.'PRE', $h)) {
                $h[$p.'PRE'] = '';
            }
        }
        foreach ($h as $k => $v) {
            if (preg_match('/^(BAS|NET|IIVA|IIV|IREC|IRE|DTO|IMP)\d?PRE$/', $k) && is_numeric($v)) {
                $h[$k] = 0;
            }
        }
        $set = function (array &$row, string $k, $v) { if (array_key_exists($k, $row)) { $row[$k] = $v; } };
        $set($h, 'CODPRE', $code);
        $set($h, 'FECPRE', $setDate($tpl['FECPRE'] ?? null));
        $set($h, 'CLIPRE', $codcli);
        $set($h, 'CNOPRE', (string) ($cli['NOFCLI'] ?? $quote->customer->displayName()));
        $set($h, 'CDOPRE', (string) ($cli['DOMCLI'] ?? ''));
        $set($h, 'CPOPRE', (string) ($cli['POBCLI'] ?? ''));
        $set($h, 'CCPPRE', (string) ($cli['CPOCLI'] ?? ''));
        $set($h, 'CPRPRE', (string) ($cli['PROCLI'] ?? ''));
        $set($h, 'CNIPRE', (string) ($cli['NIFCLI'] ?? ''));
        $set($h, 'REFPRE', mb_substr('Panel '.$quote->number, 0, 20));
        $set($h, 'OB1PRE', 'Creado desde el panel '.$quote->number.' (PRUEBA)');
        $set($h, 'ESTPRE', 0);
        $set($h, 'BAS1PRE', $base);
        $set($h, 'NET1PRE', $base);
        $set($h, 'PIVA1PRE', $pct);
        $set($h, 'IIVA1PRE', $iva);
        $set($h, 'TOTPRE', $total);

        $log = [];
        $log[] = 'Cabecera F_PRE '.$code.': '.$api->insert($year, 'F_PRE', $h);

        $pos = 0;
        foreach ($quote->lines as $l) {
            $row = $tplLine;
            $a = $l->art ? Article::where('codart', $l->art)->first() : null;
            $lineTotal = round($l->qty * $l->price * (1 - $l->discount / 100), 2);
            $set($row, 'CODLPS', $code);
            $set($row, 'POSLPS', ++$pos);
            $set($row, 'ARTLPS', (string) ($l->art ?? ''));
            $set($row, 'DESLPS', mb_substr((string) $l->description, 0, 100));
            $set($row, 'CANLPS', (float) $l->qty);
            $set($row, 'PRELPS', (float) $l->price);
            $set($row, 'DT1LPS', (float) $l->discount);
            $set($row, 'TOTLPS', $lineTotal);
            if ($a && array_key_exists('COSLPS', $row)) {
                $row['COSLPS'] = (float) $a->cost;
            }
            $log[] = "Línea $pos ({$l->art}): ".$api->insert($year, 'F_LPS', $row);
        }

        // Comprobación: se vuelve a leer
        $back = $api->table($year, 'F_PRE', "CODPRE = $code")[0] ?? null;
        $lines = count($api->table($year, 'F_LPS', "CODLPS = $code"));
        if (! $back || $lines !== $pos) {
            throw new RuntimeException("Escrito, pero la comprobación no cuadra (cabecera ".($back ? 'sí' : 'NO').", líneas $lines de $pos). Revisa el presupuesto $code en FactuSOL.");
        }
        $quote->update(['fs_year' => $year, 'fs_code' => $code]);
        Activity::log('presupuesto_a_factusol', $quote->number.' → '.$code);

        return ['code' => $code, 'year' => $year, 'lines' => $pos, 'total' => $total, 'log' => $log, 'tpl' => $last];
    }

    /** Borra de FactuSOL un presupuesto creado desde el panel (solo si lo creó el panel: REFPRE «Panel …»). */
    public function remove(Quote $quote): void
    {
        if (! $quote->fs_code) {
            throw new RuntimeException('Este presupuesto no está en FactuSOL.');
        }
        $api = new FactusolClient;
        $year = (int) ($quote->fs_year ?: date('Y'));
        $code = (int) $quote->fs_code;
        $h = $api->table($year, 'F_PRE', "CODPRE = $code")[0] ?? null;
        if ($h && ! str_starts_with((string) ($h['REFPRE'] ?? ''), 'Panel ')) {
            throw new RuntimeException("El presupuesto $code de FactuSOL no lo creó el panel; no lo borro. Bórralo a mano en FactuSOL.");
        }
        $api->delete($year, 'F_LPS', "CODLPS = $code");
        $api->delete($year, 'F_PRE', "CODPRE = $code");
        $left = count($api->table($year, 'F_PRE', "CODPRE = $code")) + count($api->table($year, 'F_LPS', "CODLPS = $code"));
        if ($left) {
            throw new RuntimeException("No se ha borrado del todo el presupuesto $code en FactuSOL; revísalo allí.");
        }
        $quote->update(['fs_year' => null, 'fs_code' => null]);
        Activity::log('presupuesto_quitado_factusol', $quote->number.' ← '.$code);
    }
}
