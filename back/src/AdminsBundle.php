<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use SymSvel\Module\Admins\Controller\AdminsPingController;
use SymSvel\Module\Admins\Auth\AdminIdentityProvider;
use SymSvel\Module\Admins\Auth\AdminPasswordResetIdentity;
use SymSvel\Module\Admins\Command\ProvisionInitialAdminCommand;
use SymSvel\Module\Admins\Command\ProvisionInitialAdminFromStdinCommand;
use SymSvel\Module\Admins\Command\VerifyInitialAdminCommand;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Admins\Setup\InitialAdministratorProvisioner;

final class AdminsBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->register(AdminsPingController::class, AdminsPingController::class)
            ->setAutowired(true)
            ->addTag('controller.service_arguments');
        foreach ([AdminIdentityProvider::class, AdminRepository::class, InitialAdministratorProvisioner::class, ProvisionInitialAdminCommand::class, ProvisionInitialAdminFromStdinCommand::class, VerifyInitialAdminCommand::class] as $service) {
            $builder->register($service, $service)->setAutowired(true)->setAutoconfigured(true);
        }
        $builder->getDefinition(AdminIdentityProvider::class)->addTag('symsvel.auth.identity_provider');
        $builder->register(AdminPasswordResetIdentity::class, AdminPasswordResetIdentity::class)
            ->setAutowired(true)
            ->addTag('symsvel.auth.password_reset_identity')
            ->addTag('symsvel.auth.email_verification_identity')
            ->addTag('symsvel.auth.admin_invitation_identity');
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
