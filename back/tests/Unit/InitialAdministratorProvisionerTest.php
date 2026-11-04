<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Entity\Admin;

final class InitialAdministratorProvisionerTest extends TestCase
{
    public function testInitialAdminReceivesDeveloperRole(): void
    {
        $admin = new Admin('developer@example.test', password_hash('long-enough-password', PASSWORD_ARGON2ID));
        self::assertSame('ROLE_DEV', $admin->getRoleCode());
    }
}
