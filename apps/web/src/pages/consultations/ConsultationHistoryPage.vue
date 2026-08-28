<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import {
  deleteTag,
  exportConsultationsBackup,
  fetchConsultations,
  fetchConsultationTags,
  fetchConsultationsForExport,
  fetchTagsWithCounts,
  importConsultationsBackup,
  renameTag,
} from '../../entities/consultation/api'
import type { ConsultationListItem, TagWithCount } from '../../entities/consultation/model'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'
import { useToastSuccess } from '../../shared/lib/useToastSuccess'
import LoadingSkeleton from '../../shared/ui/LoadingSkeleton.vue'

type Status = 'loading' | 'error' | 'ready'

type ImportState =
  | { status: 'idle' }
  | { status: 'importing' }
  | { status: 'error'; message: string }
  | { status: 'success'; imported: number }

interface ConsultationGroup {
  dateLabel: string
  consultations: ConsultationListItem[]
}

const { t } = useI18n()
const { notifySaved } = useToastSuccess()

const status = ref<Status>('loading')
const errorMessage = ref('')
const items = ref<ConsultationListItem[]>([])
const nextCursor = ref<string | null>(null)
const loadingMore = ref(false)

const searchQuery = ref('')
const debouncedQuery = ref('')
const selectedTags = ref<Set<string>>(new Set())
const favoritesOnly = ref(false)
const allTags = ref<string[]>([])

const importState = ref<ImportState>({ status: 'idle' })
const exporting = ref(false)

// "Manage tags" panel (SPEC-050) — collapsed by default.
const tagCounts = ref<TagWithCount[]>([])
const editingTag = ref<string | null>(null)
const editName = ref('')
const confirmingDelete = ref<string | null>(null)
const manageError = ref('')

useStatusAnnouncer(computed(() => status.value))

let debounceHandle: ReturnType<typeof setTimeout> | undefined
watch(searchQuery, (value) => {
  clearTimeout(debounceHandle)
  debounceHandle = setTimeout(() => {
    debouncedQuery.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(debounceHandle))

const filtersActive = computed(
  () => debouncedQuery.value !== '' || selectedTags.value.size > 0 || favoritesOnly.value,
)

async function load(reset: boolean): Promise<void> {
  if (reset) {
    status.value = 'loading'
    items.value = []
    nextCursor.value = null
  } else {
    loadingMore.value = true
  }

  try {
    const page = await fetchConsultations({
      cursor: reset ? null : nextCursor.value,
      q: debouncedQuery.value,
      tags: [...selectedTags.value],
      favorite: favoritesOnly.value,
    })
    items.value = reset ? page.items : [...items.value, ...page.items]
    nextCursor.value = page.nextCursor
    status.value = 'ready'
  } catch (error) {
    if (reset) {
      status.value = 'error'
      errorMessage.value = error instanceof Error ? error.message : t('history.loadError')
    }
  } finally {
    loadingMore.value = false
  }
}

async function loadTagCounts(): Promise<void> {
  try {
    tagCounts.value = await fetchTagsWithCounts()
  } catch {
    // The manage panel just shows nothing.
  }
}

onMounted(async () => {
  await load(true)
  try {
    allTags.value = await fetchConsultationTags()
  } catch {
    // The chips just won't render — the list itself still works.
  }
  void loadTagCounts()
})

// Any filter change restarts paging from page 1 with the new server-side params.
watch([debouncedQuery, selectedTags, favoritesOnly], () => void load(true))

const groupedConsultations = computed<ConsultationGroup[]>(() => {
  const groups: ConsultationGroup[] = []
  for (const consultation of items.value) {
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

function startRename(name: string): void {
  editingTag.value = name
  editName.value = name
  confirmingDelete.value = null
  manageError.value = ''
}

// A rename or delete may have changed which consultations match the current filters, and may
// have removed a tag that's in `selectedTags` — refresh the vocabulary, the counts, and the list.
async function refreshTagsAfterMutation(goneOrOldName: string): Promise<void> {
  try {
    allTags.value = await fetchConsultationTags()
  } catch {
    /* keep the old chip list */
  }
  await loadTagCounts()

  if (selectedTags.value.has(goneOrOldName)) {
    const next = new Set(selectedTags.value)
    next.delete(goneOrOldName)
    selectedTags.value = next // the watch on selectedTags re-runs load(true)
  } else {
    await load(true)
  }
}

async function saveRename(): Promise<void> {
  const from = editingTag.value
  const to = editName.value.trim()
  if (from === null) return
  if (to === '' || to === from) {
    editingTag.value = null
    return
  }

  try {
    const { merged } = await renameTag(from, to)
    editingTag.value = null
    notifySaved(merged ? 'history.tagMerged' : 'history.tagRenamed')
    await refreshTagsAfterMutation(from)
  } catch (error) {
    manageError.value = error instanceof Error ? error.message : t('history.tagOpError')
  }
}

async function removeTag(name: string): Promise<void> {
  try {
    await deleteTag(name)
    confirmingDelete.value = null
    notifySaved('history.tagDeleted')
    await refreshTagsAfterMutation(name)
  } catch (error) {
    manageError.value = error instanceof Error ? error.message : t('history.tagOpError')
  }
}

async function exportBackup(): Promise<void> {
  exporting.value = true
  try {
    const all = await fetchConsultationsForExport()
    exportConsultationsBackup(all)
  } catch (error) {
    importState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('history.exportError'),
    }
  } finally {
    exporting.value = false
  }
}

async function handleImportFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  importState.value = { status: 'importing' }

  try {
    const text = await file.text()
    let parsed: unknown[]
    try {
      parsed = JSON.parse(text)
    } catch {
      throw new Error(t('history.invalidJson'))
    }
    if (!Array.isArray(parsed)) {
      throw new Error(t('history.invalidBackupArray'))
    }

    const { imported } = await importConsultationsBackup(parsed)
    importState.value = { status: 'success', imported }

    await load(true)
    try {
      allTags.value = await fetchConsultationTags()
    } catch {
      /* keep whatever tag list we had */
    }
    void loadTagCounts()
  } catch (error) {
    importState.value = {
      status: 'error',
      message: error instanceof Error ? error.message : t('history.importError'),
    }
  } finally {
    input.value = ''
  }
}
</script>

<template>
  <main id="main" tabindex="-1" class="container-sm mx-auto p-4">
    <div class="mb-4 flex align-items-center justify-content-between gap-3">
      <h1 class="text-2xl font-semibold m-0">{{ t('history.title') }}</h1>
      <div class="flex align-items-center gap-3">
        <Button
          text
          size="small"
          :label="t('history.exportBackup')"
          :disabled="status !== 'ready' || exporting"
          @click="exportBackup"
        />
        <label class="cursor-pointer text-sm p-button p-button-text p-button-sm">
          {{ t('history.importBackup') }}
          <input type="file" accept="application/json" class="hidden-input" @change="handleImportFile" />
        </label>
      </div>
    </div>

    <Message v-if="importState.status === 'success'" severity="success" class="mb-4">
      {{
        t('history.imported', {
          count: importState.imported,
          suffix: importState.imported === 1 ? '' : 's',
        })
      }}
    </Message>
    <Message v-else-if="importState.status === 'error'" severity="error" role="alert" class="mb-4">
      {{ importState.message }}
    </Message>

    <LoadingSkeleton v-if="status === 'loading'" :lines="6" />
    <Message v-else-if="status === 'error'" severity="error" role="alert">{{ errorMessage }}</Message>

    <template v-else>
      <InputText
        v-model="searchQuery"
        type="search"
        :placeholder="t('history.searchPlaceholder')"
        class="mb-4 w-full"
      />

      <details v-if="tagCounts.length > 0" class="mb-4">
        <summary class="cursor-pointer text-sm font-medium">{{ t('history.manageTags') }}</summary>
        <div class="mt-3 flex flex-column gap-2">
          <Message v-if="manageError" severity="error" role="alert">{{ manageError }}</Message>
          <div
            v-for="tag in tagCounts"
            :key="tag.name"
            class="flex flex-wrap align-items-center gap-2 border-round border-1 surface-border p-2"
          >
            <template v-if="editingTag === tag.name">
              <InputText v-model="editName" class="flex-1" @keyup.enter="saveRename" />
              <Button size="small" :label="t('common.save')" @click="saveRename" />
              <Button size="small" text :label="t('common.cancel')" @click="editingTag = null" />
            </template>
            <template v-else-if="confirmingDelete === tag.name">
              <span class="flex-1 text-sm">{{ t('history.confirmDeleteTag', { name: tag.name }) }}</span>
              <Button size="small" severity="danger" :label="t('history.deleteTag')" @click="removeTag(tag.name)" />
              <Button size="small" text :label="t('common.cancel')" @click="confirmingDelete = null" />
            </template>
            <template v-else>
              <span class="flex-1 text-sm">{{ tag.name }} <span class="text-color-secondary">({{ tag.count }})</span></span>
              <Button size="small" text :label="t('history.rename')" @click="startRename(tag.name)" />
              <Button
                size="small"
                text
                severity="danger"
                :label="t('history.deleteTag')"
                @click="(confirmingDelete = tag.name), (editingTag = null), (manageError = '')"
              />
            </template>
          </div>
        </div>
      </details>

      <div class="mb-5 flex flex-wrap gap-2">
        <Button
          :aria-pressed="favoritesOnly"
          :label="t('history.favoritesOnly')"
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

      <p v-if="items.length === 0 && !filtersActive" class="text-color-secondary">
        {{ t('history.emptyPrefix') }}
        <router-link to="/consultations/new">{{ t('history.castFirstOne') }}</router-link>.
      </p>
      <p v-else-if="items.length === 0" class="text-color-secondary">
        {{ t('history.noTagMatches') }}
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

<style scoped>
.hidden-input {
  display: none;
}

.history-card:hover {
  border-color: var(--p-primary-color);
}
</style>
