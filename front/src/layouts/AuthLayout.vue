<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-base-100 via-base-200 to-base-300 p-4">
    <div class="w-full max-w-sm">
      <Transition name="fade" mode="out-in">
        <RouterView :key="route.path" />
      </Transition>
    </div>
  </div>
</template>
