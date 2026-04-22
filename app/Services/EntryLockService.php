<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalEntryLock;

class EntryLockService
{
    public function getDateLock(string $companyId): ?JournalEntryLock
    {
        return JournalEntryLock::query()
            ->where('company_id', $companyId)
            ->where('lock_type', 'date')
            ->orderByDesc('locked_until_date')
            ->first();
    }

    public function isDateLocked(string $companyId, ?string $entryDate): bool
    {
        if (! $entryDate) {
            return false;
        }

        $dateLock = $this->getDateLock($companyId);
        if (! $dateLock?->locked_until_date) {
            return false;
        }

        return $entryDate <= (string) $dateLock->locked_until_date;
    }

    public function isEntryLocked(string $entryId): bool
    {
        return JournalEntryLock::query()
            ->where('lock_type', 'entry')
            ->where('journal_entry_id', $entryId)
            ->exists();
    }

    public function isLocked(JournalEntry $entry): bool
    {
        $date = optional($entry->entry_date)?->toDateString();

        return $this->isDateLocked($entry->company_id, $date) || $this->isEntryLocked($entry->id);
    }
}
