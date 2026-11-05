<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BrandLogo extends Model
{
    protected $guarded = [];

    public static function slugFor(?string $brand): string
    {
        return Str::slug(Str::ascii((string) $brand));
    }

    /** @return array<string,string> slug => nombre */
    public static function available(): array
    {
        return Cache::remember('brand_logos', 3600, fn () => static::query()->pluck('name', 'slug')->all());
    }

    public static function forget(): void
    {
        Cache::forget('brand_logos');
    }
}
