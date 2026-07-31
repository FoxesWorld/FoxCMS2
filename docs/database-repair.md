# Repairing a legacy FoxCMS database

The modern runtime expects the schema from `database/schema-000.sql` plus migrations `001` through `005`.
Historical FoxEngine databases may contain the `users` table while missing newer columns such as
`serversOnline`, `userPerms`, UUID launcher fields, password reset tables, or hardware report tables.

## Current production failure

The modern profile contract reads `balance`, `badges`, and `serversOnline` from `users`. If any of
these columns is absent, profile and administrative requests fail during SQL prepare with error `1054`.
The current production incident is `Unknown column 'user.balance' in 'SELECT'`.

Emergency repair for the currently missing column:

```sql
ALTER TABLE `users`
    ADD COLUMN `balance` LONGTEXT NOT NULL DEFAULT '[]';

UPDATE `users`
SET `balance` = '[]'
WHERE `balance` IS NULL OR TRIM(`balance`) = '';
```

Do not run the `ADD COLUMN` statement when `balance` already exists. Migration
`005_enforce_profile_runtime_fields.sql` checks all three profile fields automatically and is safe to
run after a complete or partial migration `004`.

## Complete repair

Back up the database first, then run:

```bash
mysql --user=SCHEMA_OWNER --password DATABASE_NAME \
  < database/repair-legacy-schema.sql
```

The repair is repeatable. It uses `information_schema` before every `ALTER TABLE ADD COLUMN`, creates
missing runtime tables with `CREATE TABLE IF NOT EXISTS`, preserves existing rows, restores UUID values,
and finishes with a direct assertion that the required profile fields are selectable.

Tables covered by the repair:

- `users`
- `groupAssociation`
- `regCodes`
- `servers`
- `infobox`
- `badgesList`
- `antiBrute`
- `usersession`
- `password_reset_tokens`
- `user_hardware_reports`
- `userBadges`

## Normal migration path

After deploying migrations through `database/migrations/005_enforce_profile_runtime_fields.sql`, inspect migration state:

```bash
php scripts/migrate.php --status
```

When one or more migrations are pending, including `005_enforce_profile_runtime_fields`:

```bash
php scripts/migrate.php --dry-run
php scripts/migrate.php
```

Use the process-only `FOXESCRAFT_MIGRATION_DB_*` credentials for a schema-owner account. The PHP-FPM
runtime database account should not have `ALTER`, `CREATE`, or `DROP` privileges.

When the migration repository is absent on an old production database, apply
`database/repair-legacy-schema.sql` directly first. Do not blindly replay historical non-idempotent
migrations against an unknown schema.

## Verification

```bash
php scripts/diagnose-runtime.php
python3 scripts/verify-deployment.py /var/www/FoxCMS --theme foxengine2
```

The health endpoint now reports exact defects in `checks.schema.missingTables` and
`checks.schema.missingColumns`, for example:

```json
{
  "missingTables": [],
  "missingColumns": ["users.balance"]
}
```
