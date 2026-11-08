<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Command\VerifyInitialAdminCommand;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use Symfony\Component\Console\Tester\CommandTester;

final class VerifyInitialAdminCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/symsvel-verify-admin-'.bin2hex(random_bytes(8));
        mkdir($this->root);
        mkdir($this->root.'/backend');
        mkdir($this->root.'/.symsvel');
        $this->state('database_ready');
    }

    protected function tearDown(): void
    {
        @unlink($this->root.'/.symsvel/setup.json');
        @rmdir($this->root.'/.symsvel');
        @rmdir($this->root.'/backend');
        @rmdir($this->root);
    }

    private function state(string $status): void
    {
        file_put_contents($this->root.'/.symsvel/setup.json', json_encode(['schema' => 1, 'status' => $status], JSON_THROW_ON_ERROR));
    }

    private function verify(int $count, ?Admin $admin): int
    {
        $repository = $this->createMock(AdminRepository::class);
        $repository->method('count')->willReturn($count);
        $repository->method('findOneBy')->willReturn($admin);
        return (new CommandTester(new VerifyInitialAdminCommand($repository, $this->root.'/backend')))->execute([]);
    }

    public function testAcceptsSingleActiveDeveloper(): void
    {
        $admin = new Admin('dev@example.test', password_hash('a-long-enough-password', PASSWORD_ARGON2ID));
        self::assertSame(0, $this->verify(1, $admin));
    }

    public function testRejectsNoAdmin(): void
    {
        self::assertSame(1, $this->verify(0, null));
    }

    public function testRejectsMultipleAdmins(): void
    {
        $admin = new Admin('dev@example.test', password_hash('a-long-enough-password', PASSWORD_ARGON2ID));
        self::assertSame(1, $this->verify(2, $admin));
    }

    public function testRejectsMissingAdminDespiteCount(): void
    {
        self::assertSame(1, $this->verify(1, null));
    }

    public function testRejectsSetupBeforeDatabaseReady(): void
    {
        $this->state('running');
        $admin = new Admin('dev@example.test', password_hash('a-long-enough-password', PASSWORD_ARGON2ID));
        self::assertSame(1, $this->verify(1, $admin));
    }
}
