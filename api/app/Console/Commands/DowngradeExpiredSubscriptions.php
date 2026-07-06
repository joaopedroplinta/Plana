<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionExpired;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('subscriptions:downgrade-expired')]
#[Description('Rebaixa para starter os tenants cuja assinatura paga expirou')]
class DowngradeExpiredSubscriptions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $downgraded = 0;

        Tenant::where('plan', '!=', 'starter')
            ->get()
            ->each(function (Tenant $tenant) use (&$downgraded) {
                $latestApproved = Subscription::withoutTenantScope()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'approved')
                    ->orderByDesc('expires_at')
                    ->first();

                // Plano concedido manualmente (sem assinatura) ou sem
                // expiração definida: não rebaixar automaticamente.
                if (! $latestApproved || ! $latestApproved->expires_at) {
                    return;
                }

                if ($latestApproved->expires_at->isFuture()) {
                    return;
                }

                $previousPlan = $tenant->plan;
                $tenant->update(['plan' => 'starter']);
                $downgraded++;

                Notification::send($tenant->owner, new SubscriptionExpired($tenant, $previousPlan));

                $this->info("Tenant {$tenant->slug}: {$previousPlan} → starter");
            });

        $this->info("{$downgraded} tenant(s) rebaixado(s).");

        return self::SUCCESS;
    }
}
