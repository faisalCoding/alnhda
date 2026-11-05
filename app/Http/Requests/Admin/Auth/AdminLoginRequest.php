<?php

namespace App\Http\Requests\Admin\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'أدخل البريد الإلكتروني.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'password.required' => 'أدخل كلمة المرور.',
        ];
    }

    /**
     * Attempt to authenticate against the admin guard.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::guard('admin')->attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $this->countFailedAttempt();

            throw ValidationException::withMessages([
                'email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Count a failed attempt toward the lockout, once per distinct password.
     *
     * Clicking the button again while the first request is still on its way
     * resends the same guess. Counting it again locked people out after two
     * real tries, while an attacker gains nothing from it: every new password
     * still counts.
     */
    protected function countFailedAttempt(): void
    {
        $guess = 'admin-login-guess:'.hash_hmac(
            'sha256',
            $this->throttleKey().'|'.$this->string('password'),
            (string) config('app.key'),
        );

        if (Cache::add($guess, true, self::DECAY_SECONDS)) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'تجاوزت عدد محاولات الدخول المسموح بها. حاول مرة أخرى بعد '.$this->secondsInArabic($seconds).'.',
        ]);
    }

    /**
     * Spell a wait in seconds the way Arabic counts them.
     */
    protected function secondsInArabic(int $seconds): string
    {
        return match (true) {
            $seconds <= 1 => 'ثانية واحدة',
            $seconds === 2 => 'ثانيتين',
            $seconds <= 10 => $seconds.' ثوانٍ',
            default => $seconds.' ثانية',
        };
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
