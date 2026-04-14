<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return app()->has('currentCompany')
            && $invoice->company_id === app('currentCompany')->id;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $this->view($user, $invoice) || ! $invoice->isEditable()) {
            return false;
        }

        $companyUser = app('currentCompany')->users()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        return $companyUser
            && in_array($companyUser->pivot->role, ['owner', 'accountant'], true);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}