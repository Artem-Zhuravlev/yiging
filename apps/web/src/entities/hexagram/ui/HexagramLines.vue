<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { HexagramLine } from '../model'

const props = defineProps<{
  lines: HexagramLine[]
}>()

const { t } = useI18n()

// Domain lines are ordered position 1 (bottom) to 6 (top); reverse only for top-to-bottom
// visual stacking. The prop itself is never mutated or reordered for callers.
const topToBottom = computed(() => [...props.lines].reverse())
</script>

<template>
  <div
    class="flex flex-column gap-2"
    role="img"
    :aria-label="t('hexagramLines.ariaLabel', { count: lines.length })"
  >
    <div
      v-for="line in topToBottom"
      :key="line.position"
      class="hexagram-line flex align-items-center gap-2"
      :data-position="line.position"
      :data-polarity="line.polarity"
      :data-changing="line.changing ? 'true' : undefined"
    >
      <span v-if="line.polarity === 'yang'" class="hexagram-line-bar" />
      <template v-else>
        <span class="hexagram-line-bar hexagram-line-bar-broken" />
        <span class="hexagram-line-bar hexagram-line-bar-broken" />
      </template>
      <span v-if="line.changing" class="hexagram-line-changing-dot" :title="t('hexagramLines.changingLine')" />
    </div>
  </div>
</template>

<style scoped>
.hexagram-line {
  height: 0.5rem;
  width: 4rem;
}

.hexagram-line-bar {
  height: 100%;
  width: 100%;
  border-radius: 2px;
  background: var(--p-text-color);
}

.hexagram-line-bar-broken {
  width: 45%;
}

.hexagram-line-changing-dot {
  height: 0.375rem;
  width: 0.375rem;
  flex-shrink: 0;
  border-radius: 50%;
  background: var(--p-primary-color);
}
</style>
