<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Entity\Admin;

final class AdminTest extends TestCase
{
    public function testCreatesAdminWithHashedPassword(): void
    {
        $hash = password_hash('correct-horse-battery-123', PASSWORD_ARGON2ID);
        $admin = new Admin(' developer@example.test ', $hash);
        self::assertSame('developer@example.test', $admin->getEmail());
        self::assertSame($hash, $admin->getPasswordHash());
        self::assertTrue($admin->isEnabled());
        self::assertNull($admin->getId());
    }

    public function testInvitedAdministratorCannotBecomeDeveloperAndHasUnusableInitialPassword(): void
    {
        $invite = Admin::invited(' invited@example.test ', 'ROLE_ADMIN');
        self::assertSame('invited@example.test', $invite->getEmail());
        self::assertSame('ROLE_ADMIN', $invite->getRoleCode());
        self::assertTrue($invite->isInvitationPending());
        self::assertNull($invite->getEmailVerifiedAt());
        self::assertSame('argon2id', password_get_info($invite->getPasswordHash())['algoName']);
        $this->expectException(\LogicException::class);
        $invite->trustEmailFromLocalSetup();
    }

    public function testInvitationCannotCreateDeveloper(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Admin::invited('developer2@example.test', 'ROLE_DEV');
    }

    public function testInvitationCannotCreateAppUser(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Admin::invited('app@example.test', 'ROLE_USER');
    }

    public function testRejectsPlaintextPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Admin('developer@example.test', 'plaintext-password');
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Admin('invalid', password_hash('correct-horse-battery-123', PASSWORD_ARGON2ID));
    }
}
