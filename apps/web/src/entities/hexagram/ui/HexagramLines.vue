<script setup lang="ts">
import { computed } from 'vue'
import type { HexagramLine } from '../model'

const props = defineProps<{
  lines: HexagramLine[]
}>()

// Domain lines are ordered position 1 (bottom) to 6 (top); reverse only for top-to-bottom
// visual stacking. The prop itself is never mutated or reordered for callers.
const topToBottom = computed(() => [...props.lines].reverse())
</script>

<template>
  <div class="flex flex-col gap-2" role="img" :aria-label="`Hexagram with ${lines.length} lines`">
    <div
      v-for="line in topToBottom"
      :key="line.position"
      class="flex h-2 w-16 items-center gap-2"
      :data-position="line.position"
      :data-polarity="line.polarity"
      :data-changing="line.changing ? 'true' : undefined"
    >
      <span v-if="line.polarity === 'yang'" class="h-full w-full rounded-sm bg-neutral-800" />
      <template v-else>
        <span class="h-full w-[45%] rounded-sm bg-neutral-800" />
        <span class="h-full w-[45%] rounded-sm bg-neutral-800" />
      </template>
      <span
        v-if="line.changing"
        class="h-1.5 w-1.5 shrink-0 rounded-full bg-red-500"
        title="Changing line"
      />
    </div>
  </div>
</template>
