<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Acls\AclAuthorizer;
use SymSvel\Module\Admins\Entity\Admin;
use SymSvel\Module\Admins\Repository\AdminRepository;
use SymSvel\Module\Admins\Service\AdministratorInvitationManager;
use SymSvel\Module\Admins\Service\InvitationConflict;
use SymSvel\Module\Auth\Identity\AuthenticatedTokenResolver;
use SymSvel\Module\Languages\Service\LanguageCatalog;

/**
 * BO-only invitation endpoints. Auth resolves the current principal, ACLs
 * authorizes the action, Admins owns account creation, Auth sends the link.
 */
final readonly class AdministratorInvitationController
{
    public function __construct(
        private AuthenticatedTokenResolver $tokens,
        private AdminRepository $admins,
        private AclAuthorizer $acls,
        private AdministratorInvitationManager $invitations,
        private LanguageCatalog $languages,
    ) {
    }

    /** List pending BO invitations only after a dedicated ACL check. */
    #[Route('/apibo/admins/invitations', name: 'symsvel_admins_invitation_list', methods: ['GET'], format: 'json')]
    public function list(Request $request): JsonResponse
    {
        $actor = $this->actor($request, 'list');
        if ($actor instanceof JsonResponse) {
            return $actor;
        }

        $pending = $this->admins->findBy(
            ['invitationPending' => true],
            ['createdAt' => 'DESC'],
            100,
        );
        return self::response([
            'data' => array_map(static fn (Admin $admin): array => [
                'id' => $admin->getId(),
                'email' => $admin->getEmail(),
                'role_code' => $admin->getRoleCode(),
                'language' => $admin->getLanguage(),
                'enabled' => $admin->isEnabled(),
                'created_at' => $admin->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], $pending),
        ]);
    }

    #[Route('/apibo/admins/invitations', name: 'symsvel_admins_invitation_create', methods: ['POST'], format: 'json')]
    public function create(Request $request): JsonResponse
    {
        $actor = $this->actor($request, 'create');
        if ($actor instanceof JsonResponse) {
            return $actor;
        }

        $data = self::payload($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $email = $data['email'] ?? null;
        $roleCode = $data['role_code'] ?? null;
        $locale = $data['locale'] ?? 'fr';
        $requestedLanguage = $data['language'] ?? null;
        if (array_diff(array_keys($data), ['email', 'role_code', 'locale', 'language'])
            || ($requestedLanguage !== null && !is_string($requestedLanguage))
            || !is_string($email) || !is_string($roleCode) || !is_string($locale)
            || !in_array($locale, ['fr', 'en'], true)
            || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false
            || strlen(trim($email)) > 254
            || !preg_match('/^ROLE_[A-Z][A-Z0-9_]*$/D', $roleCode)) {
            return self::response(['error' => ['code' => 'invalid_request']], 400);
        }
        if (!$this->acls->allows($actor->getRoleCode(), 'roles', 'assign:'.$roleCode, $roleCode === 'ROLE_SUPER_ADMIN')) {
            return self::response(['error' => ['code' => 'forbidden']], 403);
        }

        try {
            $language = $this->languages->resolveAccountLanguage($requestedLanguage);
        } catch (\InvalidArgumentException) {
            return self::response(['error' => ['code' => 'admin_language_invalid']], 400);
        } catch (\DomainException) {
            return self::response(['error' => ['code' => 'admin_language_unavailable']], 409);
        }

        try {
            $id = $this->invitations->invite(
                $email, $roleCode, $locale, new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $language,
            );
        } catch (\InvalidArgumentException) {
            return self::response(['error' => ['code' => 'invalid_request']], 400);
        } catch (InvitationConflict $conflict) {
            return self::response(['error' => ['code' => $conflict->reason]], 409);
        } catch (\DomainException) {
            return self::response(['error' => ['code' => 'invitation_unavailable']], 409);
        }

        return self::response(['id' => $id, 'status' => 'invited'], 201);
    }

    #[Route('/apibo/admins/{id<\d+>}/invitations/resend', name: 'symsvel_admins_invitation_resend', methods: ['POST'], format: 'json')]
    public function resend(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request, 'resend');
        if ($actor instanceof JsonResponse) {
            return $actor;
        }

        $data = self::payload($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $locale = $data['locale'] ?? 'fr';
        if (!is_string($locale) || !in_array($locale, ['fr', 'en'], true)) {
            return self::response(['error' => ['code' => 'invalid_request']], 400);
        }

        $target = $id > 0 ? $this->admins->find($id) : null;
        if (!$target instanceof Admin
            || !$this->acls->allows($actor->getRoleCode(), 'roles', 'assign:'.$target->getRoleCode(), $target->getRoleCode() === 'ROLE_SUPER_ADMIN')) {
            return self::response(['error' => ['code' => 'invitation_unavailable']], 404);
        }

        try {
            $this->invitations->resend($id, $locale, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        } catch (InvitationConflict $conflict) {
            return self::response(['error' => ['code' => $conflict->reason]], 409);
        } catch (\DomainException) {
            return self::response(['error' => ['code' => 'invitation_unavailable']], 409);
        }

        return self::response(['status' => 'invitation_resent']);
    }

    /** @return Admin|JsonResponse */
    private function actor(Request $request, string $action): Admin|JsonResponse
    {
        $authorization = $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer ([A-Za-z0-9_-]{43})$/D', $authorization, $match)) {
            return self::response(['error' => ['code' => 'unauthorized']], 401);
        }
        $subject = $this->tokens->resolve(
            $match[1], 'bo', new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        if ($subject === null) {
            return self::response(['error' => ['code' => 'unauthorized']], 401);
        }
        $admin = $this->admins->find($subject->id);
        if (!$admin instanceof Admin || !$admin->isEnabled() || $admin->isInvitationPending()) {
            return self::response(['error' => ['code' => 'unauthorized']], 401);
        }
        if (!$this->acls->allows($admin->getRoleCode(), 'admins', $action)) {
            return self::response(['error' => ['code' => 'forbidden']], 403);
        }

        return $admin;
    }

    /** @return array<string, mixed>|JsonResponse */
    private static function payload(Request $request): array|JsonResponse
    {
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')) {
            return self::response(['error' => ['code' => 'unsupported_media_type']], 415);
        }
        $raw = $request->getContent();
        if (strlen($raw) > 8192) {
            return self::response(['error' => ['code' => 'invalid_request']], 400);
        }
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::response(['error' => ['code' => 'invalid_request']], 400);
        }
        return is_array($data) && !array_is_list($data)
            ? $data
            : self::response(['error' => ['code' => 'invalid_request']], 400);
    }

    private static function response(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
