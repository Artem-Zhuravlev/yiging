<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Panel from 'primevue/panel'
import ProgressBar from 'primevue/progressbar'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { fetchStatistics } from '../../entities/statistics/api'
import type { Statistics } from '../../entities/statistics/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; statistics: Statistics }

const { t } = useI18n()
const state = ref<State>({ status: 'loading' })

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

const yinPercent = computed(() => {
  if (state.value.status !== 'loaded') return 0
  const { yin, yang } = state.value.statistics.yinYangRatio
  const total = yin + yang
  return total === 0 ? 0 : Math.round((yin / total) * 100)
})

const yangPercent = computed(() => {
  if (state.value.status !== 'loaded') return 0
  return 100 - yinPercent.value
})
</script>

<template>
  <main class="max-w-screen-sm mx-auto p-4">
    <h1 class="text-2xl font-semibold mb-4">{{ t('statistics.title') }}</h1>

    <p v-if="state.status === 'loading'" class="text-color-secondary">{{ t('common.loading') }}</p>
    <Message v-else-if="state.status === 'error'" severity="error">{{ state.message }}</Message>

    <p v-else-if="state.statistics.totalConsultations === 0" class="text-color-secondary">
      {{ t('statistics.empty') }}
    </p>

    <div v-else class="flex flex-column gap-4">
      <p class="text-color-secondary m-0">
        {{ t('statistics.consultationsCount', { count: state.statistics.totalConsultations }) }}
      </p>

      <Panel :header="t('statistics.hexagramFrequency')">
        <ul class="flex flex-column gap-2 list-none p-0 m-0">
          <li
            v-for="entry in state.statistics.hexagramFrequency"
            :key="entry.kingWenNumber"
            class="flex justify-content-between text-sm"
          >
            <span>{{ entry.kingWenNumber }}. {{ entry.chineseName }} ({{ entry.pinyin }})</span>
            <Tag :value="String(entry.count)" severity="secondary" />
          </li>
        </ul>
      </Panel>

      <Panel :header="t('statistics.yinYangRatio')">
        <p class="text-sm mt-0">
          {{
            t('statistics.yinYangLine', {
              yin: state.statistics.yinYangRatio.yin,
              yang: state.statistics.yinYangRatio.yang,
              yinPercent,
              yangPercent,
            })
          }}
        </p>
        <ProgressBar :value="yinPercent" :show-value="false" />
      </Panel>

      <Panel v-if="state.statistics.tagFrequency.length > 0" :header="t('statistics.tagFrequency')">
        <ul class="flex flex-column gap-2 list-none p-0 m-0">
          <li
            v-for="entry in state.statistics.tagFrequency"
            :key="entry.name"
            class="flex justify-content-between text-sm"
          >
            <span>{{ entry.name }}</span>
            <Tag :value="String(entry.count)" severity="secondary" />
          </li>
        </ul>
      </Panel>
    </div>
  </main>
</template>
