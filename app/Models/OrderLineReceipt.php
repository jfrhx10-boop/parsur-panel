<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cantidad de una línea de pedido que ya ha llegado al almacén (validación hecha en el panel). */
class OrderLineReceipt extends Model
{
    protected $guarded = [];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
