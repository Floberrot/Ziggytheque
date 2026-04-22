<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import OAppSidebar from '@/components/organisms/OAppSidebar.vue'
import OBottomNav from '@/components/organisms/OBottomNav.vue'
import OSettingsSheet from '@/components/organisms/OSettingsSheet.vue'
import OToastHost from '@/components/organisms/OToastHost.vue'

const route = useRoute()
const showSettingsSheet = ref(false)
</script>

<template>
  <div class="flex min-h-screen flex-col bg-base-100 text-base-content lg:flex-row">
    <!-- Desktop Sidebar -->
    <OAppSidebar class="hidden lg:block" />

    <!-- Main Content -->
    <main id="main" class="flex-1 overflow-y-auto pb-24 lg:pb-0">
      <Transition name="fade" mode="out-in">
        <RouterView :key="route.path" />
      </Transition>
    </main>

    <!-- Mobile Bottom Nav -->
    <OBottomNav class="lg:hidden" @settings="showSettingsSheet = true" />

    <!-- Settings Sheet -->
    <OSettingsSheet :open="showSettingsSheet" @close="showSettingsSheet = false" />

    <!-- Toast Container -->
    <OToastHost />
  </div>
</template>
