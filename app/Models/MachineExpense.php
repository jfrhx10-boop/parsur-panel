<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Gasto que el cliente apunta en una máquina (otros proveedores, talleres…), con su factura si la sube. */
class MachineExpense extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date'];

    public function machine(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
