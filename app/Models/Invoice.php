<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use SoftDeletes;
    use HasUuids;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'sequence_id',
        'invoice_number',
        'document_type',
        'status',
        'contact_id',
        'client_snapshot',
        'issue_date',
        'due_date',
        'payment_mode',
        'currency',
        'subtotal_ht',
        'total_vat',
        'total_ttc',
        'notes',
        'original_invoice_id',
        'issued_at',
        'issued_by',
        'pdf_path',
        'journal_entry_id',
    ];

    protected $casts = [
        'client_snapshot' => 'array',
        'issue_date' => 'date',
        'due_date' => 'date',
        'issued_at' => 'datetime',
        'subtotal_ht' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_ttc' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            if (empty($invoice->id)) {
                $invoice->id = (string) Str::uuid();
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

        static::updating(function (Invoice $invoice) {
            $originalStatus = (string) $invoice->getOriginal('status');
            $wasImmutable = in_array($originalStatus, ['issued', 'partially_paid', 'paid', 'voided', 'replaced'], true);

            // Allow the draft -> issued transition to persist all issuance fields
            // atomically (status, issued_at, snapshot, etc). The immutability rule
            // applies only once the invoice was already immutable before update.
            if (! $wasImmutable) {
                return;
            }

            $allowedAfterIssuance = ['status', 'pdf_path', 'journal_entry_id'];

            $dirtyKeys = array_keys($invoice->getDirty());
            $forbiddenKeys = array_diff($dirtyKeys, $allowedAfterIssuance);

            if (! empty($forbiddenKeys)) {
                throw new \RuntimeException(
                    'Facture émise — modification interdite. Créez un avoir pour corriger.'
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

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(InvoiceSequence::class, 'sequence_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function vatBuckets(): HasMany
    {
        return $this->hasMany(InvoiceVatBucket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderByDesc('date')->orderByDesc('created_at');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->whereIn('status', ['issued', 'partially_paid', 'paid']);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isImmutable(): bool
    {
        return in_array($this->status, ['issued', 'partially_paid', 'paid', 'voided', 'replaced'], true);
    }
}
