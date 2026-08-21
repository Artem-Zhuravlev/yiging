<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  fetchHexagrams,
  markHexagramFavorite,
  unmarkHexagramFavorite,
} from '../../entities/hexagram/api'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import type { Hexagram } from '../../entities/hexagram/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagrams: Hexagram[] }

const state = ref<State>({ status: 'loading' })
const searchQuery = ref('')
const favoritesOnly = ref(false)

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

  return state.value.hexagrams
    .filter((h) => !favoritesOnly.value || h.favorite)
    .filter((h) => query === '' || matchesSearch(h, query))
})

async function toggleFavorite(hexagram: Hexagram): Promise<void> {
  if (state.value.status !== 'loaded') return

  try {
    if (hexagram.favorite) {
      await unmarkHexagramFavorite(hexagram.kingWenNumber)
    } else {
      await markHexagramFavorite(hexagram.kingWenNumber)
    }
    hexagram.favorite = !hexagram.favorite
  } catch {
    // Toggling a favorite is a low-stakes action here; a failed request simply leaves the
    // star showing its last-known (still-correct) state rather than needing its own error UI.
  }
}
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
      <div class="mb-6 flex flex-wrap items-center gap-3">
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Search name, pinyin, Judgment, Image…"
          class="w-full max-w-sm rounded-md border border-neutral-300 p-2 text-sm"
        />
        <button
          type="button"
          :aria-pressed="favoritesOnly"
          class="rounded-full border px-3 py-1 text-sm"
          :class="
            favoritesOnly
              ? 'border-neutral-900 bg-neutral-900 text-white'
              : 'border-neutral-300 text-neutral-600 hover:border-neutral-400'
          "
          @click="favoritesOnly = !favoritesOnly"
        >
          ★ Favorites only
        </button>
      </div>

      <p v-if="filteredHexagrams.length === 0" class="text-neutral-500">No hexagrams match your search.</p>

      <ul v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
        <li v-for="hexagram in filteredHexagrams" :key="hexagram.kingWenNumber" class="relative">
          <button
            type="button"
            class="absolute top-2 right-2 text-lg leading-none"
            :aria-label="hexagram.favorite ? 'Remove from favorites' : 'Add to favorites'"
            @click.stop.prevent="toggleFavorite(hexagram)"
          >
            {{ hexagram.favorite ? '★' : '☆' }}
          </button>
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
