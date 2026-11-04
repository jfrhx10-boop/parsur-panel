<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Registro sencillo de quién hace qué en el panel. */
class Activity
{
    public static function log(string $action, ?string $detail = null, ?int $userId = null): void
    {
        DB::table('activity_log')->insert([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'detail' => $detail ? mb_substr($detail, 0, 250) : null,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
