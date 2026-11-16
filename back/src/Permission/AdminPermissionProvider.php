<?php

declare(strict_types=1);

namespace SymSvel\Module\Admins\Permission;

use SymSvel\Module\Acls\Permission\PermissionDefinition;
use SymSvel\Module\Acls\Permission\PermissionProviderInterface;

final class AdminPermissionProvider implements PermissionProviderInterface
{
    public function permissions(): iterable
    {
        foreach ([
            ['list', 'Lister les administrateurs'],
            ['create', 'Inviter un administrateur'],
            ['update', 'Modifier un administrateur'],
            ['delete', 'Supprimer un administrateur'],
            ['resend', 'Renvoyer une invitation administrateur'],
        ] as [$action, $label]) {
            yield new PermissionDefinition('admins', $action, $label);
        }
    }
}
