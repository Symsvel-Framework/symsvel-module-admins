<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Admins\Setup\InitialAdministratorProvisioner;

final class DirectAdministratorProvisioningTest extends TestCase
{
    private function provisioner(?Admin $existing, int $count, bool $expectInsert = false): InitialAdministratorProvisioner
    {
        $repository = $this->createMock(AdminRepository::class);
        $repository->method('findOneBy')->willReturn($existing);
        $repository->method('count')->willReturn($count);
        $manager = $this->createMock(EntityManagerInterface::class);
        if ($expectInsert) {
            $manager->expects(self::once())->method('persist')->with(self::isInstanceOf(Admin::class));
            $manager->expects(self::once())->method('flush');
        } else {
            $manager->expects(self::never())->method('persist');
            $manager->expects(self::never())->method('flush');
        }
        return new InitialAdministratorProvisioner($manager, $repository);
    }

    public function testFreshProvisioningCreatesExactlyOneAdmin(): void
    {
        $this->provisioner(null, 0, true)->provisionFromPlaintext('dev@example.test', 'a-long-enough-password');
    }

    public function testRetryAcceptsMatchingCredentialsWithoutChangingHash(): void
    {
        $password = 'a-long-enough-password';
        $existing = new Admin('dev@example.test', password_hash($password, PASSWORD_ARGON2ID));
        $hash = $existing->getPasswordHash();
        $this->provisioner($existing, 1)->provisionFromPlaintext('dev@example.test', $password);
        self::assertSame($hash, $existing->getPasswordHash());
    }

    public function testRetryRejectsDifferentPassword(): void
    {
        $existing = new Admin('dev@example.test', password_hash('a-long-enough-password', PASSWORD_ARGON2ID));
        $this->expectException(\RuntimeException::class);
        $this->provisioner($existing, 1)->provisionFromPlaintext('dev@example.test', 'another-long-password');
    }

    public function testRetryRejectsAdditionalAdmin(): void
    {
        $existing = new Admin('dev@example.test', password_hash('a-long-enough-password', PASSWORD_ARGON2ID));
        $this->expectException(\RuntimeException::class);
        $this->provisioner($existing, 2)->provisionFromPlaintext('dev@example.test', 'a-long-enough-password');
    }

    public function testDifferentEmailCannotCreateSecondAdmin(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->provisioner(null, 1)->provisionFromPlaintext('other@example.test', 'a-long-enough-password');
    }
}
