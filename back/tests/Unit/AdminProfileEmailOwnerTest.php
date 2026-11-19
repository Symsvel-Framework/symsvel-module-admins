<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Auth\AdminProfileEmailOwner;
use SymSvel\Module\Auth\Token\TokenSubject;

final class AdminProfileEmailOwnerTest extends TestCase
{
    public function testOnlyItsVerifiedActivePrincipalCanChangeEmail(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement(
            'CREATE TABLE symsvel_admins (id INTEGER PRIMARY KEY, email VARCHAR(254) NOT NULL UNIQUE,'
            .' role_code VARCHAR(100) NOT NULL, enabled BOOLEAN NOT NULL,'
            .' email_verified_at TEXT NULL, invitation_pending BOOLEAN NOT NULL)',
        );
        // Match the real Doctrine column types: untyped false may be stored
        // as an empty string by PDO SQLite, not as BOOLEAN 0.
        $types = [
            'id' => ParameterType::INTEGER,
            'email' => ParameterType::STRING,
            'role_code' => ParameterType::STRING,
            'enabled' => ParameterType::BOOLEAN,
            'email_verified_at' => ParameterType::STRING,
            'invitation_pending' => ParameterType::BOOLEAN,
        ];
        $db->insert('symsvel_admins', [
            'id' => 7, 'email' => 'before@example.test', 'role_code' => 'ROLE_DEV',
            'enabled' => true, 'email_verified_at' => '2026-11-19 10:00:00',
            'invitation_pending' => false,
        ], $types);
        $db->insert('symsvel_admins', [
            'id' => 8, 'email' => 'reserved@example.test', 'role_code' => 'ROLE_ADMIN',
            'enabled' => true, 'email_verified_at' => '2026-11-19 10:00:00',
            'invitation_pending' => false,
        ], $types);
        self::assertSame(0, (int) $db->fetchOne(
            'SELECT invitation_pending FROM symsvel_admins WHERE id = 7',
        ));
        $owner = new AdminProfileEmailOwner($db);
        $now = new \DateTimeImmutable('2026-11-19T14:00:00+00:00');
        $subject = new TokenSubject('bo', 7);
        $valid = hash('sha256', 'before@example.test');

        self::assertFalse($owner->confirmNewEmail(
            new TokenSubject('app', 7), $valid, 'next@example.test', $now,
        ));
        self::assertFalse($owner->confirmNewEmail($subject, str_repeat('f', 64), 'next@example.test', $now));
        self::assertSame('before@example.test', $db->fetchOne('SELECT email FROM symsvel_admins WHERE id = 7'));
        self::assertTrue($owner->confirmNewEmail($subject, $valid, 'next@example.test', $now));
        self::assertSame('next@example.test', $db->fetchOne('SELECT email FROM symsvel_admins WHERE id = 7'));
        self::assertFalse($owner->confirmNewEmail($subject, $valid, 'another@example.test', $now));
        self::assertSame('reserved@example.test', $db->fetchOne('SELECT email FROM symsvel_admins WHERE id = 8'));

        $currentHash = hash('sha256', 'next@example.test');
        $db->executeStatement('UPDATE symsvel_admins SET enabled = 0 WHERE id = 7');
        self::assertFalse($owner->confirmNewEmail($subject, $currentHash, 'other@example.test', $now));
        $db->executeStatement('UPDATE symsvel_admins SET enabled = 1, invitation_pending = 1 WHERE id = 7');
        self::assertFalse($owner->confirmNewEmail($subject, $currentHash, 'other@example.test', $now));
        $db->executeStatement('UPDATE symsvel_admins SET invitation_pending = 0, email_verified_at = NULL WHERE id = 7');
        self::assertFalse($owner->confirmNewEmail($subject, $currentHash, 'other@example.test', $now));
        self::assertSame('next@example.test', $db->fetchOne('SELECT email FROM symsvel_admins WHERE id = 7'));
    }
}
