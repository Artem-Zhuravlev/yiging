<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { compareHexagrams } from '../../entities/hexagram/api'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import type { HexagramComparison } from '../../entities/hexagram/model'

const NOT_AVAILABLE = 'Not yet available.'
const RELATIONSHIP_LABELS = { nuclear: 'nuclear', reversed: 'reversed', complement: 'complement' } as const

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; comparison: HexagramComparison }

const route = useRoute()
const router = useRouter()
const state = ref<State>({ status: 'loading' })

function parseKingWenNumber(value: unknown, fallback: number): number {
  const parsed = Number(value)
  return Number.isInteger(parsed) && parsed >= 1 && parsed <= 64 ? parsed : fallback
}

const queryA = computed(() => parseKingWenNumber(route.query.a, 1))
const queryB = computed(() => parseKingWenNumber(route.query.b, 2))

// Form inputs are independent of the fetched state so the user can type a new pair before
// submitting, without the fields jumping around as the previous comparison loads.
const formA = ref(queryA.value)
const formB = ref(queryB.value)

function submit(): void {
  void router.push({ query: { a: String(formA.value), b: String(formB.value) } })
}

watch(
  [queryA, queryB],
  async ([a, b]) => {
    formA.value = a
    formB.value = b
    state.value = { status: 'loading' }
    try {
      const comparison = await compareHexagrams(a, b)
      state.value = { status: 'loaded', comparison }
    } catch (error) {
      state.value = {
        status: 'error',
        message: error instanceof Error ? error.message : 'Failed to compare hexagrams.',
      }
    }
  },
  { immediate: true },
)

// Equality checks only, against relationships already computed by the API (SPEC-014/017) — no
// relationship math here.
const relationshipNote = computed<string | null>(() => {
  if (state.value.status !== 'loaded') {
    return null
  }
  const { a, b } = state.value.comparison

  for (const key of Object.keys(RELATIONSHIP_LABELS) as (keyof typeof RELATIONSHIP_LABELS)[]) {
    if (a.relationships[key].kingWenNumber === b.kingWenNumber) {
      return `${b.kingWenNumber} is ${a.kingWenNumber}'s ${RELATIONSHIP_LABELS[key]} hexagram.`
    }
    if (b.relationships[key].kingWenNumber === a.kingWenNumber) {
      return `${a.kingWenNumber} is ${b.kingWenNumber}'s ${RELATIONSHIP_LABELS[key]} hexagram.`
    }
  }
  return null
})
</script>

<template>
  <main class="mx-auto max-w-3xl px-6 py-10">
    <router-link to="/hexagrams" class="text-sm text-neutral-500 hover:underline">
      &larr; Hexagrams
    </router-link>

    <h1 class="mt-4 mb-6 text-2xl font-semibold tracking-tight">Hexagram Comparison</h1>

    <form class="mb-8 flex items-end gap-4" @submit.prevent="submit">
      <label class="flex flex-col gap-1 text-sm text-neutral-700">
        Hexagram A
        <input
          v-model.number="formA"
          type="number"
          min="1"
          max="64"
          class="w-20 rounded-md border border-neutral-300 p-2"
        />
      </label>
      <label class="flex flex-col gap-1 text-sm text-neutral-700">
        Hexagram B
        <input
          v-model.number="formB"
          type="number"
          min="1"
          max="64"
          class="w-20 rounded-md border border-neutral-300 p-2"
        />
      </label>
      <button type="submit" class="rounded-md bg-neutral-800 px-4 py-2 text-white">Compare</button>
    </form>

    <p v-if="state.status === 'loading'" class="text-neutral-500">Loading…</p>
    <p v-else-if="state.status === 'error'" class="text-red-600">{{ state.message }}</p>

    <div v-else class="flex flex-col gap-6">
      <div class="flex flex-wrap gap-10">
        <div>
          <h2 class="mb-2 text-sm font-medium text-neutral-500">
            A — {{ state.comparison.a.kingWenNumber }}. {{ state.comparison.a.chineseName }}
          </h2>
          <HexagramLines :lines="state.comparison.a.lines" />
          <router-link
            :to="`/hexagrams/${state.comparison.a.kingWenNumber}`"
            class="mt-2 block text-sm underline hover:no-underline"
          >
            View full page
          </router-link>
        </div>
        <div>
          <h2 class="mb-2 text-sm font-medium text-neutral-500">
            B — {{ state.comparison.b.kingWenNumber }}. {{ state.comparison.b.chineseName }}
          </h2>
          <HexagramLines :lines="state.comparison.b.lines" />
          <router-link
            :to="`/hexagrams/${state.comparison.b.kingWenNumber}`"
            class="mt-2 block text-sm underline hover:no-underline"
          >
            View full page
          </router-link>
        </div>
      </div>

      <p v-if="relationshipNote" class="text-neutral-700">{{ relationshipNote }}</p>

      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="border-b border-neutral-200 text-left text-neutral-500">
            <th class="py-1">Position</th>
            <th class="py-1">A</th>
            <th class="py-1">B</th>
            <th class="py-1">Changed</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="line in [...state.comparison.lineComparisons].reverse()"
            :key="line.position"
            :data-position="line.position"
            :data-changed="line.changed ? 'true' : undefined"
            class="border-b border-neutral-100"
          >
            <td class="py-1">{{ line.position }}</td>
            <td class="py-1">{{ line.aPolarity }}</td>
            <td class="py-1">{{ line.bPolarity }}</td>
            <td class="py-1">{{ line.changed ? 'Yes' : '—' }}</td>
          </tr>
        </tbody>
      </table>

      <dl class="grid grid-cols-2 gap-4">
        <div>
          <dt class="text-sm text-neutral-500">Upper trigrams</dt>
          <dd>{{ state.comparison.upperTrigramDiffers ? 'Differ' : 'Match' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-neutral-500">Lower trigrams</dt>
          <dd>{{ state.comparison.lowerTrigramDiffers ? 'Differ' : 'Match' }}</dd>
        </div>
      </dl>

      <div class="grid grid-cols-2 gap-6">
        <div>
          <h3 class="text-xs font-medium text-neutral-500 uppercase">A — Judgment</h3>
          <p class="text-sm">{{ state.comparison.a.judgment ?? NOT_AVAILABLE }}</p>
        </div>
        <div>
          <h3 class="text-xs font-medium text-neutral-500 uppercase">B — Judgment</h3>
          <p class="text-sm">{{ state.comparison.b.judgment ?? NOT_AVAILABLE }}</p>
        </div>
      </div>
    </div>
  </main>
</template>
