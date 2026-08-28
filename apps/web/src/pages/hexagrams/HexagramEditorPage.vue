<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import RadioButton from 'primevue/radiobutton'
import Message from 'primevue/message'
import { computeHexagramFromLines } from '../../entities/hexagram/api'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import type { Hexagram, LinePolarity } from '../../entities/hexagram/model'
import { useStatusAnnouncer } from '../../shared/lib/useStatusAnnouncer'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagram: Hexagram }

// Position 1 (bottom) to 6 (top), matching NewConsultationPage's existing line-ordering
// convention (SPEC-009) — this is a structural exploration tool, not a casting tool, so every
// line starts non-changing/yang; there is no "changing" concept here at all.
const { t } = useI18n()
const lines = ref<LinePolarity[]>(Array.from({ length: 6 }, () => 'yang'))
const state = ref<State>({ status: 'loading' })

useStatusAnnouncer(
  computed(() => state.value.status),
  (status) => (status === 'loading' ? t('hexagramEditor.computing') : undefined),
)

watch(
  lines,
  async (current) => {
    state.value = { status: 'loading' }
    try {
      const hexagram = await computeHexagramFromLines(current)
      state.value = { status: 'loaded', hexagram }
    } catch (error) {
      state.value = {
        status: 'error',
        message: error instanceof Error ? error.message : t('hexagramEditor.computeError'),
      }
    }
  },
  { immediate: true, deep: true },
)
</script>

<template>
  <main id="main" tabindex="-1" class="container-sm mx-auto p-4">
    <router-link to="/hexagrams" class="text-sm text-color-secondary">&larr; {{ t('nav.hexagrams') }}</router-link>

    <h1 class="mt-3 mb-4 text-2xl font-semibold">{{ t('hexagramEditor.title') }}</h1>

    <div class="flex flex-wrap align-items-start gap-6">
      <fieldset class="flex flex-column gap-2 border-none p-0 m-0">
        <legend class="mb-2 text-sm font-medium">{{ t('hexagramEditor.linesTopToBottom') }}</legend>
        <fieldset
          v-for="position in [6, 5, 4, 3, 2, 1]"
          :key="position"
          class="flex align-items-center gap-3 border-none p-0 m-0"
          :data-position="position"
        >
          <legend class="sr-only">{{ t('newConsultation.lineGroupLabel', { n: position }) }}</legend>
          <span class="w-2rem text-sm text-color-secondary">{{ position }}</span>
          <label class="inline-flex align-items-center gap-2">
            <RadioButton v-model="lines[position - 1]" :name="`polarity-${position}`" value="yang" />
            {{ t('common.yang') }}
          </label>
          <label class="inline-flex align-items-center gap-2">
            <RadioButton v-model="lines[position - 1]" :name="`polarity-${position}`" value="yin" />
            {{ t('common.yin') }}
          </label>
        </fieldset>
      </fieldset>

      <div>
        <p v-if="state.status === 'loading'" class="text-color-secondary">{{ t('hexagramEditor.computing') }}</p>
        <Message v-else-if="state.status === 'error'" severity="error" role="alert">{{ state.message }}</Message>

        <div v-else class="flex flex-column gap-4">
          <div class="flex align-items-center gap-4">
            <HexagramLines :lines="state.hexagram.lines" />
            <div>
              <h2 class="text-xl font-semibold m-0">
                {{ state.hexagram.kingWenNumber }}. {{ state.hexagram.chineseName }}
              </h2>
              <p class="text-color-secondary my-1">{{ state.hexagram.pinyin }}</p>
              <router-link :to="`/hexagrams/${state.hexagram.kingWenNumber}`" class="text-sm">
                {{ t('common.viewFullPage') }}
              </router-link>
            </div>
          </div>

          <dl class="grid m-0">
            <div class="col-6">
              <dt class="text-sm text-color-secondary">{{ t('common.upperTrigram') }}</dt>
              <dd class="m-0">{{ state.hexagram.upperTrigram.symbol }} {{ state.hexagram.upperTrigram.name }}</dd>
            </div>
            <div class="col-6">
              <dt class="text-sm text-color-secondary">{{ t('common.lowerTrigram') }}</dt>
              <dd class="m-0">{{ state.hexagram.lowerTrigram.symbol }} {{ state.hexagram.lowerTrigram.name }}</dd>
            </div>
          </dl>

          <dl class="grid m-0">
            <div class="col-4">
              <dt class="text-xs text-color-secondary uppercase">{{ t('common.nuclear') }}</dt>
              <dd class="m-0">
                {{ state.hexagram.relationships.nuclear.kingWenNumber }}.
                {{ state.hexagram.relationships.nuclear.chineseName }}
              </dd>
            </div>
            <div class="col-4">
              <dt class="text-xs text-color-secondary uppercase">{{ t('common.reversed') }}</dt>
              <dd class="m-0">
                {{ state.hexagram.relationships.reversed.kingWenNumber }}.
                {{ state.hexagram.relationships.reversed.chineseName }}
              </dd>
            </div>
            <div class="col-4">
              <dt class="text-xs text-color-secondary uppercase">{{ t('common.complement') }}</dt>
              <dd class="m-0">
                {{ state.hexagram.relationships.complement.kingWenNumber }}.
                {{ state.hexagram.relationships.complement.chineseName }}
              </dd>
            </div>
          </dl>
        </div>
      </div>
    </div>
  </main>
</template>
