<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SymSvel\Module\Admins\Entity\Admin;

/** @extends ServiceEntityRepository<Admin> */
class AdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Admin::class);
    }
}
