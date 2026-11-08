<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\AdminsBundle;
use SymSvel\Module\Admins\Command\ProvisionInitialAdminFromStdinCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class ProvisionInitialAdminFromStdinCommandTest extends TestCase
{
    public function testCommandIsRegisteredByTheBundle(): void
    {
        $bundle = new AdminsBundle();
        $container = new ContainerBuilder();
        $configurator = new ContainerConfigurator($container, new \Symfony\Component\DependencyInjection\Loader\PhpFileLoader($container, new \Symfony\Component\Config\FileLocator()), 'test.php', 'test');
        $bundle->loadExtension([], $configurator, $container);
        self::assertTrue($container->hasDefinition(ProvisionInitialAdminFromStdinCommand::class));
    }
}
