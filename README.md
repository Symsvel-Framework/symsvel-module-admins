# Admins

Module SymSvel (manifest schema 1).


## Initial setup (PostgreSQL)

After database creation and native migrations, the local two-phase Setup Wizard uses `symsvel:admins:bootstrap:stdin` with a JSON payload (`email`, `password`) on standard input. The command requires `.symsvel/setup.json` in `database_ready` state, creates the sole `ROLE_DEV` administrator with an Argon2id password hash, and accepts a retry only with matching credentials. It never persists the plaintext password to a staging file. `symsvel:admins:bootstrap:verify` confirms that exactly one developer administrator exists before the static builds. Both commands are internal Tooling/Setup interfaces, not end-user CLI commands.

The older `symsvel:admins:bootstrap` command remains available for the historical staged setup path. MySQL, MariaDB, SQLite, 2FA and i18n are not covered by this change. Automated tests, CI and XT crash tests are required before this work is considered complete.

## CI (10/11/2026)

`.github/workflows/qa.yml` exécute Composer et les tests du module sur le runner Docker ARM64 d'organisation (`self-hosted`, `linux`, `ARM64`, `symsvel`), avec PHP 8.4 préinstallé. La CI GitHub-hosted originale est conservée et désactivée dans `.github/workflows/qa.yml.stub`. Pour revenir aux runners GitHub, restaurer ce `.stub` sous le nom `qa.yml`. Les jobs locaux nécessitent que le Mac et Docker soient disponibles ; runner réservé aux contributions de confiance. Validation finale soumise à une CI verte et aux crash-tests XT applicables.

## Migrations natives (chantier DBAL)

Les sources immuables utilisent désormais des noms `Migration/VersionYYYYMMDDHHMMSS.php`. Le compilateur du Tooling conserve leur version Doctrine et convertit les opérations `Schema` en SQL selon la plateforme DBAL active. Cette branche est à valider avec le Tooling associé : tests automatisés et crash-tests PostgreSQL/MySQL avant merge. Ne pas utiliser avec l'ancien compilateur.

## Distribution stable v1.0.0 (20/11/2026)

Le manifeste Composer du tag stable déclare exclusivement des contraintes `^1.0` pour ses dépendances SymSvel : `symsvel-framework/module-auth`, `symsvel-framework/module-mails`, `symsvel-framework/module-settings`, `symsvel-framework/module-roles`, `symsvel-framework/module-acls`, `symsvel-framework/module-languages`. Les références `dev-dev` restent réservées à la branche `dev` et à l'installation de maintenance avec `--dev`. Le tag `v1.0.0` n'est à publier **qu'après** le tag stable de chaque dépendance, son indexation dans Satis et la réussite des contrôles CI ; aucune release n'est produite par ce commit.
