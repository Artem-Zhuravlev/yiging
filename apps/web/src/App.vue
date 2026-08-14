<script setup lang="ts"></script>

<template>
  <nav class="border-b border-neutral-200 px-6 py-4">
    <div class="mx-auto flex max-w-5xl gap-6 text-sm font-medium">
      <router-link to="/" class="hover:underline">Yijing</router-link>
      <router-link to="/hexagrams" class="hover:underline">Hexagrams</router-link>
    </div>
  </nav>
  <router-view />
</template>
