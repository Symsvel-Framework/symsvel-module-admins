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

    /** Only active accounts with a confirmed mailbox can receive reset links. */
    public function recoveryEmail(TokenSubject $subject): ?string
    {
        if ($subject->context !== 'bo') {
            return null;
        }

        $email = $this->connection->fetchOne(
            'SELECT email FROM symsvel_admins WHERE id = ? AND enabled = ? AND email_verified_at IS NOT NULL',
            [$subject->id, true],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN],
        );

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
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
