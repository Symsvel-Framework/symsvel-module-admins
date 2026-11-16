# Admins

Documentation admin.

## Gestion des comptes BO

La page \`/admins\` présente les administrateurs, leur rôle, leur statut et les invitations en attente. La création passe uniquement par invitation : le destinataire choisit son mot de passe lors de l'activation. Les rôles doivent exister dans Roles et leur attribution est contrôlée par ACL. La modification d'un administrateur existant permet de changer son rôle et son état activé/désactivé ; la suppression invalide immédiatement ses sessions. Le premier compte \`ROLE_DEV\` et son propre compte ne sont pas modifiables ou supprimables. Le changement d'adresse n'est pas disponible tant que le parcours de nouvelle vérification n'est pas livré. Les fonctions sont soumises aux permissions \`admins:list/create/update/delete\` et \`roles:assign:ROLE_...\`.


La liste complète des administrateurs est désormais intégrée à `/admins` : elle permet d'activer/désactiver un compte, de changer le rôle et de supprimer un autre administrateur après confirmation. Les protections sont appliquées au niveau de l'API, indépendamment des boutons de l'interface.
