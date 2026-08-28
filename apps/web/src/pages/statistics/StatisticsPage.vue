<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Panel from 'primevue/panel'
import Message from 'primevue/message'
import { fetchStatistics } from '../../entities/statistics/api'
import type { Statistics } from '../../entities/statistics/model'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'
import BarChart from '../../shared/ui/BarChart.vue'
import DonutChart from '../../shared/ui/DonutChart.vue'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; statistics: Statistics }

const HEXAGRAM_ROWS = 12

const { t } = useI18n()
const state = ref<State>({ status: 'loading' })

useStatusAnnouncer(computed(() => state.value.status))

onMounted(async () => {
  try {
    const statistics = await fetchStatistics()
    state.value = { status: 'loaded', statistics }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('statistics.loadError'),
    }
  }
})

const hexagramBars = computed(() => {
  if (state.value.status !== 'loaded') return []
  return state.value.statistics.hexagramFrequency
    .slice(0, HEXAGRAM_ROWS)
    .map((h) => ({ label: `${h.kingWenNumber}. ${h.chineseName}`, value: h.count }))
})

const hexagramOverflow = computed(() => {
  if (state.value.status !== 'loaded') return 0
  return Math.max(0, state.value.statistics.hexagramFrequency.length - HEXAGRAM_ROWS)
})

const tagBars = computed(() => {
  if (state.value.status !== 'loaded') return []
  return state.value.statistics.tagFrequency.map((tg) => ({ label: tg.name, value: tg.count }))
})

const yinPercent = computed(() => {
  if (state.value.status !== 'loaded') return 0
  const { yin, yang } = state.value.statistics.yinYangRatio
  const total = yin + yang
  return total === 0 ? 0 : Math.round((yin / total) * 100)
})

const yangPercent = computed(() => 100 - yinPercent.value)

const yinYangSegments = computed(() => {
  if (state.value.status !== 'loaded') return []
  const { yin, yang } = state.value.statistics.yinYangRatio
  return [
    { label: t('common.yin'), value: yin },
    { label: t('common.yang'), value: yang },
  ]
})

const yinYangCaption = computed(() => {
  if (state.value.status !== 'loaded') return ''
  return t('statistics.yinYangLine', {
    yin: state.value.statistics.yinYangRatio.yin,
    yang: state.value.statistics.yinYangRatio.yang,
    yinPercent: yinPercent.value,
    yangPercent: yangPercent.value,
  })
})
</script>

<template>
  <main id="main" tabindex="-1" class="container-sm mx-auto p-4">
    <h1 class="text-2xl font-semibold mb-4">{{ t('statistics.title') }}</h1>

    <p v-if="state.status === 'loading'" class="text-color-secondary">{{ t('common.loading') }}</p>
    <Message v-else-if="state.status === 'error'" severity="error" role="alert">{{ state.message }}</Message>

    <p v-else-if="state.statistics.totalConsultations === 0" class="text-color-secondary">
      {{ t('statistics.empty') }}
    </p>

    <div v-else class="flex flex-column gap-4">
      <p class="text-color-secondary m-0">
        {{ t('statistics.consultationsCount', { count: state.statistics.totalConsultations }) }}
      </p>

      <Panel :header="t('statistics.hexagramFrequency')">
        <BarChart :items="hexagramBars" :caption="t('statistics.hexagramFrequency')" />
        <p v-if="hexagramOverflow > 0" class="mt-2 mb-0 text-sm text-color-secondary">
          {{ t('statistics.andMore', { count: hexagramOverflow }) }}
        </p>
      </Panel>

      <Panel :header="t('statistics.yinYangRatio')">
        <DonutChart :segments="yinYangSegments" :caption="yinYangCaption" />
      </Panel>

      <Panel v-if="tagBars.length > 0" :header="t('statistics.tagFrequency')">
        <BarChart :items="tagBars" :caption="t('statistics.tagFrequency')" />
      </Panel>
    </div>
  </main>
</template>
