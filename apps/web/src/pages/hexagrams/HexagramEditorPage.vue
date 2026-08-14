<script setup lang="ts">
import { ref, watch } from 'vue'
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
  <main class="mx-auto max-w-2xl px-6 py-10">
    <router-link to="/hexagrams" class="text-sm text-neutral-500 hover:underline">
      &larr; Hexagrams
    </router-link>

    <h1 class="mt-4 mb-6 text-2xl font-semibold tracking-tight">Visual Hexagram Editor</h1>

    <div class="flex flex-wrap items-start gap-10">
      <fieldset class="flex flex-col gap-2">
        <legend class="mb-1 text-sm font-medium text-neutral-700">Lines (top to bottom)</legend>
        <div
          v-for="position in [6, 5, 4, 3, 2, 1]"
          :key="position"
          class="flex items-center gap-4"
          :data-position="position"
        >
          <span class="w-6 text-sm text-neutral-500">{{ position }}</span>
          <label class="inline-flex items-center gap-1">
            <input
              v-model="lines[position - 1]"
              type="radio"
              :name="`polarity-${position}`"
              value="yang"
            />
            Yang
          </label>
          <label class="inline-flex items-center gap-1">
            <input
              v-model="lines[position - 1]"
              type="radio"
              :name="`polarity-${position}`"
              value="yin"
            />
            Yin
          </label>
        </div>
      </fieldset>

      <div>
        <p v-if="state.status === 'loading'" class="text-neutral-500">Computing…</p>
        <p v-else-if="state.status === 'error'" class="text-red-600">{{ state.message }}</p>

        <div v-else class="flex flex-col gap-4">
          <div class="flex items-center gap-6">
            <HexagramLines :lines="state.hexagram.lines" />
            <div>
              <h2 class="text-xl font-semibold tracking-tight">
                {{ state.hexagram.kingWenNumber }}. {{ state.hexagram.chineseName }}
              </h2>
              <p class="text-neutral-500">{{ state.hexagram.pinyin }}</p>
              <router-link
                :to="`/hexagrams/${state.hexagram.kingWenNumber}`"
                class="text-sm underline hover:no-underline"
              >
                View full page
              </router-link>
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

          <dl class="grid grid-cols-3 gap-4">
            <div>
              <dt class="text-xs text-neutral-400 uppercase">Nuclear</dt>
              <dd>
                {{ state.hexagram.relationships.nuclear.kingWenNumber }}.
                {{ state.hexagram.relationships.nuclear.chineseName }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-neutral-400 uppercase">Reversed</dt>
              <dd>
                {{ state.hexagram.relationships.reversed.kingWenNumber }}.
                {{ state.hexagram.relationships.reversed.chineseName }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-neutral-400 uppercase">Complement</dt>
              <dd>
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
