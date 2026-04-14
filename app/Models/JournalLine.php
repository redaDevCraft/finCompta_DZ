<?php

namespace App\Models;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'contact_id',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (JournalLine $line) {
            $debit = (float) ($line->debit ?? 0);
            $credit = (float) ($line->credit ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException('Le débit et le crédit ne peuvent pas être négatifs');
            }

            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException('Une ligne comptable ne peut avoir à la fois un débit et un crédit');
            }
        });
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}