<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Lettering extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'account_id',
        'contact_id',
        'code',
        'total_amount',
        'match_type',
        'notes',
        'matched_at',
        'matched_by',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Lettering $lettering) {
            if (empty($lettering->id)) {
                $lettering->id = (string) Str::uuid();
            }
        });

        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->has('currentCompany')) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    app('currentCompany')->id
                );
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'lettering_id');
    }

    /**
     * Generate the next lettering code for a given account (L0001, L0002, ...).
     */
    public static function nextCode(string $accountId): string
    {
        $last = static::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->orderByDesc('code')
            ->value('code');

        if ($last && preg_match('/^L(\d+)$/', $last, $m)) {
            $n = (int) $m[1] + 1;
        } else {
            $n = 1;
        }

        return 'L'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
