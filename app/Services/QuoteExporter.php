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

        $terms = FsTerms::of($api, $year, $codcli);
        $cli = $terms['cli'];
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
        $set($h, 'REFPRE', mb_substr((string) $quote->ref, 0, 20)); // la máquina, como se hace en FactuSOL
        $set($h, 'OB1PRE', mb_substr(trim('Creado desde el panel. '.$quote->notes), 0, 100));
        $set($h, 'ESTPRE', 0);
        $set($h, 'FOPPRE', $terms['fop']);
        $set($h, 'AGEPRE', $terms['age']);
        $set($h, 'REQPRE', $terms['req']);
        $set($h, 'TIVPRE', $terms['tiv']);
        $set($h, 'BAS1PRE', $base);
        $set($h, 'NET1PRE', $base);
        $set($h, 'PIVA1PRE', $pct);
        $set($h, 'IIVA1PRE', $iva);
        $set($h, 'TOTPRE', $total);

        $log = [];
        $log[] = 'Cabecera F_PRE '.$code.': '.$api->insert($year, 'F_PRE', $h);

        $pos = 0;
        foreach ($quote->lines as $l) {
            $log[] = 'Línea '.($pos + 1).' ('.$l->art.'): '.$api->insert($year, 'F_LPS', $this->lineRow($tplLine, $l, $code, ++$pos));
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
        if ($h && count($api->table($year, 'F_PRE', "CODPRE = $code")) > 1) {
            throw new RuntimeException("Hay varios presupuestos con el código $code en FactuSOL (series); bórralo a mano allí.");
        }
        $api->delete($year, 'F_LPS', "CODLPS = $code");
        $api->delete($year, 'F_PRE', "CODPRE = $code");
        $left = count($api->table($year, 'F_PRE', "CODPRE = $code")) + count($api->table($year, 'F_LPS', "CODLPS = $code"));
        if ($left) {
            throw new RuntimeException("No se ha borrado del todo el presupuesto $code en FactuSOL; revísalo allí.");
        }
        $ids = \App\Models\FsDoc::where('type', 'presupuesto')->where('year', $year)->where('code', $code)->pluck('id');
        \App\Models\FsDocLine::whereIn('doc_id', $ids)->delete();
        \App\Models\FsDoc::whereIn('id', $ids)->delete();
        $quote->update(['fs_year' => null, 'fs_code' => null]);
        Activity::log('presupuesto_quitado_factusol', $quote->number.' ← '.$code);
    }

    private function lineRow(array $tpl, $l, int $code, int $pos): array
    {
        $a = $l->art ? Article::where('codart', $l->art)->first() : null;
        $row = $tpl;
        $set = function (string $k, $v) use (&$row) { if (array_key_exists($k, $row)) { $row[$k] = $v; } };
        $set('CODLPS', $code);
        $set('POSLPS', $pos);
        $set('ARTLPS', (string) ($l->art ?? ''));
        $set('DESLPS', mb_substr((string) $l->description, 0, 100));
        $set('CANLPS', (float) $l->qty);
        $set('PRELPS', (float) $l->price);
        $set('DT1LPS', (float) $l->discount);
        $set('TOTLPS', round($l->qty * $l->price * (1 - $l->discount / 100), 2));
        if ($a) {
            $set('COSLPS', (float) $a->cost);
        }

        return $row;
    }

    private const STATUS_OUT = ['borrador' => 0, 'enviado' => 0, 'aceptado' => 1, 'rechazado' => 2];

    /** Deja guardado en FactuSOL lo que hay en el panel: crea el presupuesto si no existe, o lo actualiza (cabecera + líneas). */
    public function push(Quote $quote): void
    {
        if (! $quote->fs_code) {
            $this->export($quote);
            $quote->refresh();
            $quote->update(['number' => $quote->fs_year.'/'.$quote->fs_code]);

            return;
        }
        $quote->load(['customer', 'lines']);
        $api = new FactusolClient;
        $year = (int) $quote->fs_year;
        $code = (int) $quote->fs_code;
        $hdr = $api->table($year, 'F_PRE', "CODPRE = $code");
        if (count($hdr) !== 1) {
            throw new RuntimeException(count($hdr) === 0 ? "El presupuesto $code ya no existe en FactuSOL." : "Hay varios presupuestos con el código $code en FactuSOL (series); no lo toco.");
        }
        $h = $hdr[0];
        $old = $api->table($year, 'F_LPS', "CODLPS = $code");
        $tpl = $old[0] ?? null;
        if (! $tpl) {
            $last = (int) (($api->query($year, 'SELECT MAX(CODPRE) AS M FROM F_PRE')[0]['M'] ?? 0));
            $tpl = $api->table($year, 'F_LPS', "CODLPS = $last")[0] ?? null;
        }
        if (! $tpl) {
            throw new RuntimeException('No hay una línea de presupuesto en FactuSOL que sirva de plantilla.');
        }
        $base = 0.0;
        foreach ($quote->lines as $l) {
            $base += round($l->qty * $l->price * (1 - $l->discount / 100), 2);
        }
        $base = round($base, 2);
        $pct = (float) $quote->iva_pct;
        $iva = round($base * $pct / 100, 2);
        $date = ($quote->date ?? now('Europe/Madrid'))->format('Y-m-d');
        $upd = ['TIPPRE' => $h['TIPPRE'], 'CODPRE' => $code, 'ESTPRE' => self::STATUS_OUT[$quote->status] ?? 0,
            'REFPRE' => mb_substr((string) $quote->ref, 0, 20),
            'BAS1PRE' => $base, 'NET1PRE' => $base, 'PIVA1PRE' => $pct, 'IIVA1PRE' => $iva, 'TOTPRE' => round($base + $iva, 2)];
        if (array_key_exists('FECPRE', $h)) {
            $upd['FECPRE'] = preg_match('/\d{4}-\d\d-\d\d/', (string) $h['FECPRE']) ? preg_replace('/\d{4}-\d\d-\d\d/', $date, (string) $h['FECPRE'], 1) : $date;
        }
        if (array_key_exists('OB1PRE', $h)) {
            $upd['OB1PRE'] = mb_substr((string) $quote->notes, 0, 100);
        }
        $api->update($year, 'F_PRE', $upd);
        // Solo se tocan las líneas que cambian (más rápido y sin dejar el presupuesto a medias si algo falla)
        $have = [];
        foreach ($old as $o) {
            $have[(int) $o['POSLPS']] = $o;
        }
        $pos = 0;
        foreach ($quote->lines as $l) {
            ++$pos;
            $row = $this->lineRow($tpl, $l, $code, $pos);
            $cur = $have[$pos] ?? null;
            if (! $cur) {
                $api->insert($year, 'F_LPS', $row);

                continue;
            }
            $diff = false;
            foreach (['ARTLPS', 'DESLPS', 'CANLPS', 'PRELPS', 'DT1LPS', 'TOTLPS', 'COSLPS'] as $k) {
                if (! array_key_exists($k, $row)) {
                    continue;
                }
                $same = is_numeric($row[$k]) ? abs((float) $row[$k] - (float) ($cur[$k] ?? 0)) < 0.00001 : trim((string) $row[$k]) === trim((string) ($cur[$k] ?? ''));
                if (! $same) {
                    $diff = true;
                }
            }
            if ($diff) {
                $upd = ['TIPLPS' => $cur['TIPLPS'] ?? ($h['TIPPRE'] ?? ''), 'CODLPS' => $code, 'POSLPS' => $pos];
                foreach (['ARTLPS', 'DESLPS', 'CANLPS', 'PRELPS', 'DT1LPS', 'TOTLPS', 'COSLPS'] as $k) {
                    if (array_key_exists($k, $row)) {
                        $upd[$k] = $row[$k];
                    }
                }
                $api->update($year, 'F_LPS', $upd);
            }
        }
        foreach (array_keys($have) as $hp) {
            if ($hp > $pos) {
                $api->delete($year, 'F_LPS', "CODLPS = $code AND POSLPS = $hp");
            }
        }
        $n = count($api->table($year, 'F_LPS', "CODLPS = $code"));
        if ($n !== $pos) {
            usleep(1200000);
            $n = count($api->table($year, 'F_LPS', "CODLPS = $code"));
        }
        if ($n !== $pos) {
            throw new RuntimeException("Presupuesto $code actualizado, pero las líneas no cuadran ($n en FactuSOL, $pos en el panel); revísalo en FactuSOL.");
        }
        Activity::log('presupuesto_actualizado_factusol', $quote->number.' → '.$code);
    }
}
