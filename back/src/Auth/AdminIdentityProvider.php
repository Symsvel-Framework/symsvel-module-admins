<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Auth;

use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Auth\Identity\PasswordPrincipal;
use SymSvel\Module\Auth\Identity\ContextOwnedIdentity;
use SymSvel\Module\Auth\Identity\PrincipalStatus;
use SymSvel\Module\Auth\Token\TokenSubject;

/**
 * BO-only identity adapter. Auth owns tokens, Admins owns credentials and status.
 * The host composition root will select this adapter for the BO context.
 */
final readonly class AdminIdentityProvider implements PasswordPrincipal, PrincipalStatus, ContextOwnedIdentity
{
    public function __construct(private AdminRepository $admins)
    {
    }

    public function authContext(): string
    {
        return 'bo';
    }

    public function authenticate(string $context, string $identifier, string $password): ?TokenSubject
    {
        $identifier = trim($identifier);
        if ($context !== 'bo' || filter_var($identifier, FILTER_VALIDATE_EMAIL) === false || strlen($identifier) > 254) {
            return null;
        }

        $admin = $this->admins->findOneBy(['email' => $identifier]);
        if (!$admin instanceof Admin || !$admin->isEnabled() || $admin->isInvitationPending() || !password_verify($password, $admin->getPasswordHash())) {
            return null;
        }

        $id = $admin->getId();

        return $id === null ? null : new TokenSubject('bo', $id);
    }

    public function isActive(TokenSubject $subject): bool
    {
        if ($subject->context !== 'bo') {
            return false;
        }

        $admin = $this->admins->find($subject->id);

        return $admin instanceof Admin && $admin->isEnabled() && !$admin->isInvitationPending();
    }
}
