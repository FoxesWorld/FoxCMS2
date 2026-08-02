# Upload module

All HTTP file uploads must pass through `engine/classes/uploads/UploadService.class.php`.
Direct use of `$_FILES`, `is_uploaded_file`, `move_uploaded_file`, or local `UPLOAD_ERR_*` handling outside this service is prohibited by `npm run check:uploads`.

## Purposes

| Purpose | Destination | Authorization | Validation |
| --- | --- | --- | --- |
| `minecraft.skin` | `uploads/users/<uuid>/<uuid>-skin.png` | owner, admin, or `upload.minecraft.any` | PNG, ≤2 MiB, Minecraft skin dimensions |
| `minecraft.cape` | `uploads/users/<uuid>/<uuid>-cape.png` | owner, admin, or `upload.minecraft.any` | PNG, ≤2 MiB, Minecraft cape dimensions |
| `profile.photo` | `uploads/users/<uuid>/profile-photo-<random>.<ext>` | owner, admin, or `upload.profile.any` | JPEG/PNG/WebP/GIF, ≤5 MiB, dimension and pixel limits |
| `news.cover` | `uploads/news/news-<random>.<ext>` | admin or `upload.news.cover` | JPEG/PNG/WebP/GIF/AVIF, ≤8 MiB, dimension and pixel limits |
| `slider.image` | `uploads/slides/slide-<random>.<ext>` | admin or `upload.slider.image` | JPEG/PNG/WebP/GIF/AVIF, ≤12 MiB, dimension and pixel limits |
| `server.image` | `uploads/servers/server-<random>.<ext>` | admin or `upload.server.image` | JPEG/PNG/WebP, ≤12 MiB, 320×180–8192×8192, pixel limit |
| `admin.file` | selected directory inside `uploads` | admin or `upload.admin.files` | ≤64 MiB, safe filename, blocked web/server-active extensions and MIME types |

## Security contract

The service validates POST and CSRF, actor identity, ownership or granular permission, destination namespace, normalized path segments, symbolic-link traversal, real MIME type, actual versus reported size, image dimensions, pixel count, SHA-256 integrity, portable filenames, and atomic publication through a staging file.

## Audit log

Every attempt is written to the system `lastlog` through `Logger`:

- `Upload accepted.` — purpose, actor, owner, MIME, size, target and SHA-256 prefix;
- `Upload rejected.` — purpose, actor, requested destination, status and rejection reason;
- `Upload failed unexpectedly.` — purpose, actor and exception class.

Domain commits that happen after file publication, such as updating `users.profilePhoto`, log a separate error if the database operation fails.


## Server image workflow

The administrative server editor uploads through `admPanel=uploadServerImage`. A successful upload immediately writes the returned `/uploads/servers/...` path into `servers.serverImage` and renders the same URL in a 16:9 preview. A temporary `blob:` preview is used while the HTTP upload is in progress and is revoked after success, failure, replacement, or component disposal.

At server-save time, local `/uploads/servers/...` references are revalidated through `UploadService::validateReference`. The public server page and the legacy `sysRequest=serverImage` endpoint support both the new uploads namespace and existing theme image filenames.


## Shared image upload form

Regular image uploads use the single Vue component:

```text
engine/client/components/ImageUploadField.vue
```

Its shared contract includes file-picker and drag-and-drop input, local MIME/size/dimension validation, temporary `blob:` preview with URL cleanup, wide/square/circle preview modes, selected-file metadata, upload/error/disabled states, replacement and clearing. Domain components provide only policy values and the upload action.

The shared form is used for:

- user profile photos in the crop dialog and profile appearance settings;
- server cover images;
- news covers;
- slider images.

Minecraft skin and cloak inputs remain specialized because they use a separate texture contract and launcher API.
