<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Billing\SubscriptionActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class YooKassaWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionActivationService $subscriptionActivationService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (! config('services.yookassa.enabled', false)) {
            Log::warning('yookassa_webhook_rejected_disabled', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'payments_disabled'], 503);
        }

        $configuredSecret = (string) config('services.yookassa.webhook_secret', '');
        if ($configuredSecret !== '') {
            $incomingSecret = (string) ($request->header('X-YooKassa-Webhook-Secret') ?? $request->query('secret', ''));
            if (! hash_equals($configuredSecret, $incomingSecret)) {
                Log::warning('yookassa_webhook_rejected_secret_mismatch', [
                    'ip' => $request->ip(),
                    'header_present' => $request->hasHeader('X-YooKassa-Webhook-Secret'),
                ]);
                return response()->json(['error' => 'forbidden'], 403);
            }
        }

        $payload = $request->all();
        $event = (string) ($payload['event'] ?? '');
        $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
        $providerPaymentId = (string) ($object['id'] ?? '');
        $providerStatus = (string) ($object['status'] ?? '');

        $providerEventId = $event . ':' . $providerPaymentId . ':' . sha1(json_encode($payload));
        $alreadySeen = DB::table('payment_webhook_events')
            ->where('provider_event_id', $providerEventId)
            ->exists();
        if ($alreadySeen) {
            Log::info('yookassa_webhook_duplicate', [
                'event' => $event,
                'provider_payment_id' => $providerPaymentId,
            ]);
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        DB::table('payment_webhook_events')->insert([
            'provider' => 'yookassa',
            'provider_event_id' => $providerEventId,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'processed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($providerPaymentId === '') {
            Log::warning('yookassa_webhook_ignored_missing_payment_id', [
                'event' => $event,
            ]);
            return response()->json(['ok' => true, 'ignored' => 'missing_payment_id']);
        }

        /** @var PaymentTransaction|null $order */
        $order = PaymentTransaction::query()
            ->where('provider', 'yookassa')
            ->where('provider_payment_id', $providerPaymentId)
            ->first();

        if (! $order) {
            Log::warning('yookassa_webhook_ignored_order_not_found', [
                'provider_payment_id' => $providerPaymentId,
                'event' => $event,
            ]);
            return response()->json(['ok' => true, 'ignored' => 'order_not_found']);
        }

        if ($event === 'payment.succeeded' || $providerStatus === 'succeeded') {
            $order->status = 'succeeded';
            $order->paid_at = now();
            $order->meta = $payload;
            $order->save();

            $user = User::query()->find($order->user_id);
            if ($user) {
                $this->subscriptionActivationService->activatePlanForUser($user, (int) $order->plan_id);
            }
        } elseif ($event === 'payment.canceled' || $providerStatus === 'canceled') {
            $order->status = 'canceled';
            $order->meta = $payload;
            $order->save();
        } else {
            $order->status = $providerStatus !== '' ? $providerStatus : $order->status;
            $order->meta = $payload;
            $order->save();
        }

        DB::table('payment_webhook_events')
            ->where('provider_event_id', $providerEventId)
            ->update([
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

        Log::info('yookassa_webhook_processed', [
            'provider_payment_id' => $providerPaymentId,
            'event' => $event,
            'order_id' => $order->id,
            'order_status' => $order->status,
        ]);

        return response()->json(['ok' => true]);
    }
}
