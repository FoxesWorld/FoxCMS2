# Badge pages

The badge catalog is owned by the MySQL `badgesList` table. A full badge page is discovered dynamically from the badge display name.

For every database row the server runs `BadgeSlug::fromName(badgeName, id)` and checks:

```text
templates/<active-theme>/data/badges/<slug>.html
```

Examples:

```text
EarlyUser            -> earlyuser.html            -> /#/badges/earlyuser
Раннее Возрождение   -> rannee-vozrozhdenie.html -> /#/badges/rannee-vozrozhdenie
Подсвинок             -> podsvinok.html            -> /#/badges/podsvinok
LGBTQ+                -> lgbtq.html                -> /#/badges/lgbtq
```

`badgeName` is display text and accepts printable Unicode, including Cyrillic, whitespace, emoji and punctuation. URL restrictions apply only to the generated slug. Symbols are treated as separators and Cyrillic letters are transliterated. If a name produces no Latin or numeric characters, the stable fallback is `badge-<database id>`. Colliding transliterations receive the database ID suffix.

The public `/badges` API does not infer page ownership from `data-badge-name`, an image filename, or manually entered JSON. `pageConfigured` is true only when the exact computed HTML file exists.

Renaming a badge through the admin catalog recalculates its slug, migrates the HTML file when present, and updates badge-name references stored in `users.badges`.
