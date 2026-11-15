<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Auth;

use Doctrine\DBAL\Connection;
use SymSvel\Module\Auth\Identity\PasswordResetIdentity;
use SymSvel\Module\Auth\Token\TokenSubject;

/**
 * BO credential owner. Uses the host DBAL connection so a password update
 * can participate in Auth's reset-token and session-revocation transaction.
 */
final readonly class AdminPasswordResetIdentity implements PasswordResetIdentity
{
    public function __construct(private Connection $connection)
    {
    }

    public function authContext(): string
    {
        return 'bo';
    }

    public function findResetSubject(string $identifier): ?TokenSubject
    {
        $identifier = trim($identifier);
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) === false || strlen($identifier) > 254) {
            return null;
        }

        $id = $this->connection->fetchOne(
            'SELECT id FROM symsvel_admins WHERE email = ? AND enabled = ?',
            [$identifier, true],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::BOOLEAN],
        );

        return $id === false ? null : new TokenSubject('bo', (int) $id);
    }

    /**
     * No verified mailbox state exists yet in this module. Fail closed until
     * the account owner can assert and persist mailbox verification.
     */
    public function recoveryEmail(TokenSubject $subject): ?string
    {
        return null;
    }

    public function replacePassword(TokenSubject $subject, string $newPasswordHash): bool
    {
        if ($subject->context !== 'bo' || password_get_info($newPasswordHash)['algoName'] !== 'argon2id') {
            return false;
        }

        return $this->connection->executeStatement(
            'UPDATE symsvel_admins SET password_hash = ? WHERE id = ? AND enabled = ?',
            [$newPasswordHash, $subject->id, true],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN],
        ) === 1;
    }
}
