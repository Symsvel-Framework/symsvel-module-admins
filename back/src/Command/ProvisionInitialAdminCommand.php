<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Command;

use SymSvel\Module\Admins\Setup\InitialAdministratorProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'symsvel:admins:bootstrap', description: 'Provision first administrator from private staged setup data')]
final class ProvisionInitialAdminCommand extends Command
{
    public function __construct(
        private readonly InitialAdministratorProvisioner $provisioner,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = dirname($this->projectDir);
        $directory = $workspace.'/.symsvel';
        $path = $directory.'/pending-admin.json';
        try {
            if (basename($this->projectDir) !== 'backend' || is_link($directory) || is_link($path) || !is_file($path)) {
                throw new \RuntimeException('Staged administrator data unavailable.');
            }
            $statePath = $directory.'/setup.json';
            if (is_link($statePath) || !is_file($statePath)) {
                throw new \RuntimeException('Setup state unavailable.');
            }
            $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($state) || ($state['schema'] ?? null) !== 1 || ($state['status'] ?? null) !== 'running') {
                throw new \RuntimeException('Administrator provisioning requires running setup.');
            }
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || ($payload['schema'] ?? null) !== 1
                || !is_string($payload['email'] ?? null)
                || !is_string($payload['password_hash'] ?? null)
                || password_get_info($payload['password_hash'])['algoName'] !== 'argon2id'
                || ($payload['initial_role'] ?? null) !== 'developer') {
                throw new \RuntimeException('Invalid staged administrator data.');
            }
            $this->provisioner->provision($payload['email'], $payload['password_hash'], 'ROLE_DEV');
            $output->writeln('<info>Initial administrator provisioned.</info>');
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>Initial administrator provisioning failed; staged data preserved.</error>');
            return Command::FAILURE;
        }
    }
}
