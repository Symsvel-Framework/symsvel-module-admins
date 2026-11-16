<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use SymSvel\Module\Acls\AclAuthorizer;
use SymSvel\Module\Admins\Auth\AdminIdentityProvider;
use SymSvel\Module\Admins\Auth\AdminPasswordResetIdentity;
use SymSvel\Module\Admins\Controller\AdministratorInvitationController;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Admins\Service\AdministratorInvitationManager;
use SymSvel\Module\Admins\Service\PendingAdministratorCreator;
use SymSvel\Module\Auth\Identity\AdminInvitationDispatch;
use SymSvel\Module\Auth\Identity\AuthenticatedTokenResolver;
use SymSvel\Module\Auth\Mail\AuthMailTemplateProvider;
use SymSvel\Module\Auth\Schema\AdminInvitationSchema;
use SymSvel\Module\Auth\Service\AdminInvitationUrlResolver;
use SymSvel\Module\Auth\Service\AuthSettingDefinitionProvider;
use SymSvel\Module\Auth\Token\AccessTokenLookup;
use SymSvel\Module\Auth\Token\AccessTokenRecord;
use SymSvel\Module\Auth\Token\AccessTokenVerifier;
use SymSvel\Module\Auth\Token\DbalAdminInvitationStore;
use SymSvel\Module\Auth\Token\TokenSubject;
use SymSvel\Module\Mails\Mail\ConfiguredMailSender;
use SymSvel\Module\Mails\Mail\MailSender;
use SymSvel\Module\Mails\Mail\MailTemplateRegistry;
use SymSvel\Module\Mails\Mail\RegisteredMailRenderer;
use SymSvel\Module\Mails\Service\MailsSettingDefinitionProvider;
use SymSvel\Module\Roles\Entity\Role;
use SymSvel\Module\Roles\Repository\RoleRepository;
use SymSvel\Module\Settings\Entity\Setting;
use SymSvel\Module\Settings\Repository\SettingRepository;
use SymSvel\Module\Settings\Service\SettingKeyQualifier;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingRegistry;
use SymSvel\Module\Settings\Service\SettingSecretCipher;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class AdministratorInvitationHttpTest extends TestCase
{
    private const DEV_TOKEN = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    private const ADMIN_TOKEN = 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB';

    public function testDeveloperCanInviteAndResendAndCannotCreateAnotherDeveloper(): void
    {
        [$controller, $db, $admins, $messages] = $this->fixture();

        $response = $controller->create(self::request(
            '/apibo/admins/invitations', self::DEV_TOKEN,
            ['email' => ' invited@example.test ', 'role_code' => 'ROLE_ADMIN', 'locale' => 'fr'],
        ));
        self::assertSame(201, $response->getStatusCode());
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $body = json_decode((string) $response->getContent(), true, 8, JSON_THROW_ON_ERROR);
        $id = $body['id'];
        $admin = $admins->find($id);
        self::assertInstanceOf(Admin::class, $admin);
        self::assertTrue($admin->isInvitationPending());
        self::assertSame('ROLE_ADMIN', $admin->getRoleCode());
        self::assertNull($admin->getEmailVerifiedAt());
        self::assertSame(1, count($messages));
        self::assertSame('invited@example.test', $messages[0]->getTo()[0]->getAddress());
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_admin_invitations'));

        $resend = $controller->resend(self::request(
            '/apibo/admins/'.$id.'/invitations/resend', self::DEV_TOKEN, ['locale' => 'en'],
        ), $id);
        self::assertSame(200, $resend->getStatusCode());
        self::assertSame(2, count($messages));
        self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_admin_invitations'));

        $forbiddenRole = $controller->create(self::request(
            '/apibo/admins/invitations', self::DEV_TOKEN,
            ['email' => 'bad@example.test', 'role_code' => 'ROLE_DEV', 'locale' => 'fr'],
        ));
        self::assertSame(400, $forbiddenRole->getStatusCode());
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_admins WHERE invitation_pending = 1'));
    }

    public function testTokenAndAclMustBothAuthorizeOperations(): void
    {
        [$controller, $db] = $this->fixture();
        $payload = ['email' => 'new@example.test', 'role_code' => 'ROLE_ADMIN', 'locale' => 'fr'];
        self::assertSame(401, $controller->create(self::request('/apibo/admins/invitations', null, $payload))->getStatusCode());
        self::assertSame(401, $controller->create(self::request('/apibo/admins/invitations', str_repeat('X', 43), $payload))->getStatusCode());
        self::assertSame(403, $controller->create(self::request('/apibo/admins/invitations', self::ADMIN_TOKEN, $payload))->getStatusCode());

        $db->insert('symsvel_acl_rules', ['resource' => 'admins', 'action' => 'create', 'role_code' => 'ROLE_ADMIN', 'allowed' => true]);
        // Permission to create alone must never allow privilege assignment.
        self::assertSame(403, $controller->create(self::request('/apibo/admins/invitations', self::ADMIN_TOKEN, $payload))->getStatusCode());
        self::assertSame(403, $controller->resend(self::request('/apibo/admins/999/invitations/resend', self::ADMIN_TOKEN, ['locale' => 'fr']), 999)->getStatusCode());
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_admin_invitations'));
    }

    /** @return array{AdministratorInvitationController, Connection, AdminRepository, \ArrayObject} */
    private function fixture(): array
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
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
            $em->getClassMetadata(Admin::class),
            $em->getClassMetadata(Role::class),
            $em->getClassMetadata(Setting::class),
        ]);
        $db->executeStatement('CREATE TABLE symsvel_acl_rules (id INTEGER PRIMARY KEY, resource VARCHAR(150), action VARCHAR(100), role_code VARCHAR(100), allowed BOOLEAN)');
        $schema = new Schema();
        AdminInvitationSchema::apply($schema);
        foreach ($schema->toSql($db->getDatabasePlatform()) as $sql) {
            $db->executeStatement($sql);
        }

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(
            static fn (string $class) => in_array($class, [Admin::class, Role::class, Setting::class], true) ? $em : null,
        );
        $registry->method('getManager')->willReturn($em);
        $admins = new AdminRepository($registry);
        $roles = new RoleRepository($registry);
        $settingRepository = new SettingRepository($registry);
        $em->persist(new Role('ROLE_ADMIN', 'Administrator'));
        $developer = new Admin('developer@example.test', password_hash('dev-password-12345', PASSWORD_ARGON2ID));
        $developer->trustEmailFromLocalSetup();
        $em->persist($developer);
        $operator = Admin::invited('operator@example.test', 'ROLE_ADMIN');
        $em->persist($operator);
        $em->flush();
        $identity = new AdminPasswordResetIdentity($db);
        self::assertTrue($identity->activateInvitation(
            (int) $operator->getId(), hash('sha256', $operator->getEmail()),
            password_hash('operator-password-123', PASSWORD_ARGON2ID), new \DateTimeImmutable(),
        ));
        $em->clear();

        $settingsRegistry = new SettingRegistry([]);
        $qualifier = new SettingKeyQualifier();
        $settingsRegistry->registerOwned(new AuthSettingDefinitionProvider(), 'auth', $qualifier);
        $settingsRegistry->registerOwned(new MailsSettingDefinitionProvider(), 'mails', $qualifier);
        $settings = new SettingManager(
            $settingsRegistry, $settingRepository, $em, new SettingSecretCipher('test-secret-at-least-16-chars'),
        );
        $settings->set('mails.sender_email', 'noreply@example.test');
        $settings->set('auth.bo_invitation_url', 'https://bo.example.test/activate');
        $settings->flush();

        $messages = new \ArrayObject();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(
            static function (RawMessage $message) use ($messages): void { $messages->append($message); },
        );
        $loader = new FilesystemLoader();
        $loader->addPath(dirname((new \ReflectionClass(AuthMailTemplateProvider::class))->getFileName(), 3).'/templates', 'Auth');
        $renderer = new RegisteredMailRenderer(
            new MailTemplateRegistry([new AuthMailTemplateProvider()]),
            new Environment($loader, ['autoescape' => 'name']),
        );
        $dispatch = new AdminInvitationDispatch(
            new AdminInvitationUrlResolver($settings),
            new DbalAdminInvitationStore($db),
            $renderer,
            new ConfiguredMailSender(new MailSender($mailer), $settings),
        );
        $manager = new AdministratorInvitationManager(
            new PendingAdministratorCreator($em, $admins, $roles), $admins, $dispatch,
        );
        $now = new \DateTimeImmutable();
        $accessRecords = [
            hash('sha256', self::DEV_TOKEN) => new AccessTokenRecord(
                new TokenSubject('bo', (int) $developer->getId()), $now->modify('+3600 seconds'), null,
            ),
            hash('sha256', self::ADMIN_TOKEN) => new AccessTokenRecord(
                new TokenSubject('bo', (int) $operator->getId()), $now->modify('+3600 seconds'), null,
            ),
        ];
        $lookup = new class($accessRecords) implements AccessTokenLookup {
            public function __construct(private array $records) {}
            public function findAccessByHash(string $hash): ?AccessTokenRecord { return $this->records[$hash] ?? null; }
        };
        $auth = new AuthenticatedTokenResolver(new AccessTokenVerifier($lookup), new AdminIdentityProvider($admins));
        $controller = new AdministratorInvitationController($auth, $admins, new AclAuthorizer($db), $manager);

        return [$controller, $db, $admins, $messages];
    }

    private static function request(string $path, ?string $bearer, array $data): Request
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($bearer !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
        }
        return Request::create($path, 'POST', [], [], [], $server, json_encode($data, JSON_THROW_ON_ERROR));
    }
}
