# Admins

Module SymSvel (manifest schema 1).


## Initial setup (PostgreSQL)

After database creation and native migrations, the local two-phase Setup Wizard uses `symsvel:admins:bootstrap:stdin` with a JSON payload (`email`, `password`) on standard input. The command requires `.symsvel/setup.json` in `database_ready` state, creates the sole `ROLE_DEV` administrator with an Argon2id password hash, and accepts a retry only with matching credentials. It never persists the plaintext password to a staging file. `symsvel:admins:bootstrap:verify` confirms that exactly one developer administrator exists before the static builds. Both commands are internal Tooling/Setup interfaces, not end-user CLI commands.

The older `symsvel:admins:bootstrap` command remains available for the historical staged setup path. MySQL, MariaDB, SQLite, 2FA and i18n are not covered by this change. Automated tests, CI and XT crash tests are required before this work is considered complete.
