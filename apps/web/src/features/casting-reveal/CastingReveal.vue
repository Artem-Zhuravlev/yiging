<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import { fetchHexagram } from '../../entities/hexagram/api'
import type { Consultation } from '../../entities/consultation/model'
import type { HexagramLine } from '../../entities/hexagram/model'

const props = defineProps<{ consultation: Consultation }>()

const { t } = useI18n()
const router = useRouter()

const COIN_MS = 1000
const PER_LINE_MS = 350
const HOLD_MS = 1600

type Phase = 'coins' | 'lines' | 'named'
const phase = ref<Phase>('coins')
const lines = ref<HexagramLine[]>([])
const revealedCount = ref(0)

const timers: ReturnType<typeof setTimeout>[] = []
let done = false

function finish(): void {
  if (done) return
  done = true
  for (const id of timers) clearTimeout(id)
  void router.push(`/consultations/${props.consultation.id}`)
}

// Domain lines run position 1 (bottom) → 6 (top). Reveal in that order, but stack top-to-bottom
// visually, so `visibleLines` is the revealed prefix, reversed.
function visibleLines(): HexagramLine[] {
  return lines.value.slice(0, revealedCount.value).reverse()
}

function startLineReveal(): void {
  phase.value = 'lines'
  const step = (): void => {
    if (done) return
    revealedCount.value += 1
    if (revealedCount.value < lines.value.length) {
      timers.push(setTimeout(step, PER_LINE_MS))
    } else {
      timers.push(
        setTimeout(() => {
          if (done) return
          phase.value = 'named'
          timers.push(setTimeout(finish, HOLD_MS))
        }, PER_LINE_MS),
      )
    }
  }
  timers.push(setTimeout(step, PER_LINE_MS))
}

onMounted(async () => {
  try {
    const hexagram = await fetchHexagram(props.consultation.primaryHexagram.kingWenNumber)
    if (done) return
    lines.value = hexagram.lines.map((line) => ({
      ...line,
      changing: props.consultation.changingLinePositions.includes(line.position),
    }))
    timers.push(setTimeout(startLineReveal, COIN_MS))
  } catch {
    // Don't trap the user on a decorative screen — the detail page surfaces any real error.
    finish()
  }
})

onBeforeUnmount(() => {
  done = true
  for (const id of timers) clearTimeout(id)
})
</script>

<template>
  <section
    class="casting-reveal flex flex-column align-items-center gap-4 text-center py-6"
    aria-live="polite"
  >
    <div class="coins flex gap-3" :class="{ 'coins-settled': phase !== 'coins' }" aria-hidden="true">
      <span class="coin" />
      <span class="coin" />
      <span class="coin" />
    </div>

    <p v-if="phase === 'coins'" class="text-color-secondary m-0">{{ t('castingReveal.casting') }}</p>

    <div v-else class="flex flex-column gap-2">
      <div
        v-for="line in visibleLines()"
        :key="line.position"
        class="reveal-line flex align-items-center gap-2"
        :data-position="line.position"
      >
        <span v-if="line.polarity === 'yang'" class="hexagram-line-bar" />
        <template v-else>
          <span class="hexagram-line-bar hexagram-line-bar-broken" />
          <span class="hexagram-line-bar hexagram-line-bar-broken" />
        </template>
        <span v-if="line.changing" class="hexagram-line-changing-dot" />
      </div>
    </div>

    <div v-if="phase === 'named'" class="flex flex-column align-items-center gap-3">
      <p class="text-lg font-medium m-0">
        {{ consultation.primaryHexagram.kingWenNumber }}. {{ consultation.primaryHexagram.chineseName }}
        <span class="text-color-secondary">({{ consultation.primaryHexagram.pinyin }})</span>
      </p>
      <Button :label="t('castingReveal.continue')" @click="finish" />
    </div>

    <Button
      v-if="phase !== 'named'"
      text
      size="small"
      :label="t('castingReveal.skip')"
      @click="finish"
    />
  </section>
</template>

<style scoped>
.hexagram-line-bar {
  height: 0.5rem;
  width: 4rem;
  border-radius: 2px;
  background: var(--p-text-color);
}

.hexagram-line-bar-broken {
  width: 45%;
}

.reveal-line {
  height: 0.5rem;
  width: 4rem;
}

.hexagram-line-changing-dot {
  height: 0.375rem;
  width: 0.375rem;
  flex-shrink: 0;
  border-radius: 50%;
  background: var(--p-primary-color);
}

.coin {
  display: inline-block;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 50%;
  border: 2px solid var(--p-primary-color);
  background: var(--p-content-background);
}

@media (prefers-reduced-motion: no-preference) {
  .coins .coin {
    animation: coin-flip 0.5s ease-in-out infinite;
  }

  .coins .coin:nth-child(2) {
    animation-delay: 0.12s;
  }

  .coins .coin:nth-child(3) {
    animation-delay: 0.24s;
  }

  .coins-settled .coin {
    animation: none;
  }

  .reveal-line {
    animation: line-in 0.28s ease-out;
  }

  .hexagram-line-changing-dot {
    animation: dot-pulse 0.6s ease-out;
  }

  @keyframes coin-flip {
    0%,
    100% {
      transform: rotateX(0deg);
    }
    50% {
      transform: rotateX(180deg);
    }
  }

  @keyframes line-in {
    from {
      opacity: 0;
      transform: translateY(6px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @keyframes dot-pulse {
    from {
      transform: scale(0.2);
      opacity: 0.4;
    }
    to {
      transform: scale(1);
      opacity: 1;
    }
  }
}
</style>
