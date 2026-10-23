<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * A string column that is stored encrypted, for passwords and tokens the site has to use itself (IMAP/SMTP
 * passwords, Strava tokens), so a database dump doesn't give access to those accounts. Entities see plain text.
 *
 * The key is derived from APP_SECRET, which Kernel::boot() passes in. A value that can't be decrypted (APP_SECRET
 * changed, e.g. a database copied to another server) reads as '': connect the account again or enter its password again.
 */
final class EncryptedStringType extends Type
{
    public const NAME = 'encrypted_string';

    private static ?string $secret = null;
    private static ?string $key = null;

    public static function setSecret(string $secret): void
    {
        if ($secret !== self::$secret) {
            self::$secret = $secret;
            self::$key = null;
        }
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    /** null when the value isn't encrypted with this APP_SECRET */
    public static function decrypt(string $encrypted): ?string
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key()
        );

        return $plain === false ? null : $plain;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        // Encrypted and base64-encoded, a value is about 1.4 times as long plus 54 characters
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : self::encrypt((string) $value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : (self::decrypt((string) $value) ?? '');
    }

    private static function key(): string
    {
        if (self::$secret === null || self::$secret === '') {
            throw new \LogicException('EncryptedStringType has no key: APP_SECRET is empty, or Kernel::boot() did not run.');
        }

        return self::$key ??= sodium_crypto_generichash('encrypted-column|' . self::$secret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
