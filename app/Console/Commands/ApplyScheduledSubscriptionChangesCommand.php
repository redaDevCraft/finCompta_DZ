<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ApplyScheduledSubscriptionChangesCommand extends Command
{
    protected $signature = 'subscriptions:apply-scheduled-changes {--limit=500 : Max subscriptions to process}';

    protected $description = 'Apply due scheduled subscription plan and billing-cycle changes';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;

        Subscription::query()
            ->whereNotNull('next_plan_id')
            ->whereNotNull('next_change_effective_at')
            ->where('next_change_effective_at', '<=', now())
            ->orderBy('next_change_effective_at')
            ->limit($limit)
            ->get()
            ->each(function (Subscription $subscription) use ($subscriptionService, &$processed): void {
                $subscriptionService->applyScheduledChanges($subscription);
                $processed++;
            });

        $this->info("Applied scheduled changes for {$processed} subscription(s).");

        return self::SUCCESS;
    }
}
