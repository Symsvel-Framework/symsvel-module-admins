<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Controller;

use SymSvel\Module\Admins\Documentation\DocumentationCatalog;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Auth\Identity\AuthenticatedTokenResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Documentation is private runtime data; no Svelte static asset exposes it. */
final readonly class AdministratorDocumentationController
{
    public function __construct(
        private AuthenticatedTokenResolver $tokens,
        private AdminRepository $admins,
        private DocumentationCatalog $catalog,
    ) {
    }

    #[Route('/apibo/admins/documentation', name: 'symsvel_admins_documentation_index', methods: ['GET'], format: 'json')]
    public function index(Request $request): JsonResponse
    {
        $admin = $this->principal($request);
        if ($admin === null) {
            return self::reply(['error' => ['code' => 'unauthorized']], 401);
        }
        try {
            return self::reply([
                'data' => $this->catalog->listing($admin->getRoleCode() === 'ROLE_DEV'),
                'published' => $this->catalog->published(),
            ]);
        } catch (\RuntimeException) {
            return self::reply(['error' => ['code' => 'unavailable']], 503);
        }
    }

    #[Route('/apibo/admins/documentation/page', name: 'symsvel_admins_documentation_page', methods: ['GET'], format: 'json')]
    public function page(Request $request): JsonResponse
    {
        $admin = $this->principal($request);
        if ($admin === null) {
            return self::reply(['error' => ['code' => 'unauthorized']], 401);
        }
        try {
            $id = $request->query->get('id');
        } catch (\Throwable) {
            return self::reply(['error' => ['code' => 'invalid_request']], 400);
        }
        if (!is_string($id) || strlen($id) > 1024 || $id === '' || str_contains($id, "\0")) {
            return self::reply(['error' => ['code' => 'invalid_request']], 400);
        }
        try {
            $page = $this->catalog->get($id, $admin->getRoleCode() === 'ROLE_DEV');
        } catch (\RuntimeException) {
            return self::reply(['error' => ['code' => 'unavailable']], 503);
        }
        return $page === null
            ? self::reply(['error' => ['code' => 'not_found']], 404)
            : self::reply(['data' => $page]);
    }

    private function principal(Request $request): ?Admin
    {
        $header = $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer ([A-Za-z0-9_-]{43})$/D', $header, $match)) {
            return null;
        }
        $subject = $this->tokens->resolve(
            $match[1], 'bo', new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        if ($subject === null) {
            return null;
        }
        $admin = $this->admins->find($subject->id);
        return $admin instanceof Admin && $admin->isEnabled() && !$admin->isInvitationPending()
            ? $admin : null;
    }

    /** @param array<string,mixed> $data */
    private static function reply(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
