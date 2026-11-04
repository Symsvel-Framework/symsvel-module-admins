<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Admins\Setup\InitialAdministratorProvisioner;

final class InitialAdministratorProvisionerTest extends TestCase
{
    public function testRejectsNonDeveloperRoleBeforeAccessingDatabase(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $admins = $this->createMock(AdminRepository::class);
        $service = new InitialAdministratorProvisioner($entityManager, $admins);
        $this->expectException(\InvalidArgumentException::class);
        $service->provision('developer@example.test', password_hash('long-enough-password', PASSWORD_ARGON2ID), 'ROLE_ADMIN');
    }
}
