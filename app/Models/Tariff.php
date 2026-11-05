<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Tariff extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $guarded = [];

    /** @return array<int,Tariff> */
    public static function all_(): array
    {
        return Cache::remember('tariffs', 600, fn () => static::orderBy('code')->get()->keyBy('code')->all());
    }

    public function shortName(): string
    {
        return 'Tarifa '.$this->code;
    }

    /** Descripción sin el prefijo "Tarifa N" */
    public function detail(): string
    {
        return trim(preg_replace('/^tarifa\s*\d+\s*/i', '', $this->name));
    }
}
