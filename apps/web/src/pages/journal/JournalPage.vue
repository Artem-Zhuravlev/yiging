<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Card from 'primevue/card'
import { createJournalEntry, fetchJournalEntries } from '../../entities/journal/api'
import type { JournalEntry } from '../../entities/journal/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; entries: JournalEntry[] }

interface EntryGroup {
  dateLabel: string
  entries: JournalEntry[]
}

type FormState = { status: 'idle' } | { status: 'submitting' } | { status: 'error'; message: string }

const { t } = useI18n()
const state = ref<State>({ status: 'loading' })
const entryText = ref('')
const formState = ref<FormState>({ status: 'idle' })

onMounted(async () => {
  try {
    const entries = await fetchJournalEntries()
    state.value = { status: 'loaded', entries }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('journal.loadError'),
    }
  }
})

const groupedEntries = computed<EntryGroup[]>(() => {
  if (state.value.status !== 'loaded') return []

  const groups: EntryGroup[] = []
  for (const entry of state.value.entries) {
    const dateLabel = new Date(entry.createdAt).toLocaleDateString()
    const currentGroup = groups[groups.length - 1]
    if (currentGroup && currentGroup.dateLabel === dateLabel) {
      currentGroup.entries.push(entry)
    } else {
      groups.push({ dateLabel, entries: [entry] })
    }
  }
  return groups
})

async function addEntry(): Promise<void> {
  if (state.value.status !== 'loaded' || formState.value.status === 'submitting') {
    return
  }

  const loaded = state.value
  formState.value = { status: 'submitting' }

  try {
    const entry = await createJournalEntry({ text: entryText.value })
    state.value = { ...loaded, entries: [entry, ...loaded.entries] }
    entryText.value = ''
    formState.value = { status: 'idle' }
  } catch (error) {
    formState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('journal.addError'),
    }
  }
}
</script>

<template>
  <main class="max-w-screen-sm mx-auto p-4">
    <h1 class="text-2xl font-semibold mb-4">{{ t('journal.title') }}</h1>

    <p v-if="state.status === 'loading'" class="text-color-secondary">{{ t('common.loading') }}</p>
    <Message v-else-if="state.status === 'error'" severity="error">{{ state.message }}</Message>

    <template v-else>
      <form class="mb-5 flex flex-column gap-2" @submit.prevent="addEntry">
        <Textarea v-model="entryText" rows="3" required maxlength="5000" :placeholder="t('journal.entryPlaceholder')" />
        <Message v-if="formState.status === 'error'" severity="error">{{ formState.message }}</Message>
        <Button
          type="submit"
          :disabled="formState.status === 'submitting'"
          :label="formState.status === 'submitting' ? t('journal.adding') : t('journal.addEntry')"
          class="align-self-start"
        />
      </form>

      <p v-if="state.entries.length === 0" class="text-color-secondary">
        {{ t('journal.empty') }}
      </p>

      <div v-else class="flex flex-column gap-5">
        <section v-for="group in groupedEntries" :key="group.dateLabel">
          <h2 class="text-sm font-medium text-color-secondary mb-2">{{ group.dateLabel }}</h2>
          <div class="flex flex-column gap-3">
            <Card v-for="entry in group.entries" :key="entry.id">
              <template #content>
                <p class="mt-0 mb-2">{{ entry.text }}</p>
                <span class="text-xs text-color-secondary">
                  {{ new Date(entry.createdAt).toLocaleString() }}
                </span>
              </template>
            </Card>
          </div>
        </section>
      </div>
    </template>
  </main>
</template>
