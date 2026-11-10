<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AdminsPingController
{
    #[Route('/api/admins/ping', name: 'symsvel_admins_ping', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['module' => 'admins', 'status' => 'ok']);
    }
}
