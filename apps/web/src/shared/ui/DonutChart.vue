<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  segments: { label: string; value: number }[]
  /** Text shown in the middle / below the ring (e.g. the count + percent line). */
  caption?: string
}>()

const RADIUS = 16
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

const total = computed(() => props.segments.reduce((s, seg) => s + seg.value, 0))

interface Arc {
  label: string
  fraction: number
  percent: number
  dash: number
  offset: number
  primary: boolean
}

const arcs = computed<Arc[]>(() => {
  let acc = 0
  return props.segments.map((seg, i) => {
    const fraction = total.value === 0 ? 0 : seg.value / total.value
    const arc: Arc = {
      label: seg.label,
      fraction,
      percent: Math.round(fraction * 100),
      dash: fraction * CIRCUMFERENCE,
      offset: -acc * CIRCUMFERENCE,
      primary: i === 0,
    }
    acc += fraction
    return arc
  })
})

const ariaLabel = computed(() => arcs.value.map((a) => `${a.percent}% ${a.label}`).join(', '))
</script>

<template>
  <figure class="donut-chart m-0 flex align-items-center gap-3">
    <svg viewBox="0 0 40 40" class="donut-chart-svg" role="img" :aria-label="ariaLabel">
      <circle
        cx="20"
        cy="20"
        :r="RADIUS"
        fill="none"
        class="donut-chart-track"
        stroke-width="6"
      />
      <circle
        v-for="arc in arcs"
        :key="arc.label"
        cx="20"
        cy="20"
        :r="RADIUS"
        fill="none"
        stroke-width="6"
        :class="arc.primary ? 'donut-chart-primary' : 'donut-chart-secondary'"
        :stroke-dasharray="`${arc.dash} ${CIRCUMFERENCE - arc.dash}`"
        :stroke-dashoffset="arc.offset"
        transform="rotate(-90 20 20)"
      />
    </svg>
    <figcaption v-if="caption" class="text-sm text-color-secondary">{{ caption }}</figcaption>
  </figure>
</template>

<style scoped>
.donut-chart-svg {
  width: 6rem;
  height: 6rem;
  flex-shrink: 0;
}

.donut-chart-track {
  stroke: var(--p-content-border-color);
}

.donut-chart-primary {
  stroke: var(--p-primary-color);
}

.donut-chart-secondary {
  stroke: var(--p-text-color-secondary, var(--p-content-border-color));
  opacity: 0.55;
}

@media (prefers-reduced-motion: no-preference) {
  .donut-chart-primary,
  .donut-chart-secondary {
    transition: stroke-dasharray 0.4s ease-out;
  }
}
</style>
