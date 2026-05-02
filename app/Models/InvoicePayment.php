<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class InvoicePayment extends Model
{
    use SoftDeletes;
    use HasUuids;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'contact_id',
        'date',
        'amount',
        'method',
        'reference',
        'bank_transaction_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (InvoicePayment $payment) {
            if (empty($payment->id)) {
                $payment->id = (string) Str::uuid();
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
