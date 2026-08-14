<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { fetchConsultations } from '../../entities/consultation/api'
import type { Consultation } from '../../entities/consultation/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; consultations: Consultation[] }

const state = ref<State>({ status: 'loading' })

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

    <ul v-else class="flex flex-col gap-3">
      <li v-for="consultation in state.consultations" :key="consultation.id">
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
  </main>
</template>
