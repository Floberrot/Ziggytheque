<script setup lang="ts">
import { useThemeStore, THEMES } from '@/stores/useThemeStore'

const store = useThemeStore()
</script>

<template>
  <div class="dropdown dropdown-end dropdown-top">
    <button tabindex="0" class="btn btn-ghost btn-xs gap-1" :title="store.theme">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z" />
      </svg>
    </button>

    <ul
      tabindex="0"
      class="dropdown-content bg-base-200 rounded-box shadow-lg z-50 w-36 max-h-72 overflow-y-auto p-1 flex flex-col gap-0.5"
    >
      <li v-for="t in THEMES" :key="t">
        <button
          class="btn btn-ghost btn-xs w-full justify-start font-normal capitalize"
          :class="{ 'btn-active text-primary': store.theme === t }"
          @click="store.setTheme(t)"
        >
          {{ t }}
        </button>
      </li>
    </ul>
  </div>
</template>
