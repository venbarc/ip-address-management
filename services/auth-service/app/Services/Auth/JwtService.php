<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserSession;
use RuntimeException;

class JwtService
{
    public function issueAccessToken(User $user, UserSession $session): array
    {
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addMinutes(config('security.jwt_ttl_minutes'));

        $payload = [
            'iss' => config('app.url'),
            'sub' => (string) $user->id,
            'sid' => $session->session_uuid,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'iat' => $issuedAt->timestamp,
            'nbf' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
        ];

        return [
            'token' => $this->encode($payload),
            'expires_at' => $expiresAt,
        ];
    }

    public function decode(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new RuntimeException('Malformed token.');
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $segments;
        $signingInput = $headerSegment.'.'.$payloadSegment;
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $signingInput, $this->secret(), true)
        );

        if (! hash_equals($expectedSignature, $signatureSegment)) {
            throw new RuntimeException('Invalid token signature.');
        }

        $payload = json_decode($this->base64UrlDecode($payloadSegment), true, 512, JSON_THROW_ON_ERROR);
        $now = now()->timestamp;

        if (($payload['nbf'] ?? 0) > $now) {
            throw new RuntimeException('Token cannot be used yet.');
        }

        if (($payload['exp'] ?? 0) <= $now) {
            throw new RuntimeException('Token has expired.');
        }

        return $payload;
    }

    private function encode(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $headerSegment = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadSegment = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signatureSegment = $this->base64UrlEncode(
            hash_hmac('sha256', $headerSegment.'.'.$payloadSegment, $this->secret(), true)
        );

        return $headerSegment.'.'.$payloadSegment.'.'.$signatureSegment;
    }

    private function secret(): string
    {
        $secret = (string) config('security.jwt_secret');

        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET must not be empty.');
        }

        return $secret;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Unable to decode token payload.');
        }

        return $decoded;
    }
}
