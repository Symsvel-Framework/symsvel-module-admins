<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

    public function create(string $email, string $roleCode, ?string $language = null): Admin
    {
        $email = trim($email);
        $roleCode = trim($roleCode);
        if ($roleCode === 'ROLE_DEV' || $roleCode === 'ROLE_USER'
            || !preg_match('/^ROLE_[A-Z][A-Z0-9_]*$/D', $roleCode)) {
            throw new \InvalidArgumentException('An explicit, non-developer BO role is required.');
        }

        if (!$this->roles->findOneBy(['code' => $roleCode]) instanceof Role) {
            throw new InvitationConflict(InvitationConflict::ROLE_MISSING);
        }
        $existing = $this->admins->findOneBy(['email' => $email]);
        if ($existing instanceof Admin) {
            throw new InvitationConflict($existing->isInvitationPending()
                ? InvitationConflict::PENDING : InvitationConflict::EMAIL_EXISTS);
        }

        $admin = Admin::invited($email, $roleCode);
        $admin->setLanguage($language);
        $this->entityManager->persist($admin);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // The email uniqueness constraint also protects simultaneous invitations.
            throw new InvitationConflict(InvitationConflict::EMAIL_EXISTS);
        }

        return $admin;
    }
}
