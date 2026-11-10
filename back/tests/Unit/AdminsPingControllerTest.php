<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Admins\Controller\AdminsPingController;

final class AdminsPingControllerTest extends TestCase
{
    public function testPingResponseAndRouteContract(): void
    {
        $controller = new AdminsPingController();
        self::assertSame(['module' => 'admins', 'status' => 'ok'], json_decode($controller()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        $routes = (new \ReflectionMethod(AdminsPingController::class, '__invoke'))->getAttributes(Route::class);
        self::assertCount(1, $routes);
        $route = $routes[0]->newInstance();
        self::assertSame('/api/admins/ping', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
    }
}
