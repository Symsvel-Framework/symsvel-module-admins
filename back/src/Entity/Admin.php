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

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function getRoleCode(): string { return $this->roleCode; }
    public function isEnabled(): bool { return $this->enabled; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
