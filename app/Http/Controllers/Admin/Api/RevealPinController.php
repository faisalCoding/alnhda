<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevealPasswordRequest;
use App\Http\Requests\Admin\SetRevealPinRequest;
use App\Models\Account;
use App\Models\Admin;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * A correct pin unlocks every reveal for this long, so the admin is asked
     * once rather than on each account. The window is held in the session, so
     * signing out closes it immediately.
     */
    private const UNLOCK_MINUTES = 60;

    private const UNLOCK_KEY = 'reveal_unlocked_until';

    public function show(Request $request): JsonResponse
    {
        $until = $this->unlockedUntil($request);

        return response()->json(['data' => [
            'is_set' => filled($this->admin()->reveal_pin),
            'unlocked_until' => $until?->toISOString(),
        ]]);
    }

    public function store(SetRevealPinRequest $request): JsonResponse
    {
        $admin = $this->admin();
        $admin->forceFill(['reveal_pin' => $request->validated('pin')])->save();

        RateLimiter::clear($this->throttleKey());
        $request->session()->forget(self::UNLOCK_KEY);

        return response()->json(['data' => ['is_set' => true, 'unlocked_until' => null]]);
    }

    public function reveal(RevealPasswordRequest $request, Account $account): JsonResponse
    {
        return $this->guarded($request, 'social_platform', $account->id, fn (): ?string => $account->password);
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

        // Inside an open window the pin is not asked for again.
        if ($this->unlockedUntil($request) !== null) {
            return $this->release($request, $subject, $subjectId, $secret);
        }

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

        if (blank($request->validated('pin'))) {
            return response()->json([
                'message' => 'أدخل رمز الإظهار.',
                'errors' => ['pin' => ['أدخل رمز الإظهار.']],
            ], 422);
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
        $request->session()->put(self::UNLOCK_KEY, now()->addMinutes(self::UNLOCK_MINUTES)->timestamp);

        return $this->release($request, $subject, $subjectId, $secret);
    }

    /**
     * Hand over the secret and note who looked at it.
     *
     * @param  \Closure(): ?string  $secret
     */
    private function release(RevealPasswordRequest $request, string $subject, int $subjectId, \Closure $secret): JsonResponse
    {
        Log::info('secret revealed', [
            'admin_id' => $this->admin()->id,
            'subject' => $subject,
            'subject_id' => $subjectId,
            'ip' => $request->ip(),
        ]);

        return response()->json(['data' => [
            'id' => $subjectId,
            'secret' => $secret(),
            'unlocked_until' => $this->unlockedUntil($request)?->toISOString(),
        ]]);
    }

    /**
     * When the current reveal window closes, or null if none is open.
     */
    private function unlockedUntil(Request $request): ?Carbon
    {
        $timestamp = $request->session()->get(self::UNLOCK_KEY);

        if (! is_int($timestamp) || $timestamp <= now()->timestamp) {
            return null;
        }

        return Carbon::createFromTimestamp($timestamp);
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
