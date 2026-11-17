<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Entity;

use Doctrine\ORM\Mapping as ORM;
use SymSvel\Module\Admins\Repository\AdminRepository;

#[ORM\Entity(repositoryClass: AdminRepository::class)]
#[ORM\Table(name: 'symsvel_admins')]
#[ORM\UniqueConstraint(name: 'uniq_symsvel_admins_email', columns: ['email'])]
class Admin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 254)]
    private string $email;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    #[ORM\Column(name: 'role_code', length: 100)]
    private string $roleCode = 'ROLE_DEV';

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'invitation_pending', options: ['default' => false])]
    private bool $invitationPending = false;

    /** ISO 639 language code; deliberately no foreign key to Languages. */
    #[ORM\Column(name: 'language', length: 4, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(name: 'email_verified_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    public function __construct(string $email, string $passwordHash)
    {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
            throw new \InvalidArgumentException('Invalid administrator email.');
        }
        if (password_get_info($passwordHash)['algoName'] !== 'argon2id') {
            throw new \InvalidArgumentException('Administrator password must be hashed with Argon2id.');
        }
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Create a BO account that cannot authenticate until the one-time
     * invitation sets its own password and validates its mailbox.
     */
    public static function invited(string $email, string $roleCode): self
    {
        if (!preg_match('/^ROLE_[A-Z][A-Z0-9_]*$/D', $roleCode)
            || in_array($roleCode, ['ROLE_DEV', 'ROLE_USER'], true)) {
            throw new \InvalidArgumentException('Invalid role for an invited administrator.');
        }
        // An unknown random secret is never exposed as an initial password.
        $admin = new self($email, password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID));
        $admin->roleCode = $roleCode;
        $admin->invitationPending = true;

        return $admin;
    }

    /**
     * Trust the first developer's address only during the guarded local Setup bootstrap.
     * This is not a general-purpose e-mail verification mechanism.
     */
    public function trustEmailFromLocalSetup(): void
    {
        if ($this->invitationPending || $this->roleCode !== 'ROLE_DEV') {
            throw new \LogicException('Local setup trust is reserved for the developer account.');
        }
        if ($this->emailVerifiedAt === null) {
            $this->emailVerifiedAt = new \DateTimeImmutable();
        }
    }

    public function getLanguage(): ?string { return $this->language; }

    public function setLanguage(?string $language): void
    {
        if ($language !== null) {
            $language = strtolower(trim($language));
            if (preg_match('/^[a-z]{2,4}$/D', $language) !== 1) {
                throw new \InvalidArgumentException('Invalid ISO language code.');
            }
        }
        $this->language = $language;
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getEmailVerifiedAt(): ?\DateTimeImmutable { return $this->emailVerifiedAt; }
    public function isInvitationPending(): bool { return $this->invitationPending; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function getRoleCode(): string { return $this->roleCode; }
    public function isEnabled(): bool { return $this->enabled; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
