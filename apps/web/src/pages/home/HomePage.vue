<script setup lang="ts"></script>

<template>
  <main
    class="mx-auto flex min-h-screen max-w-2xl flex-col items-center justify-center gap-4 px-6 text-center"
  >
    <h1 class="text-3xl font-semibold tracking-tight">Yijing</h1>
    <p class="text-neutral-500">Digital I Ching study &amp; practice platform.</p>
    <div class="flex gap-4">
      <router-link
        to="/consultations/new"
        class="rounded-md bg-neutral-800 px-4 py-2 text-white hover:bg-neutral-700"
      >
        Cast a new consultation
      </router-link>
      <router-link
        to="/consultations"
        class="rounded-md border border-neutral-300 px-4 py-2 hover:border-neutral-400"
      >
        View history
      </router-link>
    </div>
  </main>
</template>
