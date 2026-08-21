<script setup lang="ts"></script>

<template>
  <nav class="border-b border-neutral-200 px-6 py-4">
    <div class="mx-auto flex max-w-5xl gap-6 text-sm font-medium">
      <router-link to="/" class="hover:underline">Yijing</router-link>
      <router-link to="/hexagrams" class="hover:underline">Hexagrams</router-link>
      <router-link to="/consultations/new" class="hover:underline">New Consultation</router-link>
      <router-link to="/consultations" class="hover:underline">History</router-link>
      <router-link to="/statistics" class="hover:underline">Statistics</router-link>
    </div>
  </nav>
  <router-view />
</template>
