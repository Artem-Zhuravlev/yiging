<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { fetchHexagram } from '../../entities/hexagram/api'
import { hexagramOfTheDayNumber } from '../../entities/hexagram/hexagramOfTheDay'
import type { Hexagram } from '../../entities/hexagram/model'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import {
  fetchConsultations,
  fetchDueReminders,
  setReflectionReminder,
} from '../../entities/consultation/api'
import type { ConsultationListItem, DueReminder } from '../../entities/consultation/model'
import { fetchStatistics } from '../../entities/statistics/api'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagram: Hexagram }

const { t } = useI18n()
const state = ref<State>({ status: 'loading' })

// Two secondary dashboard sections, each loading independently of the Hexagram of the Day and
// of each other; a failed fetch just leaves its section hidden (SPEC-045).
const recent = ref<ConsultationListItem[]>([])
const totalCast = ref<number | null>(null)
const dueReminders = ref<DueReminder[]>([])

function overdueDays(reminder: DueReminder): number {
  return Math.max(0, Math.floor((Date.now() - Date.parse(reminder.remindAt)) / 86_400_000))
}

async function snooze(reminder: DueReminder): Promise<void> {
  const nextWeek = new Date(Date.now() + 7 * 86_400_000).toISOString().slice(0, 10)
  try {
    await setReflectionReminder(reminder.id, nextWeek)
    dueReminders.value = dueReminders.value.filter((r) => r.id !== reminder.id)
  } catch {
    /* Leave the row in place; the user can try again or open the consultation. */
  }
}

useStatusAnnouncer(computed(() => state.value.status))

onMounted(() => {
  fetchHexagram(hexagramOfTheDayNumber())
    .then((hexagram) => {
      state.value = { status: 'loaded', hexagram }
    })
    .catch((error: unknown) => {
      state.value = {
        status: 'error',
        message: error instanceof Error ? error.message : t('home.loadError'),
      }
    })

  fetchConsultations({ limit: 4 })
    .then((page) => {
      recent.value = page.items
    })
    .catch(() => {
      /* Recent section stays hidden. */
    })

  fetchStatistics()
    .then((statistics) => {
      if (statistics.totalConsultations > 0) {
        totalCast.value = statistics.totalConsultations
      }
    })
    .catch(() => {
      /* At-a-glance line stays hidden. */
    })

  fetchDueReminders()
    .then((reminders) => {
      dueReminders.value = reminders
    })
    .catch(() => {
      /* Due-for-reflection section stays hidden. */
    })
})
</script>

<template>
  <main
    id="main"
    tabindex="-1"
    class="container-sm mx-auto flex flex-column align-items-center justify-content-center gap-4 text-center p-4"
    style="min-height: calc(100vh - 5rem)"
  >
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
    <Message v-else-if="state.status === 'error'" severity="error" role="alert" class="mt-3">{{ state.message }}</Message>

    <section v-if="recent.length > 0" class="mt-4 w-full text-left" style="max-width: 24rem">
      <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ t('home.recent') }}</h2>
      <ul class="flex flex-column gap-2 list-none p-0 m-0">
        <li v-for="consultation in recent" :key="consultation.id">
          <router-link
            :to="`/consultations/${consultation.id}`"
            class="flex flex-column gap-1 border-round border-1 surface-border p-2 no-underline text-color home-recent-card"
          >
            <span class="text-sm font-medium">{{ consultation.question }}</span>
            <span class="text-xs text-color-secondary">
              {{ consultation.primaryHexagram.kingWenNumber }}. {{ consultation.primaryHexagram.chineseName }}
              &rarr;
              {{ consultation.resultingHexagram.kingWenNumber }}. {{ consultation.resultingHexagram.chineseName }}
              &middot; {{ new Date(consultation.createdAt).toLocaleDateString() }}
            </span>
          </router-link>
        </li>
      </ul>
      <router-link to="/consultations" class="mt-2 inline-block text-sm">{{ t('home.viewAll') }}</router-link>
    </section>

    <section v-if="dueReminders.length > 0" class="mt-4 w-full text-left" style="max-width: 24rem">
      <h2 class="mb-2 text-sm font-medium text-color-secondary">{{ t('reminders.dueTitle') }}</h2>
      <ul class="flex flex-column gap-2 list-none p-0 m-0">
        <li v-for="reminder in dueReminders" :key="reminder.id" class="flex flex-column gap-1">
          <router-link
            :to="`/consultations/${reminder.id}`"
            class="flex flex-column gap-1 border-round border-1 surface-border p-2 no-underline text-color home-recent-card"
          >
            <span class="text-sm font-medium">{{ reminder.question }}</span>
            <span class="text-xs text-color-secondary">
              {{ reminder.primaryHexagram.kingWenNumber }}. {{ reminder.primaryHexagram.chineseName }}
              &rarr;
              {{ reminder.resultingHexagram.kingWenNumber }}. {{ reminder.resultingHexagram.chineseName }}
              &middot;
              {{
                overdueDays(reminder) === 0
                  ? t('reminders.dueToday')
                  : t('reminders.overdueBy', { days: overdueDays(reminder) })
              }}
            </span>
          </router-link>
          <Button
            text
            size="small"
            class="align-self-start"
            :label="t('reminders.snooze')"
            @click="snooze(reminder)"
          />
        </li>
      </ul>
    </section>

    <p v-if="totalCast !== null" class="m-0 text-sm">
      <router-link to="/statistics">{{ t('home.consultationsCast', { count: totalCast }) }}</router-link>
    </p>
  </main>
</template>

<style scoped>
.home-recent-card:hover {
  border-color: var(--p-primary-color);
}
</style>
