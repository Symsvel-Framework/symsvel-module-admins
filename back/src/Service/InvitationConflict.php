<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Service;

/** Expected BO invitation conflicts; never contains email, tokens or exception details. */
final class InvitationConflict extends \DomainException
{
    public const EMAIL_EXISTS = 'admin_email_exists';
    public const PENDING = 'admin_invitation_pending';
    public const ROLE_MISSING = 'admin_role_missing';
    public const NOT_PENDING = 'admin_invitation_not_pending';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
