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
            $this->trustExistingBootstrap($existing);
            return;
        }
        if ($this->admins->count([]) > 0) {
            throw new \RuntimeException('Initial administrator already exists; refusing to create a second bootstrap account.');
        }
        $candidate->trustEmailFromLocalSetup();
        $this->entityManager->persist($candidate);
        $this->entityManager->flush();
    }
    /** Only called after a guarded local Setup has verified the existing credentials. */
    public function trustExistingBootstrap(Admin $admin): void
    {
        if ($admin->getRoleCode() !== 'ROLE_DEV' || $this->admins->count([]) !== 1) {
            throw new \RuntimeException('Cannot trust a non-initial administrator.');
        }
        if ($admin->getEmailVerifiedAt() === null) {
            $admin->trustEmailFromLocalSetup();
            $this->entityManager->flush();
        }
    }

}
