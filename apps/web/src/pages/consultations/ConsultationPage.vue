<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
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
import { announce } from '../../shared/lib/announce'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'
import { useToastSuccess } from '../../shared/lib/useToastSuccess'
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

const { t, locale } = useI18n()
const { notifySaved } = useToastSuccess()
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

// Page-level load (SPEC-039, REQ-A11Y-004).
useStatusAnnouncer(
  computed(() => state.value.status),
  (status) => (status === 'not-found' ? t('consultationPage.notFound') : undefined),
)

// The AI interpretation section loads independently of the page; announce its transitions too,
// since it's a deliberate user action with its own latency and error surface.
watch(
  () => interpretationState.value.status,
  (status, previous) => {
    if (status === previous) return
    if (status === 'loading') announce(t('consultationPage.interpreting'))
    else if (status === 'error') announce(t('consultationPage.getInterpretationError'))
    else if (status === 'loaded') announce(t('a11y.loaded'))
  },
)

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
const noteLabelOptions = computed(() => [
  { label: t('consultationPage.noteLabelBefore'), value: 'before' },
  { label: t('consultationPage.noteLabelAfter'), value: 'after' },
  { label: t('consultationPage.noteLabelLater'), value: 'later' },
])
const noteText = ref('')
const noteFormState = ref<FormState>({ status: 'idle' })

const NOTE_LABEL_KEYS = {
  before: 'consultationPage.noteLabelBefore',
  after: 'consultationPage.noteLabelAfter',
  later: 'consultationPage.noteLabelLater',
} as const

function noteLabelText(label: string): string {
  return t(NOTE_LABEL_KEYS[label as keyof typeof NOTE_LABEL_KEYS] ?? label)
}

const LENS_LABEL_KEYS = {
  general: 'consultationPage.lensGeneral',
  psychological: 'consultationPage.lensPsychological',
  practical: 'consultationPage.lensPractical',
  symbolic: 'consultationPage.lensSymbolic',
} as const

function lensLabel(lens: string): string {
  return t(LENS_LABEL_KEYS[lens as keyof typeof LENS_LABEL_KEYS] ?? lens)
}

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
    notifySaved('consultationPage.noteAdded')
  } catch (error) {
    noteFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('consultationPage.addNoteError'),
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
    notifySaved('consultationPage.tagAdded')
  } catch (error) {
    tagFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('consultationPage.addTagError'),
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
    notifySaved('consultationPage.contextSaved')
  } catch (error) {
    contextFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('consultationPage.saveContextError'),
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
    notifySaved('consultationPage.outcomeSaved')
  } catch (error) {
    outcomeFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('consultationPage.saveOutcomeError'),
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
      message: error instanceof Error ? error.message : t('consultationPage.copyLinkError'),
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
      message: error instanceof Error ? error.message : t('consultationPage.favoriteError'),
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
    const interpretation = await requestInterpretation(id.value, lens, locale.value)
    interpretationStates.value[lens] = { status: 'loaded', interpretation }
  } catch (error) {
    interpretationStates.value[lens] = {
      status: 'error',
      message: error instanceof Error ? error.message : t('consultationPage.getInterpretationError'),
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
    const { answer } = await requestFollowUp(id.value, question, conversations.value[lens], locale.value)
    conversations.value[lens] = [...conversations.value[lens], { question, answer }]
    followUpText.value = ''
    followUpFormState.value = { status: 'idle' }
  } catch (error) {
    followUpFormState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('consultationPage.askFollowUpError'),
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
      message: error instanceof Error ? error.message : t('consultationPage.loadError'),
    }
  }
})
</script>

<template>
  <main id="main" tabindex="-1" class="container-sm mx-auto p-4">
    <router-link to="/consultations" class="print-hidden text-sm text-color-secondary">
      &larr; {{ t('history.title') }}
    </router-link>

    <p v-if="state.status === 'loading'" class="mt-4 text-color-secondary">{{ t('common.loading') }}</p>
    <p v-else-if="state.status === 'not-found'" class="mt-4 text-color-secondary">
      {{ t('consultationPage.notFound') }}
    </p>
    <Message v-else-if="state.status === 'error'" severity="error" role="alert" class="mt-4">{{ state.message }}</Message>

    <div v-else class="mt-4 flex flex-column gap-5">
      <div>
        <div class="flex flex-column sm:flex-row sm:align-items-start sm:justify-content-between gap-3">
          <h1 class="text-xl font-semibold m-0">{{ state.consultation.question }}</h1>
          <div class="print-hidden flex gap-3 flex-wrap">
            <Button
              text
              size="small"
              :disabled="favoriteFormState.status === 'submitting'"
              :label="state.consultation.favorite ? t('hexagramDetail.favorited') : t('hexagramDetail.addToFavorites')"
              @click="toggleFavorite"
            />
            <Button text size="small" :label="t('consultationPage.printExport')" @click="printPage" />
            <Button
              text
              size="small"
              :label="copyLinkState.status === 'copied' ? t('consultationPage.linkCopied') : t('consultationPage.copyShareLink')"
              @click="copyShareLink"
            />
            <router-link :to="`/share/consultations/${id}`" target="_blank" class="text-sm text-color-secondary">
              {{ t('consultationPage.viewPublicSharePage') }}
            </router-link>
          </div>
        </div>
        <p class="text-sm text-color-secondary">
          {{ state.consultation.method }} &middot;
          {{ new Date(state.consultation.createdAt).toLocaleString() }}
        </p>
        <Message v-if="favoriteFormState.status === 'error'" severity="error" role="alert" class="mt-1">
          {{ favoriteFormState.message }}
        </Message>
        <Message v-if="copyLinkState.status === 'error'" severity="error" role="alert" class="mt-1">
          {{ copyLinkState.message }}
        </Message>
      </div>

      <div class="flex flex-wrap align-items-start gap-6">
        <div>
          <h2 class="mb-2 text-sm font-medium text-color-secondary">
            {{
              t('consultation.primaryHeading', {
                number: state.consultation.primaryHexagram.kingWenNumber,
                name: state.consultation.primaryHexagram.chineseName,
              })
            }}
          </h2>
          <HexagramLines :lines="state.primaryLines" />
        </div>

        <div>
          <h2 class="mb-2 text-sm font-medium text-color-secondary">
            {{
              t('consultation.resultingHeading', {
                number: state.consultation.resultingHexagram.kingWenNumber,
                name: state.consultation.resultingHexagram.chineseName,
              })
            }}
          </h2>
          <HexagramLines :lines="state.resultingLines" />
        </div>
      </div>

      <router-link
        :to="`/hexagrams/compare?a=${state.consultation.primaryHexagram.kingWenNumber}&b=${state.consultation.resultingHexagram.kingWenNumber}`"
        class="print-hidden align-self-start text-sm"
      >
        {{ t('consultationPage.compareHexagrams') }}
      </router-link>

      <p v-if="state.consultation.changingLinePositions.length === 0" class="text-color-secondary">
        {{ t('consultation.noChangingLines') }}
      </p>
      <p v-else class="text-color-secondary">
        {{ t('consultation.changingLines', { list: state.consultation.changingLinePositions.join(', ') }) }}
      </p>

      <div>
        <p v-if="state.consultation.followUpTo" class="text-sm text-color-secondary">
          {{ t('newConsultation.followUpTo') }}
          <router-link :to="`/consultations/${state.consultation.followUpTo.id}`">
            {{ state.consultation.followUpTo.question }}
          </router-link>
        </p>

        <div v-if="state.consultation.followUps.length > 0" class="mt-1">
          <h2 class="text-sm font-medium text-color-secondary">{{ t('consultationPage.followUps') }}</h2>
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
          {{ t('consultationPage.createFollowUp') }}
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
          <h2 class="text-sm font-medium text-color-secondary">{{ t('consultationPage.samePrimaryBefore') }}</h2>
          <ul class="mt-1 flex flex-column gap-1 list-none p-0">
            <li v-for="match in repeats.primaryHexagram" :key="match.id">
              <router-link :to="`/consultations/${match.id}`" class="text-sm">
                {{ match.question }}
              </router-link>
            </li>
          </ul>
        </div>

        <div v-if="repeats.resultingHexagram.length > 0">
          <h2 class="text-sm font-medium text-color-secondary">{{ t('consultationPage.sameResultingBefore') }}</h2>
          <ul class="mt-1 flex flex-column gap-1 list-none p-0">
            <li v-for="match in repeats.resultingHexagram" :key="match.id">
              <router-link :to="`/consultations/${match.id}`" class="text-sm">
                {{ match.question }}
              </router-link>
            </li>
          </ul>
        </div>

        <div v-if="repeats.changingLines.length > 0">
          <h2 class="text-sm font-medium text-color-secondary">{{ t('consultationPage.sameChangingBefore') }}</h2>
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
        <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ t('consultation.notes') }}</h2>
        <ul v-if="state.consultation.notes.length > 0" class="mb-3 flex flex-column gap-2 list-none p-0">
          <li v-for="(note, index) in state.consultation.notes" :key="index">
            <span class="text-xs tracking-wide text-color-secondary uppercase">{{ noteLabelText(note.label) }}</span>
            <p class="mt-1 mb-0">{{ note.text }}</p>
          </li>
        </ul>

        <form class="print-hidden flex flex-column gap-2" @submit.prevent="addNote">
          <div class="flex gap-2">
            <Select v-model="noteLabel" :options="noteLabelOptions" option-label="label" option-value="value" />
            <Textarea
              v-model="noteText"
              rows="2"
              required
              maxlength="5000"
              :placeholder="t('consultationPage.addNotePlaceholder')"
              class="flex-1"
            />
          </div>
          <Message v-if="noteFormState.status === 'error'" severity="error" role="alert">{{ noteFormState.message }}</Message>
          <Button
            type="submit"
            :disabled="noteFormState.status === 'submitting'"
            :label="noteFormState.status === 'submitting' ? t('journal.adding') : t('consultationPage.addNote')"
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
            <InputText
              v-model="tagText"
              type="text"
              required
              :placeholder="t('consultationPage.addTagPlaceholder')"
              class="flex-1"
            />
            <Button
              type="submit"
              :disabled="tagFormState.status === 'submitting'"
              :label="tagFormState.status === 'submitting' ? t('journal.adding') : t('consultationPage.addTag')"
            />
          </div>
          <Message v-if="tagFormState.status === 'error'" severity="error" role="alert">{{ tagFormState.message }}</Message>
        </form>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ t('consultation.context') }}</h2>
        <form class="flex flex-column gap-3" @submit.prevent="saveContext">
          <div>
            <label for="edit-context" class="mb-1 block text-xs text-color-secondary">
              {{ t('contextFields.context') }}
            </label>
            <Textarea id="edit-context" v-model="contextForm.context" rows="2" maxlength="5000" class="w-full" />
          </div>
          <div>
            <label for="edit-what-happened-before" class="mb-1 block text-xs text-color-secondary">
              {{ t('contextFields.whatHappenedBefore') }}
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
              {{ t('contextFields.whatUserWantsToUnderstand') }}
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
              {{ t('contextFields.backgroundInformation') }}
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
              {{ t('contextFields.initialInterpretation') }}
            </label>
            <Textarea
              id="edit-initial-interpretation"
              v-model="contextForm.initialInterpretation"
              rows="2"
              maxlength="5000"
              class="w-full"
            />
          </div>
          <Message v-if="contextFormState.status === 'error'" severity="error" role="alert">
            {{ contextFormState.message }}
          </Message>
          <Button
            type="submit"
            :disabled="contextFormState.status === 'submitting'"
            :label="contextFormState.status === 'submitting' ? t('consultationPage.saving') : t('consultationPage.saveContext')"
            class="print-hidden align-self-start"
          />
        </form>
      </div>

      <div>
        <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ t('consultation.outcome') }}</h2>
        <p v-if="state.consultation.outcome" class="mb-2 text-xs text-color-secondary">
          {{
            t('consultationPage.lastRecorded', {
              date: new Date(state.consultation.outcome.recordedAt).toLocaleString(),
            })
          }}
        </p>
        <form class="flex flex-column gap-3" @submit.prevent="saveOutcome">
          <div>
            <label for="edit-what-happened" class="mb-1 block text-xs text-color-secondary">
              {{ t('consultation.whatActuallyHappened') }}
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
            <label for="edit-outcome" class="mb-1 block text-xs text-color-secondary">
              {{ t('consultation.outcome') }}
            </label>
            <Textarea id="edit-outcome" v-model="outcomeForm.outcome" rows="2" maxlength="5000" class="w-full" />
          </div>
          <div>
            <label for="edit-reflection" class="mb-1 block text-xs text-color-secondary">
              {{ t('consultation.reflection') }}
            </label>
            <Textarea id="edit-reflection" v-model="outcomeForm.reflection" rows="2" maxlength="5000" class="w-full" />
          </div>
          <div
            v-if="outcomeForm.interpretationLens"
            class="flex align-items-start justify-content-between gap-3 border-round surface-100 p-2 text-sm"
          >
            <p class="m-0">
              {{ t('consultationPage.linkedPrefix') }}
              <span class="capitalize">{{ lensLabel(outcomeForm.interpretationLens) }}</span>
              — {{ outcomeForm.interpretationSummary }}
            </p>
            <Button
              text
              size="small"
              :label="t('consultationPage.unlink')"
              class="print-hidden flex-shrink-0"
              @click="unlinkInterpretationFromOutcome"
            />
          </div>
          <Message v-if="outcomeFormState.status === 'error'" severity="error" role="alert">
            {{ outcomeFormState.message }}
          </Message>
          <Button
            type="submit"
            :disabled="outcomeFormState.status === 'submitting'"
            :label="outcomeFormState.status === 'submitting' ? t('consultationPage.saving') : t('consultationPage.saveOutcome')"
            class="print-hidden align-self-start"
          />
        </form>
      </div>

      <section
        class="border-2 border-dashed surface-border border-round p-4"
        :class="{ 'print-hidden': interpretationState.status !== 'loaded' }"
      >
        <h2 class="mb-3 text-sm font-medium text-color-secondary">{{ t('consultationPage.aiInterpretation') }}</h2>

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
            {{ lensLabel(lens) }}
            <span v-if="interpretationStates[lens].status === 'loaded'" aria-hidden="true">✓</span>
          </Button>
        </div>

        <Button
          class="print-hidden"
          :disabled="interpretationState.status === 'loading'"
          :label="
            interpretationState.status === 'loading'
              ? t('consultationPage.interpreting')
              : interpretationState.status === 'loaded'
                ? t('consultationPage.regenerate')
                : t('consultationPage.getInterpretation')
          "
          @click="getInterpretation"
        />

        <Message v-if="interpretationState.status === 'error'" severity="error" role="alert" class="mt-3">
          {{ interpretationState.message }}
        </Message>

        <div v-else-if="interpretationState.status === 'loaded'" class="mt-4 flex flex-column gap-3">
          <p class="m-0">{{ interpretationState.interpretation.summary }}</p>

          <Button
            outlined
            size="small"
            :label="t('consultationPage.linkToOutcome')"
            class="print-hidden align-self-start"
            @click="linkInterpretationToOutcome"
          />

          <div>
            <h3 class="text-xs font-medium text-color-secondary uppercase">{{ t('consultationPage.coreTheme') }}</h3>
            <p class="mt-1 mb-0">{{ interpretationState.interpretation.coreTheme }}</p>
          </div>

          <div>
            <h3 class="text-xs font-medium text-color-secondary uppercase">{{ t('consultationPage.situation') }}</h3>
            <p class="mt-1 mb-0">{{ interpretationState.interpretation.situation }}</p>
          </div>

          <div v-if="interpretationState.interpretation.changingLineMeaning">
            <h3 class="text-xs font-medium text-color-secondary uppercase">
              {{ t('consultationPage.changingLineMeaning') }}
            </h3>
            <p class="mt-1 mb-0">{{ interpretationState.interpretation.changingLineMeaning }}</p>
          </div>

          <div v-if="interpretationState.interpretation.transition">
            <h3 class="text-xs font-medium text-color-secondary uppercase">{{ t('consultationPage.transition') }}</h3>
            <p class="mt-1 mb-0">{{ interpretationState.interpretation.transition }}</p>
          </div>

          <div>
            <h3 class="text-xs font-medium text-color-secondary uppercase">
              {{ t('consultationPage.practicalReflection') }}
            </h3>
            <p class="mt-1 mb-0">{{ interpretationState.interpretation.practicalReflection }}</p>
          </div>

          <div v-if="interpretationState.interpretation.uncertainties.length > 0">
            <h3 class="text-xs font-medium text-color-secondary uppercase">
              {{ t('consultationPage.uncertainties') }}
            </h3>
            <ul class="list-inside list-disc m-0 pl-0">
              <li v-for="(note, index) in interpretationState.interpretation.uncertainties" :key="index">
                {{ note }}
              </li>
            </ul>
          </div>

          <div v-if="interpretationState.interpretation.sourceReferences.length > 0">
            <h3 class="text-xs font-medium text-color-secondary uppercase">{{ t('consultationPage.sources') }}</h3>
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
            <h3 class="mb-2 text-xs font-medium text-color-secondary uppercase">
              {{ t('consultationPage.followUpQuestions') }}
            </h3>

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
                :placeholder="t('consultationPage.askPlaceholder')"
                class="w-full"
              />
              <Message v-if="followUpFormState.status === 'error'" severity="error" role="alert">
                {{ followUpFormState.message }}
              </Message>
              <Button
                type="submit"
                :disabled="followUpFormState.status === 'submitting'"
                :label="followUpFormState.status === 'submitting' ? t('consultationPage.asking') : t('consultationPage.ask')"
                class="align-self-start"
              />
            </form>
          </div>
        </div>
      </section>
    </div>
  </main>
</template>
