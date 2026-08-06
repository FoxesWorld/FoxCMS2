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
  gameApiApplication: join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Game', 'GameApiApplication.php'),
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
  achievementsTpl: join(themeRoot, 'pages', 'templates', 'Achievements.tpl'),
  statisticsTreeTpl: join(themeRoot, 'pages', 'templates', 'achievements', 'StatisticsTree.tpl'),
  statisticsTreeNodeTpl: join(themeRoot, 'pages', 'templates', 'achievements', 'TreeNode.tpl'),
  pageTemplateStore: join(repositoryRoot, 'engine', 'client', 'runtime', 'pageTemplates.ts'),
  frontendPanel: join(themeRoot, 'src', 'userOptions', 'userOptions', 'profile', 'ProfileAchievements.vue'),
  frontendPanelTpl: join(themeRoot, 'pages', 'templates', 'achievements', 'ProfilePanel.tpl'),
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
  'GameApiApplication',
  'Request::fromGlobals()',
  '->run()',
])
requireText('Game API application', source.gameApiApplication, [
  'final class GameApiApplication',
  "private const PROTOCOL = 'fox-achievements-v1'",
  "'/game/achievements/catalog' => $this->catalog()",
  "'/game/achievements/event' => $this->event()",
  "'/game/achievements/player' => $this->player()",
  "'/game/achievements/statistics' => $this->statistics()",
  'GameServerAuthenticator::fromEnvironment()->authenticate(',
  '$this->request->jsonObject($maximumBytes)',
  "'Cache-Control' => 'no-store, max-age=0'",
  "'operation' => 'catalog'",
  "'operation' => 'event'",
  'PlayerIdentityResolver',
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

requireText('NeoForge target', source.forgeProperties, [
  'minecraft_version=1.21.1',
  'neo_version=21.1.244',
  'parchment_minecraft_version=1.21.1',
  'mod_version=0.2.0',
  'archives_base_name=fox-achievements-neoforge-1.21.1',
])
requireText('NeoForge build', source.forgeBuild, [
  "id 'net.neoforged.moddev' version '2.0.143'",
  'JavaLanguageVersion.of(21)',
  'options.release = 21',
  'prepareBundledMinecraftAssets',
  "assets/minecraft/textures/item/**",
  "assets/minecraft/textures/block/**",
  "assets/minecraft/models/item/**",
  "assets/minecraft/models/block/**",
  "assets/minecraft/lang/en_us.json",
  "minecraft/lang/ru_ru.json",
  'sourceSets.main.resources.srcDir generatedMinecraftAssets',
])
rejectText('NeoForge integration', Object.values(source).filter((value, index) => Object.keys(source)[index].startsWith('forge')).join('\n'), [
  'net.fabricmc',
  'FabricLoader',
  "apply plugin: 'forge'",
])
requireText('NeoForge mod lifecycle', source.forgeMain, [
  '@Mod(',
  'ModConfig.Type.COMMON',
  'NeoForge.EVENT_BUS.addListener(events::onAdvancementEarned)',
  'NeoForge.EVENT_BUS.addListener(events::onPlayerLogin)',
  'NeoForge.EVENT_BUS.addListener(events::onRegisterCommands)',
  'ServerStartedEvent',
  'ServerStoppingEvent',
])
requireText('Complete NeoForge advancement discovery', source.forgeCatalog, [
  'AdvancementHolder',
  'server.getAdvancements().getAllAdvancements()',
  'AdvancementRequirements',
  'AdvancementType',
  'iconBase64',
  'iconComponents',
  'requirements',
  'root.addProperty("locale", metadata.locale())',
  'titleKey',
  'descriptionKey',
  'id.getNamespace() + ":advancement/" + id.getPath()',
])
requireText('NeoForge configurable advancement language', source.forgeLanguage, [
  'AchievementLanguageResolver',
  'loadLocale("en_us")',
  'assets/minecraft/lang/',
  'readClasspath(',
  'readModFile(',
  'StandardCharsets.UTF_8',
])
requireText('NeoForge locale configuration', source.forgeConfig, [
  'ConfigValue<String> LOCALE',
  '.define("locale", "ru_RU")',
  'FOX_ACHIEVEMENTS_LOCALE',
  'resourceLocale()',
])
requireText('NeoForge component metadata', source.forgeMetadata, [
  'DisplayInfo::getTitle',
  'DisplayInfo::getDescription',
  'ComponentContents',
  'TranslatableContents',
  'component.getString()',
])
requireText('NeoForge Brigadier command', source.forgeCommand, [
  'CommandDispatcher<CommandSourceStack>',
  'Commands.literal("foxachievements")',
  'Commands.literal("status")',
  'Commands.literal("sync")',
  'service.synchronizeCatalog()',
])
requireText('NeoForge startup failure isolation', source.forgeService, [
  'catch (RuntimeException | LinkageError error)',
  'server startup will continue',
  'return -1',
])
requireText('NeoForge advancement events', source.forgeEvents, [
  'AdvancementEvent.AdvancementEarnEvent',
  'PlayerEvent.PlayerLoggedInEvent',
  'RegisterCommandsEvent',
  'service.record(',
  'service.reconcile(',
])
requireText('NeoForge Base64 icon resolution', source.forgeIcons, [
  'readOverride(',
  'readItemTexture(',
  'resolveModelTextures(',
  'readClasspath(',
  'readFromMod(',
  'fallbackPng(',
  'Base64.getEncoder().encodeToString(bytes)',
  'image/png',
])
requireText('NeoForge durable queue', source.forgeQueue, [
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
requireText('NeoForge clean API routes', source.forgeConfig, [
  '/api/game/achievements/catalog/',
  '/api/game/achievements/event/',
])
rejectText('Achievement public routes', source.forgeConfig + source.frontendClient, [
  '/api/game/achievements/catalog.php',
  '/api/game/achievements/event.php',
  '/api/game/achievements/player.php',
])
requireText('NeoForge HMAC client', source.forgeHttp, [
  'X-Fox-Server',
  'X-Fox-Timestamp',
  'X-Fox-Signature',
  'HmacSHA256',
  'setInstanceFollowRedirects(false)',
  'unexpected Content-Type',
])
requireText('NeoForge reconciliation and idempotency', source.forgeService, [
  'player.getAdvancements().getOrStartProgress(advancement)',
  'progress.isDone()',
  'UUID.nameUUIDFromBytes(eventSeed.getBytes(StandardCharsets.UTF_8))',
  'queue.enqueueCatalog(payload)',
  'queue.enqueueEvent(event(player, advancement))',
])

requireText('Achievements controller host', source.achievementsView, [
  'loadPlayerAchievementsByIdentity',
  'defineAsyncComponent',
  "() => import('@/achievements/AchievementStatisticsTree.vue')",
  'requestedIdentity.value || currentUuid.value || currentLogin.value',
  "requestedView === 'statistics'",
  "query: { view: 'statistics' }",
  'recentAchievements',
  "runtimePageTemplate('achievements')",
  '<RuntimeTpl',
])
rejectText('Achievements controller host', source.achievementsView, [
  'achievements-player-search', 'achievements-metrics', 'achievements-status-tabs', 'achievements-grid',
])
requireText('Achievements page TPL', source.achievementsTpl, [
  '<fox-page-template id="achievements"',
  'achievements-player-search',
  'achievements-metrics',
  'achievements-status-tabs',
  'achievements-grid',
  'item.iconDataUrl',
  '<AchievementStatisticsTree',
])
requireText('Achievement statistics controller host', source.statisticsTree, [
  'loadAchievementStatistics',
  'buildAchievementTrees',
  'AchievementTreeNode',
  "runtimePageTemplate('achievement-statistics')",
  '<RuntimeTpl',
])
rejectText('Achievement statistics controller host', source.statisticsTree, [
  'achievement-statistics__metrics', 'achievement-statistics__tree',
])
requireText('Achievement statistics tree TPL', source.statisticsTreeTpl, [
  '<fox-page-template id="achievement-statistics"',
  'summary.playerCount',
  'summary.unlockCount',
  'achievement-statistics__metrics',
  '<AchievementTreeNode',
])
requireText('Achievement tree-node controller host', source.statisticsTreeNode, [
  'node.earnedCount',
  "name: 'achievements'",
  "runtimePageTemplate('achievement-tree-node')",
  'getCurrentInstance()?.type',
  '<RuntimeTpl',
])
rejectText('Achievement tree-node controller host', source.statisticsTreeNode, [
  'achievement-tree-node__players', 'achievement-tree-node__children',
])
requireText('Achievement tree-node TPL', source.statisticsTreeNodeTpl, [
  '<fox-page-template id="achievement-tree-node"',
  'achievement-tree-node__players',
  'achievement-tree-node__children',
  '<RouterLink',
  '<AchievementTreeNode',
])
requireText('Achievement page template runtime', source.pageTemplateStore, [
  "'achievements'",
  "'achievement-statistics'",
  "'achievement-tree-node'",
  "loadContentRegistry<unknown>('page-templates')",
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
requireText('Achievement profile panel controller', source.frontendPanel, [
  'loadPlayerAchievements',
  'summary.value.completedCount',
  'summary.value.trackedCount',
  'controller === request',
  "runtimePageTemplate('achievement-profile-panel')",
  '<RuntimeTpl',
])
rejectText('Achievement profile panel controller', source.frontendPanel, [
  'profile-achievements__summary', 'profile-achievement-card__progress',
])
requireText('Achievement profile panel TPL', source.frontendPanelTpl, [
  '<fox-page-template id="achievement-profile-panel"',
  'item.iconDataUrl',
  'summary.completedCount',
  'summary.points',
  'profile-achievement-card__progress',
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
console.log('Game achievements contract passed: NeoForge 1.21.1 discovers vanilla and modded advancements, persists a complete Base64-icon catalog, delivers idempotent player unlock events through HMAC-authenticated FoxCMS APIs, and renders runtime-TPL views and profile panels.')
