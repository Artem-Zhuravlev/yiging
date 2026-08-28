<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Toolbar from 'primevue/toolbar'
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import { setLocale, SUPPORTED_LOCALES } from './i18n'
import type { Locale } from './i18n'
import { isDarkMode, setDarkMode } from './darkMode'
import SkipLink from './shared/ui/SkipLink.vue'
import LiveRegion from './shared/ui/LiveRegion.vue'

const route = useRoute()
const { t, locale } = useI18n()

const mobileNavOpen = ref(false)

const links = computed(() => [
  { to: '/', label: 'Yijing' },
  { to: '/hexagrams', label: t('nav.hexagrams') },
  { to: '/consultations/new', label: t('nav.newConsultation') },
  { to: '/consultations', label: t('nav.history') },
  { to: '/journal', label: t('nav.journal') },
  { to: '/statistics', label: t('nav.statistics') },
  { to: '/settings', label: t('nav.settings') },
])
</script>

<template>
  <SkipLink />
  <Toolbar class="print-hidden border-noround border-x-none border-top-none">
    <template #start>
      <template v-if="!route.meta.public">
        <nav :aria-label="t('nav.primaryLabel')" class="hidden md:flex flex-wrap gap-2">
          <router-link
            v-for="link in links"
            :key="link.to"
            :to="link.to"
            class="p-button p-button-text p-button-sm"
          >
            {{ link.label }}
          </router-link>
        </nav>
        <Button
          class="md:hidden"
          icon="pi pi-bars"
          text
          rounded
          :aria-label="t('nav.menu')"
          :aria-expanded="mobileNavOpen"
          @click="mobileNavOpen = true"
        />
      </template>
      <div v-else class="font-semibold">Yijing</div>
    </template>
    <template #end>
      <div class="flex align-items-center gap-2">
        <Button
          :icon="isDarkMode ? 'pi pi-sun' : 'pi pi-moon'"
          text
          rounded
          size="small"
          :aria-label="isDarkMode ? t('nav.lightMode') : t('nav.darkMode')"
          @click="setDarkMode(!isDarkMode)"
        />
        <div class="flex gap-1">
          <Button
            v-for="code in SUPPORTED_LOCALES"
            :key="code"
            :label="code.toUpperCase()"
            size="small"
            :text="locale !== code"
            :aria-pressed="locale === code"
            @click="setLocale(code as Locale)"
          />
        </div>
      </div>
    </template>
  </Toolbar>

  <Drawer
    v-if="!route.meta.public"
    v-model:visible="mobileNavOpen"
    :header="t('nav.menu')"
    class="print-hidden"
    @keydown.esc="mobileNavOpen = false"
  >
    <nav :aria-label="t('nav.primaryLabel')" class="flex flex-column gap-1">
      <router-link
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        class="p-button p-button-text justify-content-start"
        @click="mobileNavOpen = false"
      >
        {{ link.label }}
      </router-link>
    </nav>
  </Drawer>

  <LiveRegion />
  <router-view />
</template>
