<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Card from 'primevue/card'
import { createJournalEntry, fetchJournalEntries } from '../../entities/journal/api'
import type { JournalEntry } from '../../entities/journal/model'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'
import LoadingSkeleton from '../../shared/ui/LoadingSkeleton.vue'

type Status = 'loading' | 'error' | 'ready'

interface EntryGroup {
  dateLabel: string
  entries: JournalEntry[]
}

type FormState = { status: 'idle' } | { status: 'submitting' } | { status: 'error'; message: string }

const { t } = useI18n()

const status = ref<Status>('loading')
const errorMessage = ref('')
const items = ref<JournalEntry[]>([])
const nextCursor = ref<string | null>(null)
const loadingMore = ref(false)

const entryText = ref('')
const formState = ref<FormState>({ status: 'idle' })

useStatusAnnouncer(computed(() => status.value))

async function load(reset: boolean): Promise<void> {
  if (reset) {
    status.value = 'loading'
    items.value = []
    nextCursor.value = null
  } else {
    loadingMore.value = true
  }

  try {
    const page = await fetchJournalEntries({ cursor: reset ? null : nextCursor.value })
    items.value = reset ? page.items : [...items.value, ...page.items]
    nextCursor.value = page.nextCursor
    status.value = 'ready'
  } catch (error) {
    if (reset) {
      status.value = 'error'
      errorMessage.value = error instanceof Error ? error.message : t('journal.loadError')
    }
  } finally {
    loadingMore.value = false
  }
}

onMounted(() => load(true))

const groupedEntries = computed<EntryGroup[]>(() => {
  const groups: EntryGroup[] = []
  for (const entry of items.value) {
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
  if (status.value !== 'ready' || formState.value.status === 'submitting') {
    return
  }

  formState.value = { status: 'submitting' }

  try {
    const entry = await createJournalEntry({ text: entryText.value })
    // Page 1 is always the newest entries, so a new entry belongs at the very top — no refetch.
    items.value = [entry, ...items.value]
    entryText.value = ''
    formState.value = { status: 'idle' }
  } catch (error) {
    formState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('journal.addError'),
    }
  }
}
</script>

<template>
  <main id="main" tabindex="-1" class="container-sm mx-auto p-4">
    <h1 class="text-2xl font-semibold mb-4">{{ t('journal.title') }}</h1>

    <LoadingSkeleton v-if="status === 'loading'" :lines="4" />
    <Message v-else-if="status === 'error'" severity="error" role="alert">{{ errorMessage }}</Message>

    <template v-else>
      <form class="mb-5 flex flex-column gap-2" @submit.prevent="addEntry">
        <Textarea v-model="entryText" rows="3" required maxlength="5000" :placeholder="t('journal.entryPlaceholder')" />
        <Message v-if="formState.status === 'error'" severity="error" role="alert">{{ formState.message }}</Message>
        <Button
          type="submit"
          :disabled="formState.status === 'submitting'"
          :label="formState.status === 'submitting' ? t('journal.adding') : t('journal.addEntry')"
          class="align-self-start"
        />
      </form>

      <p v-if="items.length === 0" class="text-color-secondary">
        {{ t('journal.empty') }}
      </p>

      <div v-else class="flex flex-column gap-5">
        <section v-for="group in groupedEntries" :key="group.dateLabel">
          <h2 class="text-sm font-medium text-color-secondary mb-2">{{ group.dateLabel }}</h2>
          <div class="flex flex-column gap-3">
            <Card v-for="entry in group.entries" :key="entry.id">
              <template #content>
                <p class="mt-0 mb-2">{{ entry.text }}</p>
                <span class="text-xs text-color-secondary">
                  {{ new Date(entry.createdAt).toLocaleString() }}
                </span>
              </template>
            </Card>
          </div>
        </section>
      </div>

      <div v-if="nextCursor" class="mt-4 flex justify-content-center">
        <Button
          text
          size="small"
          :disabled="loadingMore"
          :label="loadingMore ? t('common.loadingMore') : t('common.loadMore')"
          @click="load(false)"
        />
      </div>
    </template>
  </main>
</template>
