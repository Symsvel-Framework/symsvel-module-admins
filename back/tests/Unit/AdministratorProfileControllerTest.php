<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Admins\Controller\AdministratorProfileController;
use SymSvel\Module\Admins\Entity\Admin;

final class AdministratorProfileControllerTest extends TestCase
{
    public function testSelfProfileDoesNotExposePasswordHashOrTokens(): void
    {
        $admin = new Admin('owner@example.test', password_hash('private-passphrase', PASSWORD_ARGON2ID));
        $admin->setLanguage('fr');
        $public = AdministratorProfileController::present($admin);
        self::assertSame(['id', 'email', 'role_code', 'language', 'email_verified'], array_keys($public));
        self::assertSame('owner@example.test', $public['email']);
        self::assertSame('fr', $public['language']);
        self::assertArrayNotHasKey('password_hash', $public);
    }

    public function testBothRoutesAreAuthenticatedBoSelfProfileRoutes(): void
    {
        foreach (['show' => 'GET', 'update' => 'PATCH'] as $method => $verb) {
            $attributes = (new \ReflectionMethod(AdministratorProfileController::class, $method))
                ->getAttributes(Route::class);
            self::assertCount(1, $attributes);
            $route = $attributes[0]->newInstance();
            self::assertSame('/apibo/admins/profile', $route->getPath());
            self::assertSame([$verb], $route->getMethods());
        }
    }
}
