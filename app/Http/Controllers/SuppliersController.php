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
            $rows = SupplierConsumption::join('supplier_references as r', 'r.id', '=', 'supplier_consumption.supplier_reference_id')
                ->where('r.supplier_id', $s->id)
                ->selectRaw('supplier_consumption.year as y, sum(supplier_consumption.amount) as a')
                ->groupBy('supplier_consumption.year')->orderBy('y')->pluck('a', 'y');
            $s->byYear = $rows->map(fn ($v) => (float) $v)->all();
            $s->total = array_sum($s->byYear);
            $s->nRefs = SupplierReference::where('supplier_id', $s->id)->count();
            $file = 'img/suppliers/'.strtolower($s->code).'.png';
            $s->logo = '/'.$file;

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

    /** 0 = unidades, 1 = importe */
    private int $mi = 1;

    private function amt(array $r, int $y): float
    {
        return (float) ($r['y'][$y][$this->mi] ?? 0);
    }

    public function show(Request $request, Supplier $supplier)
    {
        $this->guard();
        $this->mi = $request->query('metrica') === 'uds' ? 0 : 1;
        $metrica = $this->mi === 0 ? 'uds' : 'eur';
        $dataAll = $this->load($supplier);
        foreach ($dataAll as &$r0) {
            $r0['tot'] = $this->mi === 0 ? (float) $r0['qty'] : $r0['total'];
        }
        unset($r0);
        $data = $dataAll;
        $years = [];
        foreach ($data as $r) {
            foreach (array_keys($r['y']) as $y) {
                $years[$y] = true;
            }
        }
        $years = array_keys($years);
        sort($years);
        $maxYear = $years ? max($years) : (int) date('Y');
        $focus = (int) $request->query('anio');
        $focus = in_array($focus, $years, true) ? $focus : null;
        $cur = $focus ?? $maxYear;
        $prev = $cur - 1;
        $family = trim((string) $request->query('familia'));
        $families = [];
        foreach ($dataAll as $r) {
            $families[$r['family'] ?: '—'] = true;
        }
        $families = array_keys($families);
        sort($families);
        if ($family !== '') {
            $data = array_filter($data, fn ($r) => ($r['family'] ?: '—') === $family);
        }

        // Importe y nº de referencias por año (con el filtro de familia)
        $byYear = [];
        foreach ($years as $y) {
            $byYear[$y] = ['amount' => 0.0, 'refs' => 0];
        }
        foreach ($data as $r) {
            foreach ($r['y'] as $y => $qa) {
                $a = $qa[$this->mi];
                $byYear[$y]['amount'] += $a;
                if ($a > 0) {
                    $byYear[$y]['refs']++;
                }
            }
        }

        // Por familia (prefijo) y año
        $fam = [];
        foreach ($dataAll as $r) {
            $f = $r['family'] ?: '—';
            foreach ($years as $y) {
                $fam[$f][$y] = ($fam[$f][$y] ?? 0) + $this->amt($r, $y);
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
        $metric = fn ($r) => $focus ? $this->amt($r, $focus) : $r['tot'];
        uasort($top, fn ($a, $b) => $metric($b) <=> $metric($a));
        $pieRefs = [];
        $rest = 0.0;
        $n = 0;
        foreach ($top as $r) {
            $v = $metric($r);
            if ($v <= 0) {
                continue;
            }
            if (++$n <= 10) {
                $pieRefs[] = [$r['ref'], $v];
            } else {
                $rest += $v;
            }
        }
        if ($rest > 0) {
            $pieRefs[] = ['Otras referencias', $rest];
        }
        $top = array_slice($top, 0, 15, true);
        foreach ($top as &$t) {
            $t['metric'] = $metric($t);
        }
        unset($t);

        // Resumen por familia del año elegido (o total) frente al año anterior
        $famRows = [];
        foreach ($dataAll as $r) {
            $f = $r['family'] ?: '—';
            $famRows[$f] ??= ['f' => $f, 'cur' => 0.0, 'prev' => 0.0, 'refs' => 0];
            $v = $focus ? $this->amt($r, $cur) : $r['tot'];
            $famRows[$f]['cur'] += $v;
            $famRows[$f]['prev'] += $focus ? $this->amt($r, $prev) : 0;
            if ($v > 0) {
                $famRows[$f]['refs']++;
            }
        }
        usort($famRows, fn ($a, $b) => $b['cur'] <=> $a['cur']);
        $famRows = array_slice($famRows, 0, 12);

        // Alertas
        $lost = $drop = $dormant = [];
        foreach ($data as $r) {
            $a1 = $this->amt($r, $prev);
            $a2 = $this->amt($r, $cur);
            if ($a1 > 0 && $a2 <= 0) {
                $lost[] = $r + ['impacto' => $a1];
            } elseif ($a1 > 0 && $a2 < $a1 * 0.5) {
                $drop[] = $r + ['impacto' => $a1 - $a2];
            }
            $recent = 0;
            foreach ($years as $y) {
                if ($y >= $prev - 1) {
                    $recent += max(0, $this->amt($r, $y));
                }
            }
            if ($r['total'] >= 100 && $recent <= 0) {
                $dormant[] = $r + ['impacto' => $r['tot']];
            }
        }
        $sort = fn ($a, $b) => $b['impacto'] <=> $a['impacto'];
        usort($lost, $sort);
        usort($drop, $sort);
        usort($dormant, $sort);

        // Listado completo con filtros
        $q = trim((string) $request->query('q'));
        $list = array_filter($data, function ($r) use ($q) {
            if ($q !== '' && stripos($r['ref'].' '.$r['label'], $q) === false) {
                return false;
            }

            return true;
        });
        uasort($list, fn ($a, $b) => $metric($b) <=> $metric($a));
        $page = max(1, (int) $request->query('page', 1));
        $per = 40;
        $totalList = count($list);
        $list = array_slice($list, ($page - 1) * $per, $per, true);

        return view('suppliers.show', compact(
            'supplier', 'years', 'cur', 'prev', 'byYear', 'famTop', 'top', 'lost', 'drop', 'dormant',
            'list', 'q', 'family', 'metrica', 'page', 'per', 'totalList', 'focus', 'maxYear', 'famRows', 'families', 'pieRefs'
        ) + ['nRefs' => count($data), 'logo' => '/img/suppliers/'.strtolower($supplier->code).'.png']);
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
