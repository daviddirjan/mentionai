<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Throwable;

class TurnstileVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Verification is only enforced when a secret key is configured (local/dev stays frictionless). */
    public function enabled(): bool
    {
        return filled(config('mentionai.turnstile.secret_key'));
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(8)->post(self::ENDPOINT, array_filter([
                'secret' => config('mentionai.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));

            return $response->successful() && $response->json('success') === true;
        } catch (Throwable) {
            return false;
        }
    }
}
