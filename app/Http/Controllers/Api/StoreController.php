<?php

namespace App\Http\Controllers\Api;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverPurchase;
use App\Mail\PurchaseConfirmation;
use App\Models\StoreItem;
use App\Models\StorePurchase;
use App\Models\UserCosmetic;
use App\Notifications\PurchaseConfirmedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Stripe\StripeClient;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        $items = StoreItem::with('game')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function purchaseWithCredits(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_item_id' => ['required', 'exists:store_items,id'],
        ]);

        $item = StoreItem::findOrFail($validated['store_item_id']);
        $user = $request->user();

        if (! $item->price_credits) {
            return response()->json([
                'message' => 'This item cannot be purchased with credits.',
            ], 422);
        }

        if ($user->credits < $item->price_credits) {
            return response()->json([
                'message' => 'Insufficient credits.',
            ], 422);
        }

        $user->spendCredits($item->price_credits, "Purchased: {$item->name}", StoreItem::class, $item->id);

        $purchase = StorePurchase::create([
            'user_id' => $user->id,
            'store_item_id' => $item->id,
            'payment_method' => 'credits',
            'credits_spent' => $item->price_credits,
            'status' => 'completed',
            'delivered_at' => now(),
        ]);

        // If it's a cosmetic, add to user's cosmetics
        if (in_array($item->item_type, ['cosmetic', 'flair'])) {
            UserCosmetic::create([
                'user_id' => $user->id,
                'store_item_id' => $item->id,
                'is_active' => true,
                'activated_at' => now(),
            ]);
        }

        // Send purchase confirmation email
        Mail::to($user)->send(new PurchaseConfirmation($purchase));

        // Dispatch delivery job for RCON items
        dispatch(new DeliverPurchase($purchase));

        $user->notify(new PurchaseConfirmedNotification($purchase));
        broadcast(new NewNotification($user->id, [
            'type' => 'purchase_confirmed',
            'title' => 'Purchase confirmed',
            'body' => 'Your purchase of "' . $item->name . '" was successful',
            'url' => '/store',
        ]));
        $user->checkAchievements();

        return response()->json([
            'data' => $purchase->load('storeItem'),
            'message' => 'Purchase successful.',
        ], 201);
    }

    public function createCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_item_id' => ['required', 'exists:store_items,id'],
        ]);

        $item = StoreItem::findOrFail($validated['store_item_id']);

        if (! $item->price_money) {
            return response()->json([
                'message' => 'This item cannot be purchased with money.',
            ], 422);
        }

        $user = $request->user();
        $stripe = new StripeClient(config('services.stripe.secret'));
        $frontendUrl = config('app.frontend_url', 'https://community.voltexahub.com');

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $item->name,
                        'description' => $item->description ?? '',
                    ],
                    'unit_amount' => (int) ($item->price_money * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $frontendUrl . '/store/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontendUrl . '/store/cancel',
            'metadata' => [
                'user_id' => $user->id,
                'item_id' => $item->id,
            ],
            'customer_email' => $user->email,
        ]);

        $purchase = StorePurchase::create([
            'user_id' => $user->id,
            'store_item_id' => $item->id,
            'payment_method' => 'money',
            'amount_paid' => $item->price_money,
            'status' => 'pending',
            'stripe_payment_intent' => $session->id,
        ]);

        return response()->json([
            'data' => ['url' => $session->url],
            'message' => 'Checkout session created.',
        ]);
    }
}
