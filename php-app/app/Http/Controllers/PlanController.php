<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PaymentTransaction;
use App\Models\UserPlanSubscription;
use App\Services\Billing\PlanLimitService;
use App\Services\Billing\UserBalanceService;
use App\Support\Billing\SubscriptionSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
        private readonly UserBalanceService $userBalanceService,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $plans = Plan::query()->where('is_active', true)->orderBy('daily_price_units')->get();
        $selectedSubscription = $user?->selectedPlanSubscription();
        $selectedPlanId = $user?->selected_plan_id;
        $usageSummary = $user ? $this->planLimitService->usageSummaryForUser($user) : null;
        return view('plans.index', [
            'plans' => $plans,
            'selectedSubscription' => $selectedSubscription,
            'selectedPlanId' => $selectedPlanId,
            'usageSummary' => $usageSummary,
        ]);
    }

    public function select(Request $request, Plan $plan): RedirectResponse
    {
        $user = Auth::user();
        if ($user === null) {
            abort(401);
        }

        $previousPlan = $user->selected_plan_id ? Plan::query()->find($user->selected_plan_id) : null;
        $previousDailyPrice = $previousPlan instanceof Plan ? (int) ($previousPlan->daily_price_units ?? 0) : 0;
        $newDailyPrice = (int) ($plan->daily_price_units ?? 0);
        if ($previousPlan instanceof Plan && $newDailyPrice < $previousDailyPrice) {
            $this->userBalanceService->chargeDaily($user, $previousPlan, now(), 'plan_switch');
        }

        UserPlanSubscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'source' => SubscriptionSource::USER_SELECT,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        $user->forceFill([
            'selected_plan_id' => $plan->id,
        ])->save();

        return redirect()
            ->route('user.plans.index')
            ->with('status', __('Plan selected. Top up your balance to use paid limits.'));
    }

    public function limits(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $summary = $this->planLimitService->usageSummaryForUser($user);

        return response()->json([
            'selected_plan' => $summary['selected_plan'] ? [
                'id' => $summary['selected_plan']->id,
                'code' => $summary['selected_plan']->code,
                'name' => $summary['selected_plan']->name,
            ] : null,
            'effective_plan' => $summary['effective_plan'] ? [
                'id' => $summary['effective_plan']->id,
                'code' => $summary['effective_plan']->code,
                'name' => $summary['effective_plan']->name,
            ] : null,
            'limits' => [
                'report_epoch_seconds' => $summary['report_epoch_seconds'],
                'report_max_requests_per_epoch' => $summary['report_max_requests_per_epoch'],
                'pin_data' => [
                    'used' => $summary['pin_data_used'],
                    'max' => $summary['pin_data_max'],
                ],
                'scenarios' => [
                    'used' => $summary['scenarios_used'],
                    'max' => $summary['scenarios_max'],
                ],
                'scenario_conditions' => [
                    'used' => $summary['scenario_conditions_used'],
                    'max' => $summary['scenario_conditions_max'],
                ],
            ],
        ]);
    }

    public function pay(Request $request, Plan $plan): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $topUpUnits = (int) ($plan->daily_price_units ?? 0) * 31;
        $amount = number_format((float) $topUpUnits, 2, '.', '');
        if ((float) $amount <= 0) {
            return redirect()->route('user.plans.index')->with('status', __('Selected plan does not require payment.'));
        }

        $yooEnabled = (bool) config('services.yookassa.enabled', false);
        $shopId = (string) config('services.yookassa.shop_id', '');
        $secretKey = (string) config('services.yookassa.secret_key', '');
        $looksLikePlaceholderCreds = str_contains(mb_strtolower($shopId), 'ваш_')
            || str_contains(mb_strtolower($secretKey), 'ваш_')
            || str_contains(strtolower($shopId), 'your_')
            || str_contains(strtolower($secretKey), 'your_');

        if (app()->environment('local') && (! $yooEnabled || $looksLikePlaceholderCreds)) {
            $pendingSubscription = UserPlanSubscription::query()
                ->where('user_id', $user->id)
                ->where('plan_id', $plan->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();
            if (! $pendingSubscription) {
                $pendingSubscription = UserPlanSubscription::query()->create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => 'pending',
                    'starts_at' => now(),
                    'ends_at' => null,
                    'source' => SubscriptionSource::USER_SELECT,
                ]);
            }

            $order = PaymentTransaction::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'amount' => $amount,
                'currency' => $plan->price_currency,
                'status' => 'succeeded',
                'provider' => 'dev_local',
                'provider_payment_id' => 'dev_' . (string) Str::uuid(),
                'idempotence_key' => (string) Str::uuid(),
                'paid_at' => now(),
                'meta' => [
                    'mode' => 'local-dev-payment',
                    'subscription_id' => $pendingSubscription->id,
                ],
            ]);

            $this->userBalanceService->credit(
                $user,
                $topUpUnits,
                sprintf('Dev top-up for %s plan', $plan->name),
                $order
            );

            return redirect()->route('user.plans.index')->with('status', __('Dev payment completed. Balance topped up.'));
        }

        if (! $yooEnabled) {
            return redirect()->route('user.plans.index')->with('status', __('Payments are disabled.'));
        }

        $apiBaseUrl = rtrim((string) config('services.yookassa.api_base_url', 'https://api.yookassa.ru/v3'), '/');
        if ($shopId === '' || $secretKey === '') {
            return redirect()->route('user.plans.index')->with('status', __('YooKassa credentials are not configured.'));
        }

        $returnUrl = (string) config('services.yookassa.return_url', '');
        if ($returnUrl === '') {
            $returnUrl = route('user.plans.index');
        }

        $idempotenceKey = (string) Str::uuid();
        $pendingSubscription = UserPlanSubscription::query()
            ->where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
        if (! $pendingSubscription) {
            $pendingSubscription = UserPlanSubscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'starts_at' => now(),
                'ends_at' => null,
                'source' => SubscriptionSource::USER_SELECT,
            ]);
        }

        $order = PaymentTransaction::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $amount,
            'currency' => $plan->price_currency,
            'status' => 'pending',
            'provider' => 'yookassa',
            'idempotence_key' => $idempotenceKey,
            'meta' => [
                'subscription_id' => $pendingSubscription->id,
            ],
        ]);

        $webhookSecret = (string) config('services.yookassa.webhook_secret', '');

        $payload = [
            'amount' => [
                'value' => $amount,
                'currency' => $plan->price_currency,
            ],
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnUrl,
            ],
            'description' => sprintf('Plan %s for user #%d', $plan->name, $user->id),
            'metadata' => [
                'order_id' => (string) $order->id,
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
                'webhook_secret' => $webhookSecret,
            ],
        ];

        $response = Http::withBasicAuth($shopId, $secretKey)
            ->withHeaders(['Idempotence-Key' => $idempotenceKey])
            ->post($apiBaseUrl . '/payments', $payload);

        if (! $response->successful()) {
            $order->status = 'failed';
            $order->meta = [
                'error' => $response->json(),
                'status' => $response->status(),
            ];
            $order->save();

            return redirect()->route('user.plans.index')->with('status', __('Failed to create payment.'));
        }

        $body = $response->json();
        $order->provider_payment_id = (string) ($body['id'] ?? '');
        $order->status = (string) ($body['status'] ?? 'pending');
        $order->meta = $body;
        $order->save();

        $confirmationUrl = (string) ($body['confirmation']['confirmation_url'] ?? '');
        if ($confirmationUrl === '') {
            return redirect()->route('user.plans.index')->with('status', __('Payment created, but no confirmation URL returned.'));
        }

        return redirect()->away($confirmationUrl);
    }
}
