<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import Button from 'primevue/button'
import Message from 'primevue/message'
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
  <main class="max-w-screen-sm mx-auto p-4">
    <router-link to="/hexagrams" class="text-sm text-color-secondary">&larr; Hexagrams</router-link>

    <p v-if="state.status === 'loading'" class="mt-4 text-color-secondary">Loading…</p>
    <p v-else-if="state.status === 'not-found'" class="mt-4 text-color-secondary">Hexagram not found.</p>
    <Message v-else-if="state.status === 'error'" severity="error" class="mt-4">{{ state.message }}</Message>

    <div v-else class="mt-4 flex flex-column gap-5">
      <div class="flex align-items-start justify-content-between gap-4">
        <div class="flex align-items-center gap-4">
          <HexagramLines :lines="state.hexagram.lines" />
          <div>
            <h1 class="text-2xl font-semibold m-0">
              <span class="mr-2 text-3xl" aria-hidden="true">{{ state.hexagram.symbol }}</span>
              {{ state.hexagram.kingWenNumber }}. {{ state.hexagram.chineseName }}
            </h1>
            <p class="text-color-secondary m-0">{{ state.hexagram.pinyin }}</p>
          </div>
        </div>
        <div class="flex-shrink-0">
          <Button
            text
            size="small"
            :disabled="favoriteFormState.status === 'submitting'"
            :label="state.hexagram.favorite ? '★ Favorited' : '☆ Add to Favorites'"
            @click="toggleFavorite"
          />
          <Message v-if="favoriteFormState.status === 'error'" severity="error" class="mt-1">
            {{ favoriteFormState.message }}
          </Message>
        </div>
      </div>

      <dl class="grid m-0">
        <div class="col-6">
          <dt class="text-sm text-color-secondary">Upper trigram</dt>
          <dd class="m-0">{{ state.hexagram.upperTrigram.symbol }} {{ state.hexagram.upperTrigram.name }}</dd>
        </div>
        <div class="col-6">
          <dt class="text-sm text-color-secondary">Lower trigram</dt>
          <dd class="m-0">{{ state.hexagram.lowerTrigram.symbol }} {{ state.hexagram.lowerTrigram.name }}</dd>
        </div>
      </dl>

      <div>
        <h2 class="mb-2 text-sm font-medium text-color-secondary">Related Hexagrams</h2>
        <dl class="grid m-0">
          <div v-for="related in relatedHexagrams" :key="related.label" class="col-4">
            <dt class="text-xs text-color-secondary uppercase">{{ related.label }}</dt>
            <dd class="m-0">
              <span v-if="related.isSelf">
                {{ related.summary.kingWenNumber }}. {{ related.summary.chineseName }} (self)
              </span>
              <router-link v-else :to="`/hexagrams/${related.summary.kingWenNumber}`">
                {{ related.summary.kingWenNumber }}. {{ related.summary.chineseName }}
              </router-link>
            </dd>
          </div>
        </dl>
      </div>

      <div>
        <h2 class="text-sm font-medium text-color-secondary mb-1">Judgment</h2>
        <p class="mt-0">{{ state.hexagram.judgment ?? NOT_AVAILABLE }}</p>
      </div>

      <div>
        <h2 class="text-sm font-medium text-color-secondary mb-1">Image</h2>
        <p class="mt-0">{{ state.hexagram.image ?? NOT_AVAILABLE }}</p>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-color-secondary">Line Texts</h2>
        <p v-if="state.hexagram.lineStatements === null" class="mt-0">{{ NOT_AVAILABLE }}</p>
        <ol v-else class="flex flex-column gap-2 p-0 m-0">
          <li v-for="(text, index) in [...state.hexagram.lineStatements].reverse()" :key="index" class="list-none">
            <span class="text-xs tracking-wide text-color-secondary uppercase">
              Line {{ state.hexagram.lineStatements!.length - index }}
            </span>
            <p class="mt-1 mb-0">{{ text }}</p>
          </li>
        </ol>
      </div>

      <p class="text-xs text-color-secondary">
        Source: James Legge's <em>The I Ching</em> (1899), public domain.
      </p>
    </div>
  </main>
</template>
