<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <nav v-if="!route.meta.public" class="print:hidden border-b border-neutral-200 px-6 py-4">
    <div class="mx-auto flex max-w-5xl gap-6 text-sm font-medium">
      <router-link to="/" class="hover:underline">Yijing</router-link>
      <router-link to="/hexagrams" class="hover:underline">Hexagrams</router-link>
      <router-link to="/consultations/new" class="hover:underline">New Consultation</router-link>
      <router-link to="/consultations" class="hover:underline">History</router-link>
      <router-link to="/journal" class="hover:underline">Journal</router-link>
      <router-link to="/statistics" class="hover:underline">Statistics</router-link>
    </div>
  </nav>
  <nav v-else class="print:hidden border-b border-neutral-200 px-6 py-4">
    <div class="mx-auto max-w-5xl text-sm font-medium">Yijing</div>
  </nav>
  <router-view />
</template>
