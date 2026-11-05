<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAlert extends Model
{
    protected $guarded = [];

    public const KINDS = ['pago' => 'Pago / cobro', 'descuento' => 'Descuento / precio', 'facturacion' => 'Facturación / envío', 'aviso' => 'Aviso general'];

    /** Clasifica un texto de observaciones por palabras clave. */
    public static function classify(string $t): string
    {
        $u = mb_strtoupper($t);
        if (preg_match('/DESCUENTO|DTO|\bDCTO\b|%|PRECIO ESPECIAL|TARIFA|NETO|OFERTA/u', $u)) {
            return 'descuento';
        }
        if (preg_match('/CONTADO|RIGUROSO|PAGO|PAGA|COBR|DEUDA|DEBE|BLOQUE|NO SERVIR|NO VENDER|MOROS|IMPAGO|PENDIENTE|TRANSFERENCIA|PAGAR|EFECTIVO|TARJETA|CHEQUE|PAGAR[ÉE]|GIRO|RECIBO/u', $u)) {
            return 'pago';
        }
        if (preg_match('/FACTUR|\bFRA\b|EMAIL|E-MAIL|MAIL|ENVIAR|ENV[IÍ]O|MENSUAL|AL MES|PORTES|AGENCIA|RECOGE|ALBAR[AÁ]N|FIRMA|PEDIDO/u', $u)) {
            return 'facturacion';
        }

        return 'aviso';
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
