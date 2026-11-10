<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FsSupplier extends Model
{
    protected $guarded = [];

    public function entries(): HasMany
    {
        return $this->hasMany(FsDoc::class, 'codpro', 'codpro')->where('type', 'entrada')->orderByDesc('date')->orderByDesc('code');
    }

    public function displayName(): string
    {
        return trim((string) $this->name) !== '' ? $this->name : (string) $this->trade_name;
    }
}
