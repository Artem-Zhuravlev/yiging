<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { createConsultation } from '../../entities/consultation/api'
import type { ManualLine, NewConsultationRequest, SelectableCastingMethod } from '../../entities/consultation/model'
import { ApiError } from '../../shared/api/http'

type FormState = { status: 'idle' } | { status: 'submitting' } | { status: 'error'; message: string }

const question = ref('')
const method = ref<SelectableCastingMethod>('three_coins')
const lines = ref<ManualLine[]>(
  Array.from({ length: 6 }, (): ManualLine => ({ polarity: 'yang', changing: false })),
)
const state = ref<FormState>({ status: 'idle' })

const router = useRouter()

async function submit(): Promise<void> {
  state.value = { status: 'submitting' }

  const request: NewConsultationRequest =
    method.value === 'manual'
      ? { question: question.value, method: 'manual', lines: lines.value }
      : { question: question.value, method: 'three_coins' }

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
</script>

<template>
  <main class="mx-auto max-w-xl px-6 py-10">
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">New Consultation</h1>

    <form class="flex flex-col gap-6" @submit.prevent="submit">
      <div>
        <label for="question" class="mb-1 block text-sm font-medium text-neutral-700">
          Question
        </label>
        <textarea
          id="question"
          v-model="question"
          rows="3"
          required
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
