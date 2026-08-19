<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevealPasswordRequest;
use App\Http\Requests\Admin\SetRevealPinRequest;
use App\Models\Admin;
use App\Models\SocialPlatform;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class RevealPinController extends Controller
{
    /**
     * A four digit code is only 10,000 combinations, which a script exhausts in
     * seconds. These limits are what make it a real second factor rather than a
     * speed bump, so they are deliberately tight.
     */
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'is_set' => filled($this->admin()->reveal_pin),
        ]]);
    }

    public function store(SetRevealPinRequest $request): JsonResponse
    {
        $admin = $this->admin();
        $admin->forceFill(['reveal_pin' => $request->validated('pin')])->save();

        RateLimiter::clear($this->throttleKey());

        return response()->json(['data' => ['is_set' => true]]);
    }

    public function reveal(RevealPasswordRequest $request, SocialPlatform $socialPlatform): JsonResponse
    {
        return $this->guarded($request, 'social_platform', $socialPlatform->id, fn (): ?string => $socialPlatform->password);
    }

    public function revealPaymentAccount(RevealPasswordRequest $request, Subscription $subscription): JsonResponse
    {
        return $this->guarded($request, 'subscription', $subscription->id, fn (): ?string => $subscription->payment_account);
    }

    /**
     * Check the pin under a strict attempt limit, then hand over the secret.
     *
     * @param  \Closure(): ?string  $secret
     */
    private function guarded(RevealPasswordRequest $request, string $subject, int $subjectId, \Closure $secret): JsonResponse
    {
        $admin = $this->admin();

        if (blank($admin->reveal_pin)) {
            return response()->json([
                'message' => 'عيّن رمز الإظهار أولاً قبل استخدامه.',
                'errors' => ['pin' => ['لم تعيّن رمز إظهار بعد.']],
            ], 409);
        }

        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'تجاوزت عدد المحاولات المسموح بها.',
                'errors' => ['pin' => ['حاول مجدداً بعد '.ceil($seconds / 60).' دقيقة.']],
                'retry_after' => $seconds,
            ], 429);
        }

        if (! Hash::check($request->validated('pin'), $admin->reveal_pin)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            return response()->json([
                'message' => 'الرمز غير صحيح.',
                'errors' => ['pin' => ['الرمز غير صحيح.']],
                'attempts_left' => max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($key)),
            ], 422);
        }

        RateLimiter::clear($key);

        Log::info('secret revealed', [
            'admin_id' => $admin->id,
            'subject' => $subject,
            'subject_id' => $subjectId,
            'ip' => $request->ip(),
        ]);

        return response()->json(['data' => [
            'id' => $subjectId,
            'secret' => $secret(),
        ]]);
    }

    private function admin(): Admin
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }

    private function throttleKey(): string
    {
        return 'reveal-pin:'.$this->admin()->id;
    }
}
