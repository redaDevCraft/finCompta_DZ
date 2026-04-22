<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default plan feature matrix
    |--------------------------------------------------------------------------
    |
    | Database overrides (table: plan_features) are primary at runtime.
    | These config values are only a fallback when a plan has no override rows.
    |
    */
    'defaults' => [
        // Starter: auto-entrepreneur / very small businesses.
        'starter' => [
            'invoices',
            'quotes',
            'expenses',
            'basic_reports',
            'invoice_payments',
        ],
        // Pro: growing PME.
        'pro' => [
            'invoices',
            'quotes',
            'expenses',
            'basic_reports',
            'invoice_payments',
            'advanced_reports',
            'bank_accounts',
            'analytic_accounting',
            'multi_currency',
            'management_predictions',
            'auto_counterpart_rules',
        ],
        'enterprise' => ['*'],
        'entreprise' => ['*'],
    ],
];

