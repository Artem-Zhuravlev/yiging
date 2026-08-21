<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { fetchConsultation } from '../../entities/consultation/api'
import type { Consultation } from '../../entities/consultation/model'
import { fetchHexagram } from '../../entities/hexagram/api'
import type { HexagramLine } from '../../entities/hexagram/model'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
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

const route = useRoute()
const id = computed(() => String(route.params.id))
const state = ref<State>({ status: 'loading' })

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
    <p v-if="state.status === 'loading'" class="text-neutral-500">Loading…</p>
    <p v-else-if="state.status === 'not-found'" class="text-neutral-600">Consultation not found.</p>
    <p v-else-if="state.status === 'error'" class="text-red-600">{{ state.message }}</p>

    <div v-else class="flex flex-col gap-6">
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

      <div
        v-if="
          state.consultation.context ||
          state.consultation.whatHappenedBefore ||
          state.consultation.whatUserWantsToUnderstand ||
          state.consultation.backgroundInformation ||
          state.consultation.initialInterpretation
        "
        class="flex flex-col gap-3"
      >
        <h2 class="text-sm font-medium text-neutral-500">Context</h2>
        <div v-if="state.consultation.context">
          <h3 class="text-xs text-neutral-400">Context</h3>
          <p>{{ state.consultation.context }}</p>
        </div>
        <div v-if="state.consultation.whatHappenedBefore">
          <h3 class="text-xs text-neutral-400">What happened before</h3>
          <p>{{ state.consultation.whatHappenedBefore }}</p>
        </div>
        <div v-if="state.consultation.whatUserWantsToUnderstand">
          <h3 class="text-xs text-neutral-400">What you want to understand</h3>
          <p>{{ state.consultation.whatUserWantsToUnderstand }}</p>
        </div>
        <div v-if="state.consultation.backgroundInformation">
          <h3 class="text-xs text-neutral-400">Background information</h3>
          <p>{{ state.consultation.backgroundInformation }}</p>
        </div>
        <div v-if="state.consultation.initialInterpretation">
          <h3 class="text-xs text-neutral-400">Initial interpretation</h3>
          <p>{{ state.consultation.initialInterpretation }}</p>
        </div>
      </div>

      <div v-if="state.consultation.outcome">
        <h2 class="mb-2 text-sm font-medium text-neutral-500">Outcome</h2>
        <div v-if="state.consultation.outcome.whatActuallyHappened">
          <h3 class="text-xs text-neutral-400">What actually happened</h3>
          <p>{{ state.consultation.outcome.whatActuallyHappened }}</p>
        </div>
        <div v-if="state.consultation.outcome.outcome">
          <h3 class="text-xs text-neutral-400">Outcome</h3>
          <p>{{ state.consultation.outcome.outcome }}</p>
        </div>
        <div v-if="state.consultation.outcome.reflection">
          <h3 class="text-xs text-neutral-400">Reflection</h3>
          <p>{{ state.consultation.outcome.reflection }}</p>
        </div>
      </div>
    </div>
  </main>
</template>
