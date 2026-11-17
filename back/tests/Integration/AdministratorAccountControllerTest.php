<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use SymSvel\Module\Acls\Access\BoAccessDecision;
use SymSvel\Module\Acls\Access\BoAccessGate;
use SymSvel\Module\Acls\AclAuthorizer;
use SymSvel\Module\Admins\Controller\AdministratorAccountController;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Auth\Schema\AdminInvitationSchema;
use SymSvel\Module\Auth\Schema\AuthTokenSchema;
use SymSvel\Module\Auth\Token\DbalTokenStore;
use SymSvel\Module\Auth\Token\TokenSessionRevoker;
use SymSvel\Module\Languages\Service\LanguageCatalog;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Entity\Setting;
use SymSvel\Module\Settings\Repository\SettingRepository;
use SymSvel\Module\Settings\Service\SettingRegistry;
use SymSvel\Module\Settings\Service\SettingSecretCipher;
use SymSvel\Module\Settings\Service\SettingKeyQualifier;
use SymSvel\Module\Languages\Service\LanguagesSettingDefinitionProvider;
use SymSvel\Module\Roles\Entity\Role;
use SymSvel\Module\Roles\Repository\RoleRepository;

final class AdministratorAccountControllerTest extends TestCase
{
    /** @return array{AdministratorAccountController, EntityManager, int, int} */
    private function fixture(int $status = 200, bool $asTarget = false): array
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        $config = ORMSetup::createAttributeMetadataConfiguration([
            dirname((new \ReflectionClass(Admin::class))->getFileName()),
            dirname((new \ReflectionClass(Role::class))->getFileName()),
            dirname((new \ReflectionClass(Setting::class))->getFileName()),
        ], true);
        $config->enableNativeLazyObjects(true);
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em = new EntityManager($db, $config);
        (new SchemaTool($em))->createSchema([
            $em->getClassMetadata(Admin::class), $em->getClassMetadata(Role::class),
            $em->getClassMetadata(Setting::class),
        ]);
        $schema = new Schema();
        AuthTokenSchema::apply($schema);
        AdminInvitationSchema::apply($schema);
        foreach ($schema->toSql($db->getDatabasePlatform()) as $sql) {
            $db->executeStatement($sql);
        }

        $developer = new Admin('developer@example.test', password_hash('initial-password-123', PASSWORD_ARGON2ID));
        $developer->trustEmailFromLocalSetup();
        $target = Admin::invited('manager@example.test', 'ROLE_ADMIN');
        $em->persist($developer);
        $em->persist($target);
        $em->persist(new Role('ROLE_ADMIN', 'Administrator'));
        $em->persist(new Role('ROLE_EDITOR', 'Editor'));
        $em->flush();
        $developerId = (int) $developer->getId();
        $targetId = (int) $target->getId();
        $em->clear();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(
            static fn (string $class) => in_array($class, [Role::class, Setting::class], true) ? $em : null,
        );
        $decision = $status === 200
            ? new BoAccessDecision(200, $asTarget ? $targetId : $developerId, $asTarget ? 'ROLE_ADMIN' : 'ROLE_DEV')
            : new BoAccessDecision($status);
        $gate = new class($decision) implements BoAccessGate {
            public function __construct(private BoAccessDecision $decision) {}
            public function check(Request $request, string $resource, string $action): BoAccessDecision
            {
                if ($resource !== 'admins' || !in_array($action, ['list', 'update', 'delete'], true)) {
                    throw new \LogicException('Wrong permission');
                }
                return $this->decision;
            }
        };
        // Use the real Settings service: it is final and must not be mocked.
        $settingsRegistry = new SettingRegistry([]);
        $settingsRegistry->registerOwned(new LanguagesSettingDefinitionProvider(), 'languages', new SettingKeyQualifier());
        $settingsManager = new SettingManager($settingsRegistry, new SettingRepository($registry),
            $em, new SettingSecretCipher('test-secret-at-least-16-chars'));
        $settingsManager->set('languages.primary_language', 'fr');
        $settingsManager->flush();
        $db->executeStatement('CREATE TABLE symsvel_languages (iso_code VARCHAR(4) PRIMARY KEY, name VARCHAR(100), active BOOLEAN, pre_open BOOLEAN)');
        $db->insert('symsvel_languages', ['iso_code' => 'fr', 'name' => 'Français', 'active' => true, 'pre_open' => false]);
        $controller = new AdministratorAccountController(
            $gate, $db, new AclAuthorizer($db), new RoleRepository($registry),
            new TokenSessionRevoker(new DbalTokenStore($db)),
            new LanguageCatalog($db, $settingsManager),
        );
        return [$controller, $em, $developerId, $targetId];
    }

    public function testDeveloperAndOwnAccountCannotBeMutated(): void
    {
        [$controller, $em, $dev, $target] = $this->fixture();
        try {
            self::assertSame(403, $controller->update(self::jsonRequest($dev, ['enabled' => false]), $dev)->getStatusCode());
            self::assertSame(403, $controller->delete(Request::create('/apibo/admins/'.$dev, 'DELETE'), $dev)->getStatusCode());
            self::assertSame(1, (int) $em->getConnection()->fetchOne('SELECT enabled FROM symsvel_admins WHERE id = ?', [$dev]));
        } finally {
            $em->getConnection()->close();
        }

        [$controller, $em, , $target] = $this->fixture(200, true);
        try {
            self::assertSame(403, $controller->update(self::jsonRequest($target, ['enabled' => false]), $target)->getStatusCode());
            self::assertSame(403, $controller->delete(Request::create('/apibo/admins/'.$target, 'DELETE'), $target)->getStatusCode());
        } finally {
            $em->getConnection()->close();
        }
    }

    public function testAdminLifecycleRevokesTokensOnModificationAndDeletion(): void
    {
        [$controller, $em, $dev, $target] = $this->fixture();
        $db = $em->getConnection();
        try {
            $db->insert('symsvel_auth_access_tokens', [
                'context' => 'bo',
                'principal_id' => $target,
                'family_id' => str_repeat('a', 32),
                'token_hash' => hash('sha256', 'first-session'),
                'created_at' => '2026-11-16 12:00:00',
                'expires_at' => '2026-11-17 12:00:00',
            ]);
            $listing = $controller->list(Request::create('/apibo/admins'));
            self::assertSame(200, $listing->getStatusCode());
            self::assertTrue($listing->headers->hasCacheControlDirective('no-store'));
            self::assertStringNotContainsString('password_hash', (string) $listing->getContent());
            self::assertCount(2, json_decode((string) $listing->getContent(), true, 8, JSON_THROW_ON_ERROR)['data']);

            $res = $controller->update(self::jsonRequest($target, ['role_code' => 'ROLE_EDITOR', 'enabled' => false]), $target);
            self::assertSame(200, $res->getStatusCode());
            $row = $db->fetchAssociative('SELECT role_code, enabled FROM symsvel_admins WHERE id = ?', [$target]);
            self::assertSame('ROLE_EDITOR', $row['role_code']);
            self::assertSame(0, (int) $row['enabled']);
            self::assertNotNull($db->fetchOne('SELECT revoked_at FROM symsvel_auth_access_tokens WHERE principal_id = ?', [$target]));

            self::assertSame(200, $controller->delete(Request::create('/apibo/admins/'.$target, 'DELETE'), $target)->getStatusCode());
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_admins WHERE id = ?', [$target]));
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_admins WHERE id = ?', [$dev]));
        } finally {
            $db->close();
        }
    }

    public function testMissingTokenAndRejectedAclCannotRead(): void
    {
        foreach ([401, 403] as $status) {
            [$controller, $em] = $this->fixture($status);
            try {
                self::assertSame($status, $controller->list(Request::create('/apibo/admins'))->getStatusCode());
                self::assertSame($status, $controller->update(self::jsonRequest(2, ['enabled' => false]), 2)->getStatusCode());
            } finally {
                $em->getConnection()->close();
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private static function jsonRequest(int $id, array $payload): Request
    {
        return Request::create('/apibo/admins/'.$id, 'PATCH', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
