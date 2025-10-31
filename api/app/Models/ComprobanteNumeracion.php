<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteNumeracion extends Model
{
    protected $table = 'comprobantes_numeracion';

    protected $fillable = [
        'tipo_comprobante',
        'punto_venta',
        'ultimo_numero',
    ];

    /**
     * Genera el próximo número de comprobante para un tipo dado.
     * 
     * @param string $tipoComprobante
     * @param string $puntoVenta
     * @return string Número de comprobante formateado (ej: "0001-00000123")
     */
    public static function generarNumero(string $tipoComprobante, string $puntoVenta = '0001'): string
    {
        // Obtener o crear el registro de numeración
        $numeracion = self::firstOrCreate(
            [
                'tipo_comprobante' => $tipoComprobante,
                'punto_venta' => $puntoVenta,
            ],
            [
                'ultimo_numero' => 0,
            ]
        );

        // Incrementar el número
        $numeracion->ultimo_numero++;
        $numeracion->save();

        // Formatear: PPPP-NNNNNNNN (4 dígitos punto de venta - 8 dígitos número)
        return sprintf('%s-%08d', $puntoVenta, $numeracion->ultimo_numero);
    }

    /**
     * Obtiene el próximo número sin guardarlo (para preview).
     * 
     * @param string $tipoComprobante
     * @param string $puntoVenta
     * @return string
     */
    public static function previsualizarNumero(string $tipoComprobante, string $puntoVenta = '0001'): string
    {
        $numeracion = self::where('tipo_comprobante', $tipoComprobante)
            ->where('punto_venta', $puntoVenta)
            ->first();

        $proximoNumero = $numeracion ? $numeracion->ultimo_numero + 1 : 1;

        return sprintf('%s-%08d', $puntoVenta, $proximoNumero);
    }
}
