<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { fetchHexagram } from '../../entities/hexagram/api'
import { hexagramOfTheDayNumber } from '../../entities/hexagram/hexagramOfTheDay'
import type { Hexagram } from '../../entities/hexagram/model'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagram: Hexagram }

const state = ref<State>({ status: 'loading' })

onMounted(async () => {
  try {
    const hexagram = await fetchHexagram(hexagramOfTheDayNumber())
    state.value = { status: 'loaded', hexagram }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to load the hexagram of the day.',
    }
  }
})
</script>

<template>
  <main
    class="mx-auto flex min-h-screen max-w-2xl flex-col items-center justify-center gap-6 px-6 text-center"
  >
    <h1 class="text-3xl font-semibold tracking-tight">Yijing</h1>
    <p class="text-neutral-500">Digital I Ching study &amp; practice platform.</p>
    <div class="flex gap-4">
      <router-link
        to="/consultations/new"
        class="rounded-md bg-neutral-800 px-4 py-2 text-white hover:bg-neutral-700"
      >
        Cast a new consultation
      </router-link>
      <router-link
        to="/consultations"
        class="rounded-md border border-neutral-300 px-4 py-2 hover:border-neutral-400"
      >
        View history
      </router-link>
    </div>

    <router-link
      v-if="state.status === 'loaded'"
      :to="`/hexagrams/${state.hexagram.kingWenNumber}`"
      class="mt-4 flex flex-col items-center gap-3 rounded-lg border border-neutral-200 p-6 hover:border-neutral-400"
    >
      <h2 class="text-sm font-medium text-neutral-500">Hexagram of the Day</h2>
      <HexagramLines :lines="state.hexagram.lines" />
      <p>
        {{ state.hexagram.kingWenNumber }}. {{ state.hexagram.chineseName }}
        <span class="text-neutral-500">({{ state.hexagram.pinyin }})</span>
      </p>
    </router-link>
    <p v-else-if="state.status === 'loading'" class="mt-4 text-sm text-neutral-400">
      Loading hexagram of the day…
    </p>
    <p v-else-if="state.status === 'error'" class="mt-4 text-sm text-red-600">{{ state.message }}</p>
  </main>
</template>
