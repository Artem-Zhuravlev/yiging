<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { fetchInterpretationProfile, updateInterpretationProfile } from '../../entities/interpretation-profile/api'
import { TONES, RESPONSE_LENGTHS } from '../../entities/interpretation-profile/model'
import type { InterpretationProfile } from '../../entities/interpretation-profile/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; profile: InterpretationProfile }

type FormState = { status: 'idle' } | { status: 'submitting' } | { status: 'error'; message: string }

const state = ref<State>({ status: 'loading' })
const formState = ref<FormState>({ status: 'idle' })
const form = ref({ tone: 'neutral' as InterpretationProfile['tone'], length: 'standard' as InterpretationProfile['length'], notes: '' })

onMounted(async () => {
  try {
    const profile = await fetchInterpretationProfile()
    state.value = { status: 'loaded', profile }
    form.value = { tone: profile.tone, length: profile.length, notes: profile.notes ?? '' }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to load interpretation profile.',
    }
  }
})

async function save(): Promise<void> {
  if (formState.value.status === 'submitting') return

  formState.value = { status: 'submitting' }

  try {
    const profile = await updateInterpretationProfile({
      tone: form.value.tone,
      length: form.value.length,
      notes: form.value.notes.trim() === '' ? null : form.value.notes,
    })
    state.value = { status: 'loaded', profile }
    formState.value = { status: 'idle' }
  } catch (error) {
    formState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to save interpretation profile.',
    }
  }
}
</script>

<template>
  <main class="mx-auto max-w-2xl px-6 py-10">
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">Settings</h1>

    <p v-if="state.status === 'loading'" class="text-neutral-500">Loading…</p>
    <p v-else-if="state.status === 'error'" class="text-red-600">{{ state.message }}</p>

    <form v-else class="flex flex-col gap-4" @submit.prevent="save">
      <h2 class="text-sm font-medium text-neutral-500">Interpretation Profile</h2>
      <p class="text-sm text-neutral-500">
        Shapes every AI interpretation and follow-up answer from now on.
      </p>

      <div>
        <label for="tone" class="mb-1 block text-xs text-neutral-500">Tone</label>
        <select id="tone" v-model="form.tone" class="rounded-md border border-neutral-300 p-2 text-sm capitalize">
          <option v-for="tone in TONES" :key="tone" :value="tone">{{ tone }}</option>
        </select>
      </div>

      <div>
        <label for="length" class="mb-1 block text-xs text-neutral-500">Length</label>
        <select id="length" v-model="form.length" class="rounded-md border border-neutral-300 p-2 text-sm capitalize">
          <option v-for="length in RESPONSE_LENGTHS" :key="length" :value="length">{{ length }}</option>
        </select>
      </div>

      <div>
        <label for="notes" class="mb-1 block text-xs text-neutral-500">Notes</label>
        <textarea
          id="notes"
          v-model="form.notes"
          rows="3"
          maxlength="1000"
          placeholder="Anything else the interpretation should take into account…"
          class="w-full rounded-md border border-neutral-300 p-2 text-sm"
        />
      </div>

      <p v-if="formState.status === 'error'" class="text-sm text-red-600">{{ formState.message }}</p>

      <button
        type="submit"
        :disabled="formState.status === 'submitting'"
        class="self-start rounded-md bg-neutral-800 px-4 py-2 text-sm text-white disabled:opacity-50"
      >
        {{ formState.status === 'submitting' ? 'Saving…' : 'Save' }}
      </button>
    </form>
  </main>
</template>
