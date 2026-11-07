<?php

namespace App\Http\Controllers;

use App\Models\Machine;

/** Máquinas de clientes con mantenimiento próximo o vencido (según horas que apuntan en el portal): oportunidad de venta. */
class MaintenanceController extends Controller
{
    public function index()
    {
        $machines = Machine::whereNotNull('service_interval')->whereNotNull('hours')->with('customer')->get()
            ->map(function ($m) {
                $m->ss = $m->serviceStatus();

                return $m;
            })->filter(fn ($m) => $m->ss && $m->ss['state'] !== 'ok')->sortBy(fn ($m) => $m->ss['left'])->values();
        $dates = Machine::where(function ($q) {
            $q->whereNotNull('insurance_until')->orWhereNotNull('inspection_until');
        })->with('customer')->get()->map(function ($m) {
            $m->da = $m->dateAlerts();

            return $m;
        })->filter(fn ($m) => $m->da)->values();
        $withHours = Machine::whereNotNull('hours')->count();

        return view('maintenance.index', compact('machines', 'dates', 'withHours'));
    }
}
