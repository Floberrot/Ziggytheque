<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { getNotifications } from '@/api/notification'
import { useAuthStore } from '@/stores/useAuthStore'
import { useUiStore } from '@/stores/useUiStore'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BaseThemeSwitch from '@/components/atoms/BaseThemeSwitch.vue'
import BaseToast from '@/components/atoms/BaseToast.vue'
import LanguageSwitcher from '@/components/atoms/LanguageSwitcher.vue'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()
const { t } = useI18n()

const { data: notifs } = useQuery({
  queryKey: ['notifications'],
  queryFn: getNotifications,
  refetchInterval: 60_000,
})

const unreadCount = computed(() => notifs.value?.length ?? 0)

function logout() {
  auth.logout()
  router.push({ name: 'gate' })
}

import { computed } from 'vue'

const navItems = [
  {
    name: 'dashboard',
    labelKey: 'nav.dashboard',
    svg: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
  },
  {
    name: 'collection',
    labelKey: 'nav.collection',
    svg: 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25',
  },
  {
    name: 'wishlist',
    labelKey: 'nav.wishlist',
    svg: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
  },
  {
    name: 'add',
    labelKey: 'nav.add',
    svg: 'M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  },
  {
    name: 'price-codes',
    labelKey: 'nav.priceCodes',
    svg: 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z M6 6h.008v.008H6V6Z',
  },
  {
    name: 'notifications',
    labelKey: 'nav.notifications',
    svg: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
  },
]
</script>

<template>
  <div class="drawer lg:drawer-open min-h-screen">
    <input id="drawer" type="checkbox" class="drawer-toggle" />

    <div class="drawer-content flex flex-col">
      <!-- Topbar -->
      <header class="navbar bg-base-100 border-b border-base-200 lg:hidden">
        <label for="drawer" class="btn btn-ghost drawer-button">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </label>
        <span class="font-bold text-lg flex-1">Ziggytheque</span>
      </header>

      <!-- Page content -->
      <main class="flex-1 bg-base-200 min-h-screen">
        <RouterView />
      </main>
    </div>

    <!-- Sidebar -->
    <div class="drawer-side z-20">
      <label for="drawer" class="drawer-overlay" />
      <aside class="w-64 min-h-screen bg-base-100 flex flex-col">
        <div class="p-4 border-b border-base-200">
          <h1 class="text-xl font-bold tracking-tight">Ziggytheque</h1>
        </div>

        <nav class="flex-1 p-3 space-y-1">
          <RouterLink
            v-for="item in navItems"
            :key="item.name"
            :to="{ name: item.name }"
            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors hover:bg-base-200"
            active-class="bg-primary/10 text-primary"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 shrink-0">
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.svg" />
            </svg>
            <span>{{ t(item.labelKey) }}</span>
            <span
              v-if="item.name === 'notifications' && unreadCount > 0"
              class="badge badge-primary badge-sm ml-auto"
            >{{ unreadCount }}</span>
          </RouterLink>
        </nav>

        <div class="p-3 border-t border-base-200 space-y-2">
          <div class="flex items-center justify-between px-2">
            <LanguageSwitcher />
            <BaseThemeSwitch />
          </div>
          <button class="btn btn-ghost btn-sm w-full justify-start gap-3" @click="logout">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 shrink-0">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
            </svg>
            {{ t('nav.logout') }}
          </button>
        </div>
      </aside>
    </div>
  </div>

  <!-- Toast container -->
  <div class="toast toast-end toast-bottom z-50">
    <BaseToast
      v-for="toast in ui.toasts"
      :key="toast.id"
      :message="toast.message"
      :type="toast.type"
    />
  </div>
</template>
