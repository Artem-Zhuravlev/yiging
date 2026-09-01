<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { announce } from '../../shared/lib/announce'
import Textarea from 'primevue/textarea'
import RadioButton from 'primevue/radiobutton'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Panel from 'primevue/panel'
import { createConsultation, fetchConsultation } from '../../entities/consultation/api'
import type {
  Consultation,
  ManualLine,
  NewConsultationRequest,
  SelectableCastingMethod,
} from '../../entities/consultation/model'
import { ApiError } from '../../shared/api/http'
import CastingReveal from '../../features/casting-reveal/CastingReveal.vue'
import {
  isCastingRevealEnabled,
  prefersReducedMotion,
  setCastingRevealEnabled,
} from '../../features/casting-reveal/castingRevealPreference'

type FormState = { status: 'idle' } | { status: 'submitting' } | { status: 'error'; message: string }

const question = ref('')
const method = ref<SelectableCastingMethod>('three_coins')
const lines = ref<ManualLine[]>(
  Array.from({ length: 6 }, (): ManualLine => ({ polarity: 'yang', changing: false })),
)
const state = ref<FormState>({ status: 'idle' })

const context = ref('')
const whatHappenedBefore = ref('')
const whatUserWantsToUnderstand = ref('')
const backgroundInformation = ref('')
const initialInterpretation = ref('')

// Set once, from ?followUpTo= on mount — never edited by the user, just carried through to the
// request and shown as a small "Follow-up to" banner (fetched only for a readable label; the
// link itself only needs the id, already known from the query param).
const followUpToConsultationId = ref<string | undefined>(undefined)
const followUpToQuestion = ref<string | null>(null)

// After a successful cast we either navigate straight to the detail page (reduced motion, or
// the user opted out) or hand off to the reveal animation (SPEC-042), which does its own
// navigation once it finishes / is skipped.
const revealEnabled = ref(isCastingRevealEnabled())
const castResult = ref<Consultation | null>(null)

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

// The submit button also flips to a "Casting…" label, but that visual-only change is silent to a
// screen reader; announce the transition and any resulting error explicitly (SPEC-039,
// REQ-A11Y-005). Success navigates away, where the router's focus hook takes over.
watch(
  () => state.value.status,
  (status, previous) => {
    if (status === previous) return
    if (status === 'submitting') announce(t('newConsultation.casting'))
    else if (status === 'error') announce(t('newConsultation.createError'))
  },
)

// Blank fields are omitted from the request entirely (undefined), rather than sent as empty
// strings — an untouched optional field should look untouched to the API, not like the user
// explicitly cleared something that was never there.
function orUndefined(value: string): string | undefined {
  return value.trim() === '' ? undefined : value
}

async function submit(): Promise<void> {
  state.value = { status: 'submitting' }

  const request: NewConsultationRequest = {
    ...(method.value === 'manual'
      ? { question: question.value, method: 'manual' as const, lines: lines.value }
      : { question: question.value, method: method.value }),
    context: orUndefined(context.value),
    whatHappenedBefore: orUndefined(whatHappenedBefore.value),
    whatUserWantsToUnderstand: orUndefined(whatUserWantsToUnderstand.value),
    backgroundInformation: orUndefined(backgroundInformation.value),
    initialInterpretation: orUndefined(initialInterpretation.value),
    followUpToConsultationId: followUpToConsultationId.value,
  }

  try {
    const consultation = await createConsultation(request)
    if (revealEnabled.value && !prefersReducedMotion()) {
      castResult.value = consultation
      return
    }
    await router.push(`/consultations/${consultation.id}`)
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof ApiError ? error.message : t('newConsultation.createError'),
    }
  }
}

onMounted(async () => {
  const queryValue = route.query.followUpTo
  const id = typeof queryValue === 'string' ? queryValue : undefined

  if (id === undefined) {
    return
  }

  followUpToConsultationId.value = id
  try {
    const target = await fetchConsultation(id)
    followUpToQuestion.value = target.question
  } catch {
    // A stale/invalid ?followUpTo= just means no label is shown; the link is still submitted
    // as-is and the backend validates it exists at save time.
  }
})
</script>

<template>
  <main id="main" tabindex="-1" class="container-sm mx-auto p-4">
    <CastingReveal v-if="castResult" :consultation="castResult" />

    <template v-else>
      <h1 class="text-2xl font-semibold m-0">{{ t('newConsultation.title') }}</h1>
      <p v-if="followUpToConsultationId" class="mt-1 text-sm text-color-secondary">
        {{ t('newConsultation.followUpTo') }} <span v-if="followUpToQuestion">{{ followUpToQuestion }}</span>
        <span v-else>…</span>
      </p>

      <form class="mt-4 flex flex-column gap-4" @submit.prevent="submit">
      <div>
        <label for="question" class="mb-1 block text-sm font-medium">{{ t('newConsultation.question') }}</label>
        <Textarea id="question" v-model="question" rows="3" required maxlength="2000" class="w-full" />
      </div>

      <fieldset class="border-none p-0 m-0">
        <legend class="mb-2 text-sm font-medium">{{ t('newConsultation.method') }}</legend>
        <label class="mr-4 inline-flex align-items-center gap-2">
          <RadioButton v-model="method" name="method" value="three_coins" />
          {{ t('newConsultation.threeCoins') }}
        </label>
        <label class="mr-4 inline-flex align-items-center gap-2">
          <RadioButton v-model="method" name="method" value="yarrow" />
          {{ t('newConsultation.yarrow') }}
        </label>
        <label class="inline-flex align-items-center gap-2">
          <RadioButton v-model="method" name="method" value="manual" />
          {{ t('newConsultation.manual') }}
        </label>
        <p v-if="method === 'yarrow'" class="mt-2 mb-0 text-xs text-color-secondary">
          {{ t('newConsultation.yarrowHint') }}
        </p>
      </fieldset>

      <fieldset v-if="method === 'manual'" class="flex flex-column gap-2 border-none p-0 m-0">
        <legend class="mb-2 text-sm font-medium">{{ t('hexagramEditor.linesTopToBottom') }}</legend>
        <fieldset
          v-for="position in [6, 5, 4, 3, 2, 1]"
          :key="position"
          class="flex align-items-center gap-3 border-none p-0 m-0"
          :data-position="position"
        >
          <legend class="sr-only">{{ t('newConsultation.lineGroupLabel', { n: position }) }}</legend>
          <span class="w-2rem text-sm text-color-secondary">{{ position }}</span>
          <label class="inline-flex align-items-center gap-2">
            <RadioButton v-model="lines[position - 1]!.polarity" :name="`polarity-${position}`" value="yang" />
            {{ t('common.yang') }}
          </label>
          <label class="inline-flex align-items-center gap-2">
            <RadioButton v-model="lines[position - 1]!.polarity" :name="`polarity-${position}`" value="yin" />
            {{ t('common.yin') }}
          </label>
          <label class="inline-flex align-items-center gap-2">
            <Checkbox v-model="lines[position - 1]!.changing" binary />
            {{ t('newConsultation.changing') }}
          </label>
        </fieldset>
      </fieldset>

      <details>
        <summary class="cursor-pointer text-sm font-medium">{{ t('newConsultation.addContext') }}</summary>
        <Panel class="mt-3">
          <div class="flex flex-column gap-3">
            <div>
              <label for="context" class="mb-1 block text-sm">{{ t('contextFields.context') }}</label>
              <Textarea id="context" v-model="context" rows="2" maxlength="5000" class="w-full" />
            </div>
            <div>
              <label for="what-happened-before" class="mb-1 block text-sm">
                {{ t('contextFields.whatHappenedBefore') }}
              </label>
              <Textarea id="what-happened-before" v-model="whatHappenedBefore" rows="2" maxlength="5000" class="w-full" />
            </div>
            <div>
              <label for="what-to-understand" class="mb-1 block text-sm">
                {{ t('contextFields.whatUserWantsToUnderstand') }}
              </label>
              <Textarea
                id="what-to-understand"
                v-model="whatUserWantsToUnderstand"
                rows="2"
                maxlength="5000"
                class="w-full"
              />
            </div>
            <div>
              <label for="background" class="mb-1 block text-sm">
                {{ t('contextFields.backgroundInformation') }}
              </label>
              <Textarea id="background" v-model="backgroundInformation" rows="2" maxlength="5000" class="w-full" />
            </div>
            <div>
              <label for="initial-interpretation" class="mb-1 block text-sm">
                {{ t('contextFields.initialInterpretation') }}
              </label>
              <Textarea
                id="initial-interpretation"
                v-model="initialInterpretation"
                rows="2"
                maxlength="5000"
                class="w-full"
              />
            </div>
          </div>
        </Panel>
      </details>

      <Message v-if="state.status === 'error'" severity="error" role="alert">{{ state.message }}</Message>

      <label class="inline-flex align-items-center gap-2 text-sm text-color-secondary">
        <Checkbox
          v-model="revealEnabled"
          binary
          @update:model-value="setCastingRevealEnabled($event as boolean)"
        />
        {{ t('newConsultation.showCastingAnimation') }}
      </label>

      <Button
        type="submit"
        :disabled="state.status === 'submitting'"
        :aria-busy="state.status === 'submitting'"
        :label="state.status === 'submitting' ? t('newConsultation.casting') : t('newConsultation.cast')"
        class="align-self-start"
      />
      </form>
    </template>
  </main>
</template>
