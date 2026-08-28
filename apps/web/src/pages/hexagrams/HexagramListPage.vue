<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import {
  fetchHexagrams,
  markHexagramFavorite,
  unmarkHexagramFavorite,
} from '../../entities/hexagram/api'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import type { Hexagram } from '../../entities/hexagram/model'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'
import LoadingSkeleton from '../../shared/ui/LoadingSkeleton.vue'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagrams: Hexagram[] }

const { t } = useI18n()
const state = ref<State>({ status: 'loading' })
const searchQuery = ref('')
const favoritesOnly = ref(false)

useStatusAnnouncer(computed(() => state.value.status))

onMounted(async () => {
  try {
    const hexagrams = await fetchHexagrams()
    state.value = { status: 'loaded', hexagrams }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('hexagramList.loadError'),
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
  <main id="main" tabindex="-1" class="container-lg mx-auto p-4">
    <div class="mb-4 flex align-items-center justify-content-between">
      <h1 class="text-2xl font-semibold m-0">{{ t('hexagramList.title') }}</h1>
      <div class="flex gap-3">
        <router-link to="/trigrams" class="text-sm">{{ t('trigramExplorer.link') }}</router-link>
        <router-link to="/hexagrams/editor" class="text-sm">{{ t('hexagramList.visualEditor') }}</router-link>
      </div>
    </div>

    <LoadingSkeleton v-if="state.status === 'loading'" :lines="8" />
    <Message v-else-if="state.status === 'error'" severity="error" role="alert">{{ state.message }}</Message>

    <template v-else>
      <div class="mb-4 flex flex-wrap align-items-center gap-3">
        <InputText
          v-model="searchQuery"
          type="search"
          :placeholder="t('hexagramList.searchPlaceholder')"
          class="w-full sm:w-25rem"
        />
        <Button
          :aria-pressed="favoritesOnly"
          :label="t('hexagramList.favoritesOnly')"
          rounded
          size="small"
          :outlined="!favoritesOnly"
          @click="favoritesOnly = !favoritesOnly"
        />
      </div>

      <p v-if="filteredHexagrams.length === 0" class="text-color-secondary">
        {{ t('hexagramList.noMatches') }}
      </p>

      <ul v-else class="grid list-none p-0 m-0">
        <li
          v-for="hexagram in filteredHexagrams"
          :key="hexagram.kingWenNumber"
          class="col-6 sm:col-4 md:col-3 relative"
        >
          <Button
            class="absolute favorite-star"
            text
            rounded
            :aria-label="hexagram.favorite ? t('hexagramList.removeFromFavorites') : t('hexagramList.addToFavorites')"
            @click.stop.prevent="toggleFavorite(hexagram)"
          >
            {{ hexagram.favorite ? '★' : '☆' }}
          </Button>
          <router-link
            :to="`/hexagrams/${hexagram.kingWenNumber}`"
            class="flex flex-column align-items-center gap-2 border-round border-1 surface-border p-3 no-underline text-color hexagram-card"
          >
            <HexagramLines :lines="hexagram.lines" />
            <span class="text-sm text-color-secondary">{{ hexagram.kingWenNumber }}</span>
            <span class="font-medium">{{ hexagram.chineseName }}</span>
          </router-link>
        </li>
      </ul>
    </template>
  </main>
</template>

<style scoped>
.favorite-star {
  top: 0.5rem;
  right: 0.5rem;
  z-index: 1;
}

.hexagram-card:hover {
  border-color: var(--p-primary-color);
}
</style>
