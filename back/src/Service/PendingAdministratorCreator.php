<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Service;

use Doctrine\ORM\EntityManagerInterface;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Roles\Entity\Role;
use SymSvel\Module\Roles\Repository\RoleRepository;

/**
 * Internal business operation. The BO HTTP adapter must authorize the actor
 * before calling it. Never creates a second developer account.
 */
final readonly class PendingAdministratorCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminRepository $admins,
        private RoleRepository $roles,
    ) {
    }

    public function create(string $email, string $roleCode): Admin
    {
        $email = trim($email);
        $roleCode = trim($roleCode);
        if ($roleCode === 'ROLE_DEV' || $roleCode === 'ROLE_USER'
            || !preg_match('/^ROLE_[A-Z][A-Z0-9_]*$/D', $roleCode)) {
            throw new \InvalidArgumentException('An explicit, non-developer BO role is required.');
        }

        if (!$this->roles->findOneBy(['code' => $roleCode]) instanceof Role) {
            throw new \DomainException('Unknown administrator role.');
        }
        if ($this->admins->findOneBy(['email' => $email]) !== null) {
            throw new \DomainException('An administrator already uses this address.');
        }

        $admin = Admin::invited($email, $roleCode);
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        return $admin;
    }
}
