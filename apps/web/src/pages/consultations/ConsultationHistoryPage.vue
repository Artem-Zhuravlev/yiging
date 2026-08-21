<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import {
  exportConsultationsBackup,
  fetchConsultations,
  importConsultationsBackup,
} from '../../entities/consultation/api'
import type { Consultation } from '../../entities/consultation/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; consultations: Consultation[] }

type ImportState =
  | { status: 'idle' }
  | { status: 'importing' }
  | { status: 'error'; message: string }
  | { status: 'success'; imported: number }

interface ConsultationGroup {
  dateLabel: string
  consultations: Consultation[]
}

const state = ref<State>({ status: 'loading' })
const selectedTags = ref<Set<string>>(new Set())
const favoritesOnly = ref(false)
const searchQuery = ref('')
const importState = ref<ImportState>({ status: 'idle' })

onMounted(async () => {
  try {
    const consultations = await fetchConsultations()
    state.value = { status: 'loaded', consultations }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to load consultations.',
    }
  }
})

const allTags = computed<string[]>(() => {
  if (state.value.status !== 'loaded') return []
  return [...new Set(state.value.consultations.flatMap((c) => c.tags))].sort()
})

function matchesSearch(consultation: Consultation, query: string): boolean {
  const needle = query.toLowerCase()
  if (consultation.question.toLowerCase().includes(needle)) return true
  return consultation.notes.some((note) => note.text.toLowerCase().includes(needle))
}

const filteredConsultations = computed<Consultation[]>(() => {
  if (state.value.status !== 'loaded') return []

  const query = searchQuery.value.trim()

  return state.value.consultations
    .filter((c) => selectedTags.value.size === 0 || [...selectedTags.value].every((t) => c.tags.includes(t)))
    .filter((c) => !favoritesOnly.value || c.favorite)
    .filter((c) => query === '' || matchesSearch(c, query))
})

const groupedConsultations = computed<ConsultationGroup[]>(() => {
  const groups: ConsultationGroup[] = []
  for (const consultation of filteredConsultations.value) {
    const dateLabel = new Date(consultation.createdAt).toLocaleDateString()
    const currentGroup = groups[groups.length - 1]
    if (currentGroup && currentGroup.dateLabel === dateLabel) {
      currentGroup.consultations.push(consultation)
    } else {
      groups.push({ dateLabel, consultations: [consultation] })
    }
  }
  return groups
})

function toggleTag(tag: string): void {
  const next = new Set(selectedTags.value)
  if (next.has(tag)) next.delete(tag)
  else next.add(tag)
  selectedTags.value = next
}

function exportBackup(): void {
  if (state.value.status !== 'loaded') return
  exportConsultationsBackup(state.value.consultations)
}

async function handleImportFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  importState.value = { status: 'importing' }

  try {
    const text = await file.text()
    let items: unknown[]
    try {
      items = JSON.parse(text)
    } catch {
      throw new Error('That file is not valid JSON.')
    }
    if (!Array.isArray(items)) {
      throw new Error('That file does not contain a backup array.')
    }

    const { imported } = await importConsultationsBackup(items)
    importState.value = { status: 'success', imported }

    const consultations = await fetchConsultations()
    state.value = { status: 'loaded', consultations }
  } catch (error) {
    importState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to import backup.',
    }
  } finally {
    input.value = ''
  }
}
</script>

<template>
  <main class="max-w-screen-sm mx-auto p-4">
    <div class="mb-4 flex align-items-center justify-content-between gap-3">
      <h1 class="text-2xl font-semibold m-0">History</h1>
      <div class="flex align-items-center gap-3">
        <Button
          text
          size="small"
          label="Export Backup (JSON)"
          :disabled="state.status !== 'loaded'"
          @click="exportBackup"
        />
        <label class="cursor-pointer text-sm p-button p-button-text p-button-sm">
          Import Backup (JSON)
          <input type="file" accept="application/json" class="hidden-input" @change="handleImportFile" />
        </label>
      </div>
    </div>

    <Message v-if="importState.status === 'success'" severity="success" class="mb-4">
      Imported {{ importState.imported }} consultation{{ importState.imported === 1 ? '' : 's' }}.
    </Message>
    <Message v-else-if="importState.status === 'error'" severity="error" class="mb-4">
      {{ importState.message }}
    </Message>

    <p v-if="state.status === 'loading'" class="text-color-secondary">Loading…</p>
    <Message v-else-if="state.status === 'error'" severity="error">{{ state.message }}</Message>

    <p v-else-if="state.consultations.length === 0" class="text-color-secondary">
      No consultations yet —
      <router-link to="/consultations/new">cast your first one</router-link>.
    </p>

    <template v-else>
      <InputText
        v-model="searchQuery"
        type="search"
        placeholder="Search questions and notes…"
        class="mb-4 w-full"
      />

      <div class="mb-5 flex flex-wrap gap-2">
        <Button
          :aria-pressed="favoritesOnly"
          label="★ Favorites only"
          rounded
          size="small"
          :outlined="!favoritesOnly"
          @click="favoritesOnly = !favoritesOnly"
        />
        <Button
          v-for="tag in allTags"
          :key="tag"
          :aria-pressed="selectedTags.has(tag)"
          :label="tag"
          rounded
          size="small"
          :outlined="!selectedTags.has(tag)"
          @click="toggleTag(tag)"
        />
      </div>

      <p v-if="groupedConsultations.length === 0" class="text-color-secondary">
        No consultations match the selected tags.
      </p>

      <div v-else class="flex flex-column gap-5">
        <section v-for="group in groupedConsultations" :key="group.dateLabel">
          <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ group.dateLabel }}</h2>
          <ul class="flex flex-column gap-3 list-none p-0 m-0">
            <li v-for="consultation in group.consultations" :key="consultation.id">
              <router-link
                :to="`/consultations/${consultation.id}`"
                class="flex flex-column gap-1 border-round border-1 surface-border p-3 no-underline text-color history-card"
              >
                <span class="font-medium">{{ consultation.question }}</span>
                <span class="text-sm text-color-secondary">
                  {{ consultation.primaryHexagram.kingWenNumber }}.
                  {{ consultation.primaryHexagram.chineseName }}
                  &rarr;
                  {{ consultation.resultingHexagram.kingWenNumber }}.
                  {{ consultation.resultingHexagram.chineseName }}
                </span>
                <span class="text-xs text-color-secondary">
                  {{ new Date(consultation.createdAt).toLocaleString() }}
                </span>
              </router-link>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </main>
</template>

<style scoped>
.hidden-input {
  display: none;
}

.history-card:hover {
  border-color: var(--p-primary-color);
}
</style>
