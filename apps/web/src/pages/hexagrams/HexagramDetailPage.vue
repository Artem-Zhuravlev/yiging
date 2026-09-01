<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
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
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'
import LoadingSkeleton from '../../shared/ui/LoadingSkeleton.vue'

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

const { t, locale } = useI18n()
const route = useRoute()
const state = ref<State>({ status: 'loading' })
const kingWenNumber = computed(() => Number(route.params.number))
const favoriteFormState = ref<FavoriteFormState>({ status: 'idle' })

// The line the user has selected in the diagram to read inline (SPEC-044); null = none.
const selectedLine = ref<number | null>(null)

function toggleLine(position: number): void {
  selectedLine.value = selectedLine.value === position ? null : position
}

const selectedLineText = computed<string | null>(() => {
  if (state.value.status !== 'loaded' || selectedLine.value === null) {
    return null
  }
  return state.value.hexagram.lineStatements?.[selectedLine.value - 1] ?? null
})

useStatusAnnouncer(
  computed(() => state.value.status),
  (status) => (status === 'not-found' ? t('hexagramDetail.notFound') : undefined),
)

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
      message: error instanceof Error ? error.message : t('hexagramDetail.favoriteError'),
    }
  }
}

// The three correspondence pairs (1-4, 2-5, 3-6), derived from the first three line-dynamics
// entries (SPEC-053). The 2-5 pair (subject & ruler) is the one that matters most.
const correspondencePairs = computed(() => {
  if (state.value.status !== 'loaded' || !state.value.hexagram.lineDynamics) return []
  return state.value.hexagram.lineDynamics
    .filter((d) => d.position <= 3)
    .map((d) => ({ a: d.position, b: d.correspondsWith, corresponds: d.corresponds }))
})

// Per-line dynamics rows, top-to-bottom (6 → 1) to match the diagram, each with its polarity.
const lineDynamicsRows = computed(() => {
  if (state.value.status !== 'loaded' || !state.value.hexagram.lineDynamics) return []
  const lines = state.value.hexagram.lines
  return [...state.value.hexagram.lineDynamics].reverse().map((d) => ({
    ...d,
    polarityLabel: lines[d.position - 1]?.polarity === 'yang' ? t('common.yang') : t('common.yin'),
  }))
})

// A yin line between two yang lines both rides (乘) the one below and supports (承) the one
// above — show both when both apply.
function adjacencyLabel(row: { ridesFirmBelow: boolean; supportsFirmAbove: boolean }): string {
  const parts: string[] = []
  if (row.ridesFirmBelow) parts.push(t('lineDynamics.rides'))
  if (row.supportsFirmAbove) parts.push(t('lineDynamics.supports'))
  return parts.length > 0 ? parts.join(' · ') : '—'
}

// The predecessor in the King Wen order (SPEC-056). Only read when `sequencePrecedent` is
// non-null, which the API guarantees means kingWenNumber >= 3, so N - 1 is always a valid target.
const sequencePredecessor = computed<number | null>(() => {
  if (state.value.status !== 'loaded' || state.value.hexagram.sequencePrecedent == null) return null
  return state.value.hexagram.kingWenNumber - 1
})

const relatedHexagrams = computed<RelatedHexagram[]>(() => {
  if (state.value.status !== 'loaded') {
    return []
  }
  const { kingWenNumber: current, relationships } = state.value.hexagram

  return [
    { label: t('common.nuclear'), summary: relationships.nuclear },
    { label: t('common.reversed'), summary: relationships.reversed },
    { label: t('common.complement'), summary: relationships.complement },
  ].map((entry) => ({ ...entry, isSelf: entry.summary.kingWenNumber === current }))
})

// Re-runs on either the route hexagram OR the app language changing — the classical text is
// served per-locale (SPEC-057), so a language switch must re-fetch.
watch(
  [kingWenNumber, locale],
  async ([number]) => {
    state.value = { status: 'loading' }
    selectedLine.value = null
    try {
      const hexagram = await fetchHexagram(number, locale.value)
      state.value = { status: 'loaded', hexagram }
    } catch (error) {
      if (error instanceof ApiError && error.status === 404) {
        state.value = { status: 'not-found' }
        return
      }
      state.value = {
        status: 'error',
        message: error instanceof Error ? error.message : t('hexagramDetail.loadError'),
      }
    }
  },
  { immediate: true },
)
</script>

<template>
  <main id="main" tabindex="-1" class="container-sm mx-auto p-4">
    <router-link to="/hexagrams" class="text-sm text-color-secondary">&larr; {{ t('nav.hexagrams') }}</router-link>

    <LoadingSkeleton v-if="state.status === 'loading'" :lines="5" class="mt-4" />
    <p v-else-if="state.status === 'not-found'" class="mt-4 text-color-secondary">
      {{ t('hexagramDetail.notFound') }}
    </p>
    <Message v-else-if="state.status === 'error'" severity="error" role="alert" class="mt-4">{{ state.message }}</Message>

    <div v-else class="mt-4 flex flex-column gap-5" @keydown.esc="selectedLine = null">
      <div class="flex align-items-start justify-content-between gap-4">
        <div class="flex align-items-center gap-4">
          <HexagramLines
            :lines="state.hexagram.lines"
            :interactive="state.hexagram.lineStatements !== null"
            :selected-position="selectedLine"
            @select="toggleLine"
          />
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
            :label="state.hexagram.favorite ? t('hexagramDetail.favorited') : t('hexagramDetail.addToFavorites')"
            @click="toggleFavorite"
          />
          <Message v-if="favoriteFormState.status === 'error'" severity="error" class="mt-1">
            {{ favoriteFormState.message }}
          </Message>
        </div>
      </div>

      <div
        v-if="selectedLine !== null && selectedLineText !== null"
        class="flex align-items-start justify-content-between gap-3 border-round border-1 surface-border p-3"
      >
        <div>
          <h3 class="mt-0 mb-1 text-xs font-medium text-color-secondary uppercase">
            {{ t('hexagramDetail.line', { position: selectedLine }) }}
          </h3>
          <p class="m-0">{{ selectedLineText }}</p>
        </div>
        <Button
          text
          rounded
          size="small"
          icon="pi pi-times"
          :aria-label="t('common.close')"
          class="flex-shrink-0"
          @click="selectedLine = null"
        />
      </div>

      <dl class="grid m-0">
        <div class="col-6">
          <dt class="text-sm text-color-secondary">{{ t('common.upperTrigram') }}</dt>
          <dd class="m-0">{{ state.hexagram.upperTrigram.symbol }} {{ state.hexagram.upperTrigram.name }}</dd>
        </div>
        <div class="col-6">
          <dt class="text-sm text-color-secondary">{{ t('common.lowerTrigram') }}</dt>
          <dd class="m-0">{{ state.hexagram.lowerTrigram.symbol }} {{ state.hexagram.lowerTrigram.name }}</dd>
        </div>
      </dl>

      <div>
        <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ t('hexagramDetail.relatedHexagrams') }}</h2>
        <dl class="grid m-0">
          <div v-for="related in relatedHexagrams" :key="related.label" class="col-4">
            <dt class="text-xs text-color-secondary uppercase">{{ related.label }}</dt>
            <dd class="m-0">
              <span v-if="related.isSelf">
                {{ related.summary.kingWenNumber }}. {{ related.summary.chineseName }}
                ({{ t('hexagramDetail.self') }})
              </span>
              <router-link v-else :to="`/hexagrams/${related.summary.kingWenNumber}`">
                {{ related.summary.kingWenNumber }}. {{ related.summary.chineseName }}
              </router-link>
            </dd>
          </div>
        </dl>
      </div>

      <div>
        <h2 class="text-sm font-medium text-color-secondary mb-1">{{ t('hexagramDetail.judgment') }}</h2>
        <p class="mt-0">{{ state.hexagram.judgment ?? t('common.notAvailable') }}</p>
      </div>

      <div>
        <h2 class="text-sm font-medium text-color-secondary mb-1">{{ t('hexagramDetail.image') }}</h2>
        <p class="mt-0">{{ state.hexagram.image ?? t('common.notAvailable') }}</p>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ t('hexagramDetail.lineTexts') }}</h2>
        <p v-if="state.hexagram.lineStatements === null" class="mt-0">{{ t('common.notAvailable') }}</p>
        <ol v-else class="flex flex-column gap-2 p-0 m-0">
          <li
            v-for="(text, index) in [...state.hexagram.lineStatements].reverse()"
            :key="index"
            class="list-none line-text"
            :class="{ 'line-text-selected': state.hexagram.lineStatements!.length - index === selectedLine }"
          >
            <span class="text-xs tracking-wide text-color-secondary uppercase">
              {{ t('hexagramDetail.line', { position: state.hexagram.lineStatements!.length - index }) }}
            </span>
            <p class="mt-1 mb-0">{{ text }}</p>
          </li>
        </ol>
      </div>

      <div v-if="state.hexagram.lineDynamics">
        <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ t('lineDynamics.title') }}</h2>

        <ul class="mb-3 flex flex-column gap-1 list-none p-0 m-0 text-sm">
          <li
            v-for="pair in correspondencePairs"
            :key="pair.a"
            :class="{ 'font-medium': pair.a === 2 }"
          >
            {{ t('lineDynamics.pair', { a: pair.a, b: pair.b }) }} —
            {{ pair.corresponds ? t('lineDynamics.corresponds') : t('lineDynamics.noCorrespondence') }}
          </li>
        </ul>

        <table class="w-full text-sm line-dynamics-table">
          <thead>
            <tr>
              <th class="text-left py-1">{{ t('lineDynamics.colLine') }}</th>
              <th class="text-left py-1">{{ t('lineDynamics.colPosition') }}</th>
              <th class="text-left py-1">{{ t('lineDynamics.colCentral') }}</th>
              <th class="text-left py-1">{{ t('lineDynamics.colCorresponds') }}</th>
              <th class="text-left py-1">{{ t('lineDynamics.colAdjacency') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in lineDynamicsRows" :key="row.position" :data-position="row.position">
              <td class="py-1">{{ row.position }}. {{ row.polarityLabel }}</td>
              <td class="py-1">
                {{ row.correctPosition ? t('lineDynamics.correct') : t('lineDynamics.improper') }}
              </td>
              <td class="py-1">
                {{
                  row.centralAndCorrect
                    ? t('lineDynamics.centralCorrect')
                    : row.central
                      ? t('lineDynamics.central')
                      : '—'
                }}
              </td>
              <td class="py-1">
                {{ row.correspondsWith }} ·
                {{ row.corresponds ? t('lineDynamics.corresponds') : t('lineDynamics.noCorrespondence') }}
              </td>
              <td class="py-1">{{ adjacencyLabel(row) }}</td>
            </tr>
          </tbody>
        </table>

        <p class="mt-2 mb-0 text-xs text-color-secondary">{{ t('lineDynamics.help') }}</p>
      </div>

      <div v-if="state.hexagram.sequencePrecedent" class="hexagram-sequence">
        <h2 class="mb-1 text-sm font-medium text-color-secondary">{{ t('hexagramSequence.title') }}</h2>
        <p class="mt-0 mb-1 text-sm">
          {{ t('hexagramSequence.heading', { n: state.hexagram.kingWenNumber, name: state.hexagram.chineseName }) }}
          <router-link v-if="sequencePredecessor" :to="`/hexagrams/${sequencePredecessor}`">
            {{ t('hexagramSequence.predecessorLink', { prev: sequencePredecessor }) }}
          </router-link>
        </p>
        <p class="mt-1 mb-0">{{ state.hexagram.sequencePrecedent }}</p>
        <p class="mt-2 mb-0 text-xs text-color-secondary">{{ t('hexagramSequence.source') }}</p>
      </div>

      <p class="text-xs text-color-secondary">
        {{ t('hexagramDetail.sourcePrefix') }} <em>The I Ching</em> {{ t('hexagramDetail.sourceSuffix') }}
      </p>
    </div>
  </main>
</template>

<style scoped>
.line-text {
  padding: 0.25rem 0.5rem;
  margin: 0 -0.5rem;
  border-radius: 4px;
  transition: background-color 0.15s ease;
}

.line-text-selected {
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color, #fff);
}

.line-text-selected .text-color-secondary {
  color: inherit;
  opacity: 0.85;
}

@media (prefers-reduced-motion: reduce) {
  .line-text {
    transition: none;
  }
}

.line-dynamics-table {
  border-collapse: collapse;
}

.line-dynamics-table th,
.line-dynamics-table td {
  border-bottom: 1px solid var(--p-content-border-color);
  padding-right: 0.75rem;
}
</style>
