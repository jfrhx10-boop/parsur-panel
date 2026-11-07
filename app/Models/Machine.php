<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Machine extends Model
{
    protected $guarded = [];

    protected $casts = ['hours_at' => 'date', 'last_service_at' => 'date', 'insurance_until' => 'date', 'inspection_until' => 'date'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MachineLog::class)->orderByDesc('date')->orderByDesc('id');
    }

    /** Estado del mantenimiento por horas: null si no hay datos. */
    public function serviceStatus(): ?array
    {
        if (! $this->service_interval || $this->hours === null) {
            return null;
        }
        $since = max(0, (int) $this->hours - (int) ($this->last_service_hours ?? 0));
        $left = (int) $this->service_interval - $since;
        $pct = min(100, (int) round($since / $this->service_interval * 100));
        $state = $left <= 0 ? 'vencido' : ($left <= max(25, (int) ($this->service_interval * 0.15)) ? 'proximo' : 'ok');

        return ['since' => $since, 'left' => $left, 'pct' => $pct, 'state' => $state, 'next_at' => (int) ($this->last_service_hours ?? 0) + (int) $this->service_interval];
    }

    /** Avisos de fechas (seguro, revisión) que vencen en 30 días o ya vencieron. */
    public function dateAlerts(): array
    {
        $out = [];
        foreach (['insurance_until' => 'Seguro', 'inspection_until' => 'Revisión / ITV'] as $f => $label) {
            if ($this->$f) {
                $d = \Carbon\Carbon::parse($this->$f);
                $days = (int) now()->startOfDay()->diffInDays($d, false);
                if ($days <= 30) {
                    $out[] = ['label' => $label, 'date' => $d, 'days' => $days, 'state' => $days < 0 ? 'vencido' : 'proximo'];
                }
            }
        }

        return $out;
    }

    /** Toneladas: las guardadas a mano o, si no, la estimación por el modelo. */
    public function tonsEstimate(): ?float
    {
        if ($this->tons !== null) {
            return (float) $this->tons;
        }

        return \App\Services\MachineSize::tons($this->brand, $this->model, $this->type);
    }

    /** "Compacta", "Media" o "Pesada" (null si no se sabe). */
    public function size(): ?string
    {
        return \App\Services\MachineSize::sizeOf($this->tonsEstimate());
    }

    public function sizeLabel(): ?string
    {
        return \App\Services\MachineSize::label($this->tonsEstimate());
    }

    public function label(): string
    {
        return trim(($this->brand ?? '').' '.$this->model);
    }
}
