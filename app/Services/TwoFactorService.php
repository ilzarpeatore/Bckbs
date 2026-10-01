<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * TOTP (RFC 6238) implementado en PHP puro — sin dependencias externas.
 * Genera secretos base32, códigos de 6 dígitos (HMAC-SHA1, periodo 30s)
 * y códigos de respaldo de un solo uso. El QR se sirve como URL externa
 * porque no hay librería de generación de QR instalada; el secreto también
 * se devuelve en claro para entrada manual en la app autenticadora.
 */
class TwoFactorService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function provisioningUri(string $secret, string $accountName, ?string $issuer = null): string
    {
        $issuer = $issuer ?: config('app.name', 'Be Stronger');
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);

        return "otpauth://totp/{$label}?{$query}";
    }

    public static function qrCodeUrl(string $secret, string $accountName, ?string $issuer = null): string
    {
        $uri = self::provisioningUri($secret, $accountName, $issuer);

        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($uri);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);

        if (strlen((string) $code) !== 6) {
            return false;
        }

        $counter = (int) floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::generateCode($secret, $counter + $i), (string) $code)) {
                return true;
            }
        }

        return false;
    }

    public static function generateCode(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $counter), $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $binary = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) & 0x7fffffff;

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function generateBackupCodes(int $count = 10): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(implode('-', [
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
            ]));
        }

        return $codes;
    }

    public static function hashBackupCodes(array $codes): array
    {
        return array_map(fn ($code) => Hash::make(self::normalizeCode($code)), $codes);
    }

    public static function verifyBackupCode(User $user, string $code): bool
    {
        $code = self::normalizeCode($code);

        $codes = $user->two_factor_backup_codes ?? [];

        if (!is_array($codes) || count($codes) === 0) {
            return false;
        }

        foreach ($codes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($codes[$index]);
                $user->update(['two_factor_backup_codes' => array_values($codes)]);

                return true;
            }
        }

        return false;
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';

        foreach (unpack('C*', $data) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $output = '';

        for ($i = 0; $i < strlen($bits); $i += 5) {
            $output .= self::ALPHABET[bindec(str_pad(substr($bits, $i, 5), 5, '0', STR_PAD_RIGHT))];
        }

        return $output;
    }

    public static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32));

        $bits = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $base32[$i])), 5, '0', STR_PAD_LEFT);
        }

        $output = '';

        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $output .= chr(bindec(substr($bits, $i, 8)));
        }

        return $output;
    }
}
