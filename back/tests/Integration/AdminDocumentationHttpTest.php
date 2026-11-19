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
use SymSvel\Module\Admins\Controller\AdministratorDocumentationController;
use SymSvel\Module\Admins\Documentation\DocumentationCatalog;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Auth\Identity\AuthenticatedTokenResolver;
use SymSvel\Module\Auth\Token\AccessTokenLookup;
use SymSvel\Module\Auth\Token\AccessTokenRecord;
use SymSvel\Module\Auth\Token\AccessTokenVerifier;
use SymSvel\Module\Auth\Token\TokenSubject;
use Symfony\Component\HttpFoundation\Request;

final class AdminDocumentationHttpTest extends TestCase
{
    private const DEV_TOKEN = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    private const ADMIN_TOKEN = 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB';

    public function testOnlyAuthenticatedBoAndDeveloperMayReadSensitiveDocumentation(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }
        $root = sys_get_temp_dir().'/symsvel-doc-api-'.bin2hex(random_bytes(5));
        mkdir($root.'/var/symsvel', 0775, true);
        $pages = [
            ['id' => 'admin:module:admins:index.md', 'scope' => 'admin', 'source' => 'module',
                'owner' => 'admins', 'file' => 'index.md', 'title' => 'Admins',
                'markdown' => '# Administration'],
            ['id' => 'dev:host:back:adr/0001.md', 'scope' => 'dev', 'source' => 'host',
                'owner' => 'back', 'file' => 'adr/0001.md', 'title' => 'Architecture',
                'markdown' => '# Developer only'],
        ];
        $encoded = json_encode($pages,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        file_put_contents($root.'/var/symsvel/documentation.json',
            json_encode(['schema' => 1, 'generated_by' => 'symsvel-tooling',
                'checksum' => hash('sha256', $encoded), 'pages' => $pages],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em = new EntityManager($db, $config);
        try {
            (new SchemaTool($em))->createSchema([$em->getClassMetadata(Admin::class)]);
            $dev = new Admin('dev@example.test', password_hash('secure-test-password', PASSWORD_ARGON2ID));
            $dev->trustEmailFromLocalSetup();
            $regular = new Admin('regular@example.test', password_hash('secure-test-password', PASSWORD_ARGON2ID));
            $em->persist($dev);
            $em->persist($regular);
            $em->flush();
            $regularId = (int) $regular->getId();
            $devId = (int) $dev->getId();
            $db->executeStatement('UPDATE symsvel_admins SET role_code = ? WHERE id = ?',
                ['ROLE_ADMIN', $regularId]);
            $em->clear();

            $registry = $this->createMock(ManagerRegistry::class);
            $registry->method('getManagerForClass')->with(Admin::class)->willReturn($em);
            $registry->method('getManager')->willReturn($em);
            $admins = new AdminRepository($registry);
            $now = new \DateTimeImmutable();
            $records = [
                hash('sha256', self::DEV_TOKEN) =>
                    new AccessTokenRecord(new TokenSubject('bo', $devId), $now->modify('+1 hour'), null),
                hash('sha256', self::ADMIN_TOKEN) =>
                    new AccessTokenRecord(new TokenSubject('bo', $regularId), $now->modify('+1 hour'), null),
            ];
            $lookup = new class($records) implements AccessTokenLookup {
                public function __construct(private array $records) {}
                public function findAccessByHash(string $hash): ?AccessTokenRecord
                {
                    return $this->records[$hash] ?? null;
                }
            };
            $resolver = new AuthenticatedTokenResolver(
                new AccessTokenVerifier($lookup), new AdminIdentityProvider($admins));
            $controller = new AdministratorDocumentationController(
                $resolver, $admins, new DocumentationCatalog($root));

            self::assertSame(401, $controller->index(self::request(null))->getStatusCode());
            self::assertSame(401, $controller->index(self::request(str_repeat('X', 43)))->getStatusCode());
            $ordinary = $controller->index(self::request(self::ADMIN_TOKEN));
            self::assertSame(200, $ordinary->getStatusCode());
            self::assertTrue($ordinary->headers->hasCacheControlDirective('no-store'));
            self::assertTrue(self::body($ordinary)['published']);
            self::assertCount(1, self::body($ordinary)['data']);
            self::assertSame('admin', self::body($ordinary)['data'][0]['scope']);
            $develop = $controller->index(self::request(self::DEV_TOKEN));
            self::assertCount(2, self::body($develop)['data']);

            $devPage = 'dev:host:back:adr/0001.md';
            $regularRequest = self::request(self::ADMIN_TOKEN, $devPage);
            self::assertSame(404, $controller->page($regularRequest)->getStatusCode());
            $devResponse = $controller->page(self::request(self::DEV_TOKEN, $devPage));
            self::assertSame(200, $devResponse->getStatusCode());
            self::assertSame('# Developer only', self::body($devResponse)['data']['markdown']);
            self::assertSame(200, $controller->page(
                self::request(self::ADMIN_TOKEN, 'admin:module:admins:index.md')
            )->getStatusCode());
            self::assertSame(400, $controller->page(self::request(self::DEV_TOKEN))->getStatusCode());

            // A revoked/deactivated principal cannot use a previously valid token.
            $db->executeStatement('UPDATE symsvel_admins SET enabled = 0 WHERE id = ?', [$regularId]);
            $em->clear();
            self::assertSame(401, $controller->index(self::request(self::ADMIN_TOKEN))->getStatusCode());

            // Local tampering must fail closed for an authorized developer.
            $path = $root.'/var/symsvel/documentation.json';
            $tampered = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            $tampered['pages'][0]['markdown'] = 'tampered';
            file_put_contents($path, json_encode($tampered, JSON_THROW_ON_ERROR));
            self::assertSame(503, $controller->index(self::request(self::DEV_TOKEN))->getStatusCode());
        } finally {
            $em->close();
            $db->close();
            unlink($root.'/var/symsvel/documentation.json');
            rmdir($root.'/var/symsvel');
            rmdir($root.'/var');
            rmdir($root);
        }
    }

    private static function request(?string $token, ?string $id = null): Request
    {
        $server = $token === null ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
        $query = $id === null ? [] : ['id' => $id];
        return Request::create('/apibo/admins/documentation', 'GET', $query, [], [], $server);
    }

    /** @return array<string,mixed> */
    private static function body(\Symfony\Component\HttpFoundation\Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 16, JSON_THROW_ON_ERROR);
    }
}
