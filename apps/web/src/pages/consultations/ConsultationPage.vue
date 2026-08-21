<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { fetchConsultation, updateConsultation } from '../../entities/consultation/api'
import type { Consultation, ConsultationRepeats } from '../../entities/consultation/model'
import { fetchHexagram } from '../../entities/hexagram/api'
import type { HexagramLine } from '../../entities/hexagram/model'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import { requestFollowUp, requestInterpretation } from '../../entities/interpretation/api'
import { INTERPRETATION_LENSES } from '../../entities/interpretation/model'
import type {
  ConversationExchange,
  Interpretation,
  InterpretationLens,
} from '../../entities/interpretation/model'
import { ApiError } from '../../shared/api/http'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Message from 'primevue/message'

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

type CopyLinkState = { status: 'idle' } | { status: 'copied' } | { status: 'error'; message: string }

const route = useRoute()
const id = computed(() => String(route.params.id))
const shareUrl = computed(() => `${location.origin}/share/consultations/${id.value}`)
const state = ref<State>({ status: 'loading' })
const selectedLens = ref<InterpretationLens>('general')
const interpretationStates = ref<Record<InterpretationLens, InterpretationState>>({
  general: { status: 'idle' },
  psychological: { status: 'idle' },
  practical: { status: 'idle' },
  symbolic: { status: 'idle' },
})
// Read-only view of the currently-selected lens's state — every existing template binding
// below keeps working unchanged; only getInterpretation() writes, and it writes into
// interpretationStates keyed by whichever lens was selected at request time (SPEC-033), not
// necessarily whatever's selected once the request resolves.
const interpretationState = computed<InterpretationState>(() => interpretationStates.value[selectedLens.value])
// Independent of `state`: repeats are computed once at load and never change when notes, tags,
// context, or outcome are edited via PATCH (the hexagrams/changing lines never change), matching
// the pattern above for `interpretationState`.
const repeats = ref<ConsultationRepeats | null>(null)
const copyLinkState = ref<CopyLinkState>({ status: 'idle' })

const conversations = ref<Record<InterpretationLens, ConversationExchange[]>>({
  general: [],
  psychological: [],
  practical: [],
  symbolic: [],
})
const currentConversation = computed<ConversationExchange[]>(() => conversations.value[selectedLens.value])
const followUpText = ref('')
const followUpFormState = ref<FormState>({ status: 'idle' })

const noteLabel = ref<'before' | 'after' | 'later'>('after')
const noteLabelOptions = [
  { label: 'Before', value: 'before' },
  { label: 'After', value: 'after' },
  { label: 'Later', value: 'later' },
]
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
    interpretationLens: consultation?.outcome?.interpretationLens ?? null,
    interpretationSummary: consultation?.outcome?.interpretationSummary ?? null,
  }
}

// Populates the (unsaved) outcome form's link fields from whichever lens is currently loaded
// (SPEC-036) — nothing is persisted until the existing "Save Outcome" button is clicked, matching
// how every other outcome field already works.
function linkInterpretationToOutcome(): void {
  if (interpretationState.value.status !== 'loaded') {
    return
  }

  outcomeForm.value.interpretationLens = selectedLens.value
  outcomeForm.value.interpretationSummary = interpretationState.value.interpretation.summary
}

function unlinkInterpretationFromOutcome(): void {
  outcomeForm.value.interpretationLens = null
  outcomeForm.value.interpretationSummary = null
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
      interpretationLens: outcomeForm.value.interpretationLens,
      interpretationSummary: outcomeForm.value.interpretationSummary,
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

async function copyShareLink(): Promise<void> {
  try {
    await navigator.clipboard.writeText(shareUrl.value)
    copyLinkState.value = { status: 'copied' }
  } catch (error) {
    copyLinkState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to copy link.',
    }
  }
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
  const lens = selectedLens.value

  if (interpretationStates.value[lens].status === 'loading') {
    return
  }

  interpretationStates.value[lens] = { status: 'loading' }

  try {
    const interpretation = await requestInterpretation(id.value, lens)
    interpretationStates.value[lens] = { status: 'loaded', interpretation }
  } catch (error) {
    interpretationStates.value[lens] = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to get interpretation.',
    }
  }
}

async function askFollowUp(): Promise<void> {
  if (followUpFormState.value.status === 'submitting' || followUpText.value.trim() === '') {
    return
  }

  const lens = selectedLens.value
  const question = followUpText.value
  followUpFormState.value = { status: 'submitting' }

  try {
    const { answer } = await requestFollowUp(id.value, question, conversations.value[lens])
    conversations.value[lens] = [...conversations.value[lens], { question, answer }]
    followUpText.value = ''
    followUpFormState.value = { status: 'idle' }
  } catch (error) {
    followUpFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to get an answer.',
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
  <main class="max-w-screen-sm mx-auto p-4">
    <router-link to="/consultations" class="print-hidden text-sm text-color-secondary">
      &larr; History
    </router-link>

    <p v-if="state.status === 'loading'" class="mt-4 text-color-secondary">Loading…</p>
    <p v-else-if="state.status === 'not-found'" class="mt-4 text-color-secondary">Consultation not found.</p>
    <Message v-else-if="state.status === 'error'" severity="error" class="mt-4">{{ state.message }}</Message>

    <div v-else class="mt-4 flex flex-column gap-5">
      <div>
        <div class="flex align-items-start justify-content-between gap-3">
          <h1 class="text-xl font-semibold m-0">{{ state.consultation.question }}</h1>
          <div class="print-hidden flex flex-shrink-0 gap-3 flex-wrap">
            <Button
              text
              size="small"
              :disabled="favoriteFormState.status === 'submitting'"
              :label="state.consultation.favorite ? '★ Favorited' : '☆ Add to Favorites'"
              @click="toggleFavorite"
            />
            <Button text size="small" label="Print / Export" @click="printPage" />
            <Button
              text
              size="small"
              :label="copyLinkState.status === 'copied' ? 'Link Copied' : 'Copy Share Link'"
              @click="copyShareLink"
            />
            <router-link :to="`/share/consultations/${id}`" target="_blank" class="text-sm text-color-secondary">
              View Public Share Page
            </router-link>
          </div>
        </div>
        <p class="text-sm text-color-secondary">
          {{ state.consultation.method }} &middot;
          {{ new Date(state.consultation.createdAt).toLocaleString() }}
        </p>
        <Message v-if="favoriteFormState.status === 'error'" severity="error" class="mt-1">
          {{ favoriteFormState.message }}
        </Message>
        <Message v-if="copyLinkState.status === 'error'" severity="error" class="mt-1">
          {{ copyLinkState.message }}
        </Message>
      </div>

      <div class="flex flex-wrap align-items-start gap-6">
        <div>
          <h2 class="mb-2 text-sm font-medium text-color-secondary">
            Primary — {{ state.consultation.primaryHexagram.kingWenNumber }}.
            {{ state.consultation.primaryHexagram.chineseName }}
          </h2>
          <HexagramLines :lines="state.primaryLines" />
        </div>

        <div>
          <h2 class="mb-2 text-sm font-medium text-color-secondary">
            Resulting — {{ state.consultation.resultingHexagram.kingWenNumber }}.
            {{ state.consultation.resultingHexagram.chineseName }}
          </h2>
          <HexagramLines :lines="state.resultingLines" />
        </div>
      </div>

      <router-link
        :to="`/hexagrams/compare?a=${state.consultation.primaryHexagram.kingWenNumber}&b=${state.consultation.resultingHexagram.kingWenNumber}`"
        class="print-hidden align-self-start text-sm"
      >
        Compare hexagrams
      </router-link>

      <p v-if="state.consultation.changingLinePositions.length === 0" class="text-color-secondary">
        No changing lines.
      </p>
      <p v-else class="text-color-secondary">
        Changing lines: {{ state.consultation.changingLinePositions.join(', ') }}
      </p>

      <div>
        <p v-if="state.consultation.followUpTo" class="text-sm text-color-secondary">
          Follow-up to:
          <router-link :to="`/consultations/${state.consultation.followUpTo.id}`">
            {{ state.consultation.followUpTo.question }}
          </router-link>
        </p>

        <div v-if="state.consultation.followUps.length > 0" class="mt-1">
          <h2 class="text-sm font-medium text-color-secondary">Follow-ups</h2>
          <ul class="mt-1 flex flex-column gap-1 list-none p-0">
            <li v-for="followUp in state.consultation.followUps" :key="followUp.id">
              <router-link :to="`/consultations/${followUp.id}`" class="text-sm">
                {{ followUp.question }}
              </router-link>
            </li>
          </ul>
        </div>

        <router-link
          :to="`/consultations/new?followUpTo=${state.consultation.id}`"
          class="print-hidden mt-1 inline-block text-sm"
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
        class="flex flex-column gap-3"
      >
        <div v-if="repeats.primaryHexagram.length > 0">
          <h2 class="text-sm font-medium text-color-secondary">Same primary hexagram before</h2>
          <ul class="mt-1 flex flex-column gap-1 list-none p-0">
            <li v-for="match in repeats.primaryHexagram" :key="match.id">
              <router-link :to="`/consultations/${match.id}`" class="text-sm">
                {{ match.question }}
              </router-link>
            </li>
          </ul>
        </div>

        <div v-if="repeats.resultingHexagram.length > 0">
          <h2 class="text-sm font-medium text-color-secondary">Same resulting hexagram before</h2>
          <ul class="mt-1 flex flex-column gap-1 list-none p-0">
            <li v-for="match in repeats.resultingHexagram" :key="match.id">
              <router-link :to="`/consultations/${match.id}`" class="text-sm">
                {{ match.question }}
              </router-link>
            </li>
          </ul>
        </div>

        <div v-if="repeats.changingLines.length > 0">
          <h2 class="text-sm font-medium text-color-secondary">Same changing lines before</h2>
          <ul class="mt-1 flex flex-column gap-1 list-none p-0">
            <li v-for="match in repeats.changingLines" :key="match.id">
              <router-link :to="`/consultations/${match.id}`" class="text-sm">
                {{ match.question }}
              </router-link>
            </li>
          </ul>
        </div>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-color-secondary">Notes</h2>
        <ul v-if="state.consultation.notes.length > 0" class="mb-3 flex flex-column gap-2 list-none p-0">
          <li v-for="(note, index) in state.consultation.notes" :key="index">
            <span class="text-xs tracking-wide text-color-secondary uppercase">{{ note.label }}</span>
            <p class="mt-1 mb-0">{{ note.text }}</p>
          </li>
        </ul>

        <form class="print-hidden flex flex-column gap-2" @submit.prevent="addNote">
          <div class="flex gap-2">
            <Select v-model="noteLabel" :options="noteLabelOptions" option-label="label" option-value="value" />
            <Textarea v-model="noteText" rows="2" required maxlength="5000" placeholder="Add a note…" class="flex-1" />
          </div>
          <Message v-if="noteFormState.status === 'error'" severity="error">{{ noteFormState.message }}</Message>
          <Button
            type="submit"
            :disabled="noteFormState.status === 'submitting'"
            :label="noteFormState.status === 'submitting' ? 'Adding…' : 'Add Note'"
            class="align-self-start"
          />
        </form>
      </div>

      <div>
        <div v-if="state.consultation.tags.length > 0" class="mb-3 flex gap-2 flex-wrap">
          <Tag v-for="tag in state.consultation.tags" :key="tag" :value="tag" severity="secondary" rounded />
        </div>

        <form class="print-hidden flex flex-column gap-2" @submit.prevent="addTag">
          <div class="flex gap-2">
            <InputText v-model="tagText" type="text" required placeholder="Add a tag…" class="flex-1" />
            <Button
              type="submit"
              :disabled="tagFormState.status === 'submitting'"
              :label="tagFormState.status === 'submitting' ? 'Adding…' : 'Add Tag'"
            />
          </div>
          <Message v-if="tagFormState.status === 'error'" severity="error">{{ tagFormState.message }}</Message>
        </form>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-color-secondary">Context</h2>
        <form class="flex flex-column gap-3" @submit.prevent="saveContext">
          <div>
            <label for="edit-context" class="mb-1 block text-xs text-color-secondary">Context</label>
            <Textarea id="edit-context" v-model="contextForm.context" rows="2" maxlength="5000" class="w-full" />
          </div>
          <div>
            <label for="edit-what-happened-before" class="mb-1 block text-xs text-color-secondary">
              What happened before
            </label>
            <Textarea
              id="edit-what-happened-before"
              v-model="contextForm.whatHappenedBefore"
              rows="2"
              maxlength="5000"
              class="w-full"
            />
          </div>
          <div>
            <label for="edit-what-to-understand" class="mb-1 block text-xs text-color-secondary">
              What you want to understand
            </label>
            <Textarea
              id="edit-what-to-understand"
              v-model="contextForm.whatUserWantsToUnderstand"
              rows="2"
              maxlength="5000"
              class="w-full"
            />
          </div>
          <div>
            <label for="edit-background" class="mb-1 block text-xs text-color-secondary">
              Background information
            </label>
            <Textarea
              id="edit-background"
              v-model="contextForm.backgroundInformation"
              rows="2"
              maxlength="5000"
              class="w-full"
            />
          </div>
          <div>
            <label for="edit-initial-interpretation" class="mb-1 block text-xs text-color-secondary">
              Your initial interpretation
            </label>
            <Textarea
              id="edit-initial-interpretation"
              v-model="contextForm.initialInterpretation"
              rows="2"
              maxlength="5000"
              class="w-full"
            />
          </div>
          <Message v-if="contextFormState.status === 'error'" severity="error">
            {{ contextFormState.message }}
          </Message>
          <Button
            type="submit"
            :disabled="contextFormState.status === 'submitting'"
            :label="contextFormState.status === 'submitting' ? 'Saving…' : 'Save Context'"
            class="print-hidden align-self-start"
          />
        </form>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-color-secondary">Outcome</h2>
        <p v-if="state.consultation.outcome" class="mb-2 text-xs text-color-secondary">
          Last recorded {{ new Date(state.consultation.outcome.recordedAt).toLocaleString() }}
        </p>
        <form class="flex flex-column gap-3" @submit.prevent="saveOutcome">
          <div>
            <label for="edit-what-happened" class="mb-1 block text-xs text-color-secondary">
              What actually happened
            </label>
            <Textarea
              id="edit-what-happened"
              v-model="outcomeForm.whatActuallyHappened"
              rows="2"
              maxlength="5000"
              class="w-full"
            />
          </div>
          <div>
            <label for="edit-outcome" class="mb-1 block text-xs text-color-secondary">Outcome</label>
            <Textarea id="edit-outcome" v-model="outcomeForm.outcome" rows="2" maxlength="5000" class="w-full" />
          </div>
          <div>
            <label for="edit-reflection" class="mb-1 block text-xs text-color-secondary">Reflection</label>
            <Textarea id="edit-reflection" v-model="outcomeForm.reflection" rows="2" maxlength="5000" class="w-full" />
          </div>
          <div
            v-if="outcomeForm.interpretationLens"
            class="flex align-items-start justify-content-between gap-3 border-round surface-100 p-2 text-sm"
          >
            <p class="capitalize m-0">
              Linked: {{ outcomeForm.interpretationLens }} — {{ outcomeForm.interpretationSummary }}
            </p>
            <Button
              text
              size="small"
              label="Unlink"
              class="print-hidden flex-shrink-0"
              @click="unlinkInterpretationFromOutcome"
            />
          </div>
          <Message v-if="outcomeFormState.status === 'error'" severity="error">
            {{ outcomeFormState.message }}
          </Message>
          <Button
            type="submit"
            :disabled="outcomeFormState.status === 'submitting'"
            :label="outcomeFormState.status === 'submitting' ? 'Saving…' : 'Save Outcome'"
            class="print-hidden align-self-start"
          />
        </form>
      </div>

      <section
        class="border-2 border-dashed surface-border border-round p-4"
        :class="{ 'print-hidden': interpretationState.status !== 'loaded' }"
      >
        <h2 class="mb-3 text-sm font-medium text-color-secondary">AI Interpretation</h2>

        <div class="print-hidden mb-3 flex flex-wrap gap-2">
          <Button
            v-for="lens in INTERPRETATION_LENSES"
            :key="lens"
            :aria-pressed="selectedLens === lens"
            rounded
            size="small"
            class="capitalize"
            :outlined="selectedLens !== lens"
            @click="selectedLens = lens"
          >
            {{ lens }}
            <span v-if="interpretationStates[lens].status === 'loaded'" aria-hidden="true">✓</span>
          </Button>
        </div>

        <Button
          class="print-hidden"
          :disabled="interpretationState.status === 'loading'"
          :label="
            interpretationState.status === 'loading'
              ? 'Interpreting…'
              : interpretationState.status === 'loaded'
                ? 'Regenerate'
                : 'Get Interpretation'
          "
          @click="getInterpretation"
        />

        <Message v-if="interpretationState.status === 'error'" severity="error" class="mt-3">
          {{ interpretationState.message }}
        </Message>

        <div v-else-if="interpretationState.status === 'loaded'" class="mt-4 flex flex-column gap-3">
          <p class="m-0">{{ interpretationState.interpretation.summary }}</p>

          <Button
            outlined
            size="small"
            label="Link to Outcome"
            class="print-hidden align-self-start"
            @click="linkInterpretationToOutcome"
          />

          <div>
            <h3 class="text-xs font-medium text-color-secondary uppercase">Core theme</h3>
            <p class="mt-1 mb-0">{{ interpretationState.interpretation.coreTheme }}</p>
          </div>

          <div>
            <h3 class="text-xs font-medium text-color-secondary uppercase">Situation</h3>
            <p class="mt-1 mb-0">{{ interpretationState.interpretation.situation }}</p>
          </div>

          <div v-if="interpretationState.interpretation.changingLineMeaning">
            <h3 class="text-xs font-medium text-color-secondary uppercase">Changing line meaning</h3>
            <p class="mt-1 mb-0">{{ interpretationState.interpretation.changingLineMeaning }}</p>
          </div>

          <div v-if="interpretationState.interpretation.transition">
            <h3 class="text-xs font-medium text-color-secondary uppercase">Transition</h3>
            <p class="mt-1 mb-0">{{ interpretationState.interpretation.transition }}</p>
          </div>

          <div>
            <h3 class="text-xs font-medium text-color-secondary uppercase">Practical reflection</h3>
            <p class="mt-1 mb-0">{{ interpretationState.interpretation.practicalReflection }}</p>
          </div>

          <div v-if="interpretationState.interpretation.uncertainties.length > 0">
            <h3 class="text-xs font-medium text-color-secondary uppercase">Uncertainties</h3>
            <ul class="list-inside list-disc m-0 pl-0">
              <li v-for="(note, index) in interpretationState.interpretation.uncertainties" :key="index">
                {{ note }}
              </li>
            </ul>
          </div>

          <div v-if="interpretationState.interpretation.sourceReferences.length > 0">
            <h3 class="text-xs font-medium text-color-secondary uppercase">Sources</h3>
            <ul class="list-inside list-disc text-sm text-color-secondary m-0 pl-0">
              <li
                v-for="(sourceRef, index) in interpretationState.interpretation.sourceReferences"
                :key="index"
              >
                {{ sourceRef }}
              </li>
            </ul>
          </div>

          <div class="border-top-1 surface-border pt-3">
            <h3 class="mb-2 text-xs font-medium text-color-secondary uppercase">Follow-up questions</h3>

            <ul v-if="currentConversation.length > 0" class="mb-3 flex flex-column gap-3 list-none p-0">
              <li v-for="(exchange, index) in currentConversation" :key="index">
                <p class="text-sm font-medium m-0">{{ exchange.question }}</p>
                <p class="text-sm text-color-secondary mt-1 mb-0">{{ exchange.answer }}</p>
              </li>
            </ul>

            <form class="print-hidden flex flex-column gap-2" @submit.prevent="askFollowUp">
              <Textarea
                v-model="followUpText"
                rows="2"
                required
                maxlength="2000"
                placeholder="Ask a follow-up question…"
                class="w-full"
              />
              <Message v-if="followUpFormState.status === 'error'" severity="error">
                {{ followUpFormState.message }}
              </Message>
              <Button
                type="submit"
                :disabled="followUpFormState.status === 'submitting'"
                :label="followUpFormState.status === 'submitting' ? 'Asking…' : 'Ask'"
                class="align-self-start"
              />
            </form>
          </div>
        </div>
      </section>
    </div>
  </main>
</template>
