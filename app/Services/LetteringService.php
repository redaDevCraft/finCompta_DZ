<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\JournalLine;
use App\Models\Lettering;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LetteringService
{
    /**
     * Tolerance for matching amounts (DA).
     */
    private const AMOUNT_TOLERANCE = 0.01;

    /**
     * Find unlettered lines on an account (posted entries only).
     * Optionally filtered by contact.
     *
     * @return Collection<int, JournalLine>
     */
    public function unletteredLines(Account $account, ?Contact $contact = null): Collection
    {
        return JournalLine::query()
            ->with(['journalEntry:id,entry_date,journal_code,reference,description,status'])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.company_id', $account->company_id)
            ->where('journal_entries.status', 'posted')
            ->where('journal_lines.account_id', $account->id)
            ->whereNull('journal_lines.lettering_id')
            ->when($contact, fn ($q) => $q->where('journal_lines.contact_id', $contact->id))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.sort_order')
            ->select('journal_lines.*')
            ->get();
    }

    /**
     * Manually letter a set of lines. All lines must belong to the same account,
     * and sum(debit) must equal sum(credit) within the tolerance.
     */
    public function matchManual(
        Account $account,
        array $lineIds,
        User $user,
        string $matchType = 'manual',
        ?string $notes = null
    ): Lettering {
        if (count($lineIds) < 2) {
            throw new InvalidArgumentException('Il faut au moins 2 lignes pour un lettrage.');
        }

        return DB::transaction(function () use ($account, $lineIds, $user, $matchType, $notes) {
            $lines = JournalLine::query()
                ->whereIn('id', $lineIds)
                ->where('account_id', $account->id)
                ->whereNull('lettering_id')
                ->lockForUpdate()
                ->get();

            if ($lines->count() !== count(array_unique($lineIds))) {
                throw new InvalidArgumentException(
                    'Certaines lignes sont déjà lettrées ou n’appartiennent pas à ce compte.'
                );
            }

            // Verify every line is on a posted entry.
            $lines->loadMissing('journalEntry:id,status,company_id');
            foreach ($lines as $line) {
                if (! $line->journalEntry || $line->journalEntry->status !== 'posted') {
                    throw new InvalidArgumentException(
                        'Toutes les lignes doivent appartenir à une écriture validée.'
                    );
                }

                if ($line->journalEntry->company_id !== $account->company_id) {
                    throw new InvalidArgumentException(
                        'Les lignes ne partagent pas la même société.'
                    );
                }
            }

            $totalDebit = (float) $lines->sum('debit');
            $totalCredit = (float) $lines->sum('credit');

            if (abs($totalDebit - $totalCredit) >= self::AMOUNT_TOLERANCE) {
                throw new InvalidArgumentException(sprintf(
                    'Le lettrage n’est pas équilibré (débit %.2f ≠ crédit %.2f).',
                    $totalDebit,
                    $totalCredit
                ));
            }

            $contactId = $lines->pluck('contact_id')->filter()->unique()->count() === 1
                ? $lines->pluck('contact_id')->filter()->first()
                : null;

            /** @var Lettering $lettering */
            $lettering = Lettering::withoutGlobalScopes()->create([
                'company_id' => $account->company_id,
                'account_id' => $account->id,
                'contact_id' => $contactId,
                'code' => Lettering::nextCode($account->id),
                'total_amount' => $totalDebit,
                'match_type' => $matchType,
                'notes' => $notes,
                'matched_at' => now(),
                'matched_by' => $user->id,
            ]);

            JournalLine::query()
                ->whereIn('id', $lines->pluck('id'))
                ->update(['lettering_id' => $lettering->id]);

            return $lettering->fresh('lines');
        });
    }

    /**
     * Auto-match by reference: group unlettered lines sharing the same
     * journal entry reference, and letter each group that balances to zero.
     *
     * @return array{matched:int,groups:int}
     */
    public function autoMatchByReference(
        Account $account,
        User $user,
        ?Contact $contact = null
    ): array {
        $lines = $this->unletteredLines($account, $contact);

        $groups = $lines
            ->filter(fn (JournalLine $l) => filled($l->journalEntry?->reference))
            ->groupBy(fn (JournalLine $l) => strtoupper(trim((string) $l->journalEntry->reference)));

        return $this->commitBalancedGroups($account, $user, $groups, 'auto_reference');
    }

    /**
     * Auto-match by exact amount pairs: for each unlettered debit line,
     * find an unlettered credit line on the same account (same contact if set)
     * with the same amount and no reference mismatch, and letter the pair.
     *
     * @return array{matched:int,groups:int}
     */
    public function autoMatchByAmount(
        Account $account,
        User $user,
        ?Contact $contact = null
    ): array {
        $lines = $this->unletteredLines($account, $contact);

        $debits = $lines->filter(fn (JournalLine $l) => (float) $l->debit > 0);
        $credits = $lines->filter(fn (JournalLine $l) => (float) $l->credit > 0);

        $pairs = collect();
        $usedCreditIds = [];

        foreach ($debits as $d) {
            /** @var JournalLine|null $match */
            $match = $credits->first(function (JournalLine $c) use ($d, $usedCreditIds) {
                if (in_array($c->id, $usedCreditIds, true)) {
                    return false;
                }

                if (abs((float) $c->credit - (float) $d->debit) >= self::AMOUNT_TOLERANCE) {
                    return false;
                }

                // Respect contact grouping if both sides have a contact
                if ($d->contact_id && $c->contact_id && $d->contact_id !== $c->contact_id) {
                    return false;
                }

                return true;
            });

            if ($match) {
                $usedCreditIds[] = $match->id;
                $pairs->push(collect([$d, $match]));
            }
        }

        $groups = $pairs->mapWithKeys(fn ($pair, $i) => ["pair_{$i}" => $pair]);

        return $this->commitBalancedGroups($account, $user, $groups, 'auto_amount');
    }

    public function unmatch(Lettering $lettering): void
    {
        DB::transaction(function () use ($lettering) {
            JournalLine::query()
                ->where('lettering_id', $lettering->id)
                ->update(['lettering_id' => null]);

            $lettering->delete();
        });
    }

    /**
     * @param  Collection<string, Collection<int, JournalLine>>  $groups
     */
    private function commitBalancedGroups(
        Account $account,
        User $user,
        Collection $groups,
        string $matchType
    ): array {
        $matched = 0;
        $committed = 0;

        foreach ($groups as $groupLines) {
            if ($groupLines->count() < 2) {
                continue;
            }

            $totalDebit = (float) $groupLines->sum('debit');
            $totalCredit = (float) $groupLines->sum('credit');

            if (abs($totalDebit - $totalCredit) >= self::AMOUNT_TOLERANCE) {
                continue;
            }

            try {
                $this->matchManual(
                    $account,
                    $groupLines->pluck('id')->all(),
                    $user,
                    $matchType
                );

                $matched += $groupLines->count();
                $committed++;
            } catch (\Throwable) {
                // Skip groups that fail (e.g. lines already matched in a prior group).
                continue;
            }
        }

        return [
            'matched' => $matched,
            'groups' => $committed,
        ];
    }
}
