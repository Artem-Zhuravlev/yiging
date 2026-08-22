<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { fetchHexagram } from '../../entities/hexagram/api'
import { hexagramOfTheDayNumber } from '../../entities/hexagram/hexagramOfTheDay'
import type { Hexagram } from '../../entities/hexagram/model'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagram: Hexagram }

const { t } = useI18n()
const state = ref<State>({ status: 'loading' })

onMounted(async () => {
  try {
    const hexagram = await fetchHexagram(hexagramOfTheDayNumber())
    state.value = { status: 'loaded', hexagram }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('home.loadError'),
    }
  }
})
</script>

<template>
  <main class="flex flex-column align-items-center justify-content-center gap-4 text-center p-4">
    <h1 class="text-4xl font-semibold m-0">{{ t('home.title') }}</h1>
    <p class="text-color-secondary m-0">{{ t('home.tagline') }}</p>
    <div class="flex gap-3">
      <Button as="router-link" to="/consultations/new" :label="t('home.castNew')" />
      <Button as="router-link" to="/consultations" :label="t('home.viewHistory')" severity="secondary" outlined />
    </div>

    <router-link
      v-if="state.status === 'loaded'"
      :to="`/hexagrams/${state.hexagram.kingWenNumber}`"
      class="mt-3 no-underline text-color"
    >
      <Card>
        <template #content>
          <div class="flex flex-column align-items-center gap-3">
            <h2 class="text-sm font-medium text-color-secondary m-0 uppercase">
              {{ t('home.hexagramOfTheDay') }}
            </h2>
            <HexagramLines :lines="state.hexagram.lines" />
            <p class="m-0">
              {{ state.hexagram.kingWenNumber }}. {{ state.hexagram.chineseName }}
              <span class="text-color-secondary">({{ state.hexagram.pinyin }})</span>
            </p>
          </div>
        </template>
      </Card>
    </router-link>
    <div v-else-if="state.status === 'loading'" class="mt-3 flex align-items-center gap-2">
      <ProgressSpinner style="width: 1.5rem; height: 1.5rem" stroke-width="6" />
      <span class="text-sm text-color-secondary">{{ t('home.loadingHexagramOfTheDay') }}</span>
    </div>
    <Message v-else-if="state.status === 'error'" severity="error" class="mt-3">{{ state.message }}</Message>
  </main>
</template>
