<?php

namespace App\Http\Controllers\Api;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverPurchase;
use App\Mail\PurchaseConfirmation;
use App\Models\StorePurchase;
use App\Notifications\PurchaseConfirmedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response()->json(['message' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['message' => 'Webhook error'], 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            default => Log::info("Unhandled Stripe event: {$event->type}"),
        };

        return response()->json(['message' => 'Webhook handled']);
    }

    private function handleCheckoutSessionCompleted(object $session): void
    {
        $purchase = StorePurchase::where('stripe_payment_intent', $session->id)->first();

        if (! $purchase) {
            Log::warning("Stripe webhook: no purchase found for checkout session {$session->id}");
            return;
        }

        if ($purchase->status !== 'pending') {
            Log::info("Stripe webhook: purchase #{$purchase->id} already processed (status: {$purchase->status})");
            return;
        }

        $purchase->update([
            'status' => 'completed',
            'delivered_at' => now(),
        ]);

        dispatch(new DeliverPurchase($purchase));

        $purchase->load(['user', 'storeItem']);
        Mail::to($purchase->user)->send(new PurchaseConfirmation($purchase));
        $purchase->user->notify(new PurchaseConfirmedNotification($purchase));
        broadcast(new NewNotification($purchase->user->id, [
            'type' => 'purchase_confirmed',
            'title' => 'Purchase confirmed',
            'body' => 'Your purchase of "' . $purchase->storeItem->name . '" was successful',
            'url' => '/store',
        ]));

        Log::info("Stripe checkout session completed for purchase #{$purchase->id}");
    }

    private function handlePaymentSucceeded(object $paymentIntent): void
    {
        $purchase = StorePurchase::where('stripe_payment_intent', $paymentIntent->id)->first();

        if (! $purchase) {
            Log::warning("Stripe webhook: no purchase found for payment intent {$paymentIntent->id}");
            return;
        }

        $purchase->update([
            'status' => 'completed',
            'delivered_at' => now(),
        ]);

        // Dispatch delivery job
        dispatch(new DeliverPurchase($purchase));

        // Send confirmation email and notification
        $purchase->load(['user', 'storeItem']);
        Mail::to($purchase->user)->send(new PurchaseConfirmation($purchase));
        $purchase->user->notify(new PurchaseConfirmedNotification($purchase));
        broadcast(new NewNotification($purchase->user->id, [
            'type' => 'purchase_confirmed',
            'title' => 'Purchase confirmed',
            'body' => 'Your purchase of "' . $purchase->storeItem->name . '" was successful',
            'url' => '/store',
        ]));

        Log::info("Stripe payment succeeded for purchase #{$purchase->id}");
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        $purchase = StorePurchase::where('stripe_payment_intent', $paymentIntent->id)->first();

        if (! $purchase) {
            Log::warning("Stripe webhook: no purchase found for payment intent {$paymentIntent->id}");
            return;
        }

        $purchase->update(['status' => 'failed']);

        Log::info("Stripe payment failed for purchase #{$purchase->id}");
    }
}
