# Admins

## Fixtures temporaires du crash-test d'intégration

Ce module déclare les scopes `back`, `bo`, `app` et `front` pour le crash-test global `symsvel create-project --components=all --dev`.

- Symfony : `GET /api/admins/ping` doit retourner `{"module":"admins","status":"ok"}` sans données métier ni authentification propre au controller.
- SvelteKit : chaque interface fournit `src/routes/admins/+page.svelte`, affichant `Page admins`. Le synchroniseur copie la source sous `src/lib/modules/admins/routes/` et crée le relais `src/routes/admins/+page.svelte`.
- Le code métier et ses contrôles d'accès existants restent inchangés.

Ces pages et endpoints sont des sondes d'intégration, pas des interfaces métier définitives. **Limitation provisoire :** les trois scopes Svelte sont déclarés obligatoires par le manifeste ; cette fixture vise uniquement une installation avec tous les composants, pas les installations partielles.

Validation requise : tests automatisés, CI verte, puis crash-test XT (endpoints HTTP et affichage réel des pages BO/App/Front). La présence des fichiers seule ne suffit pas.

## Expérimentation migrations Schema (10/11/2026)

La source `Migration/001_admins.php` définit désormais la structure de table avec l'API `Doctrine\\DBAL\\Schema\\Schema` (colonnes, clé primaire, index unique), sans SQL PostgreSQL pour le DDL. Il s'agit d'une **expérience contrôlée**, pas encore du contrat de production.

**Limitation connue avant CT :** le compilateur actuel de `symsvel-tooling` extrait les corps `up()/down()` et les insère dans une migration Doctrine. Il ne génère pas de SQL à partir des mutations de `Schema`. En particulier, les changements de structure risquent de ne pas être exécutés sur la base. Aucun résultat PostgreSQL/MySQL n'est encore validé.

Les opérations de création/suppression de table sont uniquement déclaratives ; elles ne produisent pas encore de SQL via le compilateur actuel.

À vérifier sur un **XT réinitialisé**, sans aucune réparation locale : compilation, exécution, tables réellement présentes, index, rôles système, état du Setup et diagnostic/rollback en cas d'échec. Toute correction devra se faire dans le framework et être documentée avec tests et CI.

### Visibilité des tests ignorés

La CI affiche les raisons des tests PHPUnit ignorés avec `--display-skipped` (directement ou via le script Composer QA). Un test ignoré ne devient pas bloquant pour autant : les motifs sont examinés pour distinguer prérequis absents et couverture manquante.

## PHPUnit : découverte des tests (14/11/2026)

La configuration `back/phpunit.xml.dist` utilise un unique testsuite pointant vers `back/tests` pour découvrir récursivement les tests Unit, Integration et Functional. Le bootstrap reste `../vendor/autoload.php`, chemin relatif au fichier XML situé dans `back/`. La commande Composer `composer test` continue d'utiliser `phpunit -c back/phpunit.xml.dist`. Cette correction simplifie la découverte et permet l'ajout de futurs tests sans modifier la liste des sous-dossiers ; elle ne constitue pas à elle seule une validation des tests. Vérifier l'exécution réelle en CI.

## Adaptateur d'identité BO vers Auth (14/11/2026)

`Auth/AdminIdentityProvider` implémente les deux contrats `PasswordPrincipal` et `PrincipalStatus` du module Auth. Il s'appuie exclusivement sur `AdminRepository` et l'entité `Admin` : recherche par email, vérification du hash Argon2id via `password_verify`, contrôle de `enabled` et de l'identifiant persistant. Seul le contexte `bo` est accepté ; une demande `app` est rejetée avant tout accès au repository. `isActive` recharge l'administrateur à chaque contrôle, pour que la désactivation soit immédiatement effective.

Le module déclare la dépendance Composer `symsvel-framework/module-auth` et enregistre le fournisseur comme service autowiré. **Il n'installe pas d'alias global** `PasswordPrincipal` ou `PrincipalStatus` : la composition BO/App doit sélectionner les fournisseurs sans que l'adaptateur BO intercepte les identités App. Aucun endpoint ni firewall n'est ouvert. Les tests unitaires couvrent les contrats et le refus précoce ; des tests d'intégration ORM avec administrateurs persistés et les crash-tests locaux restent nécessaires. La CI de cette passe n'est pas encore validée.

### Résolution Composer d'Auth en développement (14/11/2026)

La CI Admins échouait avant les tests : son `composer.json` exigeait `symsvel-framework/module-auth:^1.0` sans déclarer de dépôt privé, alors que la seule release Auth constatée sur GitHub est `0.1.0` et que les contrats récents sont développés sur la branche `dev`. Le manifeste Composer Admins déclare désormais le dépôt Satis privé `https://packages.xavier-tezza.fr` et exige explicitement `dev-dev`, sans abaisser globalement `minimum-stability`. **Précondition :** Satis doit indexer la branche `dev` du module Auth et le runner doit disposer de ses droits d'accès habituels. Cette configuration vise les tests sur la branche de développement ; avant stabilisation, remplacer la contrainte par une release publiée correspondant réellement aux contrats. La CI et les tests restent à confirmer après ce correctif.

### Normalisation des identifiants de connexion (14/11/2026)

Le fournisseur BO supprime les espaces périphériques de l'email **avant** validation, comme le constructeur de l'entité `Admin`, et refuse les emails dépassant 254 octets. Le test unitaire couvre ce rejet sans accès à Doctrine. Le contrôle des identifiants persistés et du mot de passe nécessite toujours un test d'intégration ORM ; la CI de ce correctif reste à confirmer.

### Test d'intégration ORM du fournisseur BO (14/11/2026)

`back/tests/Integration/AdminIdentityProviderDatabaseTest.php` utilise une base SQLite **en mémoire**, un vrai `EntityManager`, le schéma Doctrine de l'entité `Admin` et le vrai `AdminRepository`. Il vérifie la connexion avec un compte persisté et son hash Argon2id, le refus d'un mauvais mot de passe, l'isolation du contexte App, la normalisation des espaces de l'email et le refus d'un compte supprimé après rechargement. Il ne modifie aucune base de projet. Le test est ignoré si `pdo_sqlite` est absent ; son exécution effective et la CI restent à confirmer. Il ne remplace pas les crash-tests multi-SGBD ni les tests de compte désactivé, faute d'API de désactivation dans l'entité actuelle.

### Correctif Doctrine ORM / PHP 8.4 (14/11/2026)

Le premier lancement du test d'intégration a échoué à la construction de l'EntityManager : Doctrine demandait Symfony LazyGhost (`symfony/var-exporter`) ou l'activation des lazy objects natifs. Puisque SymSvel exige PHP 8.4, la configuration ORM **du test** active désormais `enableNativeLazyObjects(true)`. Aucun package supplémentaire n'est ajouté à l'exécution du framework. Correctif en attente de confirmation CI.

## Composition des identités par contexte (14/11/2026)

`AdminIdentityProvider` déclare désormais `ContextOwnedIdentity::authContext()` = `bo` et est enregistré sous le tag `symsvel.auth.identity_provider`. Le routeur Auth choisit ce fournisseur uniquement pour le BO ; aucun alias de contrat global n'est installé par Admins. CI de cette passe à confirmer.

### Récupération de mot de passe BO (en cours)

`AdminPasswordResetIdentity` implémente le contrat `PasswordResetIdentity` du module Auth. Il recherche uniquement les administrateurs actifs par adresse email et met à jour leur empreinte Argon2id via la connexion DBAL partagée, afin de participer à la transaction globale de récupération. Le provider est enregistré sous `symsvel.auth.password_reset_identity`. Aucun endpoint de récupération n'est exposé à ce stade ; tests et CI de cette modification à confirmer.

## Adresse de récupération et contrat Auth (15/11/2026)

Le fournisseur `AdminPasswordResetIdentity` implémente désormais `PasswordResetIdentity::recoveryEmail(TokenSubject): ?string`. **Il renvoie intentionnellement `null` tant qu'aucun état persistant de vérification de boîte e-mail n'existe pour les comptes BO.** La présence de `email` et `enabled` ne prouve pas la propriété de la boîte. Aucun message de récupération n'est donc envoyé via cet adaptateur ; `PasswordResetDispatch` abandonne après contrôle anti-abus. Les contrats de recherche et de remplacement transactionnel de mot de passe restent inchangés.

Ce comportement temporaire est volontairement fermé et testé dans `AdminPasswordResetIdentityRecoveryEmailTest`. Une évolution future devra définir et vérifier le processus de confirmation d'adresse auprès du propriétaire du compte, avec migration et tests correspondants, avant d'autoriser la délivrance des liens. Aucune route HTTP n'est ajoutée. CI et crash-tests locaux à confirmer.

### Correctif CI : provenance du contrat Auth (15/11/2026)

Le nouveau test du contrat `PasswordResetIdentity::recoveryEmail()` a échoué pendant le chargement de `AdminPasswordResetIdentity` : l'interface `SymSvel\\Module\\Auth\\Identity\\PasswordResetIdentity` était absente de la dépendance Composer installée. L'interface est présente sur la branche `dev` du dépôt Auth, mais pas sur `main` au moment de l'audit. La version exacte résolue par Satis dans les jobs rouges reste à confirmer.

Pour le canal de développement, `composer.json` donne maintenant priorité au dépôt VCS Auth avant le catalogue Satis, tout en conservant `dev-dev`. La CI affiche `composer show symsvel-framework/module-auth` et vérifie explicitement l'existence de l'interface après `composer install`, avant `composer qa`. Aucun contrat métier, schéma ou service de production n'est modifié. Cette priorité VCS est limitée au travail sur `dev` : à la stabilisation, revenir à une release Auth publiée et indexée dans Satis. **CI à confirmer.**

### Correctif Composer : stabilité des dépendances transitives Auth (15/11/2026)

Après résolution d'Auth depuis sa branche `dev`, Composer a rejeté `symsvel-framework/module-mails:dev-dev` en dépendance transitive : la stabilité `dev` n'est pas autorisée implicitement par les dépendances indirectes lorsque le projet racine conserve son `minimum-stability` par défaut. Auth dépend de Mails et Settings, et Mails dépend lui-même de Settings.

Le `composer.json` du module déclare désormais explicitement, à la racine, `module-auth`, `module-mails` et `module-settings` en `dev-dev` et pointe en priorité sur leurs dépôts Git `dev`, avant le catalogue Satis. Cela autorise uniquement ces dépendances de développement, sans abaisser globalement `minimum-stability`. Le workflow CI affiche les trois versions installées et vérifie toujours la présence de `PasswordResetIdentity`.

**Portée temporaire du canal dev :** ces contraintes explicites sont à remplacer par des versions publiées cohérentes lorsque les trois modules seront stabilisés. Pas de modification du runtime ou du contrat métier. CI de cette correction à confirmer.

## Vérification persistante des e-mails (15/11/2026)

**Décision validée :** l'adresse doit avoir été confirmée avant qu'un lien de récupération du mot de passe puisse y être envoyé, y compris pour le premier administrateur créé pendant le Setup. Le propriétaire de la donnée reste le module Admins ; Auth reste responsable des jetons, Mails de l'envoi.

La migration additive `Migration/Version20261115000001.php` ajoute `email_verified_at` (`datetime_immutable`, nullable) à la table du module. La migration initiale publiée n'est pas réécrite. L'entité `Admin` expose `getEmailVerifiedAt()` et initialise implicitement la nouvelle propriété à `null`. Les comptes existants, ainsi que les nouveaux comptes créés par le Setup ou les constructeurs, restent donc **non vérifiés** tant qu'aucun parcours de confirmation sécurisé n'a persisté une date. Aucun compte n'est vérifié automatiquement à la migration.

`AdminPasswordResetIdentity::recoveryEmail()` consulte la connexion DBAL partagée et retourne l'adresse uniquement si le contexte vaut `bo`, le compte est actif (`enabled`) **et** `email_verified_at IS NOT NULL`. Sinon, il retourne `null` sans envoi. La recherche du sujet et le remplacement transactionnel du mot de passe ne changent pas.

Tests : `EmailVerificationMigrationTest` vérifie les mutations `Schema` et leur annulation sans toucher aux colonnes préexistantes ; `AdminPasswordResetIdentityVerifiedMailboxTest` couvre le refus sans vérification, l'isolation du contexte, le passage à un état vérifié simulé en base puis le refus du compte désactivé.

**Limites du chantier :** la confirmation d'adresse par jeton à usage unique, la persistance sécurisée de la confirmation et la gestion d'un changement d'adresse ne sont **pas encore développées**. Aucun endpoint de demande de récupération n'est ouvert. La CI des nouveaux commits et les crash-tests locaux de la compilation/exécution multi-SGBD restent à confirmer. Ne jamais renseigner `email_verified_at` à partir d'une simple saisie d'adresse ou d'une authentification locale.

## Confirmation de boîte mail BO : propriétaire du compte (15/11/2026)

`AdminPasswordResetIdentity` implémente également `EmailVerificationIdentity`. Le module enregistre ce fournisseur sous `symsvel.auth.email_verification_identity`. `confirmEmail(subject, emailHash, now)` exige le contexte `bo`, un compte actif, et une empreinte SHA-256 valide correspondant à **l'adresse actuellement enregistrée**, normalisée en minuscules après `trim`. La mise à jour DBAL conditionnelle renseigne `email_verified_at` uniquement si l'adresse est restée identique et si le compte n'était pas déjà vérifié.

Le service Auth réalise l'appel dans la transaction de consommation du jeton. Le Setup ne vérifie automatiquement aucune adresse ; le premier Admin reste non vérifié jusqu'à confirmation effective. `AdminEmailVerificationTest` couvre le contexte, la mauvaise empreinte, le succès unique, le compte désactivé et une ancienne adresse après changement.

Le parcours d'envoi, les endpoints et les crash-tests multi-SGBD restent à réaliser. CI à confirmer.

## Correctif CI des tests de confirmation (15/11/2026)

Le test `AdminPasswordResetIdentityRecoveryEmailTest` crée désormais sa table `symsvel_admins` dans SQLite en mémoire avant de vérifier le refus de récupération pour un sujet absent : l'adaptateur effectue légitimement une requête DBAL depuis l'ajout de `email_verified_at`. Le test de migration remplace `Table::hasPrimaryKey()` (absent de Doctrine DBAL 4) par `Table::getPrimaryKey() !== null`.

Les tests restent isolés de toute base de projet et ne présupposent plus une table absente. Aucune migration publiée ni logique métier de vérification n'a été modifiée dans cette passe. CI et crash-tests locaux à confirmer.

## Adresse BO éligible à la demande de confirmation (15/11/2026)

`AdminPasswordResetIdentity` fournit désormais `EmailVerificationIdentity::pendingVerificationEmail()` : seul un administrateur actif, dans le contexte `bo`, dont `email_verified_at IS NULL`, peut faire parvenir son adresse actuellement enregistrée au service interne d'envoi Auth. Cela ne vérifie pas automatiquement l'adresse ; seul le parcours de consommation transactionnelle validé précédemment marque la confirmation. Le premier compte créé au Setup reste non vérifié tant qu'il n'a pas réellement consommé un jeton. Tests de refus du mauvais contexte, de la vérification déjà réalisée et du passage non vérifié/vérifié ajoutés.

L'envoi est limité et déclenché par Auth avec Mails/Settings. Aucun contrôleur HTTP n'est ouvert, et la CI de cette passe reste à confirmer.

## Diagnostic des accès GitHub privés dans les CI (15/11/2026)

Le workflow `qa.yml` fournit explicitement le secret GitHub Actions `SYMSVEL_READ_TOKEN` à Composer via `COMPOSER_AUTH` (`github-oauth`). Avant installation, il refuse un secret absent et contrôle en lecture, par l'API GitHub, l'accès aux dépôts privés `symsvel-module-auth`, `symsvel-module-mails` et `symsvel-module-settings`. En cas d'absence, d'expiration ou de permissions insuffisantes, la CI échoue avec un diagnostic explicite **avant** `composer install` plutôt qu'un message générique de Composer. La valeur du jeton n'est jamais affichée. Les runners auto-hébergés ne doivent pas dépendre d'une authentification Composer laissée dans leur cache local.

**Configuration à maintenir :** secret `SYMSVEL_READ_TOKEN` lisible dans chacun des trois dépôts GitHub Actions (ou via une configuration de secrets autorisée), avec droits de lecture sur les dépôts privés requis. Un `GITHUB_TOKEN` standard limité au dépôt du workflow ne suffit pas pour ces dépendances inter-dépôts. Un jeton révoqué ou expiré doit être remplacé dans les secrets GitHub, pas en dur dans `composer.json` ou les logs. CI de ce correctif à confirmer ; aucune modification du runtime.

## Implémentation du bootstrap local de confiance (16/11/2026)

Le premier administrateur `ROLE_DEV` est créé par `InitialAdministratorProvisioner::provision()` uniquement à partir du Setup local contrôlé : l'entité reçoit alors `email_verified_at` sans envoi d'e-mail. Un compte administrateur créé en dehors de cette opération reste non vérifié.

Le parcours `symsvel:admins:bootstrap:stdin` traite aussi une reprise de Setup : après vérification du mot de passe du compte existant, `trustExistingBootstrap()` ne renseigne la date que si l'unique administrateur est `ROLE_DEV`. Aucune réinitialisation de son mot de passe ; le marquage est idempotent. L'ancien bootstrap à payload préparé utilise le même provisioner.

Le test unitaire `InitialAdministratorProvisionerTest` couvre l'état initial non vérifié et la transition explicite et idempotente ; le scénario complet via commandes/Doctrine reste à crash-tester. Les administrateurs invités et le parcours d'activation ne sont **pas encore implémentés**.

## Décision révisée : vérification des adresses administrateur (16/11/2026)

Cette décision remplace la règle du 15/11/2026 qui imposait une confirmation e-mail au premier administrateur du Setup.

- **Premier administrateur / développeur** : le Setup Wizard local est un canal d'initialisation de confiance. L'unique `ROLE_DEV` créé par `symsvel:admins:bootstrap:stdin` doit recevoir un `email_verified_at` renseigné sans envoi de message de confirmation. Cette confiance est limitée strictement au premier bootstrap local, pas aux créations ultérieures, importations ou commandes de gestion courante.
- **Administrateurs créés ensuite** : la possession de l'adresse sera validée au cours du **même parcours d'invitation et de création initiale du mot de passe**. Pas de double validation (un lien pour l'e-mail puis un autre pour le mot de passe). L'invitation doit être à usage unique, expirante et liée à l'adresse actuelle et au compte ; l'activation et la validation de l'adresse ne sont acquises qu'au succès de ce parcours.
- **Changement d'adresse** : la vérification précédente doit être annulée ; la nouvelle adresse doit être confirmée avant d'être éligible à la récupération du mot de passe.

Les contrats de persistance et de jetons déjà livrés restent applicables. **État** : cette section documente la décision ; la mise à jour de l'opération de bootstrap et le nouveau parcours d'invitation doivent être réalisés et validés par les tests, CI et crash-tests. Ne pas considérer ce texte comme une preuve que les comportements sont déjà opérationnels.

## Activation d'un administrateur invité (16/11/2026)

`AdminPasswordResetIdentity` implémente `AdminInvitationIdentity` et expose `activateInvitation()`. Auth lui transmet un ID, une empreinte d'adresse et un hash Argon2id au sein de sa transaction DBAL. Admins vérifie que le compte est actif, que son adresse actuelle correspond au jeton et que `email_verified_at` est encore `NULL`, puis actualise **ensemble** le mot de passe et `email_verified_at` au moyen d'un `UPDATE` conditionnel. Le bundle publie l'adaptateur avec le tag `symsvel.auth.admin_invitation_identity`.

Ce contrat ne crée pas encore les comptes invités et ne diffuse aucun e-mail. L'invitation doit être émise uniquement pour un administrateur fraîchement créé et non activé ; les changements d'adresse après activation devront suivre le parcours spécifique de revalidation. CI et crash-tests à confirmer.

## Grande passe : création interne des administrateurs invités (16/11/2026)

Le module Admins possède la création des comptes BO. `PendingAdministratorCreator` valide un **rôle explicitement choisi**, présent dans la table du module Roles via `RoleRepository`, et rejette systématiquement `ROLE_DEV` (exclusif au Setup) et `ROLE_USER` (réservé à App). Le manifeste déclare désormais la dépendance d'installation à Roles, et Composer la dépendance `symsvel-framework/module-roles` en branche `dev`.

La fabrique `Admin::invited()` crée un administrateur avec un hash Argon2id d'un secret aléatoire **jamais partagé**, un `role_code` explicite et `invitation_pending = true`. La migration additive `Version20261116000002` ajoute ce booléen à `symsvel_admins` avec `DEFAULT false` ; les admins historiques et le développeur créé au Setup restent des comptes normaux. Les migrations initiales déjà publiées ne sont pas modifiées.

- `AdminIdentityProvider` refuse l'authentification et l'état actif tant que l'invitation est en attente.
- `AdminPasswordResetIdentity` refuse la récupération, la modification du mot de passe et la confirmation autonome d'e-mail tant que `invitation_pending` est vrai.
- L'activation par invitation reste atomique : contrôle `enabled = true`, vérification de l'adresse, mot de passe Argon2id, `email_verified_at` et bascule de `invitation_pending` à `false` dans le même UPDATE. Un administrateur **désactivé** ne peut pas être activé par son lien.
- `AdministratorInvitationManager` orchestre **en interne** la création côté Admins puis `AdminInvitationDispatch` côté Auth/Mails. Une panne du transport peut laisser un compte en attente ; la méthode `resend()` permet à un appelant interne autorisé de réémettre l'invitation uniquement pour ce compte. Aucune méthode d'émission n'est accessible sans contrôle préalable par le futur endpoint BO.

Couverture ajoutée : rôles protégés, état pending, migration additive, intégration ORM/DBAL de l'invitation jusqu'à l'activation et blocage des voies alternatives. **CI et crash-tests XT multi-SGBD restent à valider.** La couche HTTP authentifiée et ses ACLs, les pages Svelte ainsi que le flux d'envoi complet ne sont pas encore livrés : les services internes ne constituent pas une API d'administration publique.

## API BO sécurisée de création et renvoi des invitations (16/11/2026)

`AdministratorInvitationController` expose deux opérations BO :
- `POST /apibo/admins/invitations` avec JSON `{"email":"…","role_code":"ROLE_ADMIN","locale":"fr"}`, réponse `201` lorsque le mail a été envoyé ;
- `POST /apibo/admins/{id}/invitations/resend` avec JSON `{"locale":"fr"}`, réponse `200` après renvoi.

Le contrôleur vérifie **d'abord** le bearer token BO via `AuthenticatedTokenResolver` (jeton actuel, non expiré, non révoqué, sujet activé). Le rôle courant est relu depuis Admins, puis `AclAuthorizer` (module ACLs) vérifie `(resource=admins, action=create)` ou `(resource=admins, action=resend)`. La création exige aussi le droit sur `(resource=roles, action=assign:ROLE_…)`, pour le rôle précis à attribuer ; le renvoi vérifie la même autorisation sur le rôle du destinataire. `ROLE_DEV` passe les ACL et `ROLE_SUPER_ADMIN` passe ces opérations fonctionnelles, conformément aux règles acceptées. Tout autre rôle doit recevoir des affectations ACL **explicites** ; aucune règle par défaut n'est créée.

L'API ne permet jamais d'inviter `ROLE_DEV` ou `ROLE_USER`, ni d'activer directement un compte. Elle ne crée pas de session, ne divulgue jamais de jeton d'invitation, refuse les JSON invalides et limite leur taille, et désactive les caches sur toutes les réponses. Erreurs : `401` non authentifié, `403` droit manquant, `400/415` requête incorrecte, `409` invitation indisponible. La création et l'envoi passent exclusivement par les services existants Admins/Auth/Mails. En cas d'échec du transport mail après création, le compte reste inactif avec `invitation_pending = true` ; le renvoi autorisé sert à le récupérer.

Le manifeste et Composer déclarent la dépendance native à ACLs. La configuration Symfony `security.yaml` du Back ne traduit pas aujourd'hui ces bearer tokens en utilisateurs Symfony : **ne pas substituer un simple `#[IsGranted]`** à la résolution Auth actuelle. Les tests contrôlent les voies d'autorisation et les réponses. **CI et crash-tests multi-SGBD requis** pour valider cette intégration.

### Lecture des invitations en attente (16/11/2026)

`GET /apibo/admins/invitations` retourne `{data: [{id, email, role_code, enabled, created_at}]}` pour les **100 invitations les plus récentes**, triées par date descendante. Ce parcours exige `admins:list` et un jeton BO authentifié : une simple autorisation de créer ou de renvoyer n'autorise pas la lecture des adresses. Les comptes activés n'apparaissent plus ; les comptes désactivés peuvent être affichés, mais le renvoi reste interdit. Les réponses sont `Cache-Control: no-store`. La limite est volontaire ; pagination et filtres seront traités seulement si le besoin est confirmé. Test HTTP de lecture, accès refusé et visibilité ajouté.

### Interface d'invitation BO Svelte (16/11/2026)

La page `/admins` du module remplace le placeholder de CT. Elle utilise `boApi()` pour la création, le renvoi et la lecture sécurisée des invitations ; `currentBo()` contrôle la session. Aucun traitement de token propre au module. Le rôle doit être fourni explicitement, puis vérifié par Roles et ACLs côté backend. Textes externalisés dans le fichier TypeScript `bo/src/lib/modules/admins/invitations.ts`, sélection FR/EN. La CI backend, même verte, ne suffit pas à valider le BO compilé ; crash-tests locaux et validation de l'utilisateur restent nécessaires.

### CI complémentaire Svelte assemblé (16/11/2026)

La CI Admins ajoute un **job indépendant `bo-integration`** sur le runner auto-hébergé. Il récupère le BO hôte en branche `dev`, reproduit les destinations de synchronisation de la route et du fichier TypeScript Admins (sans modifier Tooling), installe les dépendances et exécute `npm run format`, `npm run check`, `npm run lint`, `npm run build` et les tests unitaires Vitest avec Chromium.

Ce job valide les imports `#lib`, les composants synchronisés et la compilation statique dans le BO réel. Le formatage s'effectue **uniquement dans la copie assemblée** pour suivre la convention CI du BO ; il ne modifie ni ne pousse les sources du module. Le chantier n'est pas clôturé par ce job : les appels HTTP sont simulés en tests et les crash-tests locaux utilisateur sont obligatoires. L'accès au BO privé doit être accordé au secret `SYMSVEL_READ_TOKEN`.

## Gestion des comptes BO : CRUD sécurisé (16/11/2026)

\`AdminBoAccessGate\` implémente le contrat partagé \`Acls\Access\BoAccessGate\` pour les modules BO. Il vérifie un bearer Auth \`bo\`, recharge l'administrateur actif et non invité, puis demande à \`AclAuthorizer\` l'action requise. Le refus distingue 401 (authentification) et 403 (ACL). Aucun module client ne doit lire lui-même les tokens.

\`AdministratorAccountController\` expose \`GET /apibo/admins\` (200 comptes récents), \`PATCH /apibo/admins/{id}\` (rôle explicite existant et/ou état \`enabled\`), et \`DELETE /apibo/admins/{id}\`. La création reste **exclusivement le parcours d'invitation** existant, où l'administrateur choisit son propre mot de passe. Le serveur vérifie \`admins:list/update/delete\` et \`roles:assign:<code>\` lorsque le rôle est modifié ou retiré. Le \`ROLE_DEV\` initial et l'acteur lui-même ne sont jamais modifiables ni supprimables. Les changements de rôle et d'état, ainsi que la suppression, révoquent les sessions de la cible dans une transaction DBAL. La suppression invalide aussi les jetons d'invitation encore enregistrés.

Les changements d'adresse restent volontairement exclus de PATCH : ils demandent un parcours de vérification de la nouvelle boîte aux lettres, non encore livré. Toutes les réponses sont \`no-store\`. Les écrans BO doivent utiliser le client natif \`boApi\` et respecter les refus d'ACL. L'API et les écrans restent soumis aux tests/CI et crash-tests réels de XT.


### Écran des comptes BO (16/11/2026)

La page `/admins` complète les invitations avec `GET /apibo/admins`, `PATCH /apibo/admins/{id}` et `DELETE /apibo/admins/{id}` via `boApi` ; le développeur initial et le compte courant ne sont pas modifiables dans l'UI, en complément des contrôles stricts côté serveur. L'édition porte sur rôle et état actif, jamais sur une adresse qui requerrait une nouvelle vérification. Les textes sont externalisés et un test protège l'interdiction de modifier le développeur ou soi-même. CI BO et CT XT à valider.

### Transactions uniques Auth / Admins (16/11/2026)

Les mutations BO de rôle, d'état, d'adresse ou de suppression utilisent désormais `TokenSessionRevoker::revokeWith()` comme **unique propriétaire de transaction** : modification du compte et révocation des sessions dans le même `DbalTokenStore`. Cela évite une transaction DBAL extérieure au verrou SQLite du TokenStore, tout en conservant le rollback sur erreur et les contrôles ACL. Le contrat public `revokeAll()` demeure inchangé. Les crash-tests sur les quatre moteurs et la CI de l'assemblage sont nécessaires avant clôture.

### Tests du CRUD BO Admins (16/11/2026)

`AdministratorAccountControllerTest` démarre Doctrine ORM et Auth DBAL sur SQLite en mémoire, puis vérifie `GET/PATCH/DELETE` avec un résultat `BoAccessGate` strict. Les cas `ROLE_DEV` et auto-modification restent bloqués côté serveur ; une modification du rôle et de l'état révoque les sessions, et la suppression conserve le développeur initial. Les refus de session/ACL retournent 401/403. L'absence de transactions imbriquées SQLite est vérifiée par cette intégration. CI et crash-test XT requis.

### Déclaration de navigation BO (16/11/2026)

Le module fournit `bo/src/lib/modules/admins/navigation.ts`, qui décrit sa page `/admins` avec libellés FR/EN et sa permission `admins:list`. Le dashboard du BO hôte importe les déclarations des modules effectivement installés ; il n'affiche cette entrée que si `GET /apibo/admins` réussit via `boApi`, ce qui vérifie le droit côté PHP avant toute navigation. Aucune route Symfony centrale ni règle d'accès Svelte parallèle n'est créée. Le test `bo/tests/admins.test.ts` valide la déclaration, et la CI de l'assemblage BO doit confirmer l'import. CT XT après mise à jour du BO et des modules.


## BO : onglets, modales et tableaux XCore (16/11/2026)

L'écran Admins garde l'autorisation des endpoints existants. L'invitation
initiale et l'édition d'un compte se font dans `Modal` du BO hôte. L'écran
affiche deux onglets : comptes activés (hors invitations en attente) et
invitations en attente. Les invitations gardent leur action de renvoi.
Les comptes `ROLE_DEV` et le compte courant restent protégés ; le backend
constitue l'autorité finale sur les ACL.

Les deux listes utilisent le `DataTable` de l'hôte avec recherche, tri,
page et taille de page 10/20/50/100, sur les données chargées de l'API.
Les endpoints actuels restent limités à 200 comptes / 100 invitations :
ce lot rétablit la pagination UI XCore **sans prétendre** fournir une
pagination serveur pour les très grands volumes. Celle-ci demandera un
contrat API paginé distinct si le plafond devient limitant.

QA et crash-tests XT nécessaires (onglets, recherche, tri, pagination,
modales, clavier, invitation et protection du compte développeur).


Correctif QA du 16/11/2026 : suppression d'une variable dérivée inutilisée
pour conserver le contrôle ESLint strict des routes Svelte assemblées.
