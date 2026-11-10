<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Immutable module migration compiled by SymSvel Tooling. */
final class AdminsInitialMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('symsvel_admins');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('email', 'string', ['length' => 254]);
        $table->addColumn('password_hash', 'string', ['length' => 255]);
        $table->addColumn('role_code', 'string', ['length' => 100]);
        $table->addColumn('enabled', 'boolean', ['default' => true]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email'], 'uniq_symsvel_admins_email');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('symsvel_admins');
    }
}
