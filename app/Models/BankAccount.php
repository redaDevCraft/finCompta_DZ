<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bank_name',
        'account_number',
        'currency',
        'gl_account_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(BankStatementImport::class);
    }
}