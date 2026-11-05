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
        return ['pedido' => 'Pedido', 'albaran' => 'Albarán', 'factura' => 'Factura', 'entrada' => 'Entrada', 'presupuesto' => 'Presupuesto'][$this->type] ?? $this->type;
    }

    /** Texto del estado según el tipo (los códigos son los de FactuSOL). */
    public function statusLabel(): string
    {
        $map = [
            'pedido' => [0 => 'Pendiente', 1 => 'Parcial', 2 => 'Servido', 4 => 'Anulado'],
            'albaran' => [0 => 'Sin facturar', 1 => 'Facturado'],
            'factura' => [0 => 'Pendiente de cobro', 1 => 'Cobro parcial', 2 => 'Cobrada'],
            'entrada' => [0 => 'Recibida', 1 => 'Facturada'],
            'presupuesto' => [0 => 'Pendiente', 1 => 'Aceptado', 2 => 'Rechazado'],
        ];

        return $map[$this->type][$this->status] ?? 'Estado '.$this->status;
    }

    public function statusClass(): string
    {
        return match (true) {
            $this->type === 'pedido' && $this->status === 2, $this->type === 'albaran' && $this->status === 1, $this->type === 'factura' && $this->status === 2 => 'ok',
            $this->type === 'pedido' && $this->status === 4 => 'bad',
            $this->type === 'entrada' => 'ok',
            $this->type === 'presupuesto' && $this->status === 1 => 'ok',
            $this->type === 'presupuesto' && $this->status === 2 => 'bad',
            default => 'warn',
        };
    }

    /** Teléfono apuntado en el nombre cuando el presupuesto es de un cliente sin ficha ("661 94 52 23 JOSE"). */
    public function phoneInName(): ?string
    {
        if (preg_match('/(\d[\d\s]{7,}\d)/', (string) $this->customer_name, $m)) {
            $t = preg_replace('/\D+/', '', $m[1]);

            return strlen($t) >= 9 ? $t : null;
        }

        return null;
    }

    public function number(): string
    {
        return $this->year.'/'.$this->code;
    }
}
