<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Acls\Access\BoAccessGate;
use SymSvel\Module\Acls\AclAuthorizer;
use SymSvel\Module\Auth\Token\TokenSessionRevoker;
use SymSvel\Module\Auth\Token\TokenSubject;
use SymSvel\Module\Roles\Entity\Role;
use SymSvel\Module\Roles\Repository\RoleRepository;

/** Protected account maintenance. Creation remains invitation-only. */
final readonly class AdministratorAccountController
{
    public function __construct(
        private BoAccessGate $access,
        private Connection $db,
        private AclAuthorizer $acls,
        private RoleRepository $roles,
        private TokenSessionRevoker $sessions,
    ) {
    }

    #[Route('/apibo/admins', name: 'symsvel_admins_account_list', methods: ['GET'], format: 'json')]
    public function list(Request $request): JsonResponse
    {
        $decision = $this->access->check($request, 'admins', 'list');
        if (!$decision->allowed()) {
            return self::failure($decision->status);
        }
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, email, role_code, enabled, invitation_pending, email_verified_at, created_at FROM symsvel_admins ORDER BY id DESC LIMIT 200',
        );
        return self::response(['data' => array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'role_code' => (string) $row['role_code'],
            'enabled' => (bool) $row['enabled'],
            'invitation_pending' => (bool) $row['invitation_pending'],
            'email_verified' => $row['email_verified_at'] !== null,
            'created_at' => (string) $row['created_at'],
        ], $rows)]);
    }

    #[Route('/apibo/admins/{id<\d+>}', name: 'symsvel_admins_account_update', methods: ['PATCH'], format: 'json')]
    public function update(Request $request, int $id): JsonResponse
    {
        $decision = $this->access->check($request, 'admins', 'update');
        if (!$decision->allowed()) {
            return self::failure($decision->status);
        }
        $payload = self::payload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }
        if ($payload === [] || array_diff(array_keys($payload), ['role_code', 'enabled'])) {
            return self::failure(400);
        }
        if (array_key_exists('enabled', $payload) && !is_bool($payload['enabled'])) {
            return self::failure(400);
        }
        if (array_key_exists('role_code', $payload)
            && (!is_string($payload['role_code'])
                || !preg_match('/^ROLE_[A-Z][A-Z0-9_]*$/D', $payload['role_code'])
                || in_array($payload['role_code'], ['ROLE_DEV', 'ROLE_USER'], true))) {
            return self::failure(400);
        }

        $target = $id > 0 ? $this->db->fetchAssociative(
            'SELECT id, role_code, enabled FROM symsvel_admins WHERE id = ?', [$id],
        ) : false;
        if ($target === false) {
            return self::failure(404);
        }
        // The original developer and one's own administrator identity are immutable.
        if ($target['role_code'] === 'ROLE_DEV' || $decision->actorId === $id) {
            return self::failure(403);
        }
        $changes = [];
        if (isset($payload['role_code']) && $payload['role_code'] !== $target['role_code']) {
            $role = $payload['role_code'];
            if (!$this->roles->findOneBy(['code' => $role]) instanceof Role
                || !$this->acls->allows((string) $decision->roleCode, 'roles', 'assign:'.$role)) {
                return self::failure(403);
            }
            $changes['role_code'] = $role;
        }
        if (array_key_exists('enabled', $payload) && $payload['enabled'] !== (bool) $target['enabled']) {
            $changes['enabled'] = $payload['enabled'];
        }
        if ($changes !== []) {
            $this->sessions->revokeWith(
                new TokenSubject('bo', $id), new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                function () use ($id, $changes): void {
                    $this->db->update('symsvel_admins', $changes, ['id' => $id]);
                },
            );
        }
        return self::response(['status' => 'updated']);
    }

    #[Route('/apibo/admins/{id<\d+>}', name: 'symsvel_admins_account_delete', methods: ['DELETE'], format: 'json')]
    public function delete(Request $request, int $id): JsonResponse
    {
        $decision = $this->access->check($request, 'admins', 'delete');
        if (!$decision->allowed()) {
            return self::failure($decision->status);
        }
        $target = $id > 0 ? $this->db->fetchAssociative(
            'SELECT id, role_code FROM symsvel_admins WHERE id = ?', [$id],
        ) : false;
        if ($target === false) {
            return self::failure(404);
        }
        if ($target['role_code'] === 'ROLE_DEV' || $decision->actorId === $id) {
            return self::failure(403);
        }
        if (!$this->acls->allows((string) $decision->roleCode, 'roles', 'assign:'.$target['role_code'])) {
            return self::failure(403);
        }
        $this->sessions->revokeWith(
            new TokenSubject('bo', $id), new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            function () use ($id): void {
                $this->db->delete('symsvel_auth_admin_invitations', ['principal_id' => $id]);
                $this->db->delete('symsvel_admins', ['id' => $id]);
            },
        );
        return self::response(['status' => 'deleted']);
    }

    /** @return array<string, mixed>|JsonResponse */
    private static function payload(Request $request): array|JsonResponse
    {
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')) {
            return self::failure(415);
        }
        $raw = $request->getContent();
        if (strlen($raw) > 8192) {
            return self::failure(400);
        }
        try {
            $payload = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::failure(400);
        }
        return is_array($payload) && !array_is_list($payload) ? $payload : self::failure(400);
    }

    private static function failure(int $status): JsonResponse
    {
        return self::response(['error' => ['code' => match ($status) {
            400 => 'invalid_request', 401 => 'unauthorized', 403 => 'forbidden',
            404 => 'not_found', 415 => 'unsupported_media_type',
            default => 'unavailable',
        }]], $status);
    }

    private static function response(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }
}
