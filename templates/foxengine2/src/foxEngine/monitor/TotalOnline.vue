<script setup lang="ts">defineProps<{ online:number; max:number }>()</script><template><div class="monitor-total"><span>Общий онлайн</span><strong>{{ online }} / {{ max }}</strong></div></template>
