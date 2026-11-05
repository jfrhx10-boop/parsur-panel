<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAlert extends Model
{
    protected $guarded = [];

    public const KINDS = ['pago' => 'Pago / cobro', 'descuento' => 'Descuento / precio', 'facturacion' => 'Facturación', 'entrega' => 'Entrega / dirección', 'contacto' => 'Contacto', 'aviso' => 'Aviso general'];

    /** Clasifica un texto de observaciones por palabras clave (lo de cobro manda sobre lo demás). */
    public static function classify(string $t): string
    {
        $u = mb_strtoupper($t);
        if (preg_match('/CONTADO|RIGUROSO|DEVUEL|IMPAG|DEUDA|DEBE\b|BLOQUE|NO SERVIR|NO VENDER|MOROS|NO FIAR|SIN CREDITO|SIN CRÉDITO|COBRAR ANTES|PAGO POR ADELANTADO|PREPAGO/u', $u)) {
            return 'pago';
        }
        if (preg_match('/DESCUENTO|\bDTO\b|\bDCTO\b|%|PRECIO ESPECIAL|PRECIO NETO|OFERTA|MARGEN/u', $u)) {
            return 'descuento';
        }
        if (preg_match('/FACTUR|\bFRA\b|FRAS\b|EMAIL|E-MAIL|MAIL|MENSUAL|AL MES|CIF|NIF|RAZON SOCIAL|RAZÓN SOCIAL|VENCIMIENTO|PAGAR[ÉE]|CONFIRMING|TRANSFERENCIA|RECIBO|GIRO|TARJETA|EFECTIVO|CHEQUE/u', $u)) {
            return 'facturacion';
        }
        if (preg_match('/ENTREGA|DIRECCI[OÓ]N|\bFINCA\b|\bCTRA\b|CARRETERA|POL[IÍ]G|\bPK\b|\bKM\b|\bNAVE\b|\bCALLE\b|\bC\/|\bAVDA\b|AVENIDA|CAMINO|PARCELA|PORTES|AGENCIA|RECOGE|HORARIO|CERRADO|ABIERTO|OBRA\b|CANTERA/u', $u)) {
            return 'entrega';
        }
        if (preg_match('/\d{3}[\s.\-]?\d{2,3}[\s.\-]?\d{2,3}[\s.\-]?\d{0,2}/u', $u) || preg_match('/@/', $u) || preg_match('/\b(ENCARGADO|JEFE|MEC[AÁ]NICO|GERENTE|ADMINISTRACI[OÓ]N|CONTABILIDAD|COMPRAS|TALLER|TEL[EÉ]F|M[OÓ]VIL|LLAMAR|PREGUNTAR POR|HABLAR CON)\b/u', $u)) {
            return 'contacto';
        }
        if (preg_match('/PAGO|PAGA\b|COBR|PENDIENTE|PAGAR/u', $u)) {
            return 'pago';
        }

        return 'aviso';
    }

    /** Teléfono apuntado en el aviso (para el botón de WhatsApp). */
    public function phone(): ?string
    {
        if (preg_match('/(\d{3}[\s.\-]?\d{2,3}[\s.\-]?\d{2,3}[\s.\-]?\d{0,2})/u', $this->text, $m)) {
            $t = preg_replace('/\D+/', '', $m[1]);

            return strlen($t) >= 9 ? $t : null;
        }

        return null;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
