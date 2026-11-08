<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Command;

use SymSvel\Module\Admins\Setup\InitialAdministratorProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Receives one-time credentials over standard input, never through arguments or files.
 * The invoking process must supply a JSON object with email and password.
 */
#[AsCommand(name: 'symsvel:admins:bootstrap:stdin', description: 'Provision the first administrator from standard input')]
final class ProvisionInitialAdminFromStdinCommand extends Command
{
    public function __construct(
        private readonly InitialAdministratorProvisioner $provisioner,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $workspace = dirname($this->projectDir);
            $stateFile = $workspace.'/.symsvel/setup.json';
            if (basename($this->projectDir) !== 'backend' || is_link($stateFile) || !is_file($stateFile)) {
                throw new \RuntimeException('Setup state unavailable.');
            }
            $state = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($state) || ($state['schema'] ?? null) !== 1 || ($state['status'] ?? null) !== 'database_ready') {
                throw new \RuntimeException('Administrator provisioning requires database_ready setup.');
            }
            $stream = fopen('php://stdin', 'rb');
            if ($stream === false) {
                throw new \RuntimeException('Standard input unavailable.');
            }
            try {
                $json = stream_get_contents($stream, 8193);
            } finally {
                fclose($stream);
            }
            if (!is_string($json) || strlen($json) > 8192) {
                throw new \RuntimeException('Invalid administrator payload.');
            }
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $email = $payload['email'] ?? null;
            $password = $payload['password'] ?? null;
            if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254
                || !is_string($password) || strlen($password) < 12 || strlen($password) > 4096
                || str_contains($password, "\0")) {
                throw new \RuntimeException('Invalid administrator credentials.');
            }
            $this->provisioner->provision(trim($email), password_hash($password, PASSWORD_ARGON2ID), 'ROLE_DEV');
            $output->writeln('<info>Initial administrator provisioned.</info>');

            return Command::SUCCESS;
        } catch (\Throwable) {
            // Never include submitted credentials or database errors in console output.
            $output->writeln('<error>Initial administrator provisioning failed.</error>');

            return Command::FAILURE;
        }
    }
}
