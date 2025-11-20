<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Definitions
    |--------------------------------------------------------------------------
    |
    | Define all features available in the application.
    | These should match the slugs in the features database table.
    |
    */

    'features' => [
        'whatsapp_logging' => [
            'name' => 'Registro vía WhatsApp',
            'description' => 'Registra gastos enviando mensajes de WhatsApp',
            'type' => 'binary',
            'premium_only' => true,
            'icon' => '📱',
        ],

        'budgeting_system' => [
            'name' => 'Sistema de Presupuestos',
            'description' => 'Crea y gestiona presupuestos con rollover',
            'type' => 'binary',
            'premium_only' => true,
            'icon' => '💰',
        ],

        'alerts_system' => [
            'name' => 'Sistema de Alertas',
            'description' => 'Recibe notificaciones sobre tu actividad financiera',
            'type' => 'binary',
            'premium_only' => true,
            'icon' => '🔔',
        ],

        'exports' => [
            'name' => 'Exportaciones',
            'description' => 'Exporta tus datos en formato CSV o PDF',
            'type' => 'quota',
            'premium_only' => false, // Available on free with limits
            'limits' => [
                'free' => 2, // 2 exports per month on free plan
                'premium' => null, // Unlimited on premium
            ],
            'icon' => '📥',
        ],

        'exchange_rates' => [
            'name' => 'Conversión de Divisas',
            'description' => 'Soporte multi-moneda con tasas de cambio actualizadas',
            'type' => 'binary',
            'premium_only' => true,
            'icon' => '💱',
        ],

        'payment_insights' => [
            'name' => 'Análisis de Métodos de Pago',
            'description' => 'Estadísticas avanzadas sobre tus métodos de pago',
            'type' => 'binary',
            'premium_only' => true,
            'icon' => '💳',
        ],

        'ai_categorization' => [
            'name' => 'Categorización con IA',
            'description' => 'Categorización automática de transacciones usando inteligencia artificial',
            'type' => 'binary',
            'premium_only' => true,
            'icon' => '🤖',
        ],

        'daily_summaries' => [
            'name' => 'Resúmenes Diarios',
            'description' => 'Recibe resúmenes diarios de tu actividad financiera',
            'type' => 'binary',
            'premium_only' => true,
            'icon' => '📊',
        ],

        'milestone_tracking' => [
            'name' => 'Seguimiento de Hitos',
            'description' => 'Celebra tus logros financieros',
            'type' => 'binary',
            'premium_only' => false, // Available to all
            'icon' => '🏆',
        ],

        'advanced_analytics' => [
            'name' => 'Análisis Avanzado',
            'description' => 'Dashboards y reportes avanzados',
            'type' => 'binary',
            'premium_only' => true,
            'icon' => '📈',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Configurations
    |--------------------------------------------------------------------------
    |
    | Define which features are available on each plan.
    | This is used for seeding and documentation.
    | The source of truth is the database (plan_features table).
    |
    */

    'plans' => [
        'free' => [
            'name' => 'Gratis',
            'features' => [
                'exports' => ['enabled' => true, 'quota' => 2],
                'milestone_tracking' => ['enabled' => true],
            ],
        ],

        'premium_monthly' => [
            'name' => 'Premium Mensual',
            'features' => [
                'whatsapp_logging' => ['enabled' => true],
                'budgeting_system' => ['enabled' => true],
                'alerts_system' => ['enabled' => true],
                'exports' => ['enabled' => true, 'quota' => null], // Unlimited
                'exchange_rates' => ['enabled' => true],
                'payment_insights' => ['enabled' => true],
                'ai_categorization' => ['enabled' => true],
                'daily_summaries' => ['enabled' => true],
                'milestone_tracking' => ['enabled' => true],
                'advanced_analytics' => ['enabled' => true],
            ],
        ],

        'premium_annual' => [
            'name' => 'Premium Anual',
            'features' => [
                'whatsapp_logging' => ['enabled' => true],
                'budgeting_system' => ['enabled' => true],
                'alerts_system' => ['enabled' => true],
                'exports' => ['enabled' => true, 'quota' => null], // Unlimited
                'exchange_rates' => ['enabled' => true],
                'payment_insights' => ['enabled' => true],
                'ai_categorization' => ['enabled' => true],
                'daily_summaries' => ['enabled' => true],
                'milestone_tracking' => ['enabled' => true],
                'advanced_analytics' => ['enabled' => true],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quota Reset Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how quota-based features reset.
    |
    */

    'quota_reset' => [
        'frequency' => 'monthly', // Options: monthly, weekly, daily
        'day_of_month' => 1, // For monthly reset
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Configuration
    |--------------------------------------------------------------------------
    |
    | Configure trial period settings.
    |
    */

    'trial' => [
        'duration_days' => 30,
        'auto_convert' => true, // Automatically convert to free plan after trial
    ],
];