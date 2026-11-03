<?php

declare(strict_types=1);

namespace SymSvel\Module\SymsvelModuleAdmins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\SymsvelModuleAdmins\SymsvelModuleAdminsBundle;

final class SymsvelModuleAdminsBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        self::assertInstanceOf(SymsvelModuleAdminsBundle::class, new SymsvelModuleAdminsBundle());
    }
}
