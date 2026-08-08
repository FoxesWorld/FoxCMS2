# FoxCMS Game Achievements

Fox Achievements is a server-side Forge 1.7.10 integration that synchronizes the complete achievement catalog and player unlocks with FoxCMS.

## Supported runtime

- Minecraft `1.7.10`
- Forge `10.13.4.1614`
- Java 8 bytecode
- FoxCMS migrations `025_game_achievements.sql` → `026_game_achievement_category_labels.sql` → `027_game_achievement_points_economy.sql`

Minecraft 1.7.10 predates datapack advancements. In this runtime, the complete available catalog means every registered `Achievement` discovered through:

- `AchievementList.achievementList`;
- `StatList.allStats`;
- every registered Forge `AchievementPage`.

This includes vanilla achievements and achievements registered by installed Forge mods.

## Public API routing

FoxCMS uses a single front controller:

```text
FoxCMS2/api/index.php
```

Public URLs never contain `.php`. Directory index entrypoints delegate to the front controller:

```text
POST /api/game/achievements/catalog/
POST /api/game/achievements/event/
GET  /api/game/achievements/player/?uuid=<player-uuid>
```

Direct URLs containing `.php` are rejected by the front controller.

## Data flow

```text
Forge achievement registry
        ↓ full catalog + Base64 PNG icons
FoxCMS /api/game/achievements/catalog/
        ↓
gameAchievements

AchievementEvent / login reconciliation
        ↓ durable local queue
FoxCMS /api/game/achievements/event/
        ↓
playerAchievements + gameAchievementEvents + userNotifications
        ↓
Player profile
```

The Minecraft server never connects directly to MySQL. All writes pass through authenticated FoxCMS APIs.

## Database installation

Apply migrations using the normal deployment process. The relevant migration is:

```text
database/migrations/025_game_achievements.sql
database/migrations/026_game_achievement_category_labels.sql
database/migrations/027_game_achievement_points_economy.sql
database/migrations/028_game_achievement_category_label_cleanup.sql
```

Apply all three migrations in numeric order. `025` creates the base catalog/progress tables, `026` adds localized category labels, and `027` adds the immutable point-award/exchange economy.

It creates:

- `gameAchievements` — the active catalog for each server;
- `playerAchievements` — player progress and completion state;
- `gameAchievementEvents` — idempotent delivery history;
- `gameAchievementPointAwards` — immutable, one-time point awards per server/player/achievement;
- `gameAchievementPointExchanges` — immutable Points → Units exchanges;
- `gameAchievementEconomySettings` — administrator-controlled conversion rate and minimum exchange.

The Minecraft profile UUID is already the canonical `users.uuid` in FoxCMS, so no secondary account-link table is required.

## FoxCMS authentication configuration

Generate a random server secret:

```bash
openssl rand -hex 32
```

Configure FoxCMS `.env`:

```dotenv
FOXESCRAFT_GAME_SERVER_KEYS_JSON='{"survival-eu-1":"<generated-secret>"}'
FOXESCRAFT_GAME_HMAC_TOLERANCE_SECONDS=300
```

Every server must have a unique `serverId` and secret. Requests use:

```text
X-Fox-Server
X-Fox-Timestamp
X-Fox-Signature
```

The signature is HMAC-SHA256 over:

```text
timestamp\nHTTP_METHOD\nREQUEST_PATH\nSHA256(body)
```

## Versioned mod projects

All Minecraft implementations live under:

```text
C:\Users\Aiden\Documents\Repos\FoxesCraft\fox-achievements
```

The current projects are:

```text
fox-achievements/
├─ forge-1.7.10/
└─ neoforge-1.21.1/
```

`fox-achievements/versions.json` is the machine-readable version catalog.

### Forge 1.7.10

Requires a **JDK 8** installation.

```bat
cd C:\Users\Aiden\Documents\Repos\FoxesCraft\fox-achievements\forge-1.7.10
gradlew.bat clean build
```

Output:

```text
build/libs/fox-achievements-1.7.10-0.1.4.jar
```

The 1.7.10 implementation is server-side only (`acceptableRemoteVersions="*"`). Its configuration is generated at:

```text
config/foxachievements.cfg
```

Example:

```text
general {
    B:enabled=true
    S:serverId=survival-eu-1
    S:baseUrl=https://foxescraft.ru
    S:secret=<same secret configured in FoxCMS>
    I:connectTimeoutSeconds=5
    I:requestTimeoutSeconds=20
    I:queuePollSeconds=5
}
```

### NeoForge 1.21.1

Requires **Java/JDK 21**.

```bat
cd C:\Users\Aiden\Documents\Repos\FoxesCraft\fox-achievements\neoforge-1.21.1
gradlew.bat clean build
```

Output:

```text
build/libs/fox-achievements-neoforge-1.21.1-0.2.9.jar
```

Its configuration is generated at:

```text
config/foxachievements-common.toml
```

Both implementations accept `FOX_ACHIEVEMENTS_SECRET`; NeoForge also supports `FOX_ACHIEVEMENTS_LOCALE`.

## Catalog synchronization

The complete catalog is synchronized after the corresponding server-start lifecycle event for the selected loader.

Manual rescan:

```text
/foxachievements sync
```

Queue status:

```text
/foxachievements status
/foxachievements push
```

Definitions missing from the latest catalog revision are disabled instead of deleted. Historical player records therefore retain referential integrity while removed mod achievements disappear from the active catalog.

## Icon extraction

Every achievement stored in `gameAchievements` has:

- `iconBase64`;
- `iconMime`;
- `iconItem`;
- `iconComponents` containing damage, display name, stack size and NBT.

PNG resolution order on NeoForge 1.21.1:

1. achievement/item override under `config/fox-achievements/icons`;
2. inherited flat item model layers for genuinely 2D GUI items;
3. dedicated-server software rendering of vanilla-style JSON `elements[]` / block models with GUI transforms;
4. deterministic server-side fallback for `builtin/entity` or custom Java model loaders that cannot exist on a dedicated server.

Fox Achievements is **server-only**. It does not register client payloads, does not require installation on players, and catalog delivery is never blocked waiting for a client-side renderer. Complex custom-rendered items are represented by a server fallback or an explicit server override; raw UV/model texture sheets are never sent as the achievement icon.

Override paths are based on the achievement or item registry identifier. For example:

```text
config/fox-achievements/icons/examplemod/achievement/first_machine-<hash>.png
config/fox-achievements/icons/examplemod/copper_gear.png
```

For item icons, flat `item/generated`/`item/handheld` models keep their composed texture layers. When the same registry ID exposes a real volumetric block model, Fox Achievements resolves blockstate variants and the full block-model parent chain and renders that geometry server-side instead of uploading the flat inventory sprite. Zero-thickness cross/sprite geometry remains flat. Stateful vanilla block models are supported by bundling the matching `assets/minecraft/blockstates` resources into the server mod.

This guarantees a valid Base64 PNG even when a server has no client renderer or when a mod uses a custom item renderer.



## Localization overlays and literal advancement text

The server-side language resolver loads each mod's own language namespace first and then applies cross-namespace overlays from resource-only localization mods. This allows Azurine Russian Localization to override `assets/<foreign_namespace>/lang/ru_ru.json` without modifying third-party JARs.

Mods that embed literal display text directly in advancement JSON cannot be localized through ordinary Minecraft translation keys. Fox Achievements therefore supports an optional resource owned by a localization mod:

```text
assets/<localization_mod_id>/fox-achievements/advancements/<locale>.json
```

The JSON object is keyed by the FoxCMS `achievementKey` (`namespace:advancement/path`) and may provide `title` and `description`. Matching overrides take precedence for that locale; normal translated components remain the default path.

## Player-facing advancement filter

Fox Achievements exports only advancements that define Minecraft `display` metadata. Internal recipe unlock records (`*/recipes/*`) and other bookkeeping advancements have no display definition, are not achievements shown to players, and are excluded from both the catalog and unlock-event delivery. During catalog synchronization, durable queued events whose keys are no longer present in the player-facing catalog are pruned so legacy recipe events cannot block the delivery queue after an upgrade.

## Delivery reliability

Before network delivery, payloads are written to:

```text
config/fox-achievements/queue/catalog.json
config/fox-achievements/queue/events/*.json
```

A dedicated daemon thread sends them to FoxCMS. The Minecraft tick thread never waits for HTTP.

Completed achievements use deterministic event UUIDs based on server, player and achievement. Login reconciliation can therefore resend already unlocked achievements without creating duplicate database events.

## Public profile API

```text
GET /api/game/achievements/player/?uuid=<player-uuid>
```

The response includes the complete enabled catalog for the selected player:

- completed achievements;
- uncompleted visible achievements with zero progress;
- Base64 data URLs for icons;
- points and completion summary;
- hidden achievements only after completion.

The FoxEngine profile page consumes this endpoint through `ProfileAchievements.vue`.

## Achievement points economy

Completing an achievement does **not** credit Units automatically. FoxCMS records the achievement's point value once in `gameAchievementPointAwards`; the player's achievement score remains visible independently of whether those points are ever converted.

The player can explicitly convert any valid portion of the unspent award balance from **their own Achievements page**. The default rate is `10 points = 1 Unit`, but administrators can change the rate, minimum exchange amount, or temporarily disable exchanges from the Achievements admin destination.

The user flow is:

```text
new achievement
→ notification in FoxCMS (+N points)
→ points become available to exchange
→ player opens personal achievements
→ player chooses an amount
→ player presses “Exchange”
→ transaction atomically records the exchange and increments users.balance / Units
```

Point awards are idempotent on `(serverId, playerUuid, achievementKey)`. Exchange requests are idempotent on `requestUuid`, use the authenticated session UUID rather than any client-supplied player UUID, require CSRF validation, lock the user balance during the transaction, and update Units through the canonical `BalanceMatrix`.

Existing completed achievements are backfilled by migration 027, so installing the economy does not discard points that players earned before the feature existed. Technical recipe advancements are excluded from the backfill.

Achievement notifications link directly to the stable UUID-based personal achievements route. Viewing another player's achievements or global statistics never exposes the exchange controls.

## Administrative maintenance

FoxCMS exposes a dedicated **Achievements** destination in the administrator panel. It shows per-server counts for catalog definitions, player progress, tracked players and ingestion events, plus a searchable list of players for the selected `serverId`.

Two destructive operations are available:

- **Clear server** removes `gameAchievementEvents`, `playerAchievements` and `gameAchievements` for the selected achievement `serverId`. It does not remove the configured FoxCMS server record.
- **Clear player** removes `gameAchievementEvents` and `playerAchievements` for one player on the selected server. It does not remove the FoxCMS user account or the server catalog.

Neither operation deletes `gameAchievementPointAwards` or `gameAchievementPointExchanges`. Economic history is deliberately immutable so clearing/reconciling gameplay progress cannot be used to earn or exchange the same points twice.

Both operations are protected by the normal AdminPanel administrator/CSRF boundary, execute transactionally and write warning-level audit events. The UI displays the affected row counts before confirmation.

A running Fox Achievements instance can repopulate cleared data. A later catalog `push` restores the server catalog, while player login reconciliation or later achievement events can restore player progress. Stop or disable delivery on the game server first when the intended reset must remain empty.

## Delivery acknowledgement

Catalog and event queue files are deleted only after FoxCMS returns a JSON acknowledgement with `protocol: fox-achievements-v1`, the expected operation and server ID, and confirmed database persistence fields. Successful catalog logs include the actual FoxCMS database name and persisted row count.


## Catalog locale and icon assets

The Forge configuration property `locale` defaults to `ru_RU`; environment variable `FOX_ACHIEVEMENTS_LOCALE` overrides it. The build bundles Minecraft 1.7.10 item/block textures and `ru_RU.lang` from the local authenticated Minecraft/Forge asset cache, because a dedicated server JAR does not contain client textures or non-English vanilla language files. Installed mod JAR language files are loaded with `en_US` fallback.
