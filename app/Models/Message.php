<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mensaje del chat entre un cliente (portal) y Parsur (panel). */
class Message extends Model
{
    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Mensajes de clientes sin leer por Parsur. */
    public static function unreadForPanel(): int
    {
        return self::where('from', 'cliente')->whereNull('read_at')->count();
    }
}
