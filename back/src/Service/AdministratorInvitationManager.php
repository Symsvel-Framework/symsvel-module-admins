<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Service;

use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Auth\Identity\AdminInvitationDispatch;

/**
 * Internal BO operation. Caller MUST check the authenticated administrator's
 * ACL before creating or resending an invitation.
 */
final readonly class AdministratorInvitationManager
{
    public function __construct(
        private PendingAdministratorCreator $creator,
        private AdminRepository $admins,
        private AdminInvitationDispatch $dispatch,
    ) {
    }

    public function invite(string $email, string $roleCode, string $locale, \DateTimeImmutable $now): int
    {
        $admin = $this->creator->create($email, $roleCode);
        $id = $admin->getId();
        if ($id === null) {
            throw new \LogicException('Pending administrator was not persisted.');
        }
        // Dispatch only after the account is persisted. If mail delivery fails,
        // the pending account remains disabled for authentication and can be resent.
        $this->dispatch->dispatch($id, $admin->getEmail(), $locale, $now);

        return $id;
    }

    public function resend(int $adminId, string $locale, \DateTimeImmutable $now): void
    {
        $admin = $this->admins->find($adminId);
        if (!$admin instanceof Admin || !$admin->isEnabled()
            || !$admin->isInvitationPending() || $admin->getEmailVerifiedAt() !== null) {
            throw new \DomainException('Administrator has no pending invitation.');
        }

        $this->dispatch->dispatch($adminId, $admin->getEmail(), $locale, $now);
    }
}
