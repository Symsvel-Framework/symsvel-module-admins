<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NativeInterfaceScopesTest extends TestCase
{
    public function testOnlyFunctionalInterfaceScopesAreDeclared(): void
    {
        $root = dirname(__DIR__, 3);
        $manifest = json_decode((string) file_get_contents($root.'/module.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['back' => 'back', 'bo' => 'bo'], $manifest['components']);
        self::assertFileExists($root.'/bo/src/routes/admins/+page.svelte');
        self::assertFileDoesNotExist($root.'/app/src/routes/admins/+page.svelte');
        self::assertFileDoesNotExist($root.'/front/src/routes/admins/+page.svelte');
    }
}
