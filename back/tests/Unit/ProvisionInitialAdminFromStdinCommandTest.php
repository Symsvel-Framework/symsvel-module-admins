<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Command\ProvisionInitialAdminFromStdinCommand;
use Symfony\Component\Console\Attribute\AsCommand;

final class ProvisionInitialAdminFromStdinCommandTest extends TestCase
{
    public function testDirectBootstrapHasDedicatedCommandName(): void
    {
        $attributes = (new \ReflectionClass(ProvisionInitialAdminFromStdinCommand::class))->getAttributes(AsCommand::class);
        self::assertCount(1, $attributes);
        self::assertSame('symsvel:admins:bootstrap:stdin', $attributes[0]->newInstance()->name);
    }
}
