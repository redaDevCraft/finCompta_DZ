<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function view(User $user, Expense $expense): bool
    {
        return app()->has('currentCompany')
            && $expense->company_id === app('currentCompany')->id;
    }

    public function update(User $user, Expense $expense): bool
    {
        if (! $this->view($user, $expense) || ! $expense->isEditable()) {
            return false;
        }

        $companyUser = app('currentCompany')->users()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        return $companyUser
            && in_array($companyUser->pivot->role, ['owner', 'accountant'], true);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return false;
    }
}