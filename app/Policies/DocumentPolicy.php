<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return app()->has('currentCompany')
            && $document->company_id === app('currentCompany')->id;
    }

    public function delete(User $user, Document $document): bool
    {
        return app()->has('currentCompany')
            && $document->company_id === app('currentCompany')->id
            && $document->isDeletable();
    }
}