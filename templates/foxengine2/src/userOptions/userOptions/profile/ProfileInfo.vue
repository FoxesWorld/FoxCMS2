<script setup lang="ts">
import type { ProfileRecord } from '@engine/contracts/user-pages'

defineProps<{ profile: ProfileRecord }>()
</script>

<template>
  <section class="profile-panel profile-info-panel">
    <header class="profile-panel__heading">
      <div>
        <span class="profile-panel__eyebrow">Личное дело</span>
        <h2>О пользователе</h2>
      </div>
    </header>
    <dl class="profile-info-list">
      <div>
        <dt>Отображаемое имя</dt>
        <dd>{{ profile.realname || profile.login }}</dd>
      </div>
      <div>
        <dt>Местоположение</dt>
        <dd>{{ profile.land || 'Не указано' }}</dd>
      </div>
      <div>
        <dt>Логин</dt>
        <dd>@{{ profile.login }}</dd>
      </div>
      <div>
        <dt>Группа</dt>
        <dd>{{ profile.groupName || `Группа ${profile.user_group ?? '—'}` }}</dd>
      </div>
    </dl>
  </section>
</template>
