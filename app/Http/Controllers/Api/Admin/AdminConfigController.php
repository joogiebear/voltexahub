<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumConfig;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $configs = ForumConfig::all()->pluck('value', 'key');

        $mailKeys = ['mail_mailer','mail_host','mail_port','mail_username','mail_password','mail_encryption','mail_from_address','mail_from_name'];

        return response()->json([
            'data' => array_merge($configs->toArray(), [
                'mail' => collect($mailKeys)->mapWithKeys(fn ($k) => [$k => $configs[$k] ?? ''])->toArray(),
            ]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'config' => ['required', 'array'],
            'config.*' => ['nullable'],
        ]);

        // Block custom theme changes if plan doesn't allow it
        $themeKeys = ['custom_css', 'custom_js'];
        if (! app(PlanService::class)->customThemes()) {
            foreach ($themeKeys as $key) {
                if (array_key_exists($key, $validated['config']) && ! empty($validated['config'][$key])) {
                    return response()->json([
                        'error'       => 'custom_themes_not_available',
                        'upgrade_url' => 'https://billing.voltexahub.com',
                    ], 403);
                }
            }
        }

        foreach ($validated['config'] as $key => $value) {
            ForumConfig::set($key, is_bool($value) ? ($value ? 'true' : 'false') : (string)($value ?? ''));
        }

        $configs = ForumConfig::all()->pluck('value', 'key');

        return response()->json([
            'data' => $configs,
            'message' => 'Configuration updated successfully.',
        ]);
    }

    public function testEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "This is a test email from VoltexaHub.\n\nIf you received this, your email settings are working correctly!",
                function ($message) use ($user) {
                    $message->to($user->email, $user->username)
                            ->subject('VoltexaHub — Test Email');
                }
            );

            return response()->json(['message' => 'Test email sent to ' . $user->email]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage()], 422);
        }
    }
}