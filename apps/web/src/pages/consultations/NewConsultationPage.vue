<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { createConsultation, fetchConsultation } from '../../entities/consultation/api'
import type { ManualLine, NewConsultationRequest, SelectableCastingMethod } from '../../entities/consultation/model'
import { ApiError } from '../../shared/api/http'

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

const route = useRoute()
const router = useRouter()

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
      : { question: question.value, method: 'three_coins' as const }),
    context: orUndefined(context.value),
    whatHappenedBefore: orUndefined(whatHappenedBefore.value),
    whatUserWantsToUnderstand: orUndefined(whatUserWantsToUnderstand.value),
    backgroundInformation: orUndefined(backgroundInformation.value),
    initialInterpretation: orUndefined(initialInterpretation.value),
    followUpToConsultationId: followUpToConsultationId.value,
  }

  try {
    const consultation = await createConsultation(request)
    await router.push(`/consultations/${consultation.id}`)
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof ApiError ? error.message : 'Failed to create consultation.',
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
  <main class="mx-auto max-w-xl px-6 py-10">
    <h1 class="text-2xl font-semibold tracking-tight">New Consultation</h1>
    <p v-if="followUpToConsultationId" class="mt-1 text-sm text-neutral-500">
      Follow-up to: <span v-if="followUpToQuestion">{{ followUpToQuestion }}</span>
      <span v-else>…</span>
    </p>

    <form class="mt-6 flex flex-col gap-6" @submit.prevent="submit">
      <div>
        <label for="question" class="mb-1 block text-sm font-medium text-neutral-700">
          Question
        </label>
        <textarea
          id="question"
          v-model="question"
          rows="3"
          required
          maxlength="2000"
          class="w-full rounded-md border border-neutral-300 p-2"
        />
      </div>

      <fieldset>
        <legend class="mb-1 text-sm font-medium text-neutral-700">Method</legend>
        <label class="mr-4 inline-flex items-center gap-2">
          <input v-model="method" type="radio" value="three_coins" />
          Three Coins
        </label>
        <label class="inline-flex items-center gap-2">
          <input v-model="method" type="radio" value="manual" />
          Manual
        </label>
      </fieldset>

      <fieldset v-if="method === 'manual'" class="flex flex-col gap-2">
        <legend class="mb-1 text-sm font-medium text-neutral-700">Lines (top to bottom)</legend>
        <div
          v-for="position in [6, 5, 4, 3, 2, 1]"
          :key="position"
          class="flex items-center gap-4"
          :data-position="position"
        >
          <span class="w-6 text-sm text-neutral-500">{{ position }}</span>
          <label class="inline-flex items-center gap-1">
            <input
              v-model="lines[position - 1]!.polarity"
              type="radio"
              :name="`polarity-${position}`"
              value="yang"
            />
            Yang
          </label>
          <label class="inline-flex items-center gap-1">
            <input
              v-model="lines[position - 1]!.polarity"
              type="radio"
              :name="`polarity-${position}`"
              value="yin"
            />
            Yin
          </label>
          <label class="inline-flex items-center gap-1">
            <input v-model="lines[position - 1]!.changing" type="checkbox" />
            Changing
          </label>
        </div>
      </fieldset>

      <details class="rounded-md border border-neutral-200 p-3">
        <summary class="cursor-pointer text-sm font-medium text-neutral-700">
          Add more context (optional)
        </summary>
        <div class="mt-3 flex flex-col gap-3">
          <div>
            <label for="context" class="mb-1 block text-sm text-neutral-700">Context</label>
            <textarea
              id="context"
              v-model="context"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2"
            />
          </div>
          <div>
            <label for="what-happened-before" class="mb-1 block text-sm text-neutral-700">
              What happened before
            </label>
            <textarea
              id="what-happened-before"
              v-model="whatHappenedBefore"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2"
            />
          </div>
          <div>
            <label for="what-to-understand" class="mb-1 block text-sm text-neutral-700">
              What you want to understand
            </label>
            <textarea
              id="what-to-understand"
              v-model="whatUserWantsToUnderstand"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2"
            />
          </div>
          <div>
            <label for="background" class="mb-1 block text-sm text-neutral-700">
              Background information
            </label>
            <textarea
              id="background"
              v-model="backgroundInformation"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2"
            />
          </div>
          <div>
            <label for="initial-interpretation" class="mb-1 block text-sm text-neutral-700">
              Your initial interpretation
            </label>
            <textarea
              id="initial-interpretation"
              v-model="initialInterpretation"
              rows="2"
              maxlength="5000"
              class="w-full rounded-md border border-neutral-300 p-2"
            />
          </div>
        </div>
      </details>

      <p v-if="state.status === 'error'" class="text-red-600">{{ state.message }}</p>

      <button
        type="submit"
        :disabled="state.status === 'submitting'"
        class="rounded-md bg-neutral-800 px-4 py-2 text-white disabled:opacity-50"
      >
        {{ state.status === 'submitting' ? 'Casting…' : 'Cast' }}
      </button>
    </form>
  </main>
</template>
