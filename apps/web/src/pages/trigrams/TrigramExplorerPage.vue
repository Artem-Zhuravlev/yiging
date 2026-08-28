<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'
import Message from 'primevue/message'
import { fetchTrigrams } from '../../entities/trigram/api'
import type { Trigram } from '../../entities/trigram/model'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'
import LoadingSkeleton from '../../shared/ui/LoadingSkeleton.vue'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; trigrams: Trigram[] }

// Later Heaven (King Wen) bagua: which 3×3 cell (0..8, row-major) each compass direction sits in.
// The centre cell (4) stays empty.
const CELL_FOR_DIRECTION: Record<string, number> = {
  Northwest: 0,
  North: 1,
  Northeast: 2,
  West: 3,
  East: 5,
  Southwest: 6,
  South: 7,
  Southeast: 8,
}

const { t } = useI18n()
const state = ref<State>({ status: 'loading' })

useStatusAnnouncer(computed(() => state.value.status))

onMounted(async () => {
  try {
    const trigrams = await fetchTrigrams()
    state.value = { status: 'loaded', trigrams }
  } catch (error) {
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('trigramExplorer.loadError'),
    }
  }
})

/** Nine cells, row-major; a cell is a trigram or null (the centre, and any unmatched direction). */
const arrangement = computed<(Trigram | null)[]>(() => {
  const cells: (Trigram | null)[] = Array.from({ length: 9 }, () => null)
  if (state.value.status !== 'loaded') return cells
  for (const trigram of state.value.trigrams) {
    const cell = CELL_FOR_DIRECTION[trigram.direction]
    if (cell !== undefined) cells[cell] = trigram
  }
  return cells
})
</script>

<template>
  <main id="main" tabindex="-1" class="container-lg mx-auto p-4">
    <h1 class="text-2xl font-semibold mb-4">{{ t('trigramExplorer.title') }}</h1>

    <LoadingSkeleton v-if="state.status === 'loading'" :lines="6" />
    <Message v-else-if="state.status === 'error'" severity="error" role="alert">{{ state.message }}</Message>

    <template v-else>
      <ul class="grid list-none p-0 m-0">
        <li v-for="trigram in state.trigrams" :key="trigram.id" class="col-6 sm:col-4 lg:col-3">
          <Card>
            <template #content>
              <div class="flex flex-column gap-2">
                <span class="text-4xl" aria-hidden="true">{{ trigram.symbol }}</span>
                <h2 class="text-lg font-medium m-0">
                  {{ trigram.chineseName }} <span class="text-color-secondary">({{ trigram.pinyin }})</span>
                </h2>
                <p class="text-sm text-color-secondary m-0">{{ trigram.name }}</p>
                <dl class="m-0 text-sm flex flex-column gap-1">
                  <div class="flex justify-content-between gap-3">
                    <dt class="text-color-secondary">{{ t('trigramExplorer.image') }}</dt>
                    <dd class="m-0 text-right">{{ trigram.image }}</dd>
                  </div>
                  <div class="flex justify-content-between gap-3">
                    <dt class="text-color-secondary">{{ t('trigramExplorer.element') }}</dt>
                    <dd class="m-0 text-right">{{ trigram.element }}</dd>
                  </div>
                  <div class="flex justify-content-between gap-3">
                    <dt class="text-color-secondary">{{ t('trigramExplorer.family') }}</dt>
                    <dd class="m-0 text-right">{{ trigram.familyMember }}</dd>
                  </div>
                  <div class="flex justify-content-between gap-3">
                    <dt class="text-color-secondary">{{ t('trigramExplorer.direction') }}</dt>
                    <dd class="m-0 text-right">{{ trigram.direction }}</dd>
                  </div>
                </dl>
              </div>
            </template>
          </Card>
        </li>
      </ul>

      <figure class="mt-5 m-0">
        <figcaption class="mb-2 text-sm font-medium text-color-secondary">
          {{ t('trigramExplorer.arrangement') }}
        </figcaption>
        <div class="arrangement-grid">
          <div
            v-for="(cell, index) in arrangement"
            :key="index"
            class="arrangement-cell border-round border-1 surface-border flex flex-column align-items-center justify-content-center gap-1 p-2"
          >
            <template v-if="cell">
              <span class="text-2xl" aria-hidden="true">{{ cell.symbol }}</span>
              <span class="text-sm">{{ cell.chineseName }}</span>
              <span class="text-xs text-color-secondary">{{ cell.direction }}</span>
            </template>
          </div>
        </div>
      </figure>
    </template>
  </main>
</template>

<style scoped>
.arrangement-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
  max-width: 24rem;
}

.arrangement-cell {
  aspect-ratio: 1;
}
</style>
