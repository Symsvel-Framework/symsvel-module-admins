<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Auth\AdminPasswordResetIdentity;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Auth\Token\TokenSubject;

final class AdminPasswordResetIdentityVerifiedMailboxTest extends TestCase
{
    public function testVerifiedMailboxIsRequiredForPasswordResetDelivery(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE symsvel_admins (id INTEGER PRIMARY KEY, email VARCHAR(254) NOT NULL, password_hash VARCHAR(255) NOT NULL, enabled BOOLEAN NOT NULL, invitation_pending BOOLEAN NOT NULL DEFAULT 0, email_verified_at DATETIME DEFAULT NULL)');
        $hash = password_hash('original-password', PASSWORD_ARGON2ID);
        $db->insert('symsvel_admins', ['id' => 17, 'email' => 'confirmed@example.test', 'password_hash' => $hash, 'enabled' => true, 'email_verified_at' => null]);
        $owner = new AdminPasswordResetIdentity($db);
        $subject = new TokenSubject('bo', 17);

        self::assertNull((new Admin('new@example.test', $hash))->getEmailVerifiedAt());
        self::assertSame(17, $owner->findResetSubject('confirmed@example.test')?->id);
        self::assertNull($owner->recoveryEmail($subject));
        self::assertNull($owner->recoveryEmail(new TokenSubject('app', 17)));
        self::assertNull($owner->recoveryEmail(new TokenSubject('bo', 18)));

        // Represents the state AFTER a future verified confirmation flow.
        $db->executeStatement('UPDATE symsvel_admins SET email_verified_at = ? WHERE id = ?', ['2026-11-15 12:00:00', 17]);
        self::assertSame('confirmed@example.test', $owner->recoveryEmail($subject));

        $db->executeStatement('UPDATE symsvel_admins SET enabled = ? WHERE id = ?', [false, 17]);
        self::assertNull($owner->recoveryEmail($subject));
    }
}
