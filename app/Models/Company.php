<?php

namespace App\Models;

use Database\Seeders\CompanyBootstrapSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'id';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'raison_sociale',
        'forme_juridique',
        'nif',
        'nis',
        'rc',
        'ai',
        'address_line1',
        'address_wilaya',
        'tax_regime',
        'vat_registered',
        'fiscal_year_end',
        'currency',
        'status',
    ];

    protected $casts = [
        'vat_registered'  => 'boolean',
        'fiscal_year_end' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Company $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_users')
            ->withPivot('role', 'granted_at', 'revoked_at');
           
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
    protected static function booted(): void
    {
        static::created(function (Company $company) {
            (new CompanyBootstrapSeeder($company->id))->run();
        });
    }
}