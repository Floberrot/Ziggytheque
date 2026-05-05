<script setup lang="ts">
import { ref } from 'vue'
import { useThemeStore } from '@/stores/useThemeStore'
import BaseTypewriter from '@/components/atoms/BaseTypewriter.vue'

const themeStore = useThemeStore()

const showSplash = ref(true)
const appReady = ref(false)

function onSplashTextComplete() {
  setTimeout(() => {
    showSplash.value = false
  }, 300)
}

function onSplashLeft() {
  appReady.value = true
}
</script>

<template>
  <div :data-theme="themeStore.theme">
    <Transition
      leave-active-class="transition-opacity duration-500 ease-in-out"
      leave-to-class="opacity-0"
      @after-leave="onSplashLeft"
    >
      <div
        v-if="showSplash"
        class="fixed inset-0 z-[9999] bg-base-100 flex items-center justify-center"
      >
        <h1 class="text-5xl font-bold tracking-tight select-none">
          <BaseTypewriter text="Ziggytheque" :speed="80" @complete="onSplashTextComplete" />
        </h1>
      </div>
    </Transition>

    <RouterView v-if="appReady" />
  </div>
</template>
