<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  items: { label: string; value: number }[]
  /** Accessible name for the whole chart, e.g. "Hexagram frequency". */
  caption?: string
}>()

const { t } = useI18n()

const max = computed(() => props.items.reduce((m, it) => Math.max(m, it.value), 0))

function widthPercent(value: number): number {
  return max.value === 0 ? 0 : Math.round((value / max.value) * 100)
}
</script>

<template>
  <table class="bar-chart w-full">
    <!-- A real <table> so a screen reader gets the labels and numbers directly; the bar is
         purely visual decoration inside the value cell (SPEC-043). -->
    <caption class="sr-only">{{ caption ?? t('barChart.caption') }}</caption>
    <tbody>
      <tr v-for="item in items" :key="item.label">
        <th scope="row" class="bar-chart-label text-sm font-normal text-left pr-3">{{ item.label }}</th>
        <td class="bar-chart-value">
          <div class="flex align-items-center gap-2">
            <div class="bar-chart-track">
              <div class="bar-chart-fill" :style="{ width: widthPercent(item.value) + '%' }" />
            </div>
            <span class="text-sm text-color-secondary bar-chart-number">{{ item.value }}</span>
          </div>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped>
.bar-chart {
  border-collapse: collapse;
}

.bar-chart th,
.bar-chart td {
  padding: 0.25rem 0;
  vertical-align: middle;
}

.bar-chart-label {
  white-space: nowrap;
  max-width: 12rem;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bar-chart-value {
  width: 100%;
}

.bar-chart-track {
  flex: 1;
  height: 0.75rem;
  border-radius: 999px;
  background: var(--p-content-border-color);
  overflow: hidden;
}

.bar-chart-fill {
  height: 100%;
  border-radius: 999px;
  background: var(--p-primary-color);
}

.bar-chart-number {
  min-width: 1.5rem;
  text-align: right;
}

@media (prefers-reduced-motion: no-preference) {
  .bar-chart-fill {
    transition: width 0.4s ease-out;
  }
}
</style>
