<script setup lang="ts">
import { useRoute } from 'vue-router'
import Toolbar from 'primevue/toolbar'

const route = useRoute()

const links = [
  { to: '/', label: 'Yijing' },
  { to: '/hexagrams', label: 'Hexagrams' },
  { to: '/consultations/new', label: 'New Consultation' },
  { to: '/consultations', label: 'History' },
  { to: '/journal', label: 'Journal' },
  { to: '/statistics', label: 'Statistics' },
  { to: '/settings', label: 'Settings' },
]
</script>

<template>
  <Toolbar class="print-hidden border-noround border-x-none border-top-none">
    <template #start>
      <div v-if="!route.meta.public" class="flex flex-wrap gap-2">
        <router-link
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          class="p-button p-button-text p-button-sm"
        >
          {{ link.label }}
        </router-link>
      </div>
      <div v-else class="font-semibold">Yijing</div>
    </template>
  </Toolbar>
  <router-view />
</template>
