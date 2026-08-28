<script setup lang="ts">
import { liveMessage } from '../lib/announce'
</script>

<template>
  <!-- Mounted once in App.vue. `role="status"` + `aria-live="polite"` so transitions are spoken
       without interrupting the user; `aria-atomic` so the whole (short) message is re-read on
       each change. Visually hidden via `.sr-only` (SPEC-039). -->
  <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ liveMessage }}</div>
</template>
