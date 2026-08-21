<script setup lang="ts">
import { ref, watch } from 'vue'
import RadioButton from 'primevue/radiobutton'
import Message from 'primevue/message'
import { computeHexagramFromLines } from '../../entities/hexagram/api'
import HexagramLines from '../../entities/hexagram/ui/HexagramLines.vue'
import type { Hexagram, LinePolarity } from '../../entities/hexagram/model'

type State =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'loaded'; hexagram: Hexagram }

// Position 1 (bottom) to 6 (top), matching NewConsultationPage's existing line-ordering
// convention (SPEC-009) — this is a structural exploration tool, not a casting tool, so every
// line starts non-changing/yang; there is no "changing" concept here at all.
const lines = ref<LinePolarity[]>(Array.from({ length: 6 }, () => 'yang'))
const state = ref<State>({ status: 'loading' })

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
        message: error instanceof Error ? error.message : 'Failed to compute hexagram.',
      }
    }
  },
  { immediate: true, deep: true },
)
</script>

<template>
  <main class="max-w-screen-sm mx-auto p-4">
    <router-link to="/hexagrams" class="text-sm text-color-secondary">&larr; Hexagrams</router-link>

    <h1 class="mt-3 mb-4 text-2xl font-semibold">Visual Hexagram Editor</h1>

    <div class="flex flex-wrap align-items-start gap-6">
      <fieldset class="flex flex-column gap-2 border-none p-0 m-0">
        <legend class="mb-2 text-sm font-medium">Lines (top to bottom)</legend>
        <div
          v-for="position in [6, 5, 4, 3, 2, 1]"
          :key="position"
          class="flex align-items-center gap-3"
          :data-position="position"
        >
          <span class="w-2rem text-sm text-color-secondary">{{ position }}</span>
          <label class="inline-flex align-items-center gap-2">
            <RadioButton v-model="lines[position - 1]" :name="`polarity-${position}`" value="yang" />
            Yang
          </label>
          <label class="inline-flex align-items-center gap-2">
            <RadioButton v-model="lines[position - 1]" :name="`polarity-${position}`" value="yin" />
            Yin
          </label>
        </div>
      </fieldset>

      <div>
        <p v-if="state.status === 'loading'" class="text-color-secondary">Computing…</p>
        <Message v-else-if="state.status === 'error'" severity="error">{{ state.message }}</Message>

        <div v-else class="flex flex-column gap-4">
          <div class="flex align-items-center gap-4">
            <HexagramLines :lines="state.hexagram.lines" />
            <div>
              <h2 class="text-xl font-semibold m-0">
                {{ state.hexagram.kingWenNumber }}. {{ state.hexagram.chineseName }}
              </h2>
              <p class="text-color-secondary my-1">{{ state.hexagram.pinyin }}</p>
              <router-link :to="`/hexagrams/${state.hexagram.kingWenNumber}`" class="text-sm">
                View full page
              </router-link>
            </div>
          </div>

          <dl class="grid m-0">
            <div class="col-6">
              <dt class="text-sm text-color-secondary">Upper trigram</dt>
              <dd class="m-0">{{ state.hexagram.upperTrigram.symbol }} {{ state.hexagram.upperTrigram.name }}</dd>
            </div>
            <div class="col-6">
              <dt class="text-sm text-color-secondary">Lower trigram</dt>
              <dd class="m-0">{{ state.hexagram.lowerTrigram.symbol }} {{ state.hexagram.lowerTrigram.name }}</dd>
            </div>
          </dl>

          <dl class="grid m-0">
            <div class="col-4">
              <dt class="text-xs text-color-secondary uppercase">Nuclear</dt>
              <dd class="m-0">
                {{ state.hexagram.relationships.nuclear.kingWenNumber }}.
                {{ state.hexagram.relationships.nuclear.chineseName }}
              </dd>
            </div>
            <div class="col-4">
              <dt class="text-xs text-color-secondary uppercase">Reversed</dt>
              <dd class="m-0">
                {{ state.hexagram.relationships.reversed.kingWenNumber }}.
                {{ state.hexagram.relationships.reversed.chineseName }}
              </dd>
            </div>
            <div class="col-4">
              <dt class="text-xs text-color-secondary uppercase">Complement</dt>
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
