<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Command;

use SymSvel\Module\Admins\Repository\AdminRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Read-only prerequisite for completing the two-phase setup. */
#[AsCommand(name: 'symsvel:admins:bootstrap:verify', description: 'Verify that exactly one active initial developer administrator exists')]
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
            $workspace = dirname($this->projectDir);
            $path = $workspace.'/.symsvel/setup.json';
            if (basename($this->projectDir) !== 'backend' || is_link($workspace.'/.symsvel')
                || is_link($path) || !is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('Setup state unavailable.');
            }
            $state = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($state) || ($state['schema'] ?? null) !== 1 || ($state['status'] ?? null) !== 'database_ready') {
                throw new \RuntimeException('Database phase incomplete.');
            }
            if ($this->admins->count([]) !== 1) {
                throw new \RuntimeException('Initial administrator missing or ambiguous.');
            }
            $admin = $this->admins->findOneBy([]);
            if ($admin === null || !$admin->isEnabled() || $admin->getRoleCode() !== 'ROLE_DEV') {
                throw new \RuntimeException('Invalid initial administrator.');
            }
            $output->writeln('<info>Initial administrator verified.</info>');
            return Command::SUCCESS;
        } catch (\Throwable) {
            $output->writeln('<error>Initial administrator verification failed.</error>');
            return Command::FAILURE;
        }
    }
}
