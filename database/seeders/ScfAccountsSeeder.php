<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class ScfAccountsSeeder extends Seeder
{
    public function __construct(protected ?string $companyId = null)
    {
    }

    public function run(): void
    {
        if (! $this->companyId) {
            throw new InvalidArgumentException('Le companyId est requis pour ScfAccountsSeeder.');
        }

        $accounts = [
            ['code' => '101', 'label' => 'Capital social', 'type' => 'equity'],
            ['code' => '106', 'label' => 'Réserves', 'type' => 'equity'],
            ['code' => '119', 'label' => 'Report à nouveau', 'type' => 'equity'],

            ['code' => '211', 'label' => 'Terrains', 'type' => 'asset'],
            ['code' => '213', 'label' => 'Constructions', 'type' => 'asset'],
            ['code' => '215', 'label' => 'Installations techniques', 'type' => 'asset'],

            ['code' => '300', 'label' => 'Stocks', 'type' => 'asset'],
            ['code' => '370', 'label' => 'Stocks de marchandises', 'type' => 'asset'],

            ['code' => '401', 'label' => 'Fournisseurs', 'type' => 'liability'],
            ['code' => '411', 'label' => 'Clients', 'type' => 'asset'],
            ['code' => '421', 'label' => 'Personnel', 'type' => 'liability'],
            ['code' => '441', 'label' => 'État - impôts et taxes', 'type' => 'liability'],
            ['code' => '445', 'label' => 'TVA', 'type' => 'liability'],
            ['code' => '4451', 'label' => 'TVA collectée', 'type' => 'vat_collected'],
            ['code' => '4456', 'label' => 'TVA déductible', 'type' => 'vat_deductible'],

            ['code' => '512', 'label' => 'Banques', 'type' => 'asset'],
            ['code' => '531', 'label' => 'Caisse', 'type' => 'asset'],

            ['code' => '601', 'label' => 'Achats marchandises', 'type' => 'expense'],
            ['code' => '604', 'label' => 'Achats études et prestations', 'type' => 'expense'],
            ['code' => '606', 'label' => 'Achats non stockés', 'type' => 'expense'],
            ['code' => '611', 'label' => 'Sous-traitance', 'type' => 'expense'],
            ['code' => '613', 'label' => 'Locations', 'type' => 'expense'],
            ['code' => '616', 'label' => 'Assurances', 'type' => 'expense'],
            ['code' => '622', 'label' => 'Honoraires', 'type' => 'expense'],
            ['code' => '625', 'label' => 'Déplacements', 'type' => 'expense'],
            ['code' => '626', 'label' => 'Frais télécoms', 'type' => 'expense'],
            ['code' => '631', 'label' => 'Impôts et taxes', 'type' => 'expense'],
            ['code' => '641', 'label' => 'Rémunérations du personnel', 'type' => 'expense'],

            ['code' => '701', 'label' => 'Ventes produits finis', 'type' => 'revenue'],
            ['code' => '706', 'label' => 'Prestations de services', 'type' => 'revenue'],
            ['code' => '708', 'label' => 'Produits des activités annexes', 'type' => 'revenue'],
        ];

        foreach ($accounts as $account) {
            Account::query()->updateOrCreate(
                [
                    'company_id' => $this->companyId,
                    'code' => $account['code'],
                ],
                [
                    'label' => $account['label'],
                    'label_ar' => null,
                    'class' => (int) substr($account['code'], 0, 1),
                    'type' => $account['type'],
                    'parent_code' => strlen($account['code']) > 3
                        ? substr($account['code'], 0, 3)
                        : null,
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}