<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Command;

use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Admins\Setup\InitialAdministratorProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'symsvel:admins:bootstrap:stdin', description: 'Create the first administrator from standard input during local setup')]
final class ProvisionInitialAdminFromStdinCommand extends Command
{
    public function __construct(
        private readonly InitialAdministratorProvisioner $provisioner,
        private readonly AdminRepository $admins,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $workspace = dirname($this->projectDir);
            $statePath = $workspace.'/.symsvel/setup.json';
            if (basename($this->projectDir) !== 'backend' || is_link($statePath) || !is_file($statePath)) {
                throw new \RuntimeException('Setup state unavailable.');
            }
            $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
            if (($state['schema'] ?? null) !== 1 || ($state['status'] ?? null) !== 'database_ready') {
                throw new \RuntimeException('Administrator setup requires database_ready.');
            }
            $raw = stream_get_contents(STDIN, 8192);
            if (!is_string($raw) || $raw === '' || strlen($raw) >= 8192) {
                throw new \RuntimeException('Invalid administrator input.');
            }
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;
            if (!is_string($email) || !is_string($password) || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || strlen($password) < 12 || strlen($password) > 4096 || str_contains($password, "\0")) {
                throw new \RuntimeException('Invalid administrator credentials.');
            }
            $email = trim($email);
            $existing = $this->admins->findOneBy(['email' => $email]);
            if ($existing !== null) {
                if ($existing->getRoleCode() !== 'ROLE_DEV' || !password_verify($password, $existing->getPasswordHash())) {
                    throw new \RuntimeException('Administrator already exists with different credentials.');
                }
            } else {
                $this->provisioner->provision($email, password_hash($password, PASSWORD_ARGON2ID), 'ROLE_DEV');
            }
            $output->writeln('<info>Initial administrator ready.</info>');
            return Command::SUCCESS;
        } catch (\Throwable) {
            $output->writeln('<error>Initial administrator provisioning failed.</error>');
            return Command::FAILURE;
        }
    }
}
