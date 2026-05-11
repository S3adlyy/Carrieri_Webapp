<?php
// src/Security/JavaPbkdf2PasswordHasher.php
namespace App\Security;

use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class JavaPbkdf2PasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        // Generate new hashes in Symfony's native format (optional)
        // Or replicate Java's format:
        $salt = random_bytes(16);
        $hash = hash_pbkdf2('sha256', $plainPassword, $salt, 120_000, 32, true);

        return 'pbkdf2$120000$'
            . rtrim(strtr(base64_encode($salt), '+/', '-_'), '=') . '$'
            . rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        $parts = explode('$', $hashedPassword);
        if (count($parts) !== 4 || $parts[0] !== 'pbkdf2') {
            return false;
        }

        $iterations = (int) $parts[1];
        $salt       = base64_decode(strtr($parts[2], '-_', '+/'));
        $expected   = base64_decode(strtr($parts[3], '-_', '+/'));
        $keyLen     = strlen($expected); // 32 bytes = 256 bits

        $actual = hash_pbkdf2('sha256', $plainPassword, $salt, $iterations, $keyLen, true);

        return hash_equals($expected, $actual);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        // Optionally return true to migrate to Symfony's native hasher on next login
        return false;
    }
}