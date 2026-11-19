# Admins

Documentation admin.

## Gestion des comptes BO

La page \`/admins\` présente les administrateurs, leur rôle, leur statut et les invitations en attente. La création passe uniquement par invitation : le destinataire choisit son mot de passe lors de l'activation. Les rôles doivent exister dans Roles et leur attribution est contrôlée par ACL. La modification d'un administrateur existant permet de changer son rôle et son état activé/désactivé ; la suppression invalide immédiatement ses sessions. Le premier compte \`ROLE_DEV\` et son propre compte ne sont pas modifiables ou supprimables. Le changement d'adresse n'est pas disponible tant que le parcours de nouvelle vérification n'est pas livré. Les fonctions sont soumises aux permissions \`admins:list/create/update/delete\` et \`roles:assign:ROLE_...\`.


La liste complète des administrateurs est désormais intégrée à `/admins` : elle permet d'activer/désactiver un compte, de changer le rôle et de supprimer un autre administrateur après confirmation. Les protections sont appliquées au niveau de l'API, indépendamment des boutons de l'interface.


## Coque BO partagée (16/11/2026)

La page /admins conserve uniquement ses formulaires,
tableaux et appels `boApi`. La session, la sidebar, la topbar et la
navigation active sont gérées par le layout hôte de `symsvel-bo`.
La route générée par Tooling reste inchangée et les ACL restent dans les
contrôleurs PHP. Voir `symsvel-bo/docs/bo-ui.md`.


## Composants génériques SymSvel BO (16/11/2026)

Les écrans réutilisent maintenant `Button` (primaire et secondaire) et
`Alert` (succès et erreur) du BO hôte via
`src/lib/components/`. La liste des invitations utilise également \`DataTable\` avec colonnes et action de renvoi ; la table de comptes avec édition en ligne reste native.
Les modules fournissent les libellés, états, callbacks et appels `boApi`.
Aucune logique d'ACL n'est déplacée vers les composants.
Voir `symsvel-bo/docs/bo-ui.md`.

### Uniformisation Design System BO (19/11/2026)

Les tableaux Administrateurs et Invitations conservent leurs composants `DataTable`/`Pagination`, leurs modales natives et leurs ACL. Les cinq sélections des formulaires (rôle d'invitation, rôle édité, langues d'interface et état actif) utilisent désormais le **Dropdown commun BO**, avec options fournies par les API et drapeaux par le helper natif `languageFlag`. Les booléens conservent leur type via `value === '1'`, et un rôle existant non attribuable reste lisible avant modification, sans contourner les contrôles serveur.

Les surfaces, onglets, formulaires, bordures, textes secondaires et focus utilisent les variables CSS `--bo-*` du thème clair/sombre. La soumission continue à appeler l'API asynchrone et à préserver la page courante, sans rechargement complet. Les langues et rôles ne sont pas devinés depuis le navigateur ; les données sont celles du backend. CI et CT XT requis.
