<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class InvitationPendingMigrationTest extends TestCase
{
    public function testAdditiveInvitationFlagDoesNotRewriteInitialTable(): void
    {
        require_once dirname(__DIR__, 3).'/Migration/Version20261116000002.php';
        $schema = new Schema();
        $table = $schema->createTable('symsvel_admins');
        $table->addColumn('id', 'integer');
        $table->addColumn('email', 'string', ['length' => 254]);
        $table->setPrimaryKey(['id']);

        $migration = (new \ReflectionClass(\DoctrineMigrations\Version20261116000002::class))
            ->newInstanceWithoutConstructor();
        $migration->up($schema);
        self::assertTrue($table->hasColumn('invitation_pending'));
        self::assertFalse($table->getColumn('invitation_pending')->getDefault());
        self::assertTrue($table->hasColumn('email'));
        $migration->down($schema);
        self::assertFalse($table->hasColumn('invitation_pending'));
        self::assertTrue($table->hasColumn('email'));
    }
}
