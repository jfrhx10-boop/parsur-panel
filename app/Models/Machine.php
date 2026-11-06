<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Machine extends Model
{
    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
