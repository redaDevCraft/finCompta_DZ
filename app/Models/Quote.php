<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Quote extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'contact_id',
        'number',
        'status',
        'issue_date',
        'expiry_date',
        'currency_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'notes',
        'reference',
        'created_by_user_id',
        'updated_by_user_id',
        'invoice_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Quote $quote) {
            if (empty($quote->id)) {
                $quote->id = (string) Str::uuid();
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

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
