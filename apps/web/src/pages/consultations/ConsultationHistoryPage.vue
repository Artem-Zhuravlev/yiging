<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { fetchConsultations } from '../../entities/consultation/api'
import type { Consultation } from '../../entities/consultation/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; consultations: Consultation[] }

interface ConsultationGroup {
  dateLabel: string
  consultations: Consultation[]
}

const state = ref<State>({ status: 'loading' })
const selectedTags = ref<Set<string>>(new Set())
const favoritesOnly = ref(false)
const searchQuery = ref('')

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
</script>

<template>
  <main class="mx-auto max-w-2xl px-6 py-10">
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">History</h1>

    <p v-if="state.status === 'loading'" class="text-neutral-500">Loading…</p>
    <p v-else-if="state.status === 'error'" class="text-red-600">{{ state.message }}</p>

    <p v-else-if="state.consultations.length === 0" class="text-neutral-500">
      No consultations yet —
      <router-link to="/consultations/new" class="underline">cast your first one</router-link>.
    </p>

    <template v-else>
      <input
        v-model="searchQuery"
        type="search"
        placeholder="Search questions and notes…"
        class="mb-4 w-full rounded-md border border-neutral-300 p-2 text-sm"
      />

      <div class="mb-6 flex flex-wrap gap-2">
        <button
          type="button"
          :aria-pressed="favoritesOnly"
          class="rounded-full border px-3 py-1 text-sm"
          :class="
            favoritesOnly
              ? 'border-neutral-900 bg-neutral-900 text-white'
              : 'border-neutral-300 text-neutral-600 hover:border-neutral-400'
          "
          @click="favoritesOnly = !favoritesOnly"
        >
          ★ Favorites only
        </button>
        <button
          v-for="tag in allTags"
          :key="tag"
          type="button"
          :aria-pressed="selectedTags.has(tag)"
          class="rounded-full border px-3 py-1 text-sm"
          :class="
            selectedTags.has(tag)
              ? 'border-neutral-900 bg-neutral-900 text-white'
              : 'border-neutral-300 text-neutral-600 hover:border-neutral-400'
          "
          @click="toggleTag(tag)"
        >
          {{ tag }}
        </button>
      </div>

      <p v-if="groupedConsultations.length === 0" class="text-neutral-500">
        No consultations match the selected tags.
      </p>

      <div v-else class="flex flex-col gap-6">
        <section v-for="group in groupedConsultations" :key="group.dateLabel">
          <h2 class="mb-3 text-sm font-medium text-neutral-500">{{ group.dateLabel }}</h2>
          <ul class="flex flex-col gap-3">
            <li v-for="consultation in group.consultations" :key="consultation.id">
              <router-link
                :to="`/consultations/${consultation.id}`"
                class="flex flex-col gap-1 rounded-lg border border-neutral-200 p-4 hover:border-neutral-400"
              >
                <span class="font-medium">{{ consultation.question }}</span>
                <span class="text-sm text-neutral-500">
                  {{ consultation.primaryHexagram.kingWenNumber }}.
                  {{ consultation.primaryHexagram.chineseName }}
                  &rarr;
                  {{ consultation.resultingHexagram.kingWenNumber }}.
                  {{ consultation.resultingHexagram.chineseName }}
                </span>
                <span class="text-xs text-neutral-400">
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
