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
