<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { fetchHexagram } from '../../entities/hexagram/api'
import { ApiError } from '../../shared/api/http'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import type { Hexagram } from '../../entities/hexagram/model'

const NOT_AVAILABLE = 'Not yet available.'

type State =
  | { status: 'loading' }
  | { status: 'not-found' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagram: Hexagram }

const route = useRoute()
const state = ref<State>({ status: 'loading' })
const kingWenNumber = computed(() => Number(route.params.number))

onMounted(async () => {
  try {
    const hexagram = await fetchHexagram(kingWenNumber.value)
    state.value = { status: 'loaded', hexagram }
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      state.value = { status: 'not-found' }
      return
    }
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : 'Failed to load hexagram.',
    }
  }
})
</script>

<template>
  <main class="mx-auto max-w-2xl px-6 py-10">
    <router-link to="/hexagrams" class="text-sm text-neutral-500 hover:underline">
      &larr; Hexagrams
    </router-link>

    <p v-if="state.status === 'loading'" class="mt-6 text-neutral-500">Loading…</p>
    <p v-else-if="state.status === 'not-found'" class="mt-6 text-neutral-600">
      Hexagram not found.
    </p>
    <p v-else-if="state.status === 'error'" class="mt-6 text-red-600">{{ state.message }}</p>

    <div v-else class="mt-6 flex flex-col gap-6">
      <div class="flex items-center gap-6">
        <HexagramLines :lines="state.hexagram.lines" />
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">
            {{ state.hexagram.kingWenNumber }}. {{ state.hexagram.chineseName }}
          </h1>
          <p class="text-neutral-500">{{ state.hexagram.pinyin }}</p>
        </div>
      </div>

      <dl class="grid grid-cols-2 gap-4">
        <div>
          <dt class="text-sm text-neutral-500">Upper trigram</dt>
          <dd>{{ state.hexagram.upperTrigram.symbol }} {{ state.hexagram.upperTrigram.name }}</dd>
        </div>
        <div>
          <dt class="text-sm text-neutral-500">Lower trigram</dt>
          <dd>{{ state.hexagram.lowerTrigram.symbol }} {{ state.hexagram.lowerTrigram.name }}</dd>
        </div>
      </dl>

      <div>
        <h2 class="text-sm font-medium text-neutral-500">Judgment</h2>
        <p>{{ state.hexagram.judgment ?? NOT_AVAILABLE }}</p>
      </div>

      <div>
        <h2 class="text-sm font-medium text-neutral-500">Image</h2>
        <p>{{ state.hexagram.image ?? NOT_AVAILABLE }}</p>
      </div>
    </div>
  </main>
</template>
