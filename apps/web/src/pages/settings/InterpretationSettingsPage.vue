<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
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
  <main class="max-w-screen-sm mx-auto p-4">
    <h1 class="text-2xl font-semibold mb-4">Settings</h1>

    <p v-if="state.status === 'loading'" class="text-color-secondary">Loading…</p>
    <Message v-else-if="state.status === 'error'" severity="error">{{ state.message }}</Message>

    <form v-else class="flex flex-column gap-4" @submit.prevent="save">
      <h2 class="text-sm font-medium text-color-secondary m-0">Interpretation Profile</h2>
      <p class="text-sm text-color-secondary m-0">
        Shapes every AI interpretation and follow-up answer from now on.
      </p>

      <div class="flex flex-column gap-2">
        <label for="tone" class="text-xs text-color-secondary">Tone</label>
        <Select id="tone" v-model="form.tone" :options="[...TONES]" class="capitalize w-15rem" />
      </div>

      <div class="flex flex-column gap-2">
        <label for="length" class="text-xs text-color-secondary">Length</label>
        <Select id="length" v-model="form.length" :options="[...RESPONSE_LENGTHS]" class="capitalize w-15rem" />
      </div>

      <div class="flex flex-column gap-2">
        <label for="notes" class="text-xs text-color-secondary">Notes</label>
        <Textarea
          id="notes"
          v-model="form.notes"
          rows="3"
          maxlength="1000"
          placeholder="Anything else the interpretation should take into account…"
        />
      </div>

      <Message v-if="formState.status === 'error'" severity="error">{{ formState.message }}</Message>

      <Button
        type="submit"
        :disabled="formState.status === 'submitting'"
        :label="formState.status === 'submitting' ? 'Saving…' : 'Save'"
        class="align-self-start"
      />
    </form>
  </main>
</template>
