<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { compareHexagrams } from '../../entities/hexagram/api'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import type { HexagramComparison } from '../../entities/hexagram/model'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'
import LoadingSkeleton from '../../shared/ui/LoadingSkeleton.vue'

const RELATIONSHIP_KEYS = ['nuclear', 'reversed', 'complement'] as const

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; comparison: HexagramComparison }

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const state = ref<State>({ status: 'loading' })

function parseKingWenNumber(value: unknown, fallback: number): number {
  const parsed = Number(value)
  return Number.isInteger(parsed) && parsed >= 1 && parsed <= 64 ? parsed : fallback
}

const queryA = computed(() => parseKingWenNumber(route.query.a, 1))
const queryB = computed(() => parseKingWenNumber(route.query.b, 2))

// Form inputs are independent of the fetched state so the user can type a new pair before
// submitting, without the fields jumping around as the previous comparison loads.
const formA = ref(queryA.value)
const formB = ref(queryB.value)

useStatusAnnouncer(computed(() => state.value.status))

function submit(): void {
  void router.push({ query: { a: String(formA.value), b: String(formB.value) } })
}

watch(
  [queryA, queryB, locale],
  async () => {
    const a = queryA.value
    const b = queryB.value
    formA.value = a
    formB.value = b
    state.value = { status: 'loading' }
    try {
      const comparison = await compareHexagrams(a, b, locale.value)
      state.value = { status: 'loaded', comparison }
    } catch (error) {
      state.value = {
        status: 'error',
        message: error instanceof Error ? error.message : t('hexagramCompare.loadError'),
      }
    }
  },
  { immediate: true },
)

// Equality checks only, against relationships already computed by the API (SPEC-014/017) — no
// relationship math here.
const relationshipNote = computed<string | null>(() => {
  if (state.value.status !== 'loaded') {
    return null
  }
  const { a, b } = state.value.comparison

  for (const key of RELATIONSHIP_KEYS) {
    if (a.relationships[key].kingWenNumber === b.kingWenNumber) {
      return t('hexagramCompare.relationshipNote', {
        subject: b.kingWenNumber,
        owner: a.kingWenNumber,
        relation: t(`hexagramCompare.relationshipLabels.${key}`),
      })
    }
    if (b.relationships[key].kingWenNumber === a.kingWenNumber) {
      return t('hexagramCompare.relationshipNote', {
        subject: a.kingWenNumber,
        owner: b.kingWenNumber,
        relation: t(`hexagramCompare.relationshipLabels.${key}`),
      })
    }
  }
  return null
})
</script>

<template>
  <main id="main" tabindex="-1" class="container-md mx-auto p-4">
    <router-link to="/hexagrams" class="text-sm text-color-secondary">&larr; {{ t('nav.hexagrams') }}</router-link>

    <h1 class="mt-3 mb-4 text-2xl font-semibold">{{ t('hexagramCompare.title') }}</h1>

    <form class="mb-5 flex align-items-end gap-3" @submit.prevent="submit">
      <label class="flex flex-column gap-1 text-sm">
        {{ t('hexagramCompare.hexagramA') }}
        <input v-model.number="formA" type="number" min="1" max="64" class="p-inputtext p-component w-5rem" />
      </label>
      <label class="flex flex-column gap-1 text-sm">
        {{ t('hexagramCompare.hexagramB') }}
        <input v-model.number="formB" type="number" min="1" max="64" class="p-inputtext p-component w-5rem" />
      </label>
      <Button type="submit" :label="t('hexagramCompare.compare')" />
    </form>

    <LoadingSkeleton v-if="state.status === 'loading'" :lines="5" />
    <Message v-else-if="state.status === 'error'" severity="error" role="alert">{{ state.message }}</Message>

    <div v-else class="flex flex-column gap-5">
      <div class="flex flex-wrap gap-6">
        <div>
          <h2 class="mb-2 text-sm font-medium text-color-secondary">
            A — {{ state.comparison.a.kingWenNumber }}. {{ state.comparison.a.chineseName }}
          </h2>
          <HexagramLines :lines="state.comparison.a.lines" />
          <router-link :to="`/hexagrams/${state.comparison.a.kingWenNumber}`" class="mt-2 block text-sm">
            {{ t('common.viewFullPage') }}
          </router-link>
        </div>
        <div>
          <h2 class="mb-2 text-sm font-medium text-color-secondary">
            B — {{ state.comparison.b.kingWenNumber }}. {{ state.comparison.b.chineseName }}
          </h2>
          <HexagramLines :lines="state.comparison.b.lines" />
          <router-link :to="`/hexagrams/${state.comparison.b.kingWenNumber}`" class="mt-2 block text-sm">
            {{ t('common.viewFullPage') }}
          </router-link>
        </div>
      </div>

      <p v-if="relationshipNote" class="m-0">{{ relationshipNote }}</p>

      <table class="w-full text-sm compare-table">
        <thead>
          <tr>
            <th class="text-left py-1">{{ t('hexagramCompare.position') }}</th>
            <th class="text-left py-1">A</th>
            <th class="text-left py-1">B</th>
            <th class="text-left py-1">{{ t('hexagramCompare.changed') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="line in [...state.comparison.lineComparisons].reverse()"
            :key="line.position"
            :data-position="line.position"
            :data-changed="line.changed ? 'true' : undefined"
          >
            <td class="py-1">{{ line.position }}</td>
            <td class="py-1">{{ line.aPolarity }}</td>
            <td class="py-1">{{ line.bPolarity }}</td>
            <td class="py-1">{{ line.changed ? t('hexagramCompare.yes') : '—' }}</td>
          </tr>
        </tbody>
      </table>

      <dl class="grid m-0">
        <div class="col-6">
          <dt class="text-sm text-color-secondary">{{ t('hexagramCompare.upperTrigrams') }}</dt>
          <dd class="m-0">
            {{ state.comparison.upperTrigramDiffers ? t('hexagramCompare.differ') : t('hexagramCompare.match') }}
          </dd>
        </div>
        <div class="col-6">
          <dt class="text-sm text-color-secondary">{{ t('hexagramCompare.lowerTrigrams') }}</dt>
          <dd class="m-0">
            {{ state.comparison.lowerTrigramDiffers ? t('hexagramCompare.differ') : t('hexagramCompare.match') }}
          </dd>
        </div>
      </dl>

      <div class="grid">
        <div class="col-6">
          <h3 class="text-xs font-medium text-color-secondary uppercase">{{ t('hexagramCompare.judgmentA') }}</h3>
          <p class="text-sm">{{ state.comparison.a.judgment ?? t('common.notAvailable') }}</p>
        </div>
        <div class="col-6">
          <h3 class="text-xs font-medium text-color-secondary uppercase">{{ t('hexagramCompare.judgmentB') }}</h3>
          <p class="text-sm">{{ state.comparison.b.judgment ?? t('common.notAvailable') }}</p>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.compare-table {
  border-collapse: collapse;
}

.compare-table th,
.compare-table td {
  border-bottom: 1px solid var(--p-content-border-color);
}
</style>
