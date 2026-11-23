<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Equivalencia apuntada a mano entre una referencia de un manual y otra (nuestra o de otro proveedor). Solo informativa. */
class ManualEquiv extends Model
{
    protected $guarded = [];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
