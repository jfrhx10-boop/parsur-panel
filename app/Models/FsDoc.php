<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FsDoc extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date', 'synced_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(FsDocLine::class, 'doc_id')->orderBy('pos');
    }

    public function typeLabel(): string
    {
        return ['pedido' => 'Pedido', 'albaran' => 'Albarán', 'factura' => 'Factura', 'entrada' => 'Entrada'][$this->type] ?? $this->type;
    }

    /** Texto del estado según el tipo (los códigos son los de FactuSOL). */
    public function statusLabel(): string
    {
        $map = [
            'pedido' => [0 => 'Pendiente', 1 => 'Parcial', 2 => 'Servido', 4 => 'Anulado'],
            'albaran' => [0 => 'Sin facturar', 1 => 'Facturado'],
            'factura' => [0 => 'Pendiente de cobro', 1 => 'Cobro parcial', 2 => 'Cobrada'],
            'entrada' => [0 => 'Recibida', 1 => 'Facturada'],
        ];

        return $map[$this->type][$this->status] ?? 'Estado '.$this->status;
    }

    public function statusClass(): string
    {
        return match (true) {
            $this->type === 'pedido' && $this->status === 2, $this->type === 'albaran' && $this->status === 1, $this->type === 'factura' && $this->status === 2 => 'ok',
            $this->type === 'pedido' && $this->status === 4 => 'bad',
            $this->type === 'entrada' => 'ok',
            default => 'warn',
        };
    }

    public function number(): string
    {
        return $this->year.'/'.$this->code;
    }
}
