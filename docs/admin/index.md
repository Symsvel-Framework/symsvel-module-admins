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

Le Dropdown commun ne fournit pas de validation HTML `required` implicite comme `<select>` : les boutons des formulaires de création/modification conservent explicitement le blocage tant que la langue obligatoire n'est pas choisie (en contexte multilingue). Les contrôles d'entrée serveur restent inchangés.

## Profil personnel BO (19/11/2026)

`GET /apibo/admins/profile` retourne exclusivement l'administrateur
identifié par le token BO et ses informations **non sensibles** : id, email,
rôle, langue et état de vérification. Le mot de passe, ses empreintes,
tokens et secrets ne sont jamais exposés. Aucun accès à la liste des
administrateurs, ni permission de modifier leurs rôles, n'est nécessaire
pour lire son propre profil.

`PATCH /apibo/admins/profile` accepte **uniquement**
`{"language":"fr"}` (ou une autre langue active résolue par Languages).
L'identifiant vient du token BO, jamais du client : il est impossible
de fournir un ID cible ou de modifier sa propre permission, l'activation,
la validation du mail ou le rôle par ce chemin. L'opération utilise la
vérification de session existante et respecte l'initialisation monolingue.

**Champs de profil encore différés** : le changement d'email nécessite
la confirmation sécurisée de la nouvelle boîte et le changement de
mot de passe réutilisera les mécanismes Auth existants. Ils ne sont
pas implicitement autorisés par ce premier endpoint. La route BO
et le formulaire Svelte doivent être testés ensemble sur XT ; CI du
module seule ne vaut pas validation de sécurité de bout en bout.

## Écran « Mon profil » BO (19/11/2026)

La route Svelte `/profile` appartient au module Admins ; le menu du
BO hôte propose le lien dans le menu du compte, **pas** dans la sidebar.
L'écran lit `GET /apibo/admins/profile`, affiche email, rôle et
vérification en lecture seule, puis charge les langues actives pour
modifier uniquement la langue personnelle avec un `PATCH` asynchrone,
sans rechargement du document. Le choix des langues utilise le Dropdown
partagé du Design System. Les textes appartiennent à
`bo/src/lib/translations/{fr,en}.ts` du module.

Le changement d'adresse ou de mot de passe n'est pas implémenté par
cet écran, tant que le parcours sécurisé ne garantit pas la
confirmation de la nouvelle boîte ou de l'identité. Le CT doit
valider un compte non-DEV, un compte ROLE_DEV, le choix monolingue,
l'expiration de session et l'absence d'accès à un autre compte.

### Test des routes de profil (19/11/2026)

Les routes déclarées par attributs Symfony sont vérifiées via
`ReflectionAttribute::getArguments()`. Elles ne sont pas des instances
`Symfony\\Component\\Routing\\Route` et n'exposent donc pas les getters
des objets du routeur. Correction du test sans changement du contrat
HTTP ni relâchement des contrôles d'identité.

### Choix de langue sur le profil personnel (19/11/2026)

Un administrateur authentifié peut modifier **sa propre** langue même
s'il ne possède pas les permissions de gérer les utilisateurs ou Languages.
Le formulaire lit donc les langues actives depuis le catalogue public
`GET /apiopen/languages/account-options`, déjà utilisé côté App, et non
depuis `/apibo/languages/account-options` qui est soumis aux ACL de
création/édition des comptes. L'écriture demeure protégée par le token BO
sur `PATCH /apibo/admins/profile`. Ce découplage évite l'escalade des
ACL pour une simple préférence personnelle.

## Modification de son mot de passe (19/11/2026)

`POST /apibo/admins/profile/password` permet au compte courant de remplacer
son mot de passe. Le Bearer authentifié détermine le contexte et l'ID ;
aucun ACL de gestion d'autres comptes n'est exigé, y compris pour ROLE_DEV.
Le client fournit `current_password` et `new_password` (12 à 1024 caractères)
avec preuve du mot de passe existant. Les erreurs ne divulguent pas le hash.
Le nouveau hash Argon2id est écrit par comparaison conditionnelle de
l'ancien hash, sous transaction `TokenSessionRevoker::revokeWith` avec
révocation des access/refresh du compte. L'interface doit alors revenir
à la connexion. Si une mise à jour concurrente invalide l'ancien hash,
l'opération est annulée sans révoquer les sessions.

Les restrictions sur la suppression du dernier ROLE_DEV ou administrateur
ne concernent pas cette opération personnelle. CI et CT de reconnexion
avec l'ancien / nouveau mot de passe restent requis.

### Formulaire de modification du mot de passe (19/11/2026)

Le profil comporte trois champs : mot de passe actuel, nouveau mot de
passe et confirmation. Il refuse localement la discordance et impose
au moins douze caractères. La modification est envoyée à l'API
personnelle, jamais à l'API de gestion des comptes d'autres personnes.
Après un succès, l'application ferme la session locale et revient à
la connexion (les tokens du compte sont révoqués par Auth).
La préférence linguistique reste disponible sans bloquer les autres
formulaires lorsque seule la langue principale est active.

## Changement d'e-mail : confirmation du nouveau destinataire (19/11/2026)

La route authentifiée `POST /apibo/admins/profile/email-change`
accepte le mot de passe actuel, l'adresse demandée et la locale.
Le compte cible est celui du Bearer, jamais un identifiant de requête.
Le contrôleur vérifie le mot de passe avant de demander à Auth la
création d'un jeton de confirmation expédié à la nouvelle adresse.
**L'adresse actuelle et les sessions restent valides durant l'attente.**

Le propriétaire `AdminProfileEmailOwner` valide la consommation en contexte
`bo`, l'ancien e-mail par empreinte SHA-256, le statut actif
et l'unicité en base avant de remplacer le champ `email`. Auth
orchestre la transaction atomique, la consommation du lien et la
révocation des sessions. Les règles du dernier rôle DEV ou dernier
admin concernent la suppression, jamais le changement d'email personnel.

La migration jeton appartient au module Auth, pas aux tables Admins/Users.
L'écran doit suivre le lien reçu, puis obliger une reconnexion.
CI, tests de changement d'adresse concurrent, multi-SGBD et CT XT requis.

### Formulaire de changement d'adresse et confirmation (19/11/2026)

Depuis le profil, l'utilisateur saisit la nouvelle adresse et son mot
de passe actuel. Un POST authentifié vers
`/apibo/admins/profile/email-change`
déclenche l'envoi du lien à la nouvelle boîte, sans changer l'ancienne
adresse. La page publique Svelte `/verify-email-change` consomme le
jeton Auth de contexte BO ; elle retire immédiatement le
secret de l'URL via `history.replaceState`, utilise une requête publique
sans credentials, puis invite à une nouvelle connexion. La politique
`Referrer-Policy` de la page interdit toute fuite via Referer.

L'écran utilise le Design System du scope, les dictionnaires des modules
et affiche les erreurs d'envoi, d'identifiants ou de lien expiré. L'envoi
de mail exige un transport Mails configuré ; aucune réponse HTTP 202
ne doit être considérée comme preuve de délivrabilité. CT XT avec Mailpit
et confirmation sur le domaine réel requis.
