<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { fetchHexagrams } from '../../entities/hexagram/api'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import type { Hexagram } from '../../entities/hexagram/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagrams: Hexagram[] }

const state = ref<State>({ status: 'loading' })

onMounted(async () => {
  try {
    const hexagrams = await fetchHexagrams()
    state.value = { status: 'loaded', hexagrams }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to load hexagrams.',
    }
  }
})
</script>

<template>
  <main class="mx-auto max-w-5xl px-6 py-10">
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">Hexagram Explorer</h1>

    <p v-if="state.status === 'loading'" class="text-neutral-500">Loading hexagrams…</p>
    <p v-else-if="state.status === 'error'" class="text-red-600">{{ state.message }}</p>

    <ul v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
      <li v-for="hexagram in state.hexagrams" :key="hexagram.kingWenNumber">
        <router-link
          :to="`/hexagrams/${hexagram.kingWenNumber}`"
          class="flex flex-col items-center gap-3 rounded-lg border border-neutral-200 p-4 hover:border-neutral-400"
        >
          <HexagramLines :lines="hexagram.lines" />
          <span class="text-sm text-neutral-500">{{ hexagram.kingWenNumber }}</span>
          <span class="font-medium">{{ hexagram.chineseName }}</span>
        </router-link>
      </li>
    </ul>
  </main>
</template>
