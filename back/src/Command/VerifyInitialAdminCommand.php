<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Command;

use SymSvel\Module\Admins\Repository\AdminRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'symsvel:admins:bootstrap:verify', description: 'Verify that the first developer administrator exists')]
final class VerifyInitialAdminCommand extends Command
{
    public function __construct(
        private readonly AdminRepository $admins,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $path = dirname($this->projectDir).'/.symsvel/setup.json';
            if (basename($this->projectDir) !== 'backend' || is_link($path) || !is_file($path)) {
                throw new \RuntimeException('Setup state unavailable.');
            }
            $state = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (($state['schema'] ?? null) !== 1 || ($state['status'] ?? null) !== 'database_ready') {
                throw new \RuntimeException('Database setup not completed.');
            }
            if ($this->admins->count([]) !== 1) {
                throw new \RuntimeException('Exactly one initial administrator is required.');
            }
            $admin = $this->admins->findOneBy([]);
            if ($admin === null || $admin->getRoleCode() !== 'ROLE_DEV') {
                throw new \RuntimeException('Developer administrator not found.');
            }
            $output->writeln('<info>Initial administrator verified.</info>');
            return Command::SUCCESS;
        } catch (\Throwable) {
            $output->writeln('<error>Initial administrator verification failed.</error>');
            return Command::FAILURE;
        }
    }
}
