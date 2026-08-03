import { readFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const repositoryRoot = resolve(themeRoot, '..', '..')
const files = {
  admin: await readFile(resolve(repositoryRoot, 'engine/classes/modules/AdminPanel/AdminOptions.class.php'), 'utf8'),
  schema: await readFile(resolve(repositoryRoot, 'database/schema-000.sql'), 'utf8'),
  repair: await readFile(resolve(repositoryRoot, 'database/repair-legacy-schema.sql'), 'utf8'),
  migration004: await readFile(resolve(repositoryRoot, 'database/migrations/004_repair_legacy_schema.sql'), 'utf8'),
  migration022: await readFile(resolve(repositoryRoot, 'database/migrations/022_expand_server_structured_columns.sql'), 'utf8'),
}

const failures = []
const requireText = (source, token, message) => {
  if (!source.includes(token)) failures.push(message)
}

for (const [name, source] of Object.entries({ schema: files.schema, repair: files.repair, migration004: files.migration004 })) {
  requireText(source, '`serverGroups` LONGTEXT', `${name} must store serverGroups as LONGTEXT`)
  if (/`serverGroups`\s+VARCHAR\s*\(/i.test(source)) failures.push(`${name} must not constrain serverGroups with VARCHAR`)
}

requireText(files.admin, '$this->ensureServerStructuredStorage();', 'saveServer must repair structured storage before persistence')
requireText(files.admin, 'private function ensureServerStructuredStorage(): void', 'AdminOptions must own the structured-storage repair')
requireText(files.admin, "'serverGroups' => '[]'", 'runtime repair must cover serverGroups')
requireText(files.admin, "'ignoreDirs' => '[]'", 'runtime repair must cover ignoreDirs')
requireText(files.admin, "'modsInfo' => '[]'", 'runtime repair must cover modsInfo')
requireText(files.admin, 'CHARACTER_MAXIMUM_LENGTH', 'runtime repair must inspect actual column capacity')
requireText(files.admin, "LONGTEXT NOT NULL DEFAULT '[]'", 'runtime repair must finalize canonical LONGTEXT storage')
requireText(files.admin, 'Примените миграцию 022', 'runtime repair failure must identify the corrective migration')

for (const field of ['serverGroups', 'ignoreDirs', 'modsInfo']) {
  requireText(files.migration022, `COLUMN_NAME = '${field}'`, `migration 022 must inspect ${field}`)
  requireText(files.migration022, `MODIFY COLUMN \`${field}\` LONGTEXT NULL`, `migration 022 must widen ${field}`)
  requireText(files.migration022, `MODIFY COLUMN \`${field}\` LONGTEXT NOT NULL DEFAULT '[]'`, `migration 022 must finalize ${field} as non-null`)
}
requireText(files.migration022, 'information_schema.COLUMNS', 'migration 022 must be adaptive for legacy schemas')
requireText(files.migration022, 'PREPARE fox_stmt FROM @fox_sql', 'migration 022 must use conditional dynamic DDL')

if (failures.length) {
  console.error('Server structured-storage contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log('Server structured-storage contract passed: legacy JSON columns self-expand and serverGroups is no longer length-constrained.')
