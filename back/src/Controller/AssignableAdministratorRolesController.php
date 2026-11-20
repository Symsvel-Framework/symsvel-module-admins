<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Acls\AclAuthorizer;
use SymSvel\Module\Acls\Access\BoAccessGate;
use SymSvel\Module\Roles\Repository\RoleRepository;

/** Only persisted, assignable BO roles are exposed; invite/update recheck ACLs. */
final readonly class AssignableAdministratorRolesController
{
    public function __construct(
        private BoAccessGate $gate,
        private AclAuthorizer $acls,
        private RoleRepository $roles,
    ) {}

    #[Route('/apibo/admins/assignable-roles', name: 'symsvel_admins_assignable_roles', methods: ['GET'], format: 'json')]
    public function __invoke(Request $request): JsonResponse
    {
        $decision = $this->gate->check($request, 'admins', 'create');
        if (!$decision->allowed()) {
            $other = $this->gate->check($request, 'admins', 'update');
            if (!$other->allowed()) {
                return self::respond(['error' => ['code' => $other->status === 401 ? 'unauthorized' : 'forbidden']], $other->status);
            }
            $decision = $other;
        }
        $data = [];
        foreach ($this->roles->findBy([], ['label' => 'ASC']) as $role) {
            $code = $role->getCode();
            if (in_array($code, ['ROLE_DEV', 'ROLE_USER'], true)
                || !$this->acls->allows((string) $decision->roleCode, 'roles', 'assign:'.$code, $code === 'ROLE_SUPER_ADMIN')) {
                continue;
            }
            $data[] = ['code' => $code, 'label' => $role->getLabel()];
        }
        return self::respond(['data' => $data]);
    }

    private static function respond(array $body, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($body, $status);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }
}
