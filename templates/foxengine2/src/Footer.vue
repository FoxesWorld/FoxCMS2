<script setup lang="ts">
import { t } from '@/i18n'

import type { NavigationDefinition } from '@engine/domain/bootstrap'
import type { SocialLinkDefinition } from '@engine/shell/socialLinks'

defineProps<{ siteTitle: string; serviceVersion: string; items: NavigationDefinition[]; socialLinks: SocialLinkDefinition[] }>()
const emit = defineEmits<{ activate: [item: NavigationDefinition] }>()
const currentYear = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer legacy-footer">
    <div class="page-width site-footer__shell">
      <div class="site-footer__inner">
        <div class="legacy-footer__identity">
          <span class="legacy-footer__mark" aria-hidden="true">
            <i class="fa-solid fa-shield-halved" />
          </span>
          <span class="legacy-footer__copy">
            <strong>{{ siteTitle }}</strong>
            <p>{{ t('theme.footer.001') }}{{ currentYear }}</p>
          </span>
        </div>

        <div class="site-footer__links">
          <nav class="footer-nav" :aria-label="t('theme.footer.002')">
            <button v-for="item in items" :key="item.route" type="button" @click="emit('activate', item)">
              <span>{{ item.label }}</span>
            </button>
          </nav>

          <nav v-if="socialLinks.length" class="footer-socials" :aria-label="t('theme.footer.004')">
            <a
              v-for="link in socialLinks"
              :key="link.id"
              class="footer-socials__button"
              :href="link.href"
              target="_blank"
              rel="noopener noreferrer"
            >
              <i :class="link.icon" aria-hidden="true" />
              <span>{{ link.label }}</span>
            </a>
          </nav>
        </div>

        <span class="site-footer__status">
          <i class="fa-solid fa-circle-check" aria-hidden="true" />
          <span>{{ t('theme.footer.003') }} {{ serviceVersion }}</span>
        </span>
      </div>
    </div>
  </footer>
</template>
