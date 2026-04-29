<?php

namespace App\Core;

class Auth
{
    private static ?object $user       = null;
    private static ?int    $businessId = null;

    public static function user(): ?object
    {
        return self::$user;
    }

    public static function setUser(?object $user): void
    {
        self::$user = $user;
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function id(): ?int
    {
        return self::$user?->id ?? null;
    }

    // ── Business context (multi-tenant) ───────────────────────────────────────

    public static function businessId(): ?int
    {
        return self::$businessId;
    }

    public static function setBusinessId(?int $id): void
    {
        self::$businessId = $id;
    }

    // ── Token management ──────────────────────────────────────────────────────

    public static function validateToken(string $plainToken): ?object
    {
        $hash = hash('sha256', $plainToken);

        return DB::selectOne(
            "SELECT * FROM personal_access_tokens
             WHERE token = ?
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1",
            [$hash]
        ) ?: null;
    }

    public static function createToken(int $userId, string $name = 'auth-token', ?int $businessId = null): string
    {
        $plain = bin2hex(random_bytes(40));
        $hash  = hash('sha256', $plain);

        DB::statement(
            "INSERT INTO personal_access_tokens
                (tokenable_type, tokenable_id, business_id, name, token, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
            ['App\\Tables\\User', $userId, $businessId, $name, $hash]
        );

        return $plain;
    }

    public static function updateTokenLastUsed(string $plainToken): void
    {
        $hash = hash('sha256', $plainToken);
        DB::statement(
            "UPDATE personal_access_tokens SET last_used_at = NOW() WHERE token = ?",
            [$hash]
        );
    }

    public static function deleteCurrentToken(): void
    {
        $token = RequestHolder::bearerToken();
        if (!$token) return;

        $hash = hash('sha256', $token);
        DB::statement("DELETE FROM personal_access_tokens WHERE token = ?", [$hash]);
    }

    public static function deleteAllTokens(int $userId): void
    {
        DB::statement(
            "DELETE FROM personal_access_tokens WHERE tokenable_type = ? AND tokenable_id = ?",
            ['App\\Tables\\User', $userId]
        );
    }
}
