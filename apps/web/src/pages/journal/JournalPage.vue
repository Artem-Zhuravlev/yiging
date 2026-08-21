<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
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
      message: error instanceof Error ? error.message : 'Failed to load journal entries.',
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
      message: error instanceof Error ? error.message : 'Failed to add entry.',
    }
  }
}
</script>

<template>
  <main class="mx-auto max-w-2xl px-6 py-10">
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">Journal</h1>

    <p v-if="state.status === 'loading'" class="text-neutral-500">Loading…</p>
    <p v-else-if="state.status === 'error'" class="text-red-600">{{ state.message }}</p>

    <template v-else>
      <form class="mb-8 flex flex-col gap-2" @submit.prevent="addEntry">
        <textarea
          v-model="entryText"
          rows="3"
          required
          maxlength="5000"
          placeholder="Write a reflection…"
          class="w-full rounded-md border border-neutral-300 p-2 text-sm"
        />
        <p v-if="formState.status === 'error'" class="text-sm text-red-600">
          {{ formState.message }}
        </p>
        <button
          type="submit"
          :disabled="formState.status === 'submitting'"
          class="self-start rounded-md bg-neutral-800 px-3 py-1.5 text-sm text-white disabled:opacity-50"
        >
          {{ formState.status === 'submitting' ? 'Adding…' : 'Add Entry' }}
        </button>
      </form>

      <p v-if="state.entries.length === 0" class="text-neutral-500">
        No journal entries yet — write your first one above.
      </p>

      <div v-else class="flex flex-col gap-6">
        <section v-for="group in groupedEntries" :key="group.dateLabel">
          <h2 class="mb-3 text-sm font-medium text-neutral-500">{{ group.dateLabel }}</h2>
          <ul class="flex flex-col gap-3">
            <li
              v-for="entry in group.entries"
              :key="entry.id"
              class="rounded-lg border border-neutral-200 p-4"
            >
              <p>{{ entry.text }}</p>
              <span class="text-xs text-neutral-400">
                {{ new Date(entry.createdAt).toLocaleString() }}
              </span>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </main>
</template>
