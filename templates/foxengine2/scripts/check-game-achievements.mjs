import { readFile } from 'node:fs/promises'
import { join, resolve } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'
import { includesLocalized } from './i18n-test-utils.mjs'

const achievementsRoot = resolve(repositoryRoot, '..', 'fox-achievements')
const forgeRoot = resolve(achievementsRoot, 'forge-1.7.10')
const neoForgeRoot = resolve(achievementsRoot, 'neoforge-1.21.1')
const files = {
  migration: join(repositoryRoot, 'database', 'migrations', '025_game_achievements.sql'),
  categoryMigration: join(repositoryRoot, 'database', 'migrations', '026_game_achievement_category_labels.sql'),
  categoryCleanupMigration: join(repositoryRoot, 'database', 'migrations', '028_game_achievement_category_label_cleanup.sql'),
  economyMigration: join(repositoryRoot, 'database', 'migrations', '027_game_achievement_points_economy.sql'),
  environment: join(repositoryRoot, '.env.example'),
  health: join(repositoryRoot, 'engine', 'classes', 'services', 'HealthCheckService.class.php'),
  exception: join(repositoryRoot, 'engine', 'classes', 'game', 'GameApiException.class.php'),
  authenticator: join(repositoryRoot, 'engine', 'classes', 'game', 'GameServerAuthenticator.class.php'),
  catalogService: join(repositoryRoot, 'engine', 'classes', 'game', 'GameAchievementCatalogService.class.php'),
  eventService: join(repositoryRoot, 'engine', 'classes', 'game', 'GameAchievementEventService.class.php'),
  economyService: join(repositoryRoot, 'engine', 'classes', 'services', 'AchievementPointExchangeService.class.php'),
  userAchievementController: join(repositoryRoot, 'engine', 'src', 'FoxCMS', 'Engine', 'User', 'UserAchievementController.php'),
  userActions: join(repositoryRoot, 'engine', 'classes', 'modules', 'UserSettings', 'UserActions.class.php'),
  apiIndex: join(repositoryRoot, 'api', 'index.php'),
  gameApiApplication: join(repositoryRoot, 'api', 'src', 'FoxCMS', 'Api', 'Game', 'GameApiApplication.php'),
  catalogEndpoint: join(repositoryRoot, 'api', 'game', 'achievements', 'catalog', 'index.php'),
  eventEndpoint: join(repositoryRoot, 'api', 'game', 'achievements', 'event', 'index.php'),
  playerEndpoint: join(repositoryRoot, 'api', 'game', 'achievements', 'player', 'index.php'),
  statisticsEndpoint: join(repositoryRoot, 'api', 'game', 'achievements', 'statistics', 'index.php'),
  neoForgeProperties: join(neoForgeRoot, 'gradle.properties'),
  neoForgeBuild: join(neoForgeRoot, 'build.gradle'),
  neoForgeConfig: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'FoxAchievementsConfig.java'),
  neoForgeMain: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'FoxAchievementsMod.java'),
  neoForgeCatalog: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementCatalogBuilder.java'),
  neoForgeMetadata: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementMetadataReader.java'),
  neoForgeLanguage: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementLanguageResolver.java'),
  neoForgeEvents: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementEvents.java'),
  neoForgeIcons: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementIconResolver.java'),
  neoForgeModelInspector: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'ItemModelInspector.java'),
  neoForgeServerRenderer: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'ServerModelRenderer.java'),
  neoForgeQueue: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'DeliveryQueue.java'),
  neoForgeHttp: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementHttpClient.java'),
  neoForgeService: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementService.java'),
  neoForgeCommand: join(neoForgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'FoxAchievementsCommand.java'),
  legacyForgeProperties: join(forgeRoot, 'gradle.properties'),
  legacyForgeBuild: join(forgeRoot, 'build.gradle'),
  legacyForgeMain: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'FoxAchievementsMod.java'),
  legacyForgeCatalog: join(forgeRoot, 'src', 'main', 'java', 'ru', 'foxescraft', 'achievements', 'AchievementCatalogBuilder.java'),
  frontendClient: join(repositoryRoot, 'engine', 'client', 'achievements', 'playerAchievements.ts'),
  economyClient: join(repositoryRoot, 'engine', 'client', 'achievements', 'achievementEconomy.ts'),
  economyComposable: join(repositoryRoot, 'engine', 'client', 'achievements', 'useAchievementEconomy.ts'),
  achievementsView: join(repositoryRoot, 'engine', 'client', 'views', 'AchievementsView.vue'),
  statisticsTree: join(repositoryRoot, 'engine', 'client', 'achievements', 'AchievementStatisticsTree.vue'),
  statisticsTreeModel: join(repositoryRoot, 'engine', 'client', 'achievements', 'achievementStatisticsTree.ts'),
  statisticsTreeNode: join(repositoryRoot, 'engine', 'client', 'achievements', 'AchievementTreeNode.vue'),
  achievementsTpl: join(themeRoot, 'pages', 'templates', 'Achievements.tpl'),
  statisticsTreeTpl: join(themeRoot, 'pages', 'templates', 'achievements', 'StatisticsTree.tpl'),
  statisticsTreeNodeTpl: join(themeRoot, 'pages', 'templates', 'achievements', 'TreeNode.tpl'),
  pageTemplateStore: join(repositoryRoot, 'engine', 'client', 'runtime', 'pageTemplates.ts'),
  frontendPanel: join(themeRoot, 'src', 'userOptions', 'userOptions', 'profile', 'ProfileAchievements.vue'),
  frontendPanelTpl: join(themeRoot, 'pages', 'templates', 'achievements', 'ProfilePanel.tpl'),
  frontendProfile: join(themeRoot, 'src', 'userOptions', 'userOptions', 'Profile.vue'),
  frontendStyles: join(themeRoot, 'src', 'styles', 'profile.css'),
  adminAchievementController: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminAchievementController.class.php'),
  adminOptions: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'),
  adminComposable: join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts'),
  adminAchievementsView: join(themeRoot, 'src', 'foxEngine', 'admin', 'Achievements.vue'),
  adminPanelTpl: join(themeRoot, 'userOptions', 'AdminPanel.tpl'),
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
requireText('Achievement category-label migration', source.categoryMigration, [
  'ALTER TABLE `gameAchievements`',
  'ADD COLUMN `categoryLabel` VARCHAR(190)',
  'Keep the label empty until Fox Achievements supplies a localized value',
])
requireText('Achievement category-label cleanup migration', source.categoryCleanupMigration, [
  'UPDATE `gameAchievements`',
  "SET `categoryLabel` = ''",
  'WHERE `categoryLabel` = `category`',
])
requireText('Achievement schema migration diagnostics', source.catalogService + source.gameApiApplication, [
  'requiredMigration(Throwable $error)',
  '026_game_achievement_category_labels.sql',
  '027_game_achievement_points_economy.sql',
  "'requiredMigrations'",
])
requireText('Achievement point economy migration', source.economyMigration, [
  'CREATE TABLE IF NOT EXISTS `gameAchievementPointAwards`',
  'UNIQUE KEY `uq_game_achievement_point_award` (`serverId`, `playerUuid`, `achievementKey`)',
  'CREATE TABLE IF NOT EXISTS `gameAchievementPointExchanges`',
  'UNIQUE KEY `uq_game_achievement_point_exchange_request` (`requestUuid`)',
  'CREATE TABLE IF NOT EXISTS `gameAchievementEconomySettings`',
  '`pointsPerUnit` INT UNSIGNED NOT NULL DEFAULT 10',
  '`minimumPoints` INT UNSIGNED NOT NULL DEFAULT 10',
  'INSERT IGNORE INTO `gameAchievementPointAwards`',
  'FROM `playerAchievements` AS `player`',
  "NOT LIKE '%:advancement/recipes/%'",
])


requireText('Game server environment', source.environment, [
  'FOXESCRAFT_GAME_SERVER_KEYS_JSON',
  'FOXESCRAFT_GAME_HMAC_TOLERANCE_SECONDS=300',
])
requireText('Achievement health schema', source.health, [
  "'gameAchievements' => [",
  "'playerAchievements' => [",
  "'gameAchievementEvents' => [",
  "'gameAchievementPointAwards' => [",
  "'gameAchievementPointExchanges' => [",
  "'gameAchievementEconomySettings' => [",
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
  '($value !== [] && array_is_list($value))',
  "'iconComponents' => (object)$iconComponents",
  "'criteria' => (object)$criteria",
  'final class GameAchievementCatalogService',
  'private const MAX_ACHIEVEMENTS = 10000',
  'private const MAX_ICON_BYTES = 262144',
  "base64_decode($iconBase64, true)",
  '`categoryLabel` = VALUES(`categoryLabel`)',
  "'categoryLabel' => $categoryLabel",
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

requireText('Technical category ids are never accepted as localized labels', source.eventService, [
  'resolvedCategoryLabel(',
  '$stored !== $category',
  'looksTechnicalCategory(',
])

requireText('Legacy technical recipe rows stay out of public achievement views', source.eventService, [
  "NOT LIKE '%:advancement/recipes/%'",
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
  "'categoryLabel' =>",
  'private function categoryLabels(',
  'private function humanizeCategoryToken(',
  "$result['eventPersisted'] = true",
  "'databaseName'",
])
rejectText('Public achievement response', source.eventService.slice(source.eventService.indexOf('public function playerAchievements')), [
  'payloadJson',
  'eventUuid',
  'secret',
])
rejectText('Achievement completion never auto-credits Units', source.eventService, [
  'BalanceMatrix::increment(',
  'UPDATE `users` SET `balance`',
])
requireText('Achievement completion point award', source.eventService, [
  'new AchievementPointExchangeService($this->db)',
  '->recordAward(',
  "'pointAwarded' => $pointAwarded",
  "'achievement.game_unlocked'",
  "'Новое достижение'",
  "'/#/achievements/'",
  "очков доступны для обмена на Units",
])
requireText('Achievement point exchange service', source.economyService, [
  'final class AchievementPointExchangeService',
  'INSERT IGNORE INTO `gameAchievementPointAwards`',
  'public function state(',
  'public function exchange(',
  'transactional(function',
  'LIMIT 1 FOR UPDATE',
  'gameAchievementPointExchanges',
  'pointsSpent',
  'unitsGranted',
  'pointsPerUnit',
  'BalanceMatrix::increment(',
  "'units'",
  '$points % $rate !== 0',
  '$points > $available',
  'public function saveSettings(',
  'public function statistics()',
])
requireText('Achievement exchange authenticated user action', source.userActions + source.userAchievementController, [
  "'getAchievementEconomy' => 'achievements.economy'",
  "'exchangeAchievementPoints' => 'achievements.exchange'",
  'requireRewardAccess()',
  'CsrfToken::requireValid',
  'AchievementPointExchangeService',
  "$this->session->set('balance'",
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
  "'X-Fox-Error-Code'",
  "'X-Fox-Server-Time'",
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

requireText('Forge 1.7.10 target', source.legacyForgeProperties, [
  'minecraft_version=1.7.10',
  'forge_version=1.7.10-10.13.4.1614-1.7.10',
  'mod_id=foxachievements',
])
requireText('Forge 1.7.10 build', source.legacyForgeBuild, [
  "classpath 'com.anatawa12.forge:ForgeGradle:1.2-1.0.12'",
  "apply plugin: 'forge'",
  'JavaVersion.VERSION_1_8',
])
requireText('Forge 1.7.10 lifecycle', source.legacyForgeMain, [
  'acceptableRemoteVersions = "*"',
  'FMLServerStartedEvent',
])
requireText('Complete Forge 1.7.10 achievement discovery', source.legacyForgeCatalog, [
  'AchievementList.achievementList',
  'StatList.allStats',
  'AchievementPage.getAchievementPages()',
])

requireText('NeoForge target', source.neoForgeProperties, [
  'minecraft_version=1.21.1',
  'neo_version=21.1.244',
  'parchment_minecraft_version=1.21.1',
  'mod_version=0.2.9',
  'archives_base_name=fox-achievements-neoforge-1.21.1',
])
requireText('NeoForge build', source.neoForgeBuild, [
  "id 'net.neoforged.moddev' version '2.0.143'",
  'JavaLanguageVersion.of(21)',
  'options.release = 21',
  'prepareBundledMinecraftAssets',
  "assets/minecraft/textures/item/**",
  "assets/minecraft/textures/block/**",
  "assets/minecraft/models/item/**",
  "assets/minecraft/models/block/**",
  "assets/minecraft/blockstates/**",
  "assets/minecraft/lang/en_us.json",
  "minecraft/lang/ru_ru.json",
  'sourceSets.main.resources.srcDir generatedMinecraftAssets',
])
rejectText('NeoForge integration', Object.values(source).filter((value, index) => Object.keys(source)[index].startsWith('forge')).join('\n'), [
  'net.fabricmc',
  'FabricLoader',
  "apply plugin: 'forge'",
])
requireText('NeoForge mod lifecycle', source.neoForgeMain, [
  '@Mod(',
  'ModConfig.Type.COMMON',
  'NeoForge.EVENT_BUS.addListener(events::onAdvancementEarned)',
  'NeoForge.EVENT_BUS.addListener(events::onPlayerLogin)',
  'NeoForge.EVENT_BUS.addListener(events::onRegisterCommands)',
  'ServerStartedEvent',
  'ServerStoppingEvent',
])
requireText('NeoForge runtime version metadata', source.neoForgeMain, [
  'container.getModInfo().getVersion().toString()',
  'version()',
])
requireText('Complete NeoForge advancement discovery', source.neoForgeCatalog, [
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
requireText('NeoForge player-facing advancement filter', source.neoForgeCatalog, [
  'isPlayerFacing',
  'display().isPresent()',
  'if (isPlayerFacing(holder))',
])
requireText('NeoForge localized category labels', source.neoForgeCatalog, [
  'categoryLabels(',
  'categoryLabel',
  'modDisplayName(',
  'getDisplayName()',
])
requireText('NeoForge model classification', source.neoForgeModelInspector, [
  'FLAT_TEXTURE',
  'JSON_3D',
  'COMPLEX_MODEL',
  'builtin/entity',
  'neoforge:separate_transforms',
  'perspectives',
  'gui',
])
requireText('NeoForge dedicated-server 3D icon renderer', source.neoForgeServerRenderer, [
  'model.elements()',
  'textureRegion(',
  'guiTransform(',
  'BufferedImage',
  '128',
])
requireText('NeoForge icon resolver never guesses a complex texture', source.neoForgeIcons, [
  'case FLAT_TEXTURE -> renderFlatModel(itemModel)',
  'case JSON_3D -> modelRenderer.render(itemModel)',
  'case COMPLEX_MODEL, MISSING -> null',
  'serverFallbackIcons.add(itemKey)',
])
requireText('NeoForge server-only complex-model fallback', source.neoForgeIcons + source.neoForgeService, [
  'serverFallbackIcons',
  'serverFallbackCount()',
  'case COMPLEX_MODEL, MISSING -> null',
  'Fox Achievements remains server-only',
  'raw UV/model textures are not uploaded as achievement icons',
])
rejectText('NeoForge server-only boundary', source.neoForgeMain + source.neoForgeService + source.neoForgeIcons + source.neoForgeEvents, [
  'ClientIconRenderer',
  'RenderIconRequestPayload',
  'RenderIconResponsePayload',
  'RegisterPayloadHandlersEvent',
  'PacketDistributor',
  'NetworkRegistry',
  'requestMissingRenders(',
  'pendingClientRenders',
])
requireText('FoxCMS catalog is never blocked by render availability', source.neoForgeService, [
  'queue.enqueueCatalog(payload)',
  'queue.flushAsync()',
  'serverFallbackIcons=',
])
rejectText('FoxCMS catalog render gate removed', source.neoForgeService, [
  'catalog delivery postponed',
  'GUI model render(s) are unresolved',
  'pendingModelRenders',
])
requireText('NeoForge configurable advancement language', source.neoForgeLanguage, [
  'AchievementLanguageResolver',
  'loadLocale("en_us")',
  'assets/minecraft/lang/',
  'readClasspath(',
  'readModFile(',
  'StandardCharsets.UTF_8',
])
requireText('NeoForge localization overlays', source.neoForgeLanguage, [
  'readCrossNamespaceOverlays',
  'readAchievementOverrides',
  'achievementTitle',
  'achievementDescription',
  '":advancement/" + id.getPath()',
])
requireText('NeoForge advancement text overrides', source.neoForgeMetadata, [
  'language.achievementTitle(holder.id())',
  'language.achievementDescription(holder.id())',
])
requireText('NeoForge locale configuration', source.neoForgeConfig, [
  'ConfigValue<String> LOCALE',
  '.define("locale", "ru_RU")',
  'FOX_ACHIEVEMENTS_LOCALE',
  'resourceLocale()',
])
requireText('NeoForge component metadata', source.neoForgeMetadata, [
  'DisplayInfo::getTitle',
  'DisplayInfo::getDescription',
  'ComponentContents',
  'TranslatableContents',
  'component.getString()',
])
requireText('NeoForge Brigadier command', source.neoForgeCommand, [
  'CommandDispatcher<CommandSourceStack>',
  'Commands.literal("foxachievements")',
  'Commands.literal("push")',
  'Commands.literal("status")',
  'Commands.literal("sync")',
  'service.synchronizeCatalog()',
])
requireText('NeoForge startup failure isolation', source.neoForgeService, [
  'catch (RuntimeException | LinkageError error)',
  'server startup will continue',
  'return -1',
])
requireText('NeoForge advancement events', source.neoForgeEvents, [
  'AdvancementEvent.AdvancementEarnEvent',
  'PlayerEvent.PlayerLoggedInEvent',
  'RegisterCommandsEvent',
  'service.record(',
  'service.reconcile(',
])
requireText('NeoForge Base64 icon resolution', source.neoForgeIcons + source.neoForgeModelInspector + source.neoForgeServerRenderer, [
  'readOverride(',
  'renderFlatModel(',
  'modelRenderer.render(blockModel)',
  'modelRenderer.render(itemModel)',
  'readClasspath(',
  'readFromMod(',
  'fallbackPng(',
  'Base64.getEncoder().encodeToString(bytes)',
  'image/png',
])
requireText('NeoForge durable queue', source.neoForgeQueue, [
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
requireText('NeoForge stale event pruning', source.neoForgeQueue + source.neoForgeService, [
  'purgeEventsExcept',
  'allowedAchievementKeys',
  'if (!catalog.isPlayerFacing(advancement)) return',
])
requireText('NeoForge clean API routes', source.neoForgeConfig, [
  '/api/game/achievements/catalog/',
  '/api/game/achievements/event/',
])
rejectText('Achievement public routes', source.neoForgeConfig + source.frontendClient, [
  '/api/game/achievements/catalog.php',
  '/api/game/achievements/event.php',
  '/api/game/achievements/player.php',
])
requireText('NeoForge HMAC client', source.neoForgeHttp, [
  'X-Fox-Server',
  'X-Fox-Timestamp',
  'X-Fox-Signature',
  'HmacSHA256',
  'setInstanceFollowRedirects(false)',
  'unexpected Content-Type',
])
requireText('NeoForge reconciliation and idempotency', source.neoForgeService, [
  'player.getAdvancements().getOrStartProgress(advancement)',
  'progress.isDone()',
  'UUID.nameUUIDFromBytes(eventSeed.getBytes(StandardCharsets.UTF_8))',
  'queue.enqueueCatalog(payload)',
  'queue.enqueueEvent(event(player, advancement))',
])

requireText('Achievements controller host', source.achievementsView, [
  'loadPlayerAchievementsByIdentity',
  'loadAchievementStatistics',
  'hasPlayerContext',
  'defineAsyncComponent',
  "() => import('@/achievements/AchievementStatisticsTree.vue')",
  'requestedIdentity.value || currentUuid.value || currentLogin.value',
  "requestedView === 'statistics'",
  "query: { view: 'statistics' }",
  'recentAchievements',
  'categorySummaries',
  'visibleCategorySummaries',
  'categoryIndex',
  'activeCategorySummary',
  'openCategory',
  'closeCategory',
  'isOwnAchievements',
  'useAchievementEconomy',
  'exchangeMyAchievementPoints',
  'canExchangePoints',
  "runtimePageTemplate('achievements')",
  '<RuntimeTpl',
])
rejectText('Achievements controller host', source.achievementsView, [
  'achievements-player-search', 'achievements-metrics', 'achievements-status-tabs', 'achievements-grid',
])
requireText('Achievement economy frontend client', source.economyClient, [
  "user_doaction: 'getAchievementEconomy'",
  "user_doaction: 'exchangeAchievementPoints'",
  'requestUuid: operationUuid()',
  'appBootstrap.user.balance',
])
requireText('Achievement economy composable', source.economyComposable, [
  'loadAchievementEconomy',
  'submitAchievementPointExchange',
  'canExchangePoints',
  'exchangeAllAchievementPoints',
  'exchangeMyAchievementPoints',
  'window.confirm',
  'toValue(enabled)',
])
requireText('Achievements page TPL', source.achievementsTpl, [
  '<fox-page-template id="achievements"',
  'achievements-player-search',
  'achievements-server-context',
  'achievements-metrics',
  'achievements-status-tabs',
  'achievements-grid',
  'achievement-category-grid',
  'achievement-category-card',
  'achievement-category-card__icon',
  'achievement-category-card__progress',
  'achievement-category-card__complete',
  'entry.completionPercent',
  'entry.isCompleted',
  'v-else-if="categoryIndex"',
  '@click="openCategory(entry.id)"',
  'entry.completedCount',
  'entry.totalCount',
  'item.iconDataUrl',
  'v-if="isOwnAchievements"',
  'achievement-economy',
  'exchangePointsInput',
  'exchangePreviewUnits',
  'exchangeMyAchievementPoints',
  '<AchievementStatisticsTree',
])
rejectText('Achievements page category root', source.achievementsTpl, [
  '<select v-model="category"',
])
requireText('Achievement statistics controller host', source.statisticsTree, [
  'loadAchievementStatistics',
  'buildAchievementTrees',
  'AchievementTreeNode',
  'categorySummaries',
  'visibleCategorySummaries',
  'categoryIndex',
  'activeCategorySummary',
  'openCategory',
  'closeCategory',
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
  'achievement-category-grid',
  'achievement-category-card',
  'v-else-if="categoryIndex"',
  '@click="openCategory(entry.id)"',
  'entry.completedCount',
  'entry.totalCount',
  '<AchievementTreeNode',
])
rejectText('Achievement statistics category root', source.statisticsTreeTpl, [
  '<select v-model="category"',
])
requireText('Achievement category tree isolation', source.statisticsTreeModel, [
  'parent.category === node.category',
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

requireText('Achievement admin backend registration', source.adminOptions, [
  "'achievementsAdmin' => 'achievementsAdmin'",
  "'saveAchievementEconomy' => 'saveAchievementEconomy'",
  "'clearAchievementServer' => 'clearAchievementServer'",
  "'clearAchievementPlayer' => 'clearAchievementPlayer'",
  'AdminAchievementController',
])
requireText('Achievement admin MariaDB-safe statistics query', source.adminAchievementController, [
  'FROM `gameAchievements` GROUP BY `serverId`',
  'FROM `playerAchievements` GROUP BY `serverId`',
  'FROM `gameAchievementEvents` GROUP BY `serverId`',
  'SELECT DISTINCT `playerUuid` FROM `playerAchievements` WHERE `serverId` = :serverId',
  "foreach ($this->serverStats() as $row)",
])
rejectText('Achievement admin MariaDB-unsafe derived statistics query', source.adminAchievementController, [
  'LEFT JOIN (',
  'GROUP BY `tracked`.`serverId`',
  'TABLE_NAME IN (',
])

requireText('Achievement admin destructive boundary', source.adminAchievementController, [
  "deleteByServer('gameAchievementEvents', $serverId)",
  "deleteByServer('playerAchievements', $serverId)",
  "deleteByServer('gameAchievements', $serverId)",
  "deletePlayerRows('gameAchievementEvents', $serverId, $playerUuid)",
  "deletePlayerRows('playerAchievements', $serverId, $playerUuid)",
  "'admin.achievements.server_cleared'",
  "'admin.achievements.player_cleared'",
  'transactional(function',
])
rejectText('Achievement admin destructive scope', source.adminAchievementController, [
  'DELETE FROM `users`',
  'DELETE FROM `servers`',
  "deleteByServer('gameAchievementPointAwards'",
  "deleteByServer('gameAchievementPointExchanges'",
  "deletePlayerRows('gameAchievementPointAwards'",
  "deletePlayerRows('gameAchievementPointExchanges'",
])
requireText('Achievement admin client actions', source.adminComposable, [
  "admPanel: 'achievementsAdmin'",
  "admPanel: 'saveAchievementEconomy'",
  "admPanel: 'clearAchievementServer'",
  "admPanel: 'clearAchievementPlayer'",
  'window.confirm',
])
requireText('Achievement admin view', source.adminAchievementsView, [
  "theme.foxengine.admin.achievements.018",
  "theme.foxengine.admin.achievements.024",
  "emit('clearServer')",
  "emit('clearPlayer', player)",
  "emit('saveEconomy'",
  'economyStats.unitsGranted',
  'economyDraft.pointsPerUnit',
  'economyDraft.minimumPoints',
])
requireText('Achievement admin runtime destination', source.adminPanelTpl, [
  'id="achievements" component="Achievements" tab="achievements"',
  "activeTab === 'achievements'",
  '<AdminAchievements',
  ':economy="achievementEconomy"',
  ':economy-stats="achievementEconomyStats"',
  '@save-economy="saveAchievementEconomy"',
])

if (failures.length) {
  console.error('Game achievements contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('Game achievements contract passed: NeoForge 1.21.1 discovers and renders player-facing advancements, FoxCMS persists idempotent unlock/point ledgers, players explicitly convert earned points to Units, and runtime/admin views expose the complete achievement economy.')
