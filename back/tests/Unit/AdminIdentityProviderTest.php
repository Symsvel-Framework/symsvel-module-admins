<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Admins\Auth\AdminIdentityProvider;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Auth\Identity\PasswordPrincipal;
use SymSvel\Module\Auth\Identity\PrincipalStatus;
use SymSvel\Module\Auth\Token\TokenSubject;

final class AdminIdentityProviderTest extends TestCase
{
    public function testImplementsBothAuthContracts(): void
    {
        self::assertTrue(is_subclass_of(AdminIdentityProvider::class, PasswordPrincipal::class));
        self::assertTrue(is_subclass_of(AdminIdentityProvider::class, PrincipalStatus::class));
    }

    public function testAppContextAndMalformedEmailAreRejectedBeforeRepositoryAccess(): void
    {
        $repository = (new \ReflectionClass(AdminRepository::class))->newInstanceWithoutConstructor();
        $provider = new AdminIdentityProvider($repository);

        self::assertNull($provider->authenticate('app', 'admin@example.test', 'secret'));
        self::assertNull($provider->authenticate('bo', 'not-an-email', 'secret'));
        self::assertNull($provider->authenticate('bo', str_repeat('a', 250).'@example.test', 'secret'));
        self::assertFalse($provider->isActive(new TokenSubject('app', 1)));
    }
}
