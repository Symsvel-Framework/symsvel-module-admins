<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Command\ProvisionInitialAdminFromStdinCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Tester\CommandTester;
use SymSvel\Module\Admins\Setup\InitialAdministratorProvisioner;

final class ProvisionInitialAdminFromStdinCommandTest extends TestCase
{
    public function testRejectsMissingSetupStateBeforeReadingCredentials(): void
    {
        $root = sys_get_temp_dir().'/symsvel-admin-stdin-'.bin2hex(random_bytes(8));
        mkdir($root);
        mkdir($root.'/backend');
        try {
            $provisioner = (new \ReflectionClass(InitialAdministratorProvisioner::class))->newInstanceWithoutConstructor();
            $tester = new CommandTester(new ProvisionInitialAdminFromStdinCommand($provisioner, $root.'/backend'));
            self::assertSame(1, $tester->execute([]));
            self::assertStringContainsString('provisioning failed', $tester->getDisplay());
            self::assertStringNotContainsString($root, $tester->getDisplay());
        } finally {
            rmdir($root.'/backend');
            rmdir($root);
        }
    }

    public function testDirectBootstrapHasDedicatedCommandName(): void
    {
        $attributes = (new \ReflectionClass(ProvisionInitialAdminFromStdinCommand::class))->getAttributes(AsCommand::class);
        self::assertCount(1, $attributes);
        self::assertSame('symsvel:admins:bootstrap:stdin', $attributes[0]->newInstance()->name);
    }
}
