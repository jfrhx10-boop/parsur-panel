<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierConsumption;
use App\Models\SupplierReference;
use Illuminate\Http\Request;

class SuppliersController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
    }

    public function index()
    {
        $this->guard();
        $suppliers = Supplier::orderBy('name')->get()->map(function ($s) {
            $s->total = (float) SupplierConsumption::join('supplier_references as r', 'r.id', '=', 'supplier_consumption.supplier_reference_id')
                ->where('r.supplier_id', $s->id)->sum('amount');

            return $s;
        });

        return view('suppliers.index', ['suppliers' => $suppliers]);
    }

    /** Carga todo el histórico de un proveedor: [ref_id => ['ref','family','y'=>[año=>[q,a]]]] */
    private function load(Supplier $supplier): array
    {
        $refs = SupplierReference::where('supplier_id', $supplier->id)->get()->keyBy('id');
        $rows = SupplierConsumption::whereIn('supplier_reference_id', $refs->keys())->get();
        $data = [];
        foreach ($refs as $id => $r) {
            $data[$id] = ['id' => $id, 'ref' => $r->ref, 'family' => $r->family, 'label' => $r->label, 'y' => [], 'total' => 0.0, 'qty' => 0];
        }
        foreach ($rows as $c) {
            $data[$c->supplier_reference_id]['y'][$c->year] = [(int) $c->quantity, (float) $c->amount];
            $data[$c->supplier_reference_id]['total'] += (float) $c->amount;
            $data[$c->supplier_reference_id]['qty'] += (int) $c->quantity;
        }

        return $data;
    }

    private static function amt(array $r, int $y): float
    {
        return $r['y'][$y][1] ?? 0.0;
    }

    public function show(Request $request, Supplier $supplier)
    {
        $this->guard();
        $data = $this->load($supplier);
        $years = [];
        foreach ($data as $r) {
            foreach (array_keys($r['y']) as $y) {
                $years[$y] = true;
            }
        }
        $years = array_keys($years);
        sort($years);
        $cur = $years ? max($years) : (int) date('Y');
        $prev = $cur - 1;

        // Importe y nº de referencias por año
        $byYear = [];
        foreach ($years as $y) {
            $byYear[$y] = ['amount' => 0.0, 'refs' => 0];
        }
        foreach ($data as $r) {
            foreach ($r['y'] as $y => [$q, $a]) {
                $byYear[$y]['amount'] += $a;
                if ($a > 0) {
                    $byYear[$y]['refs']++;
                }
            }
        }

        // Por familia (prefijo) y año
        $fam = [];
        foreach ($data as $r) {
            $f = $r['family'] ?: '—';
            foreach ($years as $y) {
                $fam[$f][$y] = ($fam[$f][$y] ?? 0) + self::amt($r, $y);
            }
        }
        uasort($fam, fn ($a, $b) => array_sum($b) <=> array_sum($a));
        $famTop = array_slice($fam, 0, 6, true);
        if (count($fam) > 6) {
            foreach (array_slice($fam, 6, null, true) as $vals) {
                foreach ($years as $y) {
                    $famTop['Otras'][$y] = ($famTop['Otras'][$y] ?? 0) + ($vals[$y] ?? 0);
                }
            }
        }

        // Top referencias por importe total
        $top = $data;
        uasort($top, fn ($a, $b) => $b['total'] <=> $a['total']);
        $top = array_slice($top, 0, 15, true);

        // Alertas
        $lost = $drop = $dormant = [];
        foreach ($data as $r) {
            $a1 = self::amt($r, $prev);
            $a2 = self::amt($r, $cur);
            if ($a1 > 0 && $a2 <= 0) {
                $lost[] = $r + ['impacto' => $a1];
            } elseif ($a1 > 0 && $a2 < $a1 * 0.5) {
                $drop[] = $r + ['impacto' => $a1 - $a2];
            }
            $recent = 0;
            foreach ($years as $y) {
                if ($y >= $prev - 1) {
                    $recent += max(0, self::amt($r, $y));
                }
            }
            if ($r['total'] >= 100 && $recent <= 0) {
                $dormant[] = $r + ['impacto' => $r['total']];
            }
        }
        $sort = fn ($a, $b) => $b['impacto'] <=> $a['impacto'];
        usort($lost, $sort);
        usort($drop, $sort);
        usort($dormant, $sort);

        // Listado completo con filtros
        $q = trim((string) $request->query('q'));
        $family = trim((string) $request->query('familia'));
        $list = array_filter($data, function ($r) use ($q, $family) {
            if ($family !== '' && ($r['family'] ?: '—') !== $family) {
                return false;
            }
            if ($q !== '' && stripos($r['ref'].' '.$r['label'], $q) === false) {
                return false;
            }

            return true;
        });
        uasort($list, fn ($a, $b) => $b['total'] <=> $a['total']);
        $page = max(1, (int) $request->query('page', 1));
        $per = 40;
        $totalList = count($list);
        $list = array_slice($list, ($page - 1) * $per, $per, true);

        return view('suppliers.show', compact(
            'supplier', 'years', 'cur', 'prev', 'byYear', 'famTop', 'top', 'lost', 'drop', 'dormant',
            'list', 'q', 'family', 'page', 'per', 'totalList'
        ) + ['families' => array_keys($fam), 'nRefs' => count($data)]);
    }

    public function reference(Supplier $supplier, SupplierReference $reference)
    {
        $this->guard();
        abort_unless($reference->supplier_id === $supplier->id, 404);
        $rows = SupplierConsumption::where('supplier_reference_id', $reference->id)->orderBy('year')->get();
        $years = $rows->pluck('year')->all();
        $allYears = $years ? range(min($years), max($years)) : [];

        return view('suppliers.reference', [
            'supplier' => $supplier, 'reference' => $reference,
            'rows' => $rows->keyBy('year'), 'years' => $allYears,
        ]);
    }
}
