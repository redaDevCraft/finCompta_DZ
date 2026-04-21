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
            'invoicing',
            'contacts',
            'basic_reports',
            'purchase_orders',
        ],
        // Pro: growing PME.
        'pro' => [
            'invoicing',
            'contacts',
            'journal_entries',
            'basic_reports',
            'advanced_reports',
            'bank_accounts',
            'ocr',
            'purchase_orders',
            'multi_currency',
            'coa_customization',
        ],
        'enterprise' => ['*'],
        'entreprise' => ['*'],
    ],
];

