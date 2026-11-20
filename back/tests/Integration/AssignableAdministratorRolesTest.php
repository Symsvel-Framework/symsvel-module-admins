<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use SymSvel\Module\Acls\Access\BoAccessDecision;
use SymSvel\Module\Acls\Access\BoAccessGate;
use SymSvel\Module\Acls\AclAuthorizer;
use SymSvel\Module\Admins\Controller\AssignableAdministratorRolesController;
use SymSvel\Module\Roles\Entity\Role;
use SymSvel\Module\Roles\Repository\RoleRepository;

final class AssignableAdministratorRolesTest extends TestCase
{
    public function testListsOnlyExistingAndAllowedBoRoles(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite required.');
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $config = ORMSetup::createAttributeMetadataConfiguration([
            dirname((new \ReflectionClass(Role::class))->getFileName()),
        ], true);
        $config->enableNativeLazyObjects(true);
        $em = new EntityManager($db, $config);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Role::class)]);
        foreach ([
            new Role('ROLE_DEV', 'Développeur', true),
            new Role('ROLE_USER', 'Utilisateur', true),
            new Role('ROLE_SUPER_ADMIN', 'Super Administrateur', true),
            new Role('ROLE_ADMIN', 'Administrateur', true),
            new Role('ROLE_EDITOR', 'Éditeur'),
        ] as $role) {
            $em->persist($role);
        }
        $em->flush();
        $db->executeStatement('CREATE TABLE symsvel_acl_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, resource VARCHAR(150), action VARCHAR(100), role_code VARCHAR(100), allowed BOOLEAN)');
        $db->insert('symsvel_acl_rules', [
            'resource' => 'roles', 'action' => 'assign:ROLE_EDITOR',
            'role_code' => 'ROLE_ADMIN', 'allowed' => true,
        ]);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::atLeastOnce())->method('getManagerForClass')->with(Role::class)->willReturn($em);
        $repo = new RoleRepository($registry);

        $gate = new class implements BoAccessGate {
            public function check(Request $request, string $resource, string $action): BoAccessDecision
            {
                $auth = $request->headers->get('Authorization');
                return match ($auth) {
                    'Bearer Dev' => new BoAccessDecision(200, 1, 'ROLE_DEV'),
                    'Bearer Admin' => new BoAccessDecision(200, 2, 'ROLE_ADMIN'),
                    'Bearer Super' => new BoAccessDecision(200, 3, 'ROLE_SUPER_ADMIN'),
                    default => new BoAccessDecision(401),
                };
            }
        };
        $controller = new AssignableAdministratorRolesController($gate, new AclAuthorizer($db), $repo);
        self::assertSame(401, $controller(Request::create('/apibo/admins/assignable-roles'))->getStatusCode());

        $adminResponse = $controller(Request::create('/apibo/admins/assignable-roles', 'GET', [], [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer Admin']));
        self::assertSame(200, $adminResponse->getStatusCode());
        self::assertSame(['ROLE_EDITOR'], array_column(
            json_decode((string) $adminResponse->getContent(), true)['data'], 'code',
        ));

        $devResponse = $controller(Request::create('/apibo/admins/assignable-roles', 'GET', [], [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer Dev']));
        self::assertSame(200, $devResponse->getStatusCode());
        self::assertEqualsCanonicalizing(
            ['ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_SUPER_ADMIN'],
            array_column(json_decode((string) $devResponse->getContent(), true)['data'], 'code'),
        );
        self::assertTrue($devResponse->headers->hasCacheControlDirective('no-store'));

        // SUPER_ADMIN inherits functional operations, not the technical
        // permission to create another SUPER_ADMIN account.
        $superRequest = Request::create('/apibo/admins/assignable-roles', 'GET', [], [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer Super']);
        $superCodes = array_column(json_decode((string) $controller($superRequest)->getContent(), true)['data'], 'code');
        self::assertContains('ROLE_ADMIN', $superCodes);
        self::assertNotContains('ROLE_SUPER_ADMIN', $superCodes);
        $db->insert('symsvel_acl_rules', [
            'resource' => 'roles', 'action' => 'assign:ROLE_SUPER_ADMIN',
            'role_code' => 'ROLE_SUPER_ADMIN', 'allowed' => true,
        ]);
        $superCodes = array_column(json_decode((string) $controller($superRequest)->getContent(), true)['data'], 'code');
        self::assertContains('ROLE_SUPER_ADMIN', $superCodes);
    }
}
