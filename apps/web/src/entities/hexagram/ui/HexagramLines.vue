<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { HexagramLine } from '../model'

const props = withDefaults(
  defineProps<{
    lines: HexagramLine[]
    /** Opt-in: render each line as a focusable button that emits `select` (SPEC-044). Used only
     * on the hexagram detail page; every other caller leaves this off and gets the plain
     * `role="img"` picture it always was. */
    interactive?: boolean
    selectedPosition?: number | null
  }>(),
  { interactive: false, selectedPosition: null },
)

const emit = defineEmits<{ select: [position: number] }>()

const { t } = useI18n()

// Domain lines are ordered position 1 (bottom) to 6 (top); reverse only for top-to-bottom
// visual stacking. The prop itself is never mutated or reordered for callers.
const topToBottom = computed(() => [...props.lines].reverse())

function onLineClick(position: number): void {
  if (props.interactive) {
    emit('select', position)
  }
}
</script>

<template>
  <div
    class="flex flex-column gap-2"
    :role="interactive ? 'group' : 'img'"
    :aria-label="t('hexagramLines.ariaLabel', { count: lines.length })"
  >
    <component
      :is="interactive ? 'button' : 'div'"
      v-for="line in topToBottom"
      :key="line.position"
      class="hexagram-line flex align-items-center gap-2"
      :class="{ 'hexagram-line-interactive': interactive, 'hexagram-line-selected': interactive && line.position === selectedPosition }"
      :type="interactive ? 'button' : undefined"
      :aria-label="interactive ? t('hexagramLines.lineAriaLabel', { position: line.position }) : undefined"
      :aria-pressed="interactive ? String(line.position === selectedPosition) : undefined"
      :data-position="line.position"
      :data-polarity="line.polarity"
      :data-changing="line.changing ? 'true' : undefined"
      @click="onLineClick(line.position)"
    >
      <span v-if="line.polarity === 'yang'" class="hexagram-line-bar" />
      <template v-else>
        <span class="hexagram-line-bar hexagram-line-bar-broken" />
        <span class="hexagram-line-bar hexagram-line-bar-broken" />
      </template>
      <span v-if="line.changing" class="hexagram-line-changing-dot" :title="t('hexagramLines.changingLine')" />
    </component>
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

.hexagram-line-interactive {
  background: none;
  border: 0;
  padding: 0;
  cursor: pointer;
}

.hexagram-line-selected .hexagram-line-bar {
  background: var(--p-primary-color);
}

.hexagram-line-interactive:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 3px;
  border-radius: 2px;
}
</style>
