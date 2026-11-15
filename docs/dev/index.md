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
