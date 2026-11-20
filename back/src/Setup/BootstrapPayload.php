<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Setup;

/** Validates the ephemeral administrator credentials passed by the Setup Wizard. */
final class BootstrapPayload
{
    /** @return array{email: string, password: string, language: ?string} */
    public function parse(string $raw): array
    {
        if ($raw === '' || strlen($raw) >= 8192) {
            throw new \InvalidArgumentException('Invalid administrator input size.');
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid administrator input.');
        }
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        if (!is_string($email) || !is_string($password)) {
            throw new \InvalidArgumentException('Invalid administrator credentials.');
        }
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254
            || strlen($password) < 12 || strlen($password) > 4096 || str_contains($password, "\0")) {
            throw new \InvalidArgumentException('Invalid administrator credentials.');
        }
        $language = $data['language'] ?? null;
        if ($language !== null && (!is_string($language)
            || preg_match('/^[a-z]{2,4}$/D', strtolower(trim($language))) !== 1)) {
            throw new \InvalidArgumentException('Invalid administrator language.');
        }
        return ['email' => $email, 'password' => $password,
            'language' => $language !== null ? strtolower(trim($language)) : null];
    }
}
