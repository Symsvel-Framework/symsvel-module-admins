<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Auth\Identity\AuthenticatedTokenResolver;
use SymSvel\Module\Languages\Service\LanguageCatalog;

/** An authenticated BO principal may view and change only their own language. */
final readonly class AdministratorProfileController
{
    public function __construct(
        private AuthenticatedTokenResolver $tokens,
        private AdminRepository $admins,
        private EntityManagerInterface $entityManager,
        private LanguageCatalog $languages,
    ) {
    }

    #[Route('/apibo/admins/profile', name: 'symsvel_admins_self_profile', methods: ['GET'], format: 'json')]
    public function show(Request $request): JsonResponse
    {
        $admin = $this->self($request);
        return $admin === null ? self::failure(401) : self::reply(['data' => self::present($admin)]);
    }

    #[Route('/apibo/admins/profile', name: 'symsvel_admins_self_profile_update', methods: ['PATCH'], format: 'json')]
    public function update(Request $request): JsonResponse
    {
        $admin = $this->self($request);
        if ($admin === null) return self::failure(401);
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 1024) return self::failure(400);
        try {
            $body = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::failure(400);
        }
        // Never accept ID, role, email, enabled or verification state from a profile form.
        if (!is_array($body) || array_is_list($body)
            || array_keys($body) !== ['language'] || !is_string($body['language'])) {
            return self::failure(400);
        }
        try {
            $language = $this->languages->resolveAccountLanguage($body['language']);
            $admin->setLanguage($language);
        } catch (\InvalidArgumentException) {
            return self::failure(400);
        } catch (\DomainException) {
            return self::failure(409);
        }
        $this->entityManager->flush();

        return self::reply(['data' => self::present($admin)]);
    }

    private function self(Request $request): ?Admin
    {
        if (!preg_match('/^Bearer ([A-Za-z0-9_-]{43})$/D', $request->headers->get('Authorization', ''), $found)) {
            return null;
        }
        $subject = $this->tokens->resolve(
            $found[1], 'bo', new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        if ($subject === null) return null;
        $admin = $this->admins->find($subject->id);
        return $admin instanceof Admin && $admin->isEnabled() && !$admin->isInvitationPending()
            ? $admin : null;
    }

    /** @return array{id:int,email:string,role_code:string,language:?string,email_verified:bool} */
    public static function present(Admin $admin): array
    {
        return [
            'id' => (int) $admin->getId(),
            'email' => $admin->getEmail(),
            'role_code' => $admin->getRoleCode(),
            'language' => $admin->getLanguage(),
            'email_verified' => $admin->getEmailVerifiedAt() !== null,
        ];
    }

    private static function failure(int $status): JsonResponse
    {
        return self::reply(['error' => ['code' => match ($status) {
            400 => 'invalid_request', 401 => 'unauthorized', 409 => 'unavailable',
            default => 'unavailable',
        }]], $status);
    }

    private static function reply(array $payload, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($payload, $status);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
