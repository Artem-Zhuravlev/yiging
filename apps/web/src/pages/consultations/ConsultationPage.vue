<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { fetchConsultation } from '../../entities/consultation/api'
import type { Consultation } from '../../entities/consultation/model'
import { fetchHexagram } from '../../entities/hexagram/api'
import type { HexagramLine } from '../../entities/hexagram/model'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import { requestInterpretation } from '../../entities/interpretation/api'
import type { Interpretation } from '../../entities/interpretation/model'
import { ApiError } from '../../shared/api/http'

type State =
  | { status: 'loading' }
  | { status: 'not-found' }
  | { status: 'error'; message: string }
  | {
      status: 'loaded'
      consultation: Consultation
      primaryLines: HexagramLine[]
      resultingLines: HexagramLine[]
    }

// Independent of `state` above: a failed/retried interpretation request must never disturb
// the already-loaded consultation detail.
type InterpretationState =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; interpretation: Interpretation }

const route = useRoute()
const id = computed(() => String(route.params.id))
const state = ref<State>({ status: 'loading' })
const interpretationState = ref<InterpretationState>({ status: 'idle' })

async function getInterpretation(): Promise<void> {
  if (interpretationState.value.status === 'loading') {
    return
  }

  interpretationState.value = { status: 'loading' }

  try {
    const interpretation = await requestInterpretation(id.value)
    interpretationState.value = { status: 'loaded', interpretation }
  } catch (error) {
    interpretationState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to get interpretation.',
    }
  }
}

onMounted(async () => {
  try {
    const consultation = await fetchConsultation(id.value)
    const [primaryHexagram, resultingHexagram] = await Promise.all([
      fetchHexagram(consultation.primaryHexagram.kingWenNumber),
      fetchHexagram(consultation.resultingHexagram.kingWenNumber),
    ])

    const primaryLines: HexagramLine[] = primaryHexagram.lines.map((line) => ({
      ...line,
      changing: consultation.changingLinePositions.includes(line.position),
    }))

    state.value = {
      status: 'loaded',
      consultation,
      primaryLines,
      resultingLines: resultingHexagram.lines,
    }
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      state.value = { status: 'not-found' }
      return
    }
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to load consultation.',
    }
  }
})
</script>

<template>
  <main class="mx-auto max-w-2xl px-6 py-10">
    <router-link to="/consultations" class="text-sm text-neutral-500 hover:underline">
      &larr; History
    </router-link>

    <p v-if="state.status === 'loading'" class="mt-6 text-neutral-500">Loading…</p>
    <p v-else-if="state.status === 'not-found'" class="mt-6 text-neutral-600">
      Consultation not found.
    </p>
    <p v-else-if="state.status === 'error'" class="mt-6 text-red-600">{{ state.message }}</p>

    <div v-else class="mt-6 flex flex-col gap-6">
      <div>
        <h1 class="text-xl font-semibold tracking-tight">{{ state.consultation.question }}</h1>
        <p class="text-sm text-neutral-500">
          {{ state.consultation.method }} &middot;
          {{ new Date(state.consultation.createdAt).toLocaleString() }}
        </p>
      </div>

      <div class="flex flex-wrap items-start gap-10">
        <div>
          <h2 class="mb-2 text-sm font-medium text-neutral-500">
            Primary — {{ state.consultation.primaryHexagram.kingWenNumber }}.
            {{ state.consultation.primaryHexagram.chineseName }}
          </h2>
          <HexagramLines :lines="state.primaryLines" />
        </div>

        <div>
          <h2 class="mb-2 text-sm font-medium text-neutral-500">
            Resulting — {{ state.consultation.resultingHexagram.kingWenNumber }}.
            {{ state.consultation.resultingHexagram.chineseName }}
          </h2>
          <HexagramLines :lines="state.resultingLines" />
        </div>
      </div>

      <p v-if="state.consultation.changingLinePositions.length === 0" class="text-neutral-500">
        No changing lines.
      </p>
      <p v-else class="text-neutral-500">
        Changing lines: {{ state.consultation.changingLinePositions.join(', ') }}
      </p>

      <div v-if="state.consultation.notes.length > 0">
        <h2 class="mb-2 text-sm font-medium text-neutral-500">Notes</h2>
        <ul class="flex flex-col gap-2">
          <li v-for="(note, index) in state.consultation.notes" :key="index">
            <span class="text-xs tracking-wide text-neutral-400 uppercase">{{ note.label }}</span>
            <p>{{ note.text }}</p>
          </li>
        </ul>
      </div>

      <div v-if="state.consultation.tags.length > 0" class="flex gap-2">
        <span
          v-for="tag in state.consultation.tags"
          :key="tag"
          class="rounded-full bg-neutral-100 px-3 py-1 text-xs text-neutral-600"
        >
          {{ tag }}
        </span>
      </div>

      <section class="rounded-lg border-2 border-dashed border-neutral-300 p-4">
        <h2 class="mb-3 text-sm font-medium text-neutral-500">AI Interpretation</h2>

        <button
          type="button"
          :disabled="interpretationState.status === 'loading'"
          class="rounded-md bg-neutral-800 px-4 py-2 text-sm text-white disabled:opacity-50"
          @click="getInterpretation"
        >
          {{ interpretationState.status === 'loading' ? 'Interpreting…' : 'Get Interpretation' }}
        </button>

        <p v-if="interpretationState.status === 'error'" class="mt-3 text-red-600">
          {{ interpretationState.message }}
        </p>

        <div v-else-if="interpretationState.status === 'loaded'" class="mt-4 flex flex-col gap-3">
          <p>{{ interpretationState.interpretation.summary }}</p>

          <div>
            <h3 class="text-xs font-medium text-neutral-500 uppercase">Core theme</h3>
            <p>{{ interpretationState.interpretation.coreTheme }}</p>
          </div>

          <div>
            <h3 class="text-xs font-medium text-neutral-500 uppercase">Situation</h3>
            <p>{{ interpretationState.interpretation.situation }}</p>
          </div>

          <div v-if="interpretationState.interpretation.changingLineMeaning">
            <h3 class="text-xs font-medium text-neutral-500 uppercase">Changing line meaning</h3>
            <p>{{ interpretationState.interpretation.changingLineMeaning }}</p>
          </div>

          <div v-if="interpretationState.interpretation.transition">
            <h3 class="text-xs font-medium text-neutral-500 uppercase">Transition</h3>
            <p>{{ interpretationState.interpretation.transition }}</p>
          </div>

          <div>
            <h3 class="text-xs font-medium text-neutral-500 uppercase">Practical reflection</h3>
            <p>{{ interpretationState.interpretation.practicalReflection }}</p>
          </div>

          <div v-if="interpretationState.interpretation.uncertainties.length > 0">
            <h3 class="text-xs font-medium text-neutral-500 uppercase">Uncertainties</h3>
            <ul class="list-inside list-disc">
              <li v-for="(note, index) in interpretationState.interpretation.uncertainties" :key="index">
                {{ note }}
              </li>
            </ul>
          </div>

          <div v-if="interpretationState.interpretation.sourceReferences.length > 0">
            <h3 class="text-xs font-medium text-neutral-500 uppercase">Sources</h3>
            <ul class="list-inside list-disc text-sm text-neutral-500">
              <li
                v-for="(sourceRef, index) in interpretationState.interpretation.sourceReferences"
                :key="index"
              >
                {{ sourceRef }}
              </li>
            </ul>
          </div>
        </div>
      </section>
    </div>
  </main>
</template>
