# FoxCMS Game Achievements

Fox Achievements is a server-side Forge 1.7.10 integration that synchronizes the complete achievement catalog and player unlocks with FoxCMS.

## Supported runtime

- Minecraft `1.7.10`
- Forge `10.13.4.1614`
- Java 8 bytecode
- FoxCMS migration `025_game_achievements.sql`

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
```

It creates:

- `gameAchievements` — the active catalog for each server;
- `playerAchievements` — player progress and completion state;
- `gameAchievementEvents` — idempotent delivery history.

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

## Building the Forge mod

Module:

```text
C:\Users\Aiden\Documents\Repos\FoxesCraft\fox-achievements-forge-1.7.10
```

Build:

```bat
cd C:\Users\Aiden\Documents\Repos\FoxesCraft\fox-achievements-forge-1.7.10
gradlew.bat clean build
```

Output:

```text
build/libs/fox-achievements-1.7.10-0.1.4.jar
```

Install the JAR only on the dedicated server. `acceptableRemoteVersions="*"` means players do not need a client-side copy.

## Forge server configuration

Start the server once to generate:

```text
config/foxachievements.cfg
```

Then configure:

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

For production, prefer the process environment instead of storing the secret in the file:

```text
FOX_ACHIEVEMENTS_SECRET=<generated-secret>
```

## Catalog synchronization

The complete catalog is synchronized after the server reaches `FMLServerStartedEvent`.

Manual rescan:

```text
/foxachievements sync
```

Queue status:

```text
/foxachievements status
```

Definitions missing from the latest catalog revision are disabled instead of deleted. Historical player records therefore retain referential integrity while removed mod achievements disappear from the active catalog.

## Icon extraction

Every achievement stored in `gameAchievements` has:

- `iconBase64`;
- `iconMime`;
- `iconItem`;
- `iconComponents` containing damage, display name, stack size and NBT.

PNG resolution order:

1. achievement override under `config/fox-achievements/icons`;
2. item override under the same directory;
3. item or block texture extracted from the owning mod JAR/resource directory;
4. deterministic generated PNG fallback.

Override paths are based on the achievement or item registry identifier. For example:

```text
config/fox-achievements/icons/examplemod/achievement/first_machine-<hash>.png
config/fox-achievements/icons/examplemod/copper_gear.png
```

This guarantees a valid Base64 PNG even when a server has no client renderer or when a mod uses a custom item renderer.

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

## Delivery acknowledgement

Catalog and event queue files are deleted only after FoxCMS returns a JSON acknowledgement with `protocol: fox-achievements-v1`, the expected operation and server ID, and confirmed database persistence fields. Successful catalog logs include the actual FoxCMS database name and persisted row count.


## Catalog locale and icon assets

The Forge configuration property `locale` defaults to `ru_RU`; environment variable `FOX_ACHIEVEMENTS_LOCALE` overrides it. The build bundles Minecraft 1.7.10 item/block textures and `ru_RU.lang` from the local authenticated Minecraft/Forge asset cache, because a dedicated server JAR does not contain client textures or non-English vanilla language files. Installed mod JAR language files are loaded with `en_US` fallback.
