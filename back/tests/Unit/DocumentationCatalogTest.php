<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Documentation\DocumentationCatalog;

final class DocumentationCatalogTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/symsvel-doc-'.bin2hex(random_bytes(5));
        mkdir($this->root.'/var/symsvel', 0775, true);
        $pages = [
            ['id' => 'admin:module:admins:index.md', 'scope' => 'admin', 'source' => 'module',
                'owner' => 'admins', 'file' => 'index.md', 'title' => 'Administrateurs',
                'markdown' => "# Administrateurs\n<script>alert('x')</script>"],
            ['id' => 'dev:host:back:adr/0001.md', 'scope' => 'dev', 'source' => 'host',
                'owner' => 'back', 'file' => 'adr/0001.md', 'title' => 'Architecture',
                'markdown' => '# Architecture interne'],
            ['id' => 'user:module:users:index.md', 'scope' => 'user', 'source' => 'module',
                'owner' => 'users', 'file' => 'index.md', 'title' => 'Utilisateur',
                'markdown' => '# Utilisateur'],
        ];
        $this->publish($pages);
    }

    protected function tearDown(): void
    {
        $path = $this->root.'/var/symsvel/documentation.json';
        if (is_file($path) || is_link($path)) {
            unlink($path);
        }
        rmdir($this->root.'/var/symsvel');
        rmdir($this->root.'/var');
        rmdir($this->root);
    }

    public function testDeveloperOnlyScopeNeverLeaksIntoOrdinaryBoListingsOrPages(): void
    {
        $catalog = new DocumentationCatalog($this->root);
        self::assertTrue($catalog->published());
        self::assertCount(2, $catalog->listing(false));
        self::assertCount(3, $catalog->listing(true));
        self::assertNull($catalog->get('dev:host:back:adr/0001.md', false));
        self::assertSame('Architecture', $catalog->get('dev:host:back:adr/0001.md', true)['title']);
        self::assertStringContainsString(
            "<script>alert('x')</script>",
            $catalog->get('admin:module:admins:index.md', false)['markdown'],
        );
        self::assertArrayNotHasKey('markdown', $catalog->listing(false)[0]);
    }

    public function testTamperingAndSymbolicPublicationAreRejected(): void
    {
        $catalog = new DocumentationCatalog($this->root);
        $path = $this->root.'/var/symsvel/documentation.json';
        $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        $data['pages'][0]['markdown'] = 'changed';
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        try {
            $catalog->listing(true);
            self::fail('Tampered documentation must not be served.');
        } catch (\RuntimeException) {
            self::assertTrue(is_file($path));
        }
        unlink($path);
        symlink(__FILE__, $path);
        $this->expectException(\RuntimeException::class);
        $catalog->listing(true);
    }

    /** @param list<array<string,string>> $pages */
    private function publish(array $pages): void
    {
        $pagesJson = json_encode($pages,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        file_put_contents($this->root.'/var/symsvel/documentation.json',
            json_encode(['schema' => 1, 'generated_by' => 'symsvel-tooling',
                'checksum' => hash('sha256', $pagesJson), 'pages' => $pages],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
