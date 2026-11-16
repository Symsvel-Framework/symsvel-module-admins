<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Pending invitations cannot authenticate or bypass first-password activation. */
final class Version20261116000002 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $schema->getTable('symsvel_admins')->addColumn('invitation_pending', 'boolean', ['default' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('symsvel_admins')->dropColumn('invitation_pending');
    }
}
