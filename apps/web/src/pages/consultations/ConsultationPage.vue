<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { fetchConsultation, updateConsultation } from '../../entities/consultation/api'
import type { Consultation, ConsultationRepeats } from '../../entities/consultation/model'
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

// Independent of `state`/`interpretationState`: a failed/retried note or tag submission must
// never disturb the already-loaded consultation detail or the interpretation section.
type FormState = { status: 'idle' } | { status: 'submitting' } | { status: 'error'; message: string }

const route = useRoute()
const id = computed(() => String(route.params.id))
const state = ref<State>({ status: 'loading' })
const interpretationState = ref<InterpretationState>({ status: 'idle' })
// Independent of `state`: repeats are computed once at load and never change when notes, tags,
// context, or outcome are edited via PATCH (the hexagrams/changing lines never change), matching
// the pattern above for `interpretationState`.
const repeats = ref<ConsultationRepeats | null>(null)

const noteLabel = ref<'before' | 'after' | 'later'>('after')
const noteText = ref('')
const noteFormState = ref<FormState>({ status: 'idle' })

const tagText = ref('')
const tagFormState = ref<FormState>({ status: 'idle' })

const favoriteFormState = ref<FormState>({ status: 'idle' })

const contextForm = ref(contextFormFrom(null))
const contextFormState = ref<FormState>({ status: 'idle' })

function contextFormFrom(consultation: Consultation | null) {
  return {
    context: consultation?.context ?? '',
    whatHappenedBefore: consultation?.whatHappenedBefore ?? '',
    whatUserWantsToUnderstand: consultation?.whatUserWantsToUnderstand ?? '',
    backgroundInformation: consultation?.backgroundInformation ?? '',
    initialInterpretation: consultation?.initialInterpretation ?? '',
  }
}

const outcomeForm = ref(outcomeFormFrom(null))
const outcomeFormState = ref<FormState>({ status: 'idle' })

function outcomeFormFrom(consultation: Consultation | null) {
  return {
    whatActuallyHappened: consultation?.outcome?.whatActuallyHappened ?? '',
    outcome: consultation?.outcome?.outcome ?? '',
    reflection: consultation?.outcome?.reflection ?? '',
  }
}

async function addNote(): Promise<void> {
  if (state.value.status !== 'loaded' || noteFormState.value.status === 'submitting') {
    return
  }

  const loaded = state.value
  noteFormState.value = { status: 'submitting' }

  try {
    const updated = await updateConsultation(loaded.consultation.id, {
      note: { label: noteLabel.value, text: noteText.value },
    })
    state.value = { ...loaded, consultation: updated }
    noteText.value = ''
    noteFormState.value = { status: 'idle' }
  } catch (error) {
    noteFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to add note.',
    }
  }
}

async function addTag(): Promise<void> {
  if (state.value.status !== 'loaded' || tagFormState.value.status === 'submitting') {
    return
  }

  const loaded = state.value
  tagFormState.value = { status: 'submitting' }

  try {
    const updated = await updateConsultation(loaded.consultation.id, { tag: tagText.value })
    state.value = { ...loaded, consultation: updated }
    tagText.value = ''
    tagFormState.value = { status: 'idle' }
  } catch (error) {
    tagFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to add tag.',
    }
  }
}

// Sends the full current form state on every save; a blank field means "clear this field"
// (updateConsultation's explicit-null-clears semantics, SPEC-019), matching what the form
// visibly shows — there's no separate "did the user touch this" tracking to keep in sync.
async function saveContext(): Promise<void> {
  if (state.value.status !== 'loaded' || contextFormState.value.status === 'submitting') {
    return
  }

  const loaded = state.value
  contextFormState.value = { status: 'submitting' }

  try {
    const updated = await updateConsultation(loaded.consultation.id, {
      context: contextForm.value.context.trim() === '' ? null : contextForm.value.context,
      whatHappenedBefore:
        contextForm.value.whatHappenedBefore.trim() === '' ? null : contextForm.value.whatHappenedBefore,
      whatUserWantsToUnderstand:
        contextForm.value.whatUserWantsToUnderstand.trim() === ''
          ? null
          : contextForm.value.whatUserWantsToUnderstand,
      backgroundInformation:
        contextForm.value.backgroundInformation.trim() === '' ? null : contextForm.value.backgroundInformation,
      initialInterpretation:
        contextForm.value.initialInterpretation.trim() === '' ? null : contextForm.value.initialInterpretation,
    })
    state.value = { ...loaded, consultation: updated }
    contextForm.value = contextFormFrom(updated)
    contextFormState.value = { status: 'idle' }
  } catch (error) {
    contextFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to save context.',
    }
  }
}

// Same shape as saveContext(): sends the full current form state every time, blank meaning
// "clear this field" (SPEC-020 reuses SPEC-019's PATCH semantics) — but this is a genuinely
// separate historical record (its own consultation_outcomes row), never touching question,
// hexagrams, notes, tags, or the context fields above.
async function saveOutcome(): Promise<void> {
  if (state.value.status !== 'loaded' || outcomeFormState.value.status === 'submitting') {
    return
  }

  const loaded = state.value
  outcomeFormState.value = { status: 'submitting' }

  try {
    const updated = await updateConsultation(loaded.consultation.id, {
      whatActuallyHappened:
        outcomeForm.value.whatActuallyHappened.trim() === '' ? null : outcomeForm.value.whatActuallyHappened,
      outcome: outcomeForm.value.outcome.trim() === '' ? null : outcomeForm.value.outcome,
      reflection: outcomeForm.value.reflection.trim() === '' ? null : outcomeForm.value.reflection,
    })
    state.value = { ...loaded, consultation: updated }
    outcomeForm.value = outcomeFormFrom(updated)
    outcomeFormState.value = { status: 'idle' }
  } catch (error) {
    outcomeFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to save outcome.',
    }
  }
}

function printPage(): void {
  window.print()
}

async function toggleFavorite(): Promise<void> {
  if (state.value.status !== 'loaded' || favoriteFormState.value.status === 'submitting') {
    return
  }

  const loaded = state.value
  favoriteFormState.value = { status: 'submitting' }

  try {
    const updated = await updateConsultation(loaded.consultation.id, {
      favorite: !loaded.consultation.favorite,
    })
    state.value = { ...loaded, consultation: updated }
    favoriteFormState.value = { status: 'idle' }
  } catch (error) {
    favoriteFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to update favorite.',
    }
  }
}

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
    repeats.value = consultation.repeats
    contextForm.value = contextFormFrom(consultation)
    outcomeForm.value = outcomeFormFrom(consultation)
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
    <router-link to="/consultations" class="print:hidden text-sm text-neutral-500 hover:underline">
      &larr; History
    </router-link>

    <p v-if="state.status === 'loading'" class="mt-6 text-neutral-500">Loading…</p>
    <p v-else-if="state.status === 'not-found'" class="mt-6 text-neutral-600">
      Consultation not found.
    </p>
    <p v-else-if="state.status === 'error'" class="mt-6 text-red-600">{{ state.message }}</p>

    <div v-else class="mt-6 flex flex-col gap-6">
      <div>
        <div class="flex items-start justify-between gap-3">
          <h1 class="text-xl font-semibold tracking-tight">{{ state.consultation.question }}</h1>
          <div class="print:hidden flex shrink-0 gap-3">
            <button
              type="button"
              :disabled="favoriteFormState.status === 'submitting'"
              class="text-sm text-neutral-500 hover:text-neutral-900 disabled:opacity-50"
              @click="toggleFavorite"
            >
              {{ state.consultation.favorite ? '★ Favorited' : '☆ Add to Favorites' }}
            </button>
            <button
              type="button"
              class="text-sm text-neutral-500 hover:text-neutral-900"
              @click="printPage"
            >
              Print / Export
            </button>
          </div>
        </div>
        <p class="text-sm text-neutral-500">
          {{ state.consultation.method }} &middot;
          {{ new Date(state.consultation.createdAt).toLocaleString() }}
        </p>
        <p v-if="favoriteFormState.status === 'error'" class="mt-1 text-sm text-red-600">
          {{ favoriteFormState.message }}
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

      <router-link
        :to="`/hexagrams/compare?a=${state.consultation.primaryHexagram.kingWenNumber}&b=${state.consultation.resultingHexagram.kingWenNumber}`"
        class="print:hidden self-start text-sm underline hover:no-underline"
      >
        Compare hexagrams
      </router-link>

      <p v-if="state.consultation.changingLinePositions.length === 0" class="text-neutral-500">
        No changing lines.
      </p>
      <p v-else class="text-neutral-500">
        Changing lines: {{ state.consultation.changingLinePositions.join(', ') }}
      </p>

      <div>
        <p v-if="state.consultation.followUpTo" class="text-sm text-neutral-500">
          Follow-up to:
          <router-link
            :to="`/consultations/${state.consultation.followUpTo.id}`"
            class="underline hover:no-underline"
          >
            {{ state.consultation.followUpTo.question }}
          </router-link>
        </p>

        <div v-if="state.consultation.followUps.length > 0" class="mt-1">
          <h2 class="text-sm font-medium text-neutral-500">Follow-ups</h2>
          <ul class="mt-1 flex flex-col gap-1">
            <li v-for="followUp in state.consultation.followUps" :key="followUp.id">
              <router-link
                :to="`/consultations/${followUp.id}`"
                class="text-sm underline hover:no-underline"
              >
                {{ followUp.question }}
              </router-link>
            </li>
          </ul>
        </div>

        <router-link
          :to="`/consultations/new?followUpTo=${state.consultation.id}`"
          class="print:hidden mt-1 inline-block text-sm underline hover:no-underline"
        >
          Create Follow-up
        </router-link>
      </div>

      <div
        v-if="
          repeats &&
          (repeats.primaryHexagram.length > 0 ||
            repeats.resultingHexagram.length > 0 ||
            repeats.changingLines.length > 0)
        "
        class="flex flex-col gap-3"
      >
        <div v-if="repeats.primaryHexagram.length > 0">
          <h2 class="text-sm font-medium text-neutral-500">Same primary hexagram before</h2>
          <ul class="mt-1 flex flex-col gap-1">
            <li v-for="match in repeats.primaryHexagram" :key="match.id">
              <router-link :to="`/consultations/${match.id}`" class="text-sm underline hover:no-underline">
                {{ match.question }}
              </router-link>
            </li>
          </ul>
        </div>

        <div v-if="repeats.resultingHexagram.length > 0">
          <h2 class="text-sm font-medium text-neutral-500">Same resulting hexagram before</h2>
          <ul class="mt-1 flex flex-col gap-1">
            <li v-for="match in repeats.resultingHexagram" :key="match.id">
              <router-link :to="`/consultations/${match.id}`" class="text-sm underline hover:no-underline">
                {{ match.question }}
              </router-link>
            </li>
          </ul>
        </div>

        <div v-if="repeats.changingLines.length > 0">
          <h2 class="text-sm font-medium text-neutral-500">Same changing lines before</h2>
          <ul class="mt-1 flex flex-col gap-1">
            <li v-for="match in repeats.changingLines" :key="match.id">
              <router-link :to="`/consultations/${match.id}`" class="text-sm underline hover:no-underline">
                {{ match.question }}
              </router-link>
            </li>
          </ul>
        </div>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-neutral-500">Notes</h2>
        <ul v-if="state.consultation.notes.length > 0" class="mb-3 flex flex-col gap-2">
          <li v-for="(note, index) in state.consultation.notes" :key="index">
            <span class="text-xs tracking-wide text-neutral-400 uppercase">{{ note.label }}</span>
            <p>{{ note.text }}</p>
          </li>
        </ul>

        <form class="print:hidden flex flex-col gap-2" @submit.prevent="addNote">
          <div class="flex gap-2">
            <select v-model="noteLabel" class="rounded-md border border-neutral-300 p-2 text-sm">
              <option value="before">Before</option>
              <option value="after">After</option>
              <option value="later">Later</option>
            </select>
            <textarea
              v-model="noteText"
              rows="2"
              required
              maxlength="5000"
              placeholder="Add a note…"
              class="flex-1 rounded-md border border-neutral-300 p-2 text-sm"
            />
          </div>
          <p v-if="noteFormState.status === 'error'" class="text-sm text-red-600">
            {{ noteFormState.message }}
          </p>
          <button
            type="submit"
            :disabled="noteFormState.status === 'submitting'"
            class="self-start rounded-md bg-neutral-800 px-3 py-1.5 text-sm text-white disabled:opacity-50"
          >
            {{ noteFormState.status === 'submitting' ? 'Adding…' : 'Add Note' }}
          </button>
        </form>
      </div>

      <div>
        <div v-if="state.consultation.tags.length > 0" class="mb-3 flex gap-2">
          <span
            v-for="tag in state.consultation.tags"
            :key="tag"
            class="rounded-full bg-neutral-100 px-3 py-1 text-xs text-neutral-600"
          >
            {{ tag }}
          </span>
        </div>

        <form class="print:hidden flex flex-col gap-2" @submit.prevent="addTag">
          <div class="flex gap-2">
            <input
              v-model="tagText"
              type="text"
              required
              placeholder="Add a tag…"
              class="flex-1 rounded-md border border-neutral-300 p-2 text-sm"
            />
            <button
              type="submit"
              :disabled="tagFormState.status === 'submitting'"
              class="rounded-md bg-neutral-800 px-3 py-1.5 text-sm text-white disabled:opacity-50"
            >
              {{ tagFormState.status === 'submitting' ? 'Adding…' : 'Add Tag' }}
            </button>
          </div>
          <p v-if="tagFormState.status === 'error'" class="text-sm text-red-600">
            {{ tagFormState.message }}
          </p>
        </form>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-neutral-500">Context</h2>
        <form class="flex flex-col gap-3" @submit.prevent="saveContext">
          <div>
            <label for="edit-context" class="mb-1 block text-xs text-neutral-500">Context</label>
            <textarea
              id="edit-context"
              v-model="contextForm.context"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2 text-sm"
            />
          </div>
          <div>
            <label for="edit-what-happened-before" class="mb-1 block text-xs text-neutral-500">
              What happened before
            </label>
            <textarea
              id="edit-what-happened-before"
              v-model="contextForm.whatHappenedBefore"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2 text-sm"
            />
          </div>
          <div>
            <label for="edit-what-to-understand" class="mb-1 block text-xs text-neutral-500">
              What you want to understand
            </label>
            <textarea
              id="edit-what-to-understand"
              v-model="contextForm.whatUserWantsToUnderstand"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2 text-sm"
            />
          </div>
          <div>
            <label for="edit-background" class="mb-1 block text-xs text-neutral-500">
              Background information
            </label>
            <textarea
              id="edit-background"
              v-model="contextForm.backgroundInformation"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2 text-sm"
            />
          </div>
          <div>
            <label for="edit-initial-interpretation" class="mb-1 block text-xs text-neutral-500">
              Your initial interpretation
            </label>
            <textarea
              id="edit-initial-interpretation"
              v-model="contextForm.initialInterpretation"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2 text-sm"
            />
          </div>
          <p v-if="contextFormState.status === 'error'" class="text-sm text-red-600">
            {{ contextFormState.message }}
          </p>
          <button
            type="submit"
            :disabled="contextFormState.status === 'submitting'"
            class="print:hidden self-start rounded-md bg-neutral-800 px-3 py-1.5 text-sm text-white disabled:opacity-50"
          >
            {{ contextFormState.status === 'submitting' ? 'Saving…' : 'Save Context' }}
          </button>
        </form>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-neutral-500">Outcome</h2>
        <p v-if="state.consultation.outcome" class="mb-2 text-xs text-neutral-400">
          Last recorded {{ new Date(state.consultation.outcome.recordedAt).toLocaleString() }}
        </p>
        <form class="flex flex-col gap-3" @submit.prevent="saveOutcome">
          <div>
            <label for="edit-what-happened" class="mb-1 block text-xs text-neutral-500">
              What actually happened
            </label>
            <textarea
              id="edit-what-happened"
              v-model="outcomeForm.whatActuallyHappened"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2 text-sm"
            />
          </div>
          <div>
            <label for="edit-outcome" class="mb-1 block text-xs text-neutral-500">Outcome</label>
            <textarea
              id="edit-outcome"
              v-model="outcomeForm.outcome"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2 text-sm"
            />
          </div>
          <div>
            <label for="edit-reflection" class="mb-1 block text-xs text-neutral-500">Reflection</label>
            <textarea
              id="edit-reflection"
              v-model="outcomeForm.reflection"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2 text-sm"
            />
          </div>
          <p v-if="outcomeFormState.status === 'error'" class="text-sm text-red-600">
            {{ outcomeFormState.message }}
          </p>
          <button
            type="submit"
            :disabled="outcomeFormState.status === 'submitting'"
            class="print:hidden self-start rounded-md bg-neutral-800 px-3 py-1.5 text-sm text-white disabled:opacity-50"
          >
            {{ outcomeFormState.status === 'submitting' ? 'Saving…' : 'Save Outcome' }}
          </button>
        </form>
      </div>

      <section
        class="rounded-lg border-2 border-dashed border-neutral-300 p-4"
        :class="{ 'print:hidden': interpretationState.status !== 'loaded' }"
      >
        <h2 class="mb-3 text-sm font-medium text-neutral-500">AI Interpretation</h2>

        <button
          type="button"
          :disabled="interpretationState.status === 'loading'"
          class="print:hidden rounded-md bg-neutral-800 px-4 py-2 text-sm text-white disabled:opacity-50"
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
