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

    public function checks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MachineCheck::class)->orderByDesc('date')->orderByDesc('id');
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MachineExpense::class)->orderByDesc('date')->orderByDesc('id');
    }

    public function reminders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MachineReminder::class)->whereNull('done_at')->orderBy('due_date')->orderBy('due_hours');
    }

    /**
     * Todos los avisos de la máquina en un formato único (para el portal y el panel):
     * mantenimiento por horas, seguro/revisión y avisos propios. Solo los que toca atender (próximos o vencidos).
     * @return array<int,array{kind:string,text:string,state:string,when:string,reminder:?MachineReminder}>
     */
    public function alerts(bool $all = false): array
    {
        $out = [];
        if ($ss = $this->serviceStatus()) {
            if ($all || $ss['state'] !== 'ok') {
                $out[] = ['kind' => 'mantenimiento', 'text' => 'Mantenimiento a las '.number_format($ss['next_at'], 0, ',', '.').' h', 'state' => $ss['state'],
                    'when' => $ss['left'] > 0 ? 'faltan '.number_format($ss['left'], 0, ',', '.').' h' : 'vencido hace '.number_format(-$ss['left'], 0, ',', '.').' h', 'reminder' => null];
            }
        }
        foreach ($this->dateAlerts() as $a) {
            $out[] = ['kind' => 'fecha', 'text' => $a['label'], 'state' => $a['state'], 'when' => $a['days'] < 0 ? 'venció el '.$a['date']->format('d/m/Y') : 'vence el '.$a['date']->format('d/m/Y'), 'reminder' => null];
        }
        foreach ($this->reminders as $r) {
            $st = $r->state($this->hours);
            if ($all || $st !== 'ok') {
                $when = $r->due_date ? ($r->due_date->isPast() && ! $r->due_date->isToday() ? 'era el ' : 'el ').$r->due_date->format('d/m/Y') : ($r->due_hours !== null ? 'a las '.number_format($r->due_hours, 0, ',', '.').' h' : '');
                $out[] = ['kind' => 'aviso', 'text' => $r->text, 'state' => $st, 'when' => $when, 'reminder' => $r];
            }
        }
        usort($out, fn ($a, $b) => ['vencido' => 0, 'proximo' => 1, 'ok' => 2][$a['state']] <=> ['vencido' => 0, 'proximo' => 1, 'ok' => 2][$b['state']]);

        return $out;
    }

    /** Dónde está: obra · lugar · para quién. */
    public function whereLabel(): ?string
    {
        $parts = array_filter([$this->site, $this->location, $this->site_client ? 'para '.$this->site_client : null]);

        return $parts ? implode(' · ', $parts) : null;
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
