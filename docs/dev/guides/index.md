# Admins

Module natif, installé dans tous les projets. Il gère les administrateurs du BO ; les utilisateurs de l'App relèvent de Users.

## Utiliser le module

Connectez-vous au BO avec votre compte administrateur. Dans Administrateurs, consultez les comptes, invitez un nouveau membre, sélectionnez un rôle autorisé et activez ou désactivez son accès. L'invité reçoit un lien pour définir son mot de passe. Configurez le transport Mails avant d'envoyer une invitation. Le premier compte créé au Setup possède ROLE_DEV ; les autres comptes ne peuvent pas s'attribuer ce rôle.

## Configuration

Aucune installation ni configuration supplémentaire propre au module. Les rôles proviennent de Roles, les autorisations d'ACLs, les sessions et invitations d'Auth. Les adresses et la langue des comptes se gèrent dans le BO.

## Intégration

- GET /apibo/admins/invitations : invitations, sous réserve d'autorisation.
- POST /apibo/admins/invitations : inviter un administrateur.
- POST /apibo/admins/{id}/invitations/resend : renvoyer une invitation.

Utilisez les services et contrôleurs existants plutôt que d'écrire directement dans symsvel_admins. Pour protéger un endpoint métier du BO, utilisez [ACLs](../acls/index.md).
