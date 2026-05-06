<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\User;
use App\Support\Billing\SubscriptionSource;
use Illuminate\Support\Facades\DB;

class SubscriptionActivationService
{
    public function activatePlanForUser(User $user, int $planId): void
    {
        $now = now();
        $periodDays = max(1, (int) config('smarthome.subscription_period_days', 30));
        $endsAt = $now->copy()->addDays($periodDays);

        DB::table('user_subscriptions')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
                'updated_at' => $now,
            ]);

        DB::table('user_subscriptions')
            ->where('user_id', $user->id)
            ->where('plan_id', $planId)
            ->whereIn('status', ['pending', 'expired'])
            ->orderByDesc('id')
            ->limit(1)
            ->update([
                'status' => 'active',
                'starts_at' => $now,
                'ends_at' => $endsAt,
                'source' => SubscriptionSource::PAYMENT,
                'updated_at' => $now,
            ]);

        $user->forceFill([
            'selected_plan_id' => $planId,
        ])->save();
    }
}
