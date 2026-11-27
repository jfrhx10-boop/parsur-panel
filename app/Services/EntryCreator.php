<?php

namespace App\Services;

use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Models\PanelEntrada;
use RuntimeException;

/**
 * Crea en FactuSOL una entrada de proveedor (F_ENT + F_LEN) partiendo del último registro real como plantilla.
 * La API escribe los documentos pero no mueve el stock de FactuSOL: el stock se suma aparte en F_STO (moveStock).
 */
class EntryCreator
{
    private string $kind = 'entrada';

    private string $T = 'F_ENT';   // cabecera

    private string $TL = 'F_LEN';  // líneas

    private string $H = 'ENT';

    private string $L = 'LEN';

    private string $EXT = 'ALB';   // campo con el nº de documento del proveedor

    private string $name = 'Entrada';

    /** $kind: 'entrada' (F_ENT) o 'facturarec' (factura recibida, F_FRE, que en FactuSOL también hace de entrada). */
    private function as(string $kind): void
    {
        $this->kind = $kind === 'facturarec' ? 'facturarec' : 'entrada';
        [$this->T, $this->TL, $this->H, $this->L, $this->EXT, $this->name] = $this->kind === 'facturarec'
            ? ['F_FRE', 'F_LFR', 'FRE', 'LFR', 'FAC', 'Factura recibida']
            : ['F_ENT', 'F_LEN', 'ENT', 'LEN', 'ALB', 'Entrada'];
    }

    /** @param array<int,array{art:?string,desc:string,qty:float,cost:float}> $items */
    public function create(int $codpro, string $theirNo, string $date, array $items, string $kind = 'entrada', float $irpf = 0.0): array
    {
        $this->as($kind);
        $fsLock = FsLock::hold($this->T);
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
        $last = $this->lastInSequence($api, $year);
        $tc = (int) ($api->query($year, "SELECT MAX(COD{$this->L}) AS M FROM {$this->TL}")[0]['M'] ?? 0) ?: $last; // último con líneas
        $th = $tc ? ($api->table($year, $this->T, "COD{$this->H} = $tc")[0] ?? null) : null;
        $tl = $tc ? ($api->table($year, $this->TL, "COD{$this->L} = $tc")[0] ?? null) : null;
        if (! $th || ! $tl) {
            throw new RuntimeException('No hay una entrada anterior en FactuSOL que sirva de plantilla.');
        }
        $new = $last + 1;
        if ($api->table($year, $this->T, "COD{$this->H} = $new")) {
            throw new RuntimeException("El nº $new de {$this->name} ya existe en FactuSOL; revisa la numeración.");
        }

        $net = 0.0;
        $lines = [];
        $pos = 0;
        foreach ($items as $i) {
            $tot = round($i['qty'] * $i['cost'], 2);
            $net += $tot;
            $row = $tl;
            $row['COD'.$this->L] = $new;
            $row['POS'.$this->L] = ++$pos;
            $row['ART'.$this->L] = (string) ($i['art'] ?? '');
            $row['DES'.$this->L] = mb_substr($i['desc'], 0, 100);
            $row['CAN'.$this->L] = $i['qty'];
            $row['PRE'.$this->L] = $i['cost'];
            $row['TOT'.$this->L] = $tot;
            foreach (['DT1'.$this->L, 'DT2'.$this->L, 'DT3'.$this->L] as $k) {
                if (array_key_exists($k, $row)) {
                    $row[$k] = 0;
                }
            }
            foreach (['MEM'.$this->L, 'FFA'.$this->L, 'FCO'.$this->L, 'DOC'.$this->L, 'DTP'.$this->L, 'EJE'.$this->L] as $k) {
                if (array_key_exists($k, $row) && is_string($row[$k])) {
                    $row[$k] = '';
                }
            }
            foreach (['DCO'.$this->L] as $k) {
                if (array_key_exists($k, $row)) {
                    $row[$k] = 0;
                }
            }
            $lines[] = $row;
        }
        $net = round($net, 2);
        $pct = (float) ($th['PIVA1'.$this->H] ?? 21);
        $iva = round($net * $pct / 100, 2);
        // Retención de IRPF (facturas de profesionales/autónomos): resta del total
        $irpf = max(0.0, min(25.0, $irpf));
        $ret = $irpf > 0 ? round($net * $irpf / 100, 2) : 0.0;
        $total = round($net + $iva - $ret, 2);

        $h = $th;
        foreach ($h as $k => $v) {
            if (preg_match('/^(NET|BAS|IIVA|IREC|IRET|IDTO|IPPA|IPOR|IFIN|PDTO|PPPA|PPOR|PFIN)\d?[A-Z]*'.$this->H.'$|^TOT'.$this->H.'$/', $k) && is_numeric($v)) {
                $h[$k] = 0;
            }
        }
        foreach (['OB1', 'OB2', 'REF', 'PED', 'PAS', 'TRZ', 'COM', 'TPO', 'ALB', 'FAC', 'VEN', 'PDF', 'IMG', 'BIN', 'EER', 'TRA'] as $p) {
            if (array_key_exists($p.$this->H, $h) && is_string($h[$p.$this->H])) {
                $h[$p.$this->H] = '';
            }
        }
        $today = now('Europe/Madrid');
        $h['COD'.$this->H] = $new;
        $h['FEC'.$this->H] = $date.'T00:00:00';
        if (array_key_exists('HOR'.$this->H, $h)) {
            $h['HOR'.$this->H] = '1900-01-01T'.$today->format('H:i:s');
        }
        if (array_key_exists('EST'.$this->H, $h)) {
            $h['EST'.$this->H] = 0;
        }
        $h['PRO'.$this->H] = $codpro;
        $h[$this->EXT.$this->H] = mb_substr($theirNo, 0, 20);
        $map = ['PNO' => 'NOFPRO', 'PDO' => 'DOMPRO', 'PPO' => 'POBPRO', 'PCP' => 'CPOPRO', 'PPR' => 'PROPRO', 'PNI' => 'NIFPRO', 'PTE' => 'TELPRO'];
        foreach ($map as $a => $b) {
            if (array_key_exists($a.$this->H, $h)) {
                $h[$a.$this->H] = (string) ($pro[$b] ?? '');
            }
        }
        if (array_key_exists('FOP'.$this->H, $h) && ($pro['FPAPRO'] ?? '') !== '') {
            $h['FOP'.$this->H] = (string) $pro['FPAPRO'];
        }
        $h['NET1'.$this->H] = $net;
        $h['BAS1'.$this->H] = $net;
        $h['IIVA1'.$this->H] = $iva;
        $h['TOT'.$this->H] = $total;
        if ($ret > 0) {
            if (! array_key_exists('IRET1'.$this->H, $h)) {
                throw new RuntimeException('Este tipo de documento no admite retención de IRPF en FactuSOL.');
            }
            $h['PRET1'.$this->H] = $irpf;
            $h['IRET1'.$this->H] = $ret;
        }

        $api->insert($year, $this->T, $h);
        foreach ($lines as $row) {
            $api->insert($year, $this->TL, $row);
        }
        $chk = $api->table($year, $this->T, "COD{$this->H} = $new")[0] ?? null;
        $nl = count($api->table($year, $this->TL, "COD{$this->L} = $new"));
        if (! $chk || $nl !== count($lines)) {
            throw new RuntimeException("{$this->name} $new escrita, pero la comprobación no cuadra (cabecera ".($chk ? 'sí' : 'NO').", líneas $nl de ".count($lines).'). Revísala en FactuSOL.');
        }
        $this->moveStock($api, $year, $items, +1);
        PanelEntrada::create(['year' => $year, 'entry_code' => $new, 'codpro' => $codpro, 'total' => $total, 'user_id' => auth()->id(), 'kind' => $this->kind]);
        Activity::log($this->kind.'_creada', "{$this->name} $new ← proveedor $codpro");
        $this->pull();

        return ['code' => $new, 'year' => $year, 'lines' => count($lines), 'total' => $total, 'name' => $this->name];
    }

    /** Desde aquí el panel mueve el stock al crear/quitar (lo anterior se creó sin moverlo). */
    public const STOCK_SINCE = '2026-11-17 18:00:00';

    /**
     * Último número de la serie normal. Si alguien ha tecleado en FactuSOL un número suelto muy alto
     * (p. ej. 10164 detrás del 806) no se sigue desde ahí: se sigue la serie correlativa.
     */
    private function lastInSequence(FactusolClient $api, int $year): int
    {
        $codes = array_map(fn ($r) => (int) $r['C'], $api->query($year, "SELECT COD{$this->H} AS C FROM {$this->T} ORDER BY COD{$this->H}"));
        $last = 0;
        foreach ($codes as $c) {
            if ($last && $c - $last > 50) {
                break;
            }
            $last = $c;
        }

        return $last;
    }

    /**
     * La API de DELSOL no mueve el stock: se suma (o resta al deshacer) a mano en F_STO, en el almacén de la ficha.
     * Solo artículos con ficha de stock; los portes no.
     *
     * @param  array<int,array{art:?string,qty:float}>  $items
     */
    private function moveStock(FactusolClient $api, int $year, array $items, int $sign): void
    {
        $by = [];
        foreach ($items as $i) {
            $a = trim((string) ($i['art'] ?? ''));
            if ($a !== '' && strtoupper($a) !== 'PORTE' && (float) ($i['qty'] ?? 0) != 0.0) {
                $by[$a] = ($by[$a] ?? 0) + (float) $i['qty'];
            }
        }
        foreach ($by as $art => $qty) {
            try {
                $rows = $api->table($year, 'F_STO', "ARTSTO = '".str_replace("'", "''", $art)."'");
                $row = collect($rows)->firstWhere('ALMSTO', 'GEN') ?? ($rows[0] ?? null);
                if (! $row) {
                    continue;
                }
                $row['ACTSTO'] = round((float) $row['ACTSTO'] + $sign * $qty, 4);
                $row['DISSTO'] = round((float) $row['DISSTO'] + $sign * $qty, 4);
                $api->update($year, 'F_STO', $row);
                Activity::log('stock_movido', "$art ".($sign > 0 ? '+' : '-')."$qty ({$this->name})");
            } catch (\Throwable $e) {
                Activity::log('stock_error', "$art: ".mb_substr($e->getMessage(), 0, 150));
            }
        }
    }

    public function remove(PanelEntrada $pe): void
    {
        if ($pe->removed_at) {
            throw new RuntimeException('Esta entrada ya se quitó.');
        }
        $this->as((string) ($pe->kind ?: 'entrada'));
        $api = new FactusolClient;
        $year = (int) $pe->year;
        $code = (int) $pe->entry_code;
        $cur = $api->table($year, $this->T, "COD{$this->H} = $code")[0] ?? null;
        if ($cur && (int) ($cur['PRO'.$this->H] ?? 0) !== (int) $pe->codpro) {
            throw new RuntimeException("El documento $code de FactuSOL ya no es el que creó el panel (otro proveedor); no lo borro.");
        }
        if ($cur && $this->kind === 'facturarec' && (int) ($cur['ESTFRE'] ?? 0) !== 0) {
            throw new RuntimeException("La factura recibida $code ya tiene pagos apuntados; quítala en FactuSOL.");
        }
        $old = $api->table($year, $this->TL, "COD{$this->L} = $code");
        $api->delete($year, $this->TL, "COD{$this->L} = $code");
        $api->delete($year, $this->T, "COD{$this->H} = $code");
        if ($pe->created_at && $pe->created_at->gte(self::STOCK_SINCE)) {
            $this->moveStock($api, $year, array_map(fn ($r) => ['art' => (string) ($r['ART'.$this->L] ?? ''), 'qty' => (float) ($r['CAN'.$this->L] ?? 0)], $old), -1);
        }
        if (count($api->table($year, $this->T, "COD{$this->H} = $code")) + count($api->table($year, $this->TL, "COD{$this->L} = $code"))) {
            throw new RuntimeException("No se ha borrado del todo ($this->name $code); revísalo en FactuSOL.");
        }
        $pe->update(['removed_at' => now()]);
        $d = FsDoc::where('type', $this->kind)->where('year', $year)->where('code', $code)->first();
        if ($d) {
            FsDocLine::where('doc_id', $d->id)->delete();
            $d->delete();
        }
        \App\Models\SupplierNote::where('entry_year', $year)->where('entry_code', $code)->where('kind', $this->kind === 'facturarec' ? 'factura' : 'albaran')->update(['entry_year' => null, 'entry_code' => null]);
        Activity::log($this->kind.'_quitada', "{$this->name} $code");
    }

    private function pull(): void
    {
        try {
            (new FactusolDocs)->quick([$this->kind], 10, true);
        } catch (\Throwable $e) {
        }
    }
}
