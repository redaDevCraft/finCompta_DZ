<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\User;

class JournalPermissionService
{
    public function canView(User $user, Journal $journal): bool
    {
        $count = $journal->userPermissions()->count();
        if ($count === 0) {
            return true;
        }

        return $journal->userPermissions()
            ->where('user_id', $user->id)
            ->where('can_view', true)
            ->exists();
    }

    public function canPost(User $user, Journal $journal): bool
    {
        $count = $journal->userPermissions()->count();
        if ($count === 0) {
            return true;
        }

        return $journal->userPermissions()
            ->where('user_id', $user->id)
            ->where('can_post', true)
            ->exists();
    }
}
