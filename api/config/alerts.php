<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración de Alertas del Sistema
    |--------------------------------------------------------------------------
    |
    | Este archivo contiene la configuración para el sistema de alertas
    | de negocio del CRM. Puedes ajustar los umbrales de tiempo y
    | prioridades según las necesidades del negocio.
    |
    */

    /**
     * Días de preaviso para alertas de cheques próximos a vencer
     */
    'cheques' => [
        'dias_preaviso_vencimiento' => env('ALERT_CHEQUES_DIAS_PREAVISO', 7),
        
        // Niveles de alerta según días restantes
        'niveles' => [
            'critico' => 2,   // 0-2 días: crítico
            'alto' => 5,      // 3-5 días: alto
            'moderado' => 7,  // 6-7 días: moderado
        ],
    ],

    /**
     * Días de preaviso para alertas de pedidos próximos a entregar
     */
    'pedidos' => [
        'dias_preaviso_entrega' => env('ALERT_PEDIDOS_DIAS_PREAVISO', 3),
        
        // Niveles de alerta según días restantes
        'niveles' => [
            'critico' => 1,   // 0-1 días: crítico
            'alto' => 2,      // 2 días: alto
            'moderado' => 3,  // 3 días: moderado
        ],
        
        // Estados que consideramos para alertas de pedidos pendientes
        'estados_alertables' => ['pendiente', 'en_proceso'],
    ],

    /**
     * Configuración general del sistema de alertas
     */
    'general' => [
        // Límite de alertas por tipo en el resumen
        'limite_por_tipo' => 100,
        
        // Paginación por defecto en listados de alertas
        'paginacion_default' => 15,
        
        // Cache (en minutos) para el resumen de alertas
        'cache_resumen_minutos' => 5,
    ],
];
