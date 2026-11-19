<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use Doctrine\DBAL\DriverManager;
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
            .' enabled BOOLEAN NOT NULL, email_verified_at TEXT NULL, invitation_pending BOOLEAN NOT NULL)',
        );
        $db->insert('symsvel_admins', [
            'id' => 7, 'email' => 'before@example.test', 'enabled' => true,
            'email_verified_at' => '2026-11-19 10:00:00', 'invitation_pending' => false,
        ]);
        $db->insert('symsvel_admins', [
            'id' => 8, 'email' => 'reserved@example.test', 'enabled' => true,
            'email_verified_at' => '2026-11-19 10:00:00', 'invitation_pending' => false,
        ]);
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
    }
}
