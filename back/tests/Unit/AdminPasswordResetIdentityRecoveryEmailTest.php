<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Auth\AdminPasswordResetIdentity;
use SymSvel\Module\Auth\Token\TokenSubject;

final class AdminPasswordResetIdentityRecoveryEmailTest extends TestCase
{
    public function testRecoveryEmailFailsClosedUntilMailboxVerificationExists(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $db = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $db->executeStatement(
            'CREATE TABLE symsvel_admins (id INTEGER PRIMARY KEY, email VARCHAR(254) NOT NULL, enabled BOOLEAN NOT NULL, invitation_pending BOOLEAN NOT NULL DEFAULT 0, email_verified_at DATETIME NULL)',
        );
        $provider = new AdminPasswordResetIdentity($db);

        self::assertSame('bo', $provider->authContext());
        self::assertNull($provider->recoveryEmail(new TokenSubject('bo', 1)));
        self::assertNull($provider->recoveryEmail(new TokenSubject('app', 1)));
    }
}
