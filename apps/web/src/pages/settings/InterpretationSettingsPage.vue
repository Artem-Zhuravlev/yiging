<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { fetchInterpretationProfile, updateInterpretationProfile } from '../../entities/interpretation-profile/api'
import { TONES, RESPONSE_LENGTHS } from '../../entities/interpretation-profile/model'
import type { InterpretationProfile } from '../../entities/interpretation-profile/model'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; profile: InterpretationProfile }

type FormState = { status: 'idle' } | { status: 'submitting' } | { status: 'error'; message: string }

const { t } = useI18n()
const state = ref<State>({ status: 'loading' })
const formState = ref<FormState>({ status: 'idle' })
const form = ref({ tone: 'neutral' as InterpretationProfile['tone'], length: 'standard' as InterpretationProfile['length'], notes: '' })

const toneOptions = computed(() => TONES.map((tone) => ({ label: t(`settings.toneOptions.${tone}`), value: tone })))
const lengthOptions = computed(() =>
  RESPONSE_LENGTHS.map((length) => ({ label: t(`settings.lengthOptions.${length}`), value: length })),
)

useStatusAnnouncer(computed(() => state.value.status))

onMounted(async () => {
  try {
    const profile = await fetchInterpretationProfile()
    state.value = { status: 'loaded', profile }
    form.value = { tone: profile.tone, length: profile.length, notes: profile.notes ?? '' }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('settings.loadError'),
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
      message: error instanceof Error ? error.message : t('settings.saveError'),
    }
  }
}
</script>

<template>
  <main id="main" tabindex="-1" class="container-sm mx-auto p-4">
    <h1 class="text-2xl font-semibold mb-4">{{ t('settings.title') }}</h1>

    <p v-if="state.status === 'loading'" class="text-color-secondary">{{ t('common.loading') }}</p>
    <Message v-else-if="state.status === 'error'" severity="error" role="alert">{{ state.message }}</Message>

    <form v-else class="flex flex-column gap-4" @submit.prevent="save">
      <h2 class="text-sm font-medium text-color-secondary m-0">{{ t('settings.profileHeading') }}</h2>
      <p class="text-sm text-color-secondary m-0">
        {{ t('settings.profileDescription') }}
      </p>

      <div class="flex flex-column gap-2">
        <label for="tone" class="text-xs text-color-secondary">{{ t('settings.tone') }}</label>
        <Select
          id="tone"
          v-model="form.tone"
          :options="toneOptions"
          option-label="label"
          option-value="value"
          class="w-15rem"
        />
      </div>

      <div class="flex flex-column gap-2">
        <label for="length" class="text-xs text-color-secondary">{{ t('settings.length') }}</label>
        <Select
          id="length"
          v-model="form.length"
          :options="lengthOptions"
          option-label="label"
          option-value="value"
          class="w-15rem"
        />
      </div>

      <div class="flex flex-column gap-2">
        <label for="notes" class="text-xs text-color-secondary">{{ t('settings.notes') }}</label>
        <Textarea id="notes" v-model="form.notes" rows="3" maxlength="1000" :placeholder="t('settings.notesPlaceholder')" />
      </div>

      <Message v-if="formState.status === 'error'" severity="error" role="alert">{{ formState.message }}</Message>

      <Button
        type="submit"
        :disabled="formState.status === 'submitting'"
        :label="formState.status === 'submitting' ? t('settings.saving') : t('settings.save')"
        class="align-self-start"
      />
    </form>
  </main>
</template>
