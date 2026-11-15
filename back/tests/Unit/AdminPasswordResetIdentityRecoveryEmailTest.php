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

        $provider = new AdminPasswordResetIdentity(DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]));

        self::assertSame('bo', $provider->authContext());
        self::assertNull($provider->recoveryEmail(new TokenSubject('bo', 1)));
        self::assertNull($provider->recoveryEmail(new TokenSubject('app', 1)));
    }
}
