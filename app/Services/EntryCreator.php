<?php

namespace App\Services;

use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Models\PanelEntrada;
use RuntimeException;

/**
 * Crea en FactuSOL una entrada de proveedor (F_ENT + F_LEN) partiendo del último registro real como plantilla.
 * Ojo: la API escribe los documentos pero no mueve el stock de FactuSOL.
 */
class EntryCreator
{
    /** @param array<int,array{art:?string,desc:string,qty:float,cost:float}> $items */
    public function create(int $codpro, string $theirNo, string $date, array $items): array
    {
        $fsLock = FsLock::hold('F_ENT');
        $items = array_values(array_filter($items, fn ($i) => $i['qty'] > 0 && ($i['art'] !== '' || $i['desc'] !== '')));
        if (! $items) {
            throw new RuntimeException('La entrada no tiene líneas.');
        }
        $api = new FactusolClient;
        $year = (int) substr($date, 0, 4) ?: (int) date('Y');
        $pro = $api->record($year, 'F_PRO', "CODPRO = $codpro");
        if (! $pro) {
            throw new RuntimeException('No encuentro ese proveedor en FactuSOL.');
        }
        if ((int) ($pro['IVAPRO'] ?? 0) !== 0 || (int) ($pro['REQPRO'] ?? 0) === 1) {
            throw new RuntimeException('Este proveedor lleva recargo de equivalencia o un IVA especial. Para no descuadrar importes, haz la entrada en FactuSOL.');
        }
        $max = $api->query($year, 'SELECT MAX(CODENT) AS M FROM F_ENT');
        $last = (int) ($max[0]['M'] ?? 0);
        $th = $last ? ($api->table($year, 'F_ENT', "CODENT = $last")[0] ?? null) : null;
        $tl = $last ? ($api->table($year, 'F_LEN', "CODLEN = $last")[0] ?? null) : null;
        if (! $th || ! $tl) {
            throw new RuntimeException('No hay una entrada anterior en FactuSOL que sirva de plantilla.');
        }
        $new = $last + 1;

        $net = 0.0;
        $lines = [];
        $pos = 0;
        foreach ($items as $i) {
            $tot = round($i['qty'] * $i['cost'], 2);
            $net += $tot;
            $row = $tl;
            $row['CODLEN'] = $new;
            $row['POSLEN'] = ++$pos;
            $row['ARTLEN'] = (string) ($i['art'] ?? '');
            $row['DESLEN'] = mb_substr($i['desc'], 0, 100);
            $row['CANLEN'] = $i['qty'];
            $row['PRELEN'] = $i['cost'];
            $row['TOTLEN'] = $tot;
            foreach (['DT1LEN', 'DT2LEN', 'DT3LEN'] as $k) {
                if (array_key_exists($k, $row)) {
                    $row[$k] = 0;
                }
            }
            foreach (['MEMLEN', 'FFALEN', 'FCOLEN', 'DOCLEN', 'DTPLEN', 'EJELEN'] as $k) {
                if (array_key_exists($k, $row) && is_string($row[$k])) {
                    $row[$k] = '';
                }
            }
            foreach (['DCOLEN'] as $k) {
                if (array_key_exists($k, $row)) {
                    $row[$k] = 0;
                }
            }
            $lines[] = $row;
        }
        $net = round($net, 2);
        $pct = (float) ($th['PIVA1ENT'] ?? 21);
        $iva = round($net * $pct / 100, 2);
        $total = round($net + $iva, 2);

        $h = $th;
        foreach ($h as $k => $v) {
            if (preg_match('/^(NET|BAS|IIVA|IREC|IRET|IDTO|IPPA|IPOR|IFIN|PDTO|PPPA|PPOR|PFIN)\d?[A-Z]*ENT$|^TOTENT$/', $k) && is_numeric($v)) {
                $h[$k] = 0;
            }
        }
        foreach (['OB1', 'OB2', 'REF', 'PED', 'PAS', 'TRZ', 'COM', 'TPO', 'ALB'] as $p) {
            if (array_key_exists($p.'ENT', $h) && is_string($h[$p.'ENT'])) {
                $h[$p.'ENT'] = '';
            }
        }
        $today = now('Europe/Madrid');
        $h['CODENT'] = $new;
        $h['FECENT'] = $date.'T00:00:00';
        if (array_key_exists('HORENT', $h)) {
            $h['HORENT'] = '1900-01-01T'.$today->format('H:i:s');
        }
        if (array_key_exists('ESTENT', $h)) {
            $h['ESTENT'] = 0;
        }
        $h['PROENT'] = $codpro;
        $h['ALBENT'] = mb_substr($theirNo, 0, 20);
        $map = ['PNO' => 'NOFPRO', 'PDO' => 'DOMPRO', 'PPO' => 'POBPRO', 'PCP' => 'CPOPRO', 'PPR' => 'PROPRO', 'PNI' => 'NIFPRO', 'PTE' => 'TELPRO'];
        foreach ($map as $a => $b) {
            if (array_key_exists($a.'ENT', $h)) {
                $h[$a.'ENT'] = (string) ($pro[$b] ?? '');
            }
        }
        if (array_key_exists('FOPENT', $h) && ($pro['FPAPRO'] ?? '') !== '') {
            $h['FOPENT'] = (string) $pro['FPAPRO'];
        }
        $h['NET1ENT'] = $net;
        $h['BAS1ENT'] = $net;
        $h['IIVA1ENT'] = $iva;
        $h['TOTENT'] = $total;

        $api->insert($year, 'F_ENT', $h);
        foreach ($lines as $row) {
            $api->insert($year, 'F_LEN', $row);
        }
        $chk = $api->table($year, 'F_ENT', "CODENT = $new")[0] ?? null;
        $nl = count($api->table($year, 'F_LEN', "CODLEN = $new"));
        if (! $chk || $nl !== count($lines)) {
            throw new RuntimeException("Entrada $new escrita, pero la comprobación no cuadra (cabecera ".($chk ? 'sí' : 'NO').", líneas $nl de ".count($lines).'). Revísala en FactuSOL.');
        }
        PanelEntrada::create(['year' => $year, 'entry_code' => $new, 'codpro' => $codpro, 'total' => $total, 'user_id' => auth()->id()]);
        Activity::log('entrada_creada', "entrada $new ← proveedor $codpro");
        $this->pull();

        return ['code' => $new, 'year' => $year, 'lines' => count($lines), 'total' => $total];
    }

    public function remove(PanelEntrada $pe): void
    {
        if ($pe->removed_at) {
            throw new RuntimeException('Esta entrada ya se quitó.');
        }
        $api = new FactusolClient;
        $year = (int) $pe->year;
        $code = (int) $pe->entry_code;
        $api->delete($year, 'F_LEN', "CODLEN = $code");
        $api->delete($year, 'F_ENT', "CODENT = $code");
        if (count($api->table($year, 'F_ENT', "CODENT = $code")) + count($api->table($year, 'F_LEN', "CODLEN = $code"))) {
            throw new RuntimeException("No se ha borrado del todo la entrada $code; revísala en FactuSOL.");
        }
        $pe->update(['removed_at' => now()]);
        $d = FsDoc::where('type', 'entrada')->where('year', $year)->where('code', $code)->first();
        if ($d) {
            FsDocLine::where('doc_id', $d->id)->delete();
            $d->delete();
        }
        \App\Models\SupplierNote::where('entry_year', $year)->where('entry_code', $code)->update(['entry_year' => null, 'entry_code' => null]);
        Activity::log('entrada_quitada', "entrada $code");
    }

    private function pull(): void
    {
        try {
            (new FactusolDocs)->quick(['entrada'], 10, true);
        } catch (\Throwable $e) {
        }
    }
}
