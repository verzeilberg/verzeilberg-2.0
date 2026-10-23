<?php

declare(strict_types=1);

namespace App\Service\Google;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts OAuth tokens before they are stored, so a database dump doesn't give access to someone's Google account.
 * The key is derived from APP_SECRET; changing APP_SECRET means connected Google accounts have to be connected again.
 */
class TokenEncryptor
{
    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        $this->key = sodium_crypto_generichash('google-token-encryption|' . $secret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    /** @throws \RuntimeException when the value can't be decrypted (e.g. APP_SECRET changed) */
    public function decrypt(string $encrypted): string
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted token.');
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key
        );
        if ($plain === false) {
            throw new \RuntimeException('Het token kon niet worden ontsleuteld; koppel het Google-account opnieuw.');
        }

        return $plain;
    }
}
