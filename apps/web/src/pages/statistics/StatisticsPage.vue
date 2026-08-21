<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { fetchStatistics } from '../../entities/statistics/api'
import type { Statistics } from '../../entities/statistics/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; statistics: Statistics }

const state = ref<State>({ status: 'loading' })

onMounted(async () => {
  try {
    const statistics = await fetchStatistics()
    state.value = { status: 'loaded', statistics }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to load statistics.',
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
  <main class="mx-auto max-w-2xl px-6 py-10">
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">Statistics</h1>

    <p v-if="state.status === 'loading'" class="text-neutral-500">Loading…</p>
    <p v-else-if="state.status === 'error'" class="text-red-600">{{ state.message }}</p>

    <p v-else-if="state.statistics.totalConsultations === 0" class="text-neutral-500">
      No consultations yet — nothing to show statistics for.
    </p>

    <div v-else class="flex flex-col gap-8">
      <p class="text-neutral-500">{{ state.statistics.totalConsultations }} consultations</p>

      <div>
        <h2 class="mb-3 text-sm font-medium text-neutral-500">Hexagram frequency</h2>
        <ul class="flex flex-col gap-1">
          <li
            v-for="entry in state.statistics.hexagramFrequency"
            :key="entry.kingWenNumber"
            class="flex justify-between text-sm"
          >
            <span>{{ entry.kingWenNumber }}. {{ entry.chineseName }} ({{ entry.pinyin }})</span>
            <span class="text-neutral-500">{{ entry.count }}</span>
          </li>
        </ul>
      </div>

      <div>
        <h2 class="mb-3 text-sm font-medium text-neutral-500">Yin / Yang ratio</h2>
        <p class="text-sm">
          {{ state.statistics.yinYangRatio.yin }} yin / {{ state.statistics.yinYangRatio.yang }} yang
          ({{ yinPercent }}% / {{ yangPercent }}%)
        </p>
      </div>

      <div v-if="state.statistics.tagFrequency.length > 0">
        <h2 class="mb-3 text-sm font-medium text-neutral-500">Tag frequency</h2>
        <ul class="flex flex-col gap-1">
          <li
            v-for="entry in state.statistics.tagFrequency"
            :key="entry.name"
            class="flex justify-between text-sm"
          >
            <span>{{ entry.name }}</span>
            <span class="text-neutral-500">{{ entry.count }}</span>
          </li>
        </ul>
      </div>
    </div>
  </main>
</template>
