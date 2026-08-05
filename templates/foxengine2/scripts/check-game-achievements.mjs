import { readFile } from 'node:fs/promises'
import { join, resolve } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'
import { includesLocalized } from './i18n-test-utils.mjs'

const forgeRoot = resolve(repositoryRoot, '..', 'fox-achievements-forge-1.7.10')
const files = {
  migration: join(repositoryRoot, 'database', 'migrations', '025_game_achievements.sql'),
  environment: join(repositoryRoot, '.env.example'),
  health: join(repositoryRoot, 'engine', 'classes', 'services', 'HealthCheckService.class.php'),
  exception: join(repositoryRoot, 'engine', 'classes', 'game', 'GameApiException.class.php'),
  authenticator: join(repositoryRoot, 'engine', 'classes', 'game', 'GameServerAuthenticator.class.php'),
  catalogService: join(repositoryRoot, 'engine', 'classes', 'game', 'GameAchievementCatalogService.class.php'),
  eventService: join(repositoryRoot, 'engine', 'classes', 'game', 'GameAchievementEventService.class.php'),
  apiIndex: join(repositoryRoot, 'api', 'index.php'),
  catalogEndpoint: join(repositoryRoot, 'api', 'game', 'achievements', 'catalog', 'index.php'),
  eventEndpoint: join(repositoryRoot, 'api', 'game', 'achievements', 'event', 'index.php'),
  playerEndpoint: join(repositoryRoot, 'api', 'game', 'achievements', 'player', 'index.php'),
  statisticsEndpoint: join(repositoryRoot, 'api', 'game', 'achievements', 'statistics', 'index.php'),
  forgeProperties: join(forgeRoot, 'gradle.properties'),
  forgeBuild: join(forgeRoot, 'build.gradle'),
  forgeConfig: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'FoxAchievementsConfig.java'),
  forgeMain: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'FoxAchievementsMod.java'),
  forgeCatalog: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementCatalogBuilder.java'),
  forgeMetadata: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementMetadataReader.java'),
  forgeLanguage: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementLanguageResolver.java'),
  forgeEvents: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementEvents.java'),
  forgeIcons: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementIconResolver.java'),
  forgeQueue: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'DeliveryQueue.java'),
  forgeHttp: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementHttpClient.java'),
  forgeService: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementService.java'),
  forgeCommand: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'FoxAchievementsCommand.java'),
  frontendClient: join(repositoryRoot, 'engine', 'client', 'achievements', 'playerAchievements.ts'),
  achievementsView: join(repositoryRoot, 'engine', 'client', 'views', 'AchievementsView.vue'),
  statisticsTree: join(repositoryRoot, 'engine', 'client', 'achievements', 'AchievementStatisticsTree.vue'),
  statisticsTreeNode: join(repositoryRoot, 'engine', 'client', 'achievements', 'AchievementTreeNode.vue'),
  frontendPanel: join(themeRoot, 'src', 'userOptions', 'userOptions', 'profile', 'ProfileAchievements.vue'),
  frontendProfile: join(themeRoot, 'src', 'userOptions', 'userOptions', 'Profile.vue'),
  frontendStyles: join(themeRoot, 'src', 'styles', 'profile.css'),
}

const values = await Promise.all(Object.values(files).map((path) => readFile(path, 'utf8')))
const source = Object.fromEntries(Object.keys(files).map((key, index) => [key, values[index]]))
const failures = []
const requireText = (label, text, tokens) => {
  for (const token of tokens) if (!includesLocalized(text, token)) failures.push(`${label} is missing ${token}`)
}
const rejectText = (label, text, tokens) => {
  for (const token of tokens) if (text.includes(token)) failures.push(`${label} must not contain ${token}`)
}

requireText('Achievement database migration', source.migration, [
  'CREATE TABLE IF NOT EXISTS `gameAchievements`',
  '`iconBase64` MEDIUMTEXT NOT NULL',
  '`iconComponents` JSON NOT NULL',
  '`criteria` JSON NOT NULL',
  '`requirements` JSON NOT NULL',
  '`definitionHash` CHAR(64)',
  '`catalogRevision` CHAR(64)',
  'CREATE TABLE IF NOT EXISTS `playerAchievements`',
  'CREATE TABLE IF NOT EXISTS `gameAchievementEvents`',
  'UNIQUE KEY `uq_game_achievement_event_uuid` (`eventUuid`)',
  'FOREIGN KEY (`playerUuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE',
])
rejectText('Achievement database migration', source.migration, ['secretKey', 'rawSecret', 'hmacSecret'])

requireText('Game server environment', source.environment, [
  'FOXESCRAFT_GAME_SERVER_KEYS_JSON',
  'FOXESCRAFT_GAME_HMAC_TOLERANCE_SECONDS=300',
])
requireText('Achievement health schema', source.health, [
  "'gameAchievements' => [",
  "'playerAchievements' => [",
  "'gameAchievementEvents' => [",
])
requireText('Game API exception', source.exception, [
  'final class GameApiException',
  'public function errorCode(): string',
  'public function statusCode(): int',
])
requireText('Game server HMAC authentication', source.authenticator, [
  'FOXESCRAFT_GAME_SERVER_KEYS_JSON',
  'FOXESCRAFT_GAME_HMAC_TOLERANCE_SECONDS',
  "hash('sha256', $body)",
  "hash_hmac('sha256', $canonical, $secret)",
  'hash_equals($expected, $signature)',
])
rejectText('Game server HMAC authentication', source.authenticator, ['md5(', 'sha1('])

requireText('Achievement catalog service', source.catalogService, [
  'final class GameAchievementCatalogService',
  'private const MAX_ACHIEVEMENTS = 10000',
  'private const MAX_ICON_BYTES = 262144',
  "base64_decode($iconBase64, true)",
  '`iconBase64` = VALUES(`iconBase64`)',
  '`catalogRevision` = VALUES(`catalogRevision`)',
  '`catalogRevision` <> :catalogRevision',
  '`enabled` = 0',
  "function_exists('mb_strlen')",
  "~^[a-z0-9_.-]+:[a-z0-9_./-]+$~D",
  "'persistedCount'",
  "'locale' => $locale",
  "achievement_locale_invalid",
  "SELECT DATABASE()",
  "public static function isSchemaOutdated(Throwable $error): bool",
  "$error instanceof PDOException",
  "self::matchesDatabaseError($error, '42S02', 1146)",
  "self::matchesDatabaseError($error, '42S22', 1054)",
])
rejectText('Achievement identifier delimiter safety', source.catalogService + source.eventService, [
  '/^[a-z0-9_.-]+:[a-z0-9_./-]+$/D',
])
rejectText('Achievement schema error classification', source.catalogService, [
  "|| str_contains($message, 'gameachievements')",
  "|| str_contains($message, 'playerachievements')",
  "|| str_contains($message, 'gameachievementevents')",
])

requireText('Achievement event service', source.eventService, [
  'final class GameAchievementEventService',
  'INSERT IGNORE INTO `gameAchievementEvents`',
  '`playerUuid` = :playerUuid',
  '`achievementKey` = :achievementKey',
  'GREATEST(`progress`, VALUES(`progress`))',
  "'achievement.game_unlocked'",
  'public function playerAchievements(',
  'public function achievementStatistics(',
  'LEFT JOIN `playerAchievements` AS `player`',
  "$playerLimitPerAchievement = 24",
  "`achievement`.`enabled` = 1",
  "'iconDataUrl' => 'data:'",
  "$result['eventPersisted'] = true",
  "'databaseName'",
])
rejectText('Public achievement response', source.eventService.slice(source.eventService.indexOf('public function playerAchievements')), [
  'payloadJson',
  'eventUuid',
  'secret',
])

requireText('Game API front controller', source.apiIndex, [
  'GameServerAuthenticator::fromEnvironment()->authenticate(',
  'foxApiJsonBody(',
  'foxApiAchievementCatalog(',
  'foxApiAchievementEvent(',
  'foxApiPlayerAchievements(',
  "'/game/achievements/catalog'",
  "'/game/achievements/event'",
  "'/game/achievements/player'",
  "str_contains(strtolower($path), '.php')",
  "'X-Content-Type-Options: nosniff'",
  "'Cache-Control: no-store, max-age=0'",
  "'protocol' => 'fox-achievements-v1'",
  "'operation' => 'catalog'",
  "'operation' => 'event'",
])
requireText('Catalog directory index', source.catalogEndpoint, [
  "$_SERVER['FOX_API_ROUTE'] = '/game/achievements/catalog'",
  "require dirname(__DIR__, 3) . '/index.php'",
])
requireText('Event directory index', source.eventEndpoint, [
  "$_SERVER['FOX_API_ROUTE'] = '/game/achievements/event'",
  "require dirname(__DIR__, 3) . '/index.php'",
])
requireText('Player directory index', source.playerEndpoint, [
  "$_SERVER['FOX_API_ROUTE'] = '/game/achievements/player'",
  "require dirname(__DIR__, 3) . '/index.php'",
])
requireText('Statistics directory index', source.statisticsEndpoint, [
  "$_SERVER['FOX_API_ROUTE'] = '/game/achievements/statistics'",
  "require dirname(__DIR__, 3) . '/index.php'",
])

requireText('Forge target', source.forgeProperties, [
  'minecraft_version=1.7.10',
  'forge_version=1.7.10-10.13.4.1614-1.7.10',
])
requireText('Forge build', source.forgeBuild, [
  "apply plugin: 'forge'",
  'sourceCompatibility = JavaVersion.VERSION_1_8',
  'targetCompatibility = JavaVersion.VERSION_1_8',
  'prepareBundledMinecraftAssets',
  'assets/minecraft/textures/items/**',
  'assets/minecraft/textures/blocks/**',
  "['ru_RU']",
  'assets/minecraft/lang/en_US.lang',
  'verifyReleaseBytecode',
  'net/minecraft/command/CommandBase',
  'java/lang/reflect/Proxy',
  'newProxyInstance',
  'java/lang/LinkageError',
  'check.dependsOn verifyReleaseBytecode',
])
rejectText('Forge integration', Object.values(source).filter((value, index) => Object.keys(source)[index].startsWith('forge')).join('\n'), [
  'fabric-loader',
  'net.fabricmc',
  'FabricLoader',
])
requireText('Forge mod lifecycle', source.forgeMain, [
  '@Mod(',
  'FMLServerStartingEvent',
  'FMLServerStartedEvent',
  'FMLServerStoppingEvent',
  'MinecraftForge.EVENT_BUS.register(events)',
  'FMLCommonHandler.instance().bus().register(events)',
])
requireText('Complete Forge achievement discovery', source.forgeCatalog, [
  'AchievementList.achievementList',
  'StatList.allStats',
  'AchievementPage.getAchievementPages()',
  'page.getAchievements()',
  'iconBase64',
  'iconComponents',
  'requirements',
  'root.addProperty("locale", metadata.locale())',
  'titleKey',
  'descriptionKey',
])
requireText('Forge configurable achievement language', source.forgeLanguage, [
  'AchievementLanguageResolver',
  'loadLocale("en_US")',
  'readFromSource(ownSource(), vanillaResource)',
  'StandardCharsets.UTF_8',
])
requireText('Forge locale configuration', source.forgeConfig, [
  'public final String locale',
  '"locale", "ru_RU"',
  'FOX_ACHIEVEMENTS_LOCALE',
])

requireText('Forge runtime-compatible achievement metadata', source.forgeMetadata, [
  'StatCollector.translateToLocal',
  'field_75996_k',
  'field_75995_m',
  'func_75989_e',
  'func_75984_f',
  'invokeNoArg(',
])
rejectText('Forge direct achievement metadata linkage', source.forgeCatalog, [
  'achievement.getDescription()',
  'achievement.getSpecial()',
  'achievement.func_150951_e()',
])
requireText('Forge mapping-independent command', source.forgeCommand, [
  'Proxy.newProxyInstance',
  'new Class<?>[] {ICommand.class}',
  'implements InvocationHandler',
  'method.getParameterTypes()',
  'process((ICommandSender) arguments[0], (String[]) arguments[1])',
])
rejectText('Forge mapping-independent command', source.forgeCommand, [
  'extends CommandBase',
  'new FoxAchievementsCommand(service)',
])
requireText('Forge command lifecycle isolation', source.forgeMain, [
  'FoxAchievementsCommand.create(service)',
  'catch (LinkageError error)',
  'command registration failed, but server startup will continue',
])

requireText('Forge startup failure isolation', source.forgeService, [
  'catch (LinkageError error)',
  'server startup will continue',
  'return -1',
])

requireText('Forge achievement events', source.forgeEvents, [
  'AchievementEvent',
  'PlayerLoggedInEvent',
  'service.record(',
  'service.reconcile(',
])
requireText('Forge Base64 icon resolution', source.forgeIcons, [
  'readOverride(',
  'readItemTexture(',
  'readFromSource(',
  'fallbackPng(',
  'readKnownVanillaTexture(',
  'readFromSource(ownSource(), resource)',
  'furnace_front_off.png',
  'crafting_table_front.png',
  'fish_cod_cooked.png',
  'Base64.getEncoder().encodeToString(bytes)',
  'image/png',
])
requireText('Forge durable queue', source.forgeQueue, [
  'queueRoot.resolve("catalog.json")',
  'queueRoot.resolve("events")',
  'Files.move(',
  'ATOMIC_MOVE',
  'scheduleWithFixedDelay(',
  'FoxAchievements-Delivery',
  'fox-achievements-v1',
  'validateBaseResponse(',
  'persistedCount',
  'eventPersisted',
  'databaseName',
  'requireString(response, "locale", expectedLocale)',
])
requireText('Forge clean API routes', source.forgeConfig, [
  '/api/game/achievements/catalog/',
  '/api/game/achievements/event/',
])
rejectText('Achievement public routes', source.forgeConfig + source.frontendClient, [
  '/api/game/achievements/catalog.php',
  '/api/game/achievements/event.php',
  '/api/game/achievements/player.php',
])

requireText('Forge HMAC client', source.forgeHttp, [
  'X-Fox-Server',
  'X-Fox-Timestamp',
  'X-Fox-Signature',
  'HmacSHA256',
  'sha256(bytes)',
  'setInstanceFollowRedirects(false)',
  'unexpected Content-Type',
])
requireText('Forge reconciliation and idempotency', source.forgeService, [
  'hasAchievementUnlocked(achievement)',
  'UUID.nameUUIDFromBytes(eventSeed.getBytes(StandardCharsets.UTF_8))',
  'queue.enqueueCatalog(payload)',
  'queue.enqueueEvent(event(player, achievement))',
])

requireText('Achievements page', source.achievementsView, [
  'loadPlayerAchievementsByIdentity',
  'defineAsyncComponent',
  "() => import('@/achievements/AchievementStatisticsTree.vue')",
  'requestedIdentity.value || currentUuid.value || currentLogin.value',
  "requestedView === 'statistics'",
  "query: { view: 'statistics' }",
  'achievements-player-search',
  'achievements-metrics',
  'achievements-status-tabs',
  'achievements-grid',
  'recentAchievements',
  'item.iconDataUrl',
])
requireText('Achievement statistics tree', source.statisticsTree, [
  'loadAchievementStatistics',
  'buildAchievementTrees',
  'AchievementTreeNode',
  'summary.playerCount',
  'summary.unlockCount',
])
requireText('Achievement statistics player nodes', source.statisticsTreeNode, [
  'node.earnedCount',
  'node.players',
  "name: 'achievements'",
])
requireText('Achievements route manifest', await readFile(join(themeRoot, 'frontend.json'), 'utf8'), [
  '"path": "/achievements/:value?"',
  '"name": "achievements"',
  '"view": "AchievementsView"',
  '"game.achievements"',
])

requireText('Achievement frontend client', source.frontendClient, [
  '/api/game/achievements/player/',
  'export interface PlayerAchievement',
  'iconDataUrl: string',
  'export async function loadPlayerAchievements(',
])
requireText('Achievement profile panel', source.frontendPanel, [
  'loadPlayerAchievements',
  'item.iconDataUrl',
  'summary.completedCount',
  'summary.points',
  'profile-achievement-card__progress',
  'controller === request',
])
requireText('Achievement profile integration', source.frontendProfile, [
  "import ProfileAchievements from './profile/ProfileAchievements.vue'",
  '<ProfileAchievements v-if="profile.uuid" :player-uuid="profile.uuid" />',
])
requireText('Achievement profile styles', source.frontendStyles, [
  '/* Fox Achievements profile panel */',
  '.profile-achievement-card__icon',
  'image-rendering: pixelated',
])

if (failures.length) {
  console.error('Game achievements contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Game achievements contract passed: Forge 1.7.10 discovers vanilla and modded achievements, persists a complete Base64-icon catalog, delivers idempotent player unlock events through HMAC-authenticated FoxCMS APIs, and renders results in player profiles.')
