<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// A plain in-page anchor: activating it jumps the viewport, and because every routed page's
// <main> carries tabindex="-1" (SPEC-039, REQ-A11Y-002) the browser also moves keyboard focus
// there. No JS needed for the common case.
</script>

<template>
  <a href="#main" class="sr-only skip-link">{{ t('nav.skipToContent') }}</a>
</template>
