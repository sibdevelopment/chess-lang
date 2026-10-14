# Change Log

## 2026-10-14

- Imported the existing ChessLang Laravel application into its own Git repository.
- Excluded local credentials, dependencies, database backups, logs, sessions, and generated files.
- Added configurable Reverb authentication routing for hosting the application under `/app`.
- Moved the initial admin password from the seeder into deployment environment configuration.
- Fixed MySQL migrations for the reserved `groups` table identifier.
- Normalized the logo filename casing for Linux deployments.
- Documented the subpath route-cache limitation discovered during deployment verification.
- Documented the isolated development deployment layout and required environment settings.
- Migrated the legacy SQLite dataset into the isolated development MySQL database after a successful dry run and rollback backup.
