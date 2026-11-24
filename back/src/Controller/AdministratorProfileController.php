<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Controller;

use SymSvel\Module\Auth\Identity\NewPasswordPolicy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SymSvel\Module\Auth\Token\TokenSessionRevoker;
use SymSvel\Module\Auth\Token\TokenSubject;
use SymSvel\Module\Auth\Identity\ProfileEmailChangeDispatch;
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
        private Connection $db,
        private TokenSessionRevoker $sessionRevoker,
        private ProfileEmailChangeDispatch $emailChange,
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


    /**
     * Self-service password change. Context and account identity come exclusively
     * from the verified Bearer; no role or account-management ACL is required.
     */
    #[Route('/apibo/admins/profile/password', name: 'symsvel_admins_profile_password', methods: ['POST'], format: 'json')]
    public function changePassword(Request $request): JsonResponse
    {
        $account = $this->self($request);
        if ($account === null) return self::failure(401);
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 4096) return self::failure(400);
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::failure(400);
        }
        if (!is_array($data) || array_is_list($data) || count($data) !== 2
            || !is_string($data['current_password'] ?? null)
            || !is_string($data['new_password'] ?? null)
            || !NewPasswordPolicy::accepts($data['new_password'])
            || strlen($data['current_password']) > 4096) {
            return self::failure(400);
        }
        $oldHash = $account->getPasswordHash();
        if (!password_verify($data['current_password'], $oldHash)
            || password_verify($data['new_password'], $oldHash)) {
            return self::failure(403);
        }
        $newHash = password_hash($data['new_password'], PASSWORD_ARGON2ID);
        $subject = new TokenSubject('bo', (int) $account->getId());
        try {
            $this->sessionRevoker->revokeWith(
                $subject,
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                function () use ($subject, $oldHash, $newHash): void {
                    $updated = $this->db->executeStatement(
                        'UPDATE symsvel_admins SET password_hash = ? WHERE id = ? AND password_hash = ? AND enabled = ? AND invitation_pending = ?',
                        [$newHash, $subject->id, $oldHash, true, false],
                        [ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING, ParameterType::BOOLEAN, ParameterType::BOOLEAN],
                    );
                    if ($updated !== 1) {
                        throw new \DomainException('Account credentials changed concurrently.');
                    }
                },
            );
        } catch (\DomainException) {
            return self::failure(409);
        }
        return self::reply(['status' => 'updated']);
    }


    /** Stage a new address; it is not applied until the recipient confirms the token. */
    #[Route('/apibo/admins/profile/email-change', name: 'symsvel_admins_profile_email_change', methods: ['POST'], format: 'json')]
    public function requestEmailChange(Request $request): JsonResponse
    {
        $account = $this->self($request);
        if ($account === null) return self::failure(401);
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 4096) return self::failure(400);
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::failure(400);
        }
        if (!is_array($data) || array_is_list($data) || count($data) !== 3
            || !is_string($data['current_password'] ?? null)
            || strlen($data['current_password']) > 4096
            || !is_string($data['new_email'] ?? null)
            || strlen(trim($data['new_email'])) > 254
            || filter_var(trim($data['new_email']), FILTER_VALIDATE_EMAIL) === false
            || !in_array($data['locale'] ?? null, ['fr', 'en'], true)) {
            return self::failure(400);
        }
        if (!password_verify($data['current_password'], $account->getPasswordHash())) {
            return self::failure(403);
        }
        try {
            $dispatched = $this->emailChange->dispatch(
                new TokenSubject('bo', (int) $account->getId()),
                $account->getEmail(),
                $data['new_email'],
                $data['locale'],
                (string) $request->getClientIp(),
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
        } catch (\Exception) {
            return self::failure(503);
        }
        return $dispatched ? self::reply(['status' => 'accepted'], 202)
            : self::reply(['error' => ['code' => 'unavailable']], 429);
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
            400 => 'invalid_request', 401 => 'unauthorized', 403 => 'invalid_credentials', 409 => 'unavailable', 503 => 'unavailable',
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
