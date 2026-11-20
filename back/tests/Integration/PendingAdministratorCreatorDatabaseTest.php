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
use SymSvel\Module\Admins\Auth\AdminPasswordResetIdentity;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Admins\Service\PendingAdministratorCreator;
use SymSvel\Module\Auth\Token\TokenSubject;
use SymSvel\Module\Roles\Entity\Role;
use SymSvel\Module\Roles\Repository\RoleRepository;

final class PendingAdministratorCreatorDatabaseTest extends TestCase
{
    public function testInvitedAdminRequiresExistingNonDeveloperRoleAndIsInactiveUntilAtomicActivation(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }
        $config = ORMSetup::createAttributeMetadataConfiguration([
            dirname(__DIR__, 2).'/src/Entity',
            dirname((new \ReflectionClass(Role::class))->getFileName()),
        ], true);
        $config->enableNativeLazyObjects(true);
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em = new EntityManager($db, $config);
        try {
            (new SchemaTool($em))->createSchema([$em->getClassMetadata(Admin::class), $em->getClassMetadata(Role::class)]);
            $em->persist(new Role('ROLE_ADMIN', 'Administrator'));
            $em->flush();

            $registry = $this->createStub(ManagerRegistry::class);
            $registry->method('getManagerForClass')->willReturnCallback(
                static fn (string $class): ?EntityManager => in_array($class, [Admin::class, Role::class], true) ? $em : null,
            );
            $admins = new AdminRepository($registry);
            $roles = new RoleRepository($registry);
            $creator = new PendingAdministratorCreator($em, $admins, $roles);

            try {
                $creator->create('developer@example.test', 'ROLE_DEV');
                self::fail('The setup developer role must not be invit-able.');
            } catch (\InvalidArgumentException) {
            }
            try {
                $creator->create('missing@example.test', 'ROLE_MISSING');
                self::fail('The role must exist in Roles.');
            } catch (\DomainException) {
            }
            $admin = $creator->create(' invited@example.test ', 'ROLE_ADMIN');
            $id = $admin->getId();
            self::assertNotNull($id);
            self::assertTrue($admin->isInvitationPending());
            self::assertSame('ROLE_ADMIN', $admin->getRoleCode());

            $provider = new AdminIdentityProvider($admins);
            self::assertFalse($provider->isActive(new TokenSubject('bo', $id)));
            self::assertNull($provider->authenticate('bo', 'invited@example.test', 'any-password'));

            $identity = new AdminPasswordResetIdentity($db);
            $subject = new TokenSubject('bo', $id);
            self::assertNull($identity->recoveryEmail($subject));
            self::assertNull($identity->pendingVerificationEmail($subject));
            self::assertFalse($identity->replacePassword($subject, password_hash('not-yet-allowed-123', PASSWORD_ARGON2ID)));
            self::assertFalse($identity->confirmEmail($subject, hash('sha256', 'invited@example.test'), new \DateTimeImmutable()));
            self::assertFalse($identity->activateInvitation($id, hash('sha256', 'wrong@example.test'), password_hash('new-password-123', PASSWORD_ARGON2ID), new \DateTimeImmutable()));

            $hash = password_hash('new-password-123', PASSWORD_ARGON2ID);
            self::assertTrue($identity->activateInvitation($id, hash('sha256', 'invited@example.test'), $hash, new \DateTimeImmutable()));
            $em->clear();
            $activated = $em->find(Admin::class, $id);
            self::assertInstanceOf(Admin::class, $activated);
            self::assertFalse($activated->isInvitationPending());
            self::assertNotNull($activated->getEmailVerifiedAt());
            self::assertTrue($provider->isActive($subject));
            self::assertEquals($subject, $provider->authenticate('bo', 'invited@example.test', 'new-password-123'));
            self::assertFalse($identity->activateInvitation($id, hash('sha256', 'invited@example.test'), password_hash('other-password-123', PASSWORD_ARGON2ID), new \DateTimeImmutable()));

            try {
                $creator->create('invited@example.test', 'ROLE_ADMIN');
                self::fail('Duplicate addresses must not be invited.');
            } catch (\DomainException) {
            }
        } finally {
            $em->close();
            $db->close();
        }
    }
}
