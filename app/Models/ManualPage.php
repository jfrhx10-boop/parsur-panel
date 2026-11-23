<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Texto de una página de un manual, para buscar referencias y palabras. */
class ManualPage extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function manual(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Manual::class);
    }
}
