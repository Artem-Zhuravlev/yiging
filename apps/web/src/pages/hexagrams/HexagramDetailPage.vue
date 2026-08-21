<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import {
  fetchHexagram,
  markHexagramFavorite,
  unmarkHexagramFavorite,
} from '../../entities/hexagram/api'
import { ApiError } from '../../shared/api/http'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import type { Hexagram, HexagramSummary } from '../../entities/hexagram/model'

const NOT_AVAILABLE = 'Not yet available.'

type State =
  | { status: 'loading' }
  | { status: 'not-found' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagram: Hexagram }

type FavoriteFormState = { status: 'idle' } | { status: 'submitting' } | { status: 'error'; message: string }

interface RelatedHexagram {
  label: string
  summary: HexagramSummary
  isSelf: boolean
}

const route = useRoute()
const state = ref<State>({ status: 'loading' })
const kingWenNumber = computed(() => Number(route.params.number))
const favoriteFormState = ref<FavoriteFormState>({ status: 'idle' })

async function toggleFavorite(): Promise<void> {
  if (state.value.status !== 'loaded' || favoriteFormState.value.status === 'submitting') {
    return
  }

  const loaded = state.value
  favoriteFormState.value = { status: 'submitting' }

  try {
    if (loaded.hexagram.favorite) {
      await unmarkHexagramFavorite(loaded.hexagram.kingWenNumber)
    } else {
      await markHexagramFavorite(loaded.hexagram.kingWenNumber)
    }
    state.value = { status: 'loaded', hexagram: { ...loaded.hexagram, favorite: !loaded.hexagram.favorite } }
    favoriteFormState.value = { status: 'idle' }
  } catch (error) {
    favoriteFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to update favorite.',
    }
  }
}

const relatedHexagrams = computed<RelatedHexagram[]>(() => {
  if (state.value.status !== 'loaded') {
    return []
  }
  const { kingWenNumber: current, relationships } = state.value.hexagram

  return [
    { label: 'Nuclear', summary: relationships.nuclear },
    { label: 'Reversed', summary: relationships.reversed },
    { label: 'Complement', summary: relationships.complement },
  ].map((entry) => ({ ...entry, isSelf: entry.summary.kingWenNumber === current }))
})

watch(
  kingWenNumber,
  async (number) => {
    state.value = { status: 'loading' }
    try {
      const hexagram = await fetchHexagram(number)
      state.value = { status: 'loaded', hexagram }
    } catch (error) {
      if (error instanceof ApiError && error.status === 404) {
        state.value = { status: 'not-found' }
        return
      }
      state.value = {
        status: 'error',
        message: error instanceof Error ? error.message : 'Failed to load hexagram.',
      }
    }
  },
  { immediate: true },
)
</script>

<template>
  <main class="mx-auto max-w-2xl px-6 py-10">
    <router-link to="/hexagrams" class="text-sm text-neutral-500 hover:underline">
      &larr; Hexagrams
    </router-link>

    <p v-if="state.status === 'loading'" class="mt-6 text-neutral-500">Loading…</p>
    <p v-else-if="state.status === 'not-found'" class="mt-6 text-neutral-600">
      Hexagram not found.
    </p>
    <p v-else-if="state.status === 'error'" class="mt-6 text-red-600">{{ state.message }}</p>

    <div v-else class="mt-6 flex flex-col gap-6">
      <div class="flex items-start justify-between gap-6">
        <div class="flex items-center gap-6">
          <HexagramLines :lines="state.hexagram.lines" />
          <div>
            <h1 class="text-2xl font-semibold tracking-tight">
              <span class="mr-1 text-3xl" aria-hidden="true">{{ state.hexagram.symbol }}</span>
              {{ state.hexagram.kingWenNumber }}. {{ state.hexagram.chineseName }}
            </h1>
            <p class="text-neutral-500">{{ state.hexagram.pinyin }}</p>
          </div>
        </div>
        <div class="shrink-0">
          <button
            type="button"
            :disabled="favoriteFormState.status === 'submitting'"
            class="text-sm text-neutral-500 hover:text-neutral-900 disabled:opacity-50"
            @click="toggleFavorite"
          >
            {{ state.hexagram.favorite ? '★ Favorited' : '☆ Add to Favorites' }}
          </button>
          <p v-if="favoriteFormState.status === 'error'" class="mt-1 text-sm text-red-600">
            {{ favoriteFormState.message }}
          </p>
        </div>
      </div>

      <dl class="grid grid-cols-2 gap-4">
        <div>
          <dt class="text-sm text-neutral-500">Upper trigram</dt>
          <dd>{{ state.hexagram.upperTrigram.symbol }} {{ state.hexagram.upperTrigram.name }}</dd>
        </div>
        <div>
          <dt class="text-sm text-neutral-500">Lower trigram</dt>
          <dd>{{ state.hexagram.lowerTrigram.symbol }} {{ state.hexagram.lowerTrigram.name }}</dd>
        </div>
      </dl>

      <div>
        <h2 class="mb-2 text-sm font-medium text-neutral-500">Related Hexagrams</h2>
        <dl class="grid grid-cols-3 gap-4">
          <div v-for="related in relatedHexagrams" :key="related.label">
            <dt class="text-xs text-neutral-400 uppercase">{{ related.label }}</dt>
            <dd>
              <span v-if="related.isSelf">
                {{ related.summary.kingWenNumber }}. {{ related.summary.chineseName }} (self)
              </span>
              <router-link
                v-else
                :to="`/hexagrams/${related.summary.kingWenNumber}`"
                class="underline hover:no-underline"
              >
                {{ related.summary.kingWenNumber }}. {{ related.summary.chineseName }}
              </router-link>
            </dd>
          </div>
        </dl>
      </div>

      <div>
        <h2 class="text-sm font-medium text-neutral-500">Judgment</h2>
        <p>{{ state.hexagram.judgment ?? NOT_AVAILABLE }}</p>
      </div>

      <div>
        <h2 class="text-sm font-medium text-neutral-500">Image</h2>
        <p>{{ state.hexagram.image ?? NOT_AVAILABLE }}</p>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-neutral-500">Line Texts</h2>
        <p v-if="state.hexagram.lineStatements === null">{{ NOT_AVAILABLE }}</p>
        <ol v-else class="flex flex-col gap-2">
          <li v-for="(text, index) in [...state.hexagram.lineStatements].reverse()" :key="index">
            <span class="text-xs tracking-wide text-neutral-400 uppercase">
              Line {{ state.hexagram.lineStatements!.length - index }}
            </span>
            <p>{{ text }}</p>
          </li>
        </ol>
      </div>

      <p class="text-xs text-neutral-400">
        Source: James Legge's <em>The I Ching</em> (1899), public domain.
      </p>
    </div>
  </main>
</template>
