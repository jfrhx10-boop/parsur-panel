<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FsDocLine extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function doc(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FsDoc::class, 'doc_id');
    }
}
