<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Message from 'primevue/message'
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
  <main class="max-w-screen-md mx-auto p-4">
    <router-link to="/hexagrams" class="text-sm text-color-secondary">&larr; Hexagrams</router-link>

    <h1 class="mt-3 mb-4 text-2xl font-semibold">Hexagram Comparison</h1>

    <form class="mb-5 flex align-items-end gap-3" @submit.prevent="submit">
      <label class="flex flex-column gap-1 text-sm">
        Hexagram A
        <input v-model.number="formA" type="number" min="1" max="64" class="p-inputtext p-component w-5rem" />
      </label>
      <label class="flex flex-column gap-1 text-sm">
        Hexagram B
        <input v-model.number="formB" type="number" min="1" max="64" class="p-inputtext p-component w-5rem" />
      </label>
      <Button type="submit" label="Compare" />
    </form>

    <p v-if="state.status === 'loading'" class="text-color-secondary">Loading…</p>
    <Message v-else-if="state.status === 'error'" severity="error">{{ state.message }}</Message>

    <div v-else class="flex flex-column gap-5">
      <div class="flex flex-wrap gap-6">
        <div>
          <h2 class="mb-2 text-sm font-medium text-color-secondary">
            A — {{ state.comparison.a.kingWenNumber }}. {{ state.comparison.a.chineseName }}
          </h2>
          <HexagramLines :lines="state.comparison.a.lines" />
          <router-link :to="`/hexagrams/${state.comparison.a.kingWenNumber}`" class="mt-2 block text-sm">
            View full page
          </router-link>
        </div>
        <div>
          <h2 class="mb-2 text-sm font-medium text-color-secondary">
            B — {{ state.comparison.b.kingWenNumber }}. {{ state.comparison.b.chineseName }}
          </h2>
          <HexagramLines :lines="state.comparison.b.lines" />
          <router-link :to="`/hexagrams/${state.comparison.b.kingWenNumber}`" class="mt-2 block text-sm">
            View full page
          </router-link>
        </div>
      </div>

      <p v-if="relationshipNote" class="m-0">{{ relationshipNote }}</p>

      <table class="w-full text-sm compare-table">
        <thead>
          <tr>
            <th class="text-left py-1">Position</th>
            <th class="text-left py-1">A</th>
            <th class="text-left py-1">B</th>
            <th class="text-left py-1">Changed</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="line in [...state.comparison.lineComparisons].reverse()"
            :key="line.position"
            :data-position="line.position"
            :data-changed="line.changed ? 'true' : undefined"
          >
            <td class="py-1">{{ line.position }}</td>
            <td class="py-1">{{ line.aPolarity }}</td>
            <td class="py-1">{{ line.bPolarity }}</td>
            <td class="py-1">{{ line.changed ? 'Yes' : '—' }}</td>
          </tr>
        </tbody>
      </table>

      <dl class="grid m-0">
        <div class="col-6">
          <dt class="text-sm text-color-secondary">Upper trigrams</dt>
          <dd class="m-0">{{ state.comparison.upperTrigramDiffers ? 'Differ' : 'Match' }}</dd>
        </div>
        <div class="col-6">
          <dt class="text-sm text-color-secondary">Lower trigrams</dt>
          <dd class="m-0">{{ state.comparison.lowerTrigramDiffers ? 'Differ' : 'Match' }}</dd>
        </div>
      </dl>

      <div class="grid">
        <div class="col-6">
          <h3 class="text-xs font-medium text-color-secondary uppercase">A — Judgment</h3>
          <p class="text-sm">{{ state.comparison.a.judgment ?? NOT_AVAILABLE }}</p>
        </div>
        <div class="col-6">
          <h3 class="text-xs font-medium text-color-secondary uppercase">B — Judgment</h3>
          <p class="text-sm">{{ state.comparison.b.judgment ?? NOT_AVAILABLE }}</p>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.compare-table {
  border-collapse: collapse;
}

.compare-table th,
.compare-table td {
  border-bottom: 1px solid var(--p-content-border-color);
}
</style>
