<template>
  <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
    <AppLogoIcon class="size-5 fill-current text-white dark:text-black"/>
  </div>
  <div class="ml-1 hidden sm:grid flex-1 text-left text-sm">
    <span class="mb-0.5 truncate leading-tight font-semibold">easyDoklad</span>
  </div>
</template>

<script setup lang="ts">
import AppLogoIcon from '@/Components/AppLogoIcon.vue';
</script>
