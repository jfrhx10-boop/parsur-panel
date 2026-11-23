<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nota de consulta sobre una página de un manual. */
class ManualNote extends Model
{
    protected $guarded = [];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manual(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Manual::class);
    }
}
