<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import { fetchConsultation } from '../../entities/consultation/api'
import type { Consultation } from '../../entities/consultation/model'
import { fetchHexagram } from '../../entities/hexagram/api'
import type { HexagramLine } from '../../entities/hexagram/model'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import { ApiError } from '../../shared/api/http'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'
import LoadingSkeleton from '../../shared/ui/LoadingSkeleton.vue'

type State =
  | { status: 'loading' }
  | { status: 'not-found' }
  | { status: 'error'; message: string }
  | {
      status: 'loaded'
      consultation: Consultation
      primaryLines: HexagramLine[]
      resultingLines: HexagramLine[]
    }

const { t } = useI18n()
const route = useRoute()
const id = computed(() => String(route.params.id))
const state = ref<State>({ status: 'loading' })

useStatusAnnouncer(
  computed(() => state.value.status),
  (status) => (status === 'not-found' ? t('consultation.notFound') : undefined),
)

onMounted(async () => {
  try {
    const consultation = await fetchConsultation(id.value)
    const [primaryHexagram, resultingHexagram] = await Promise.all([
      fetchHexagram(consultation.primaryHexagram.kingWenNumber),
      fetchHexagram(consultation.resultingHexagram.kingWenNumber),
    ])

    const primaryLines: HexagramLine[] = primaryHexagram.lines.map((line) => ({
      ...line,
      changing: consultation.changingLinePositions.includes(line.position),
    }))

    state.value = {
      status: 'loaded',
      consultation,
      primaryLines,
      resultingLines: resultingHexagram.lines,
    }
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      state.value = { status: 'not-found' }
      return
    }
    state.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('consultation.loadError'),
    }
  }
})
</script>

<template>
  <main id="main" tabindex="-1" class="container-sm mx-auto p-4">
    <LoadingSkeleton v-if="state.status === 'loading'" :lines="5" />
    <p v-else-if="state.status === 'not-found'" class="text-color-secondary">{{ t('consultation.notFound') }}</p>
    <Message v-else-if="state.status === 'error'" severity="error" role="alert">{{ state.message }}</Message>

    <div v-else class="flex flex-column gap-5">
      <div>
        <h1 class="text-xl font-semibold m-0">{{ state.consultation.question }}</h1>
        <p class="text-sm text-color-secondary">
          {{ state.consultation.method }} &middot;
          {{ new Date(state.consultation.createdAt).toLocaleString() }}
        </p>
      </div>

      <div class="flex flex-wrap align-items-start gap-6">
        <div>
          <h2 class="mb-2 text-sm font-medium text-color-secondary">
            {{
              t('consultation.primaryHeading', {
                number: state.consultation.primaryHexagram.kingWenNumber,
                name: state.consultation.primaryHexagram.chineseName,
              })
            }}
          </h2>
          <HexagramLines :lines="state.primaryLines" />
        </div>

        <div>
          <h2 class="mb-2 text-sm font-medium text-color-secondary">
            {{
              t('consultation.resultingHeading', {
                number: state.consultation.resultingHexagram.kingWenNumber,
                name: state.consultation.resultingHexagram.chineseName,
              })
            }}
          </h2>
          <HexagramLines :lines="state.resultingLines" />
        </div>
      </div>

      <p v-if="state.consultation.changingLinePositions.length === 0" class="text-color-secondary">
        {{ t('consultation.noChangingLines') }}
      </p>
      <p v-else class="text-color-secondary">
        {{ t('consultation.changingLines', { list: state.consultation.changingLinePositions.join(', ') }) }}
      </p>

      <div v-if="state.consultation.notes.length > 0">
        <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ t('consultation.notes') }}</h2>
        <ul class="flex flex-column gap-2 list-none p-0 m-0">
          <li v-for="(note, index) in state.consultation.notes" :key="index">
            <span class="text-xs tracking-wide text-color-secondary uppercase">{{ note.label }}</span>
            <p class="mt-1 mb-0">{{ note.text }}</p>
          </li>
        </ul>
      </div>

      <div v-if="state.consultation.tags.length > 0" class="flex gap-2 flex-wrap">
        <Tag v-for="tag in state.consultation.tags" :key="tag" :value="tag" severity="secondary" rounded />
      </div>

      <div
        v-if="
          state.consultation.context ||
          state.consultation.whatHappenedBefore ||
          state.consultation.whatUserWantsToUnderstand ||
          state.consultation.backgroundInformation ||
          state.consultation.initialInterpretation
        "
        class="flex flex-column gap-3"
      >
        <h2 class="text-sm font-medium text-color-secondary m-0">{{ t('consultation.context') }}</h2>
        <div v-if="state.consultation.context">
          <h3 class="text-xs text-color-secondary m-0">{{ t('consultation.context') }}</h3>
          <p class="mt-1 mb-0">{{ state.consultation.context }}</p>
        </div>
        <div v-if="state.consultation.whatHappenedBefore">
          <h3 class="text-xs text-color-secondary m-0">{{ t('consultation.whatHappenedBefore') }}</h3>
          <p class="mt-1 mb-0">{{ state.consultation.whatHappenedBefore }}</p>
        </div>
        <div v-if="state.consultation.whatUserWantsToUnderstand">
          <h3 class="text-xs text-color-secondary m-0">{{ t('consultation.whatUserWantsToUnderstand') }}</h3>
          <p class="mt-1 mb-0">{{ state.consultation.whatUserWantsToUnderstand }}</p>
        </div>
        <div v-if="state.consultation.backgroundInformation">
          <h3 class="text-xs text-color-secondary m-0">{{ t('consultation.backgroundInformation') }}</h3>
          <p class="mt-1 mb-0">{{ state.consultation.backgroundInformation }}</p>
        </div>
        <div v-if="state.consultation.initialInterpretation">
          <h3 class="text-xs text-color-secondary m-0">{{ t('consultation.initialInterpretation') }}</h3>
          <p class="mt-1 mb-0">{{ state.consultation.initialInterpretation }}</p>
        </div>
      </div>

      <div v-if="state.consultation.outcome">
        <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ t('consultation.outcome') }}</h2>
        <div v-if="state.consultation.outcome.whatActuallyHappened">
          <h3 class="text-xs text-color-secondary m-0">{{ t('consultation.whatActuallyHappened') }}</h3>
          <p class="mt-1 mb-0">{{ state.consultation.outcome.whatActuallyHappened }}</p>
        </div>
        <div v-if="state.consultation.outcome.outcome">
          <h3 class="text-xs text-color-secondary m-0">{{ t('consultation.outcome') }}</h3>
          <p class="mt-1 mb-0">{{ state.consultation.outcome.outcome }}</p>
        </div>
        <div v-if="state.consultation.outcome.reflection">
          <h3 class="text-xs text-color-secondary m-0">{{ t('consultation.reflection') }}</h3>
          <p class="mt-1 mb-0">{{ state.consultation.outcome.reflection }}</p>
        </div>
      </div>
    </div>
  </main>
</template>
