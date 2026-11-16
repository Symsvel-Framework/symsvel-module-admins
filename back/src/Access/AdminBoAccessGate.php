<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Access;

use Symfony\Component\HttpFoundation\Request;
use SymSvel\Module\Acls\Access\BoAccessDecision;
use SymSvel\Module\Acls\Access\BoAccessGate;
use SymSvel\Module\Acls\AclAuthorizer;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Auth\Identity\AuthenticatedTokenResolver;

/** Only Admins resolves BO principal identities; feature modules never parse tokens. */
final readonly class AdminBoAccessGate implements BoAccessGate
{
    public function __construct(
        private AuthenticatedTokenResolver $tokens,
        private AdminRepository $admins,
        private AclAuthorizer $acls,
    ) {
    }

    public function check(Request $request, string $resource, string $action): BoAccessDecision
    {
        $header = $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer ([A-Za-z0-9_-]{43})$/D', $header, $matches)) {
            return new BoAccessDecision(401);
        }

        $subject = $this->tokens->resolve(
            $matches[1], 'bo', new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        if ($subject === null) {
            return new BoAccessDecision(401);
        }

        $actor = $this->admins->find($subject->id);
        if (!$actor instanceof Admin || !$actor->isEnabled() || $actor->isInvitationPending()) {
            return new BoAccessDecision(401);
        }

        if (!$this->acls->allows($actor->getRoleCode(), $resource, $action)) {
            return new BoAccessDecision(403);
        }

        return new BoAccessDecision(200, (int) $actor->getId(), $actor->getRoleCode());
    }
}
