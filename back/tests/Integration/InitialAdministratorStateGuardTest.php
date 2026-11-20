<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Command\VerifyInitialAdminCommand;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Admins\Setup\InitialAdministratorProvisioner;
use Symfony\Component\Console\Tester\CommandTester;

final class InitialAdministratorStateGuardTest extends TestCase
{
    public function testVerifyRejectsDisabledPendingUnverifiedOrMultipleDevelopers(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required for setup guard test.');
        }
        $root = sys_get_temp_dir().'/symsvel-admin-bootstrap-'.bin2hex(random_bytes(6));
        mkdir($root.'/backend', 0775, true);
        mkdir($root.'/.symsvel', 0775, true);
        file_put_contents($root.'/.symsvel/setup.json',
            json_encode(['schema' => 1, 'status' => 'database_ready'], JSON_THROW_ON_ERROR));
        $config = ORMSetup::createAttributeMetadataConfiguration([
            dirname(__DIR__, 2).'/src/Entity',
        ], true);
        $config->enableNativeLazyObjects(true);
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em = new EntityManager($db, $config);
        try {
            (new SchemaTool($em))->createSchema([$em->getClassMetadata(Admin::class)]);
            $registry = $this->createMock(ManagerRegistry::class);
            $registry->expects(self::atLeastOnce())->method('getManagerForClass')->with(Admin::class)->willReturn($em);
            $registry->method('getManager')->willReturn($em);
            $admins = new AdminRepository($registry);
            $provisioner = new InitialAdministratorProvisioner($em, $admins);
            $command = new CommandTester(new VerifyInitialAdminCommand($admins, $root.'/backend'));
            self::assertSame(1, $command->execute([]), 'Empty Setup must not verify.');

            $admin = new Admin('dev@example.test', password_hash('current-password-long', PASSWORD_ARGON2ID));
            $admin->trustEmailFromLocalSetup();
            $em->persist($admin);
            $em->flush();
            $id = $admin->getId();
            self::assertNotNull($id);
            $em->clear();
            self::assertSame(0, $command->execute([]));

            $db->executeStatement('UPDATE symsvel_admins SET enabled = 0 WHERE id = ?', [$id]);
            $em->clear();
            self::assertSame(1, $command->execute([]), 'Disabled DEV cannot complete Setup.');
            $disabled = $admins->findOneBy(['email' => 'dev@example.test']);
            self::assertNotNull($disabled);
            try {
                $provisioner->trustExistingBootstrap($disabled);
                self::fail('Disabled account must not be trusted by setup retry.');
            } catch (\RuntimeException) {
                self::assertFalse($disabled->isEnabled());
            }

            $db->executeStatement('UPDATE symsvel_admins SET enabled = 1, invitation_pending = 1 WHERE id = ?', [$id]);
            $em->clear();
            self::assertSame(1, $command->execute([]), 'Pending invitation cannot complete Setup.');
            $pending = $admins->findOneBy(['email' => 'dev@example.test']);
            self::assertNotNull($pending);
            try {
                $provisioner->trustExistingBootstrap($pending);
                self::fail('Pending account must not be trusted by setup retry.');
            } catch (\RuntimeException) {
                self::assertTrue($pending->isInvitationPending());
            }

            $db->executeStatement('UPDATE symsvel_admins SET invitation_pending = 0, email_verified_at = NULL WHERE id = ?', [$id]);
            $em->clear();
            self::assertSame(1, $command->execute([]), 'Unverified bootstrap cannot complete Setup.');

            $db->executeStatement('UPDATE symsvel_admins SET email_verified_at = ? WHERE id = ?',
                ['2026-11-19 10:00:00', $id]);
            $em->clear();
            self::assertSame(0, $command->execute([]));

            $other = Admin::invited('other@example.test', 'ROLE_ADMIN');
            $em->persist($other);
            $em->flush();
            $em->clear();
            self::assertSame(1, $command->execute([]), 'Setup requires exactly one account.');
        } finally {
            $em->close();
            $db->close();
            unlink($root.'/.symsvel/setup.json');
            rmdir($root.'/.symsvel');
            rmdir($root.'/backend');
            rmdir($root);
        }
    }
}
