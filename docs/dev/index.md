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
