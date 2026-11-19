<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Auth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SymSvel\Module\Auth\Identity\ProfileEmailOwner;
use SymSvel\Module\Auth\Token\TokenSubject;

/** Only this identity owner can replace its context's verified mailbox. */
final readonly class AdminProfileEmailOwner implements ProfileEmailOwner
{
    public function __construct(private Connection $db)
    {
    }

    public function authContext(): string
    {
        return 'bo';
    }

    public function confirmNewEmail(
        TokenSubject $subject,
        string $oldEmailHash,
        string $newEmail,
        \DateTimeImmutable $now,
    ): bool {
        if ($subject->context !== 'bo' || !preg_match('/^[a-f0-9]{64}$/D', $oldEmailHash)
            || filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false || strlen($newEmail) > 254) {
            return false;
        }
        $old = $this->db->fetchOne(
            'SELECT email FROM symsvel_admins WHERE id = ? AND enabled = ? AND invitation_pending = ? AND email_verified_at IS NOT NULL',
            [$subject->id, true, false],
            [ParameterType::INTEGER, ParameterType::BOOLEAN, ParameterType::BOOLEAN],
        );
        if (!is_string($old) || !hash_equals(hash('sha256', strtolower(trim($old))), $oldEmailHash)
            || strtolower(trim($old)) === strtolower(trim($newEmail))) {
            return false;
        }
        return $this->db->executeStatement(
            'UPDATE symsvel_admins SET email = ?, email_verified_at = ? WHERE id = ? AND email = ? AND enabled = ? AND invitation_pending = ?',
            [
                strtolower(trim($newEmail)),
                $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
                $subject->id, $old, true, false,
            ],
            [
                ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER,
                ParameterType::STRING, ParameterType::BOOLEAN, ParameterType::BOOLEAN,
            ],
        ) === 1;
    }
}
