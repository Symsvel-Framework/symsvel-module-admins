<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use SymSvel\Module\Admins\Command\ProvisionInitialAdminCommand;
use SymSvel\Module\Admins\Command\ProvisionInitialAdminFromStdinCommand;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Admins\Setup\InitialAdministratorProvisioner;

final class AdminsBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        foreach ([AdminRepository::class, InitialAdministratorProvisioner::class, ProvisionInitialAdminCommand::class, ProvisionInitialAdminFromStdinCommand::class] as $service) {
            $builder->register($service, $service)->setAutowired(true)->setAutoconfigured(true);
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->extension('doctrine', [
            'orm' => [
                'mappings' => [
                    'SymSvelAdmins' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'SymSvel\\Module\\Admins\\Entity',
                        'alias' => 'SymSvelAdmins',
                    ],
                ],
            ],
        ]);
    }
}
