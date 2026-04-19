<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Journal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class JournalSeeder extends Seeder
{
    public function __construct(protected ?string $companyId = null) {}

    public function run(): void
    {
        if (! $this->companyId) {
            throw new InvalidArgumentException('Le companyId est requis pour JournalSeeder.');
        }

        $defaults = [
            [
                'code' => 'VT',
                'label' => 'Journal des ventes',
                'label_ar' => 'يومية المبيعات',
                'type' => 'sales',
                'counterpart_code' => null,
                'position' => 10,
            ],
            [
                'code' => 'AC',
                'label' => 'Journal des achats',
                'label_ar' => 'يومية المشتريات',
                'type' => 'purchase',
                'counterpart_code' => null,
                'position' => 20,
            ],
            [
                'code' => 'BQ',
                'label' => 'Journal de banque',
                'label_ar' => 'يومية البنك',
                'type' => 'bank',
                'counterpart_code' => '512',
                'position' => 30,
            ],
            [
                'code' => 'CA',
                'label' => 'Journal de caisse',
                'label_ar' => 'يومية الصندوق',
                'type' => 'cash',
                'counterpart_code' => '531',
                'position' => 40,
            ],
            [
                'code' => 'OD',
                'label' => 'Opérations diverses',
                'label_ar' => 'عمليات متنوعة',
                'type' => 'misc',
                'counterpart_code' => null,
                'position' => 50,
            ],
        ];

        $resolveAccountId = function (?string $code): ?string {
            if ($code === null) {
                return null;
            }

            /** @var Account|null $account */
            $account = Account::withoutGlobalScopes()
                ->where('company_id', $this->companyId)
                ->where('code', 'LIKE', $code.'%')
                ->orderBy('code')
                ->first();

            return $account?->id;
        };

        foreach ($defaults as $data) {
            /** @var Model $journal */
            Journal::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $this->companyId,
                    'code' => $data['code'],
                ],
                [
                    'label' => $data['label'],
                    'label_ar' => $data['label_ar'],
                    'type' => $data['type'],
                    'counterpart_account_id' => $resolveAccountId($data['counterpart_code']),
                    'is_system' => true,
                    'is_active' => true,
                    'position' => $data['position'],
                ]
            );
        }
    }
}
