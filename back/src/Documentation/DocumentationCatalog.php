<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Documentation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Read-only private documentation artifact, prepared by maintenance Tooling. */
final readonly class DocumentationCatalog
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    /** @return list<array{id:string,scope:string,owner:string,title:string,source:string,file:string}> */
    public function listing(bool $developer): array
    {
        $pages = [];
        foreach ($this->pages() as $page) {
            if ($page['scope'] === 'dev' && !$developer) {
                continue;
            }
            $pages[] = [
                'id' => $page['id'], 'scope' => $page['scope'],
                'owner' => $page['owner'], 'title' => $page['title'],
                'source' => $page['source'], 'file' => $page['file'],
            ];
        }
        return $pages;
    }

    /** @return null|array{id:string,scope:string,owner:string,title:string,source:string,file:string,markdown:string} */
    public function get(string $id, bool $developer): ?array
    {
        foreach ($this->pages() as $page) {
            if ($page['id'] === $id && ($page['scope'] !== 'dev' || $developer)) {
                return $page;
            }
        }
        return null;
    }

    public function published(): bool
    {
        return is_file($this->path());
    }

    private function path(): string
    {
        return rtrim($this->projectDir, '/').'/var/symsvel/documentation.json';
    }

    /** @return list<array{id:string,scope:string,owner:string,title:string,source:string,file:string,markdown:string}> */
    private function pages(): array
    {
        $path = $this->path();
        foreach ([dirname($path), dirname(dirname($path)), $path] as $location) {
            if (is_link($location)) {
                throw new \RuntimeException('Documentation link refused.');
            }
        }
        if (!is_file($path)) {
            return [];
        }
        $size = filesize($path);
        if ($size === false || $size > 16777216) {
            throw new \RuntimeException('Invalid documentation size.');
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Invalid documentation index.', 0, $e);
        }
        if (!is_array($data) || ($data['schema'] ?? null) !== 1
            || ($data['generated_by'] ?? null) !== 'symsvel-tooling'
            || !is_array($data['pages'] ?? null) || !array_is_list($data['pages'])
            || !is_string($data['checksum'] ?? null)) {
            throw new \RuntimeException('Invalid documentation index.');
        }
        $checksum = hash('sha256', json_encode($data['pages'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        if (!hash_equals($data['checksum'], $checksum)) {
            throw new \RuntimeException('Documentation index checksum mismatch.');
        }
        $seen = [];
        foreach ($data['pages'] as $page) {
            if (!is_array($page)) {
                throw new \RuntimeException('Invalid documentation page.');
            }
            foreach (['id', 'scope', 'source', 'owner', 'file', 'title', 'markdown'] as $key) {
                if (!is_string($page[$key] ?? null)) {
                    throw new \RuntimeException('Invalid documentation page.');
                }
            }
            if (!in_array($page['scope'], ['dev', 'admin', 'user'], true)
                || !in_array($page['source'], ['module', 'host'], true)
                || !preg_match('/^[a-z][a-z0-9-]*$/D', $page['owner'])
                || strlen($page['markdown']) > 524288
                || strlen($page['title']) > 500
                || isset($seen[$page['id']])
                || $page['id'] !== $page['scope'].':'.$page['source'].':'.$page['owner'].':'.$page['file']) {
                throw new \RuntimeException('Invalid documentation page.');
            }
            $seen[$page['id']] = true;
        }
        return $data['pages'];
    }
}
