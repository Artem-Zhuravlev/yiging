<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { fetchHexagrams } from '../../entities/hexagram/api'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import type { Hexagram } from '../../entities/hexagram/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagrams: Hexagram[] }

const state = ref<State>({ status: 'loading' })
const searchQuery = ref('')

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

function matchesSearch(hexagram: Hexagram, query: string): boolean {
  return (
    hexagram.chineseName.toLowerCase().includes(query) ||
    hexagram.pinyin.toLowerCase().includes(query) ||
    (hexagram.judgment?.toLowerCase().includes(query) ?? false) ||
    (hexagram.image?.toLowerCase().includes(query) ?? false)
  )
}

const filteredHexagrams = computed<Hexagram[]>(() => {
  if (state.value.status !== 'loaded') return []

  const query = searchQuery.value.trim().toLowerCase()
  if (query === '') return state.value.hexagrams

  return state.value.hexagrams.filter((h) => matchesSearch(h, query))
})
</script>

<template>
  <main class="mx-auto max-w-5xl px-6 py-10">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Hexagram Explorer</h1>
      <router-link to="/hexagrams/editor" class="text-sm underline hover:no-underline">
        Visual Editor
      </router-link>
    </div>

    <p v-if="state.status === 'loading'" class="text-neutral-500">Loading hexagrams…</p>
    <p v-else-if="state.status === 'error'" class="text-red-600">{{ state.message }}</p>

    <template v-else>
      <input
        v-model="searchQuery"
        type="search"
        placeholder="Search name, pinyin, Judgment, Image…"
        class="mb-6 w-full max-w-sm rounded-md border border-neutral-300 p-2 text-sm"
      />

      <p v-if="filteredHexagrams.length === 0" class="text-neutral-500">No hexagrams match your search.</p>

      <ul v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
        <li v-for="hexagram in filteredHexagrams" :key="hexagram.kingWenNumber">
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
    </template>
  </main>
</template>
