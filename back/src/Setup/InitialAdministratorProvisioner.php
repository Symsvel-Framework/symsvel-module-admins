<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Setup;

use Doctrine\ORM\EntityManagerInterface;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;

/** Idempotent initial administrator provisioning; never resets an existing password. */
final class InitialAdministratorProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminRepository $admins,
    ) {
    }

    public function provision(string $email, string $passwordHash, string $roleCode): void
    {
        if ($roleCode !== 'ROLE_DEV') {
            throw new \InvalidArgumentException('Initial administrator must have the developer role.');
        }
        $candidate = new Admin($email, $passwordHash);
        $existing = $this->admins->findOneBy(['email' => $candidate->getEmail()]);
        if ($existing !== null) {
            if ($existing->getRoleCode() !== 'ROLE_DEV' || !hash_equals($existing->getPasswordHash(), $passwordHash)) {
                throw new \RuntimeException('Existing administrator differs from staged setup credentials.');
            }
            return;
        }
        if ($this->admins->count([]) > 0) {
            throw new \RuntimeException('Initial administrator already exists; refusing to create a second bootstrap account.');
        }
        $this->entityManager->persist($candidate);
        $this->entityManager->flush();
    }
}
