<?php

namespace App\Observers;

use App\Models\InvoicePayment;
use App\Support\Cache\DashboardCache;

class InvoicePaymentObserver
{
    public function created(InvoicePayment $payment): void
    {
        DashboardCache::forget($payment->company_id);
    }

    public function updated(InvoicePayment $payment): void
    {
        DashboardCache::forget($payment->company_id);
    }

    public function deleted(InvoicePayment $payment): void
    {
        DashboardCache::forget($payment->company_id);
    }
}
