<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Auth;

use Doctrine\DBAL\Connection;
use SymSvel\Module\Auth\Identity\PasswordResetIdentity;
use SymSvel\Module\Auth\Identity\EmailVerificationIdentity;
use SymSvel\Module\Auth\Identity\AdminInvitationIdentity;
use SymSvel\Module\Auth\Token\TokenSubject;

/**
 * BO credential owner. Uses the host DBAL connection so a password update
 * can participate in Auth's reset-token and session-revocation transaction.
 */
final readonly class AdminPasswordResetIdentity implements PasswordResetIdentity, EmailVerificationIdentity, AdminInvitationIdentity
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
            'SELECT id FROM symsvel_admins WHERE email = ? AND enabled = ? AND invitation_pending = ?',
            [$identifier, true, false],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::BOOLEAN, \Doctrine\DBAL\ParameterType::BOOLEAN],
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
            'SELECT email FROM symsvel_admins WHERE id = ? AND enabled = ? AND email_verified_at IS NOT NULL AND invitation_pending = ?',
            [$subject->id, true, false],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN, \Doctrine\DBAL\ParameterType::BOOLEAN],
        );

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /** An active account may only confirm its own as-yet-unverified mailbox. */
    public function pendingVerificationEmail(TokenSubject $subject): ?string
    {
        if ($subject->context !== 'bo') {
            return null;
        }

        $email = $this->connection->fetchOne(
            'SELECT email FROM symsvel_admins WHERE id = ? AND enabled = ? AND email_verified_at IS NULL AND invitation_pending = ?',
            [$subject->id, true, false],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN, \Doctrine\DBAL\ParameterType::BOOLEAN],
        );

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /**
     * The mailbox fingerprint is matched against the account's current email.
     * No unverified account is implicitly confirmed during bootstrap.
     */
    public function confirmEmail(TokenSubject $subject, string $emailHash, \DateTimeImmutable $now): bool
    {
        if ($subject->context !== 'bo' || !preg_match('/^[a-f0-9]{64}$/D', $emailHash)) {
            return false;
        }

        $email = $this->connection->fetchOne(
            'SELECT email FROM symsvel_admins WHERE id = ? AND enabled = ? AND invitation_pending = ?',
            [$subject->id, true, false],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN, \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
        if (!is_string($email)
            || !hash_equals(hash('sha256', strtolower(trim($email))), $emailHash)) {
            return false;
        }

        return $this->connection->executeStatement(
            'UPDATE symsvel_admins SET email_verified_at = ? WHERE id = ? AND enabled = ? AND email = ? AND email_verified_at IS NULL AND invitation_pending = ?',
            [$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'), $subject->id, true, $email, false],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::BOOLEAN],
        ) === 1;
    }

    /**
     * No separate verification step: the first password and mailbox approval
     * are one conditional update inside Auth's invitation transaction.
     */
    public function activateInvitation(int $adminId, string $emailHash, string $passwordHash, \DateTimeImmutable $now): bool
    {
        if ($adminId < 1 || !preg_match('/^[a-f0-9]{64}$/D', $emailHash)
            || password_get_info($passwordHash)['algoName'] !== 'argon2id') {
            return false;
        }

        $email = $this->connection->fetchOne(
            'SELECT email FROM symsvel_admins WHERE id = ? AND enabled = ? AND email_verified_at IS NULL AND invitation_pending = ?',
            [$adminId, true, true],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN, \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
        if (!is_string($email) || !hash_equals(hash('sha256', strtolower(trim($email))), $emailHash)) {
            return false;
        }

        return $this->connection->executeStatement(
            'UPDATE symsvel_admins SET password_hash = ?, email_verified_at = ?, invitation_pending = ? WHERE id = ? AND enabled = ? AND email = ? AND email_verified_at IS NULL AND invitation_pending = ?',
            [$passwordHash, $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'), false, $adminId, true, $email, true],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::BOOLEAN, \Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::BOOLEAN],
        ) === 1;
    }

    public function replacePassword(TokenSubject $subject, string $newPasswordHash): bool
    {
        if ($subject->context !== 'bo' || password_get_info($newPasswordHash)['algoName'] !== 'argon2id') {
            return false;
        }

        return $this->connection->executeStatement(
            'UPDATE symsvel_admins SET password_hash = ? WHERE id = ? AND enabled = ? AND invitation_pending = ?',
            [$newPasswordHash, $subject->id, true, false],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN, \Doctrine\DBAL\ParameterType::BOOLEAN],
        ) === 1;
    }
}
