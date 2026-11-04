<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Ajustes del panel. Los valores se guardan cifrados con la clave de la aplicación.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = static::find($key);
        if (! $row || $row->value === null || $row->value === '') {
            return $default;
        }
        try {
            return Crypt::decryptString($row->value);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => ($value === null || $value === '') ? null : Crypt::encryptString($value)]
        );
    }

    public static function has(string $key): bool
    {
        return static::get($key) !== null;
    }
}
