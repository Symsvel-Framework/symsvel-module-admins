<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Auth\AdminIdentityProvider;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Auth\Token\TokenSubject;

final class AdminIdentityProviderDatabaseTest extends TestCase
{
    public function testPersistedAdminCanLoginAndIsRejectedAfterDeletion(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for the integration test.');
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 2).'/src/Entity'],
            true,
        );
        // PHP 8.4 native lazy objects avoid an unnecessary Symfony LazyGhost dependency.
        $configuration->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = new EntityManager($connection, $configuration);

        try {
            (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Admin::class)]);

            $registry = $this->createMock(ManagerRegistry::class);
            $registry->expects(self::atLeastOnce())->method('getManagerForClass')->with(Admin::class)->willReturn($entityManager);
            $registry->method('getManager')->willReturn($entityManager);
            $provider = new AdminIdentityProvider(new AdminRepository($registry));

            $admin = new Admin('admin@example.test', password_hash('correct-password', PASSWORD_ARGON2ID));
            $entityManager->persist($admin);
            $entityManager->flush();
            $id = $admin->getId();
            self::assertNotNull($id);
            $entityManager->clear();

            self::assertNull($provider->authenticate('bo', 'admin@example.test', 'wrong-password'));
            self::assertNull($provider->authenticate('app', 'admin@example.test', 'correct-password'));
            self::assertEquals(new TokenSubject('bo', $id), $provider->authenticate('bo', ' admin@example.test ', 'correct-password'));
            self::assertTrue($provider->isActive(new TokenSubject('bo', $id)));

            $persisted = $entityManager->find(Admin::class, $id);
            self::assertNotNull($persisted);
            $entityManager->remove($persisted);
            $entityManager->flush();
            $entityManager->clear();

            self::assertFalse($provider->isActive(new TokenSubject('bo', $id)));
            self::assertNull($provider->authenticate('bo', 'admin@example.test', 'correct-password'));
        } finally {
            $entityManager->close();
            $connection->close();
        }
    }
}
