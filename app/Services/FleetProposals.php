<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\Setting;

/**
 * Máquinas que aparecen en la referencia de documentos de FactuSOL (presupuesto, pedido, albarán) y todavía no están
 * en la flota del cliente. El nº de serie se toma de las observaciones del documento. Nunca añade nada solo.
 */
class FleetProposals
{
    private static function norm(?string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(ArticleCategories::normalize((string) $s)));
    }

    public static function serialFrom(?string $notes): ?string
    {
        $n = trim((string) $notes);
        if ($n === '') {
            return null;
        }
        if (preg_match('/(?:n[º°o.]?\s*(?:de\s*)?serie|n\/s|s\/n|serie|serial|bastidor)\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\/]{4,})/iu', $n, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('/^[A-Z0-9][A-Z0-9\-\/]{5,}$/i', $n) && preg_match('/\d/', $n)) {
            return strtoupper($n);
        }

        return null;
    }

    public static function ignored(Customer $c): array
    {
        return (array) json_decode((string) Setting::get('fleet_ign_'.$c->id, '[]'), true);
    }

    public static function ignore(Customer $c, string $key): void
    {
        $l = self::ignored($c);
        $l[] = $key;
        Setting::put('fleet_ign_'.$c->id, json_encode(array_values(array_unique($l))));
    }

    public static function key(string $brand, string $model, ?string $serial): string
    {
        return $serial ? 'S:'.self::norm($serial) : 'M:'.self::norm($brand.$model);
    }

    /** @return array<int,array{brand:string,model:string,serial:?string,doc:string,key:string}> */
    public static function for(Customer $c, ?int $fromYear = null, int $limit = 300): array
    {
        if (! $c->codcli) {
            return [];
        }
        $have = $c->machines;
        $haveSerial = $have->pluck('serial')->filter()->map(fn ($x) => self::norm($x))->all();
        $haveModel = $have->map(fn ($m) => self::norm(($m->brand ?? '').$m->model))->all();
        $ign = self::ignored($c);
        $out = [];
        $docs = FsDoc::where('codcli', $c->codcli)->whereIn('type', ['presupuesto', 'pedido', 'albaran'])
            ->where('year', '>=', $fromYear ?? ((int) date('Y') - 1))->whereNotNull('ref')->where('ref', '!=', '')
            ->orderByDesc('date')->orderByDesc('code')->limit($limit)->get(['type', 'year', 'code', 'ref', 'notes', 'date']);
        foreach ($docs as $d) {
            $m = FleetParser::fromRef((string) $d->ref);
            $serial = self::serialFrom($d->notes);
            if (! $m || trim((string) $m['model']) === '') {
                continue;
            }
            $brand = trim((string) ($m['brand'] ?? ''));
            if ($brand === '' && ! $serial) {
                continue; // sin marca ni serie es demasiado dudoso
            }
            $model = trim((string) $m['model']);
            $key = self::key($brand, $model, $serial);
            if (isset($out[$key])) {
                $out[$key]['n']++; // la misma máquina sale en más documentos
                if (! $out[$key]['serial'] && $serial) {
                    $out[$key]['serial'] = $serial;
                }

                continue;
            }
            if (in_array($key, $ign, true)) {
                continue;
            }
            $nm = self::norm($brand.$model);
            if (($serial && in_array(self::norm($serial), $haveSerial, true)) || in_array($nm, $haveModel, true)) {
                continue;
            }
            foreach ($haveModel as $hm) {
                if ($hm !== '' && $nm !== '' && (str_contains($hm, self::norm($model)) || str_contains($nm, $hm)) && ! $serial) {
                    continue 2; // ya tiene una máquina parecida y no hay serie distinto
                }
            }
            $out[$key] = ['brand' => $brand, 'model' => $model, 'serial' => $serial, 'type' => $m['type'] ?? null, 'doc' => ['presupuesto' => 'Presupuesto', 'pedido' => 'Pedido', 'albaran' => 'Albarán'][$d->type].' '.$d->year.'/'.$d->code, 'key' => $key,
                'n' => 1, 'last' => $d->date?->format('d/m/Y'), 'ref' => (string) $d->ref];
        }

        return array_values($out);
    }

    /**
     * Cruce completo: todas las máquinas que salen en documentos de FactuSOL (desde $fromYear) y no están en la flota de su cliente.
     * @return array<int,array{customer:Customer,items:array}>
     */
    public static function all(int $fromYear): array
    {
        $cods = FsDoc::whereIn('type', ['presupuesto', 'pedido', 'albaran'])->where('year', '>=', $fromYear)
            ->whereNotNull('ref')->where('ref', '!=', '')->whereNotNull('codcli')->distinct()->pluck('codcli');
        $out = [];
        foreach ($cods->chunk(400) as $chunk) {
            foreach (Customer::whereIn('codcli', $chunk)->with('machines')->orderBy('name')->get() as $c) {
                $items = self::for($c, $fromYear, 1500);
                if ($items) {
                    $out[] = ['customer' => $c, 'items' => $items];
                }
            }
        }
        usort($out, fn ($a, $b) => strcasecmp($a['customer']->displayName(), $b['customer']->displayName()));

        return $out;
    }
}
