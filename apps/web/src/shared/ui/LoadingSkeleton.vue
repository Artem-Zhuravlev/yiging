<script setup lang="ts">
import Skeleton from 'primevue/skeleton'
import { useI18n } from 'vue-i18n'

withDefaults(defineProps<{ lines?: number }>(), { lines: 4 })

const { t } = useI18n()
</script>

<template>
  <!-- The bars are decoration; the loading state itself is exposed to assistive tech by the
       .sr-only span below (and, on the transition, by SPEC-039's live region). -->
  <div class="loading-skeleton flex flex-column gap-2" aria-hidden="true">
    <Skeleton width="40%" height="1.5rem" />
    <Skeleton v-for="i in lines" :key="i" height="1rem" />
  </div>
  <span class="sr-only">{{ t('common.loading') }}</span>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
  .loading-skeleton :deep(.p-skeleton)::after {
    animation: none !important;
  }
}
</style>
