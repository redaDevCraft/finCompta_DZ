<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class JournalEntryLock extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'lock_type',
        'locked_until_date',
        'journal_entry_id',
        'locked_by_user_id',
    ];

    protected $casts = [
        'locked_until_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (JournalEntryLock $lock) {
            if (empty($lock->id)) {
                $lock->id = (string) Str::uuid();
            }
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
