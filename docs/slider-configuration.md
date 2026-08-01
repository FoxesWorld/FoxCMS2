# Slider configuration

The slider source is `templates/<active-theme>/data/slides.json`. `Slider.vue` requests this file directly at runtime with `cache: no-store`; slide content is not embedded in the JavaScript bundle or server bootstrap.

The administrative **Слайды** tab edits global eyebrow/autoplay settings and an ordered list of slides. Saving calls `admPanel=saveSlides`; `ThemeSlidesRepository` validates routes, image references, lengths and IDs, then writes the JSON atomically.

Images may reference a theme asset relative to `assets/`, such as `img/slides/slide1.png`, or an uploaded `/uploads/slides/...` file. Uploads use the centralized `slider.image` policy.

Write failures include the exact attempted temporary file, final `slides.json` path, containing directory, directory permissions, byte count and the last PHP filesystem warning. Atomic replacement failures also report the backup path and whether the previous file was restored. The admin error response includes the same detail together with a correlation `requestId`.
