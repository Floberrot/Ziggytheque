<script setup lang="ts">
import { computed, ref } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { getNotifications } from '@/api/notification'
import { useAuthStore } from '@/stores/useAuthStore'
import { useUiStore } from '@/stores/useUiStore'
import { useThemeStore, THEMES } from '@/stores/useThemeStore'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BaseThemeSwitch from '@/components/atoms/BaseThemeSwitch.vue'
import BaseToast from '@/components/atoms/BaseToast.vue'
import LanguageSwitcher from '@/components/atoms/LanguageSwitcher.vue'

const auth = useAuthStore()
const ui = useUiStore()
const themeStore = useThemeStore()
const router = useRouter()
const { t, locale } = useI18n()

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

function toggleLocale() {
  locale.value = locale.value === 'fr' ? 'en' : 'fr'
  localStorage.setItem('locale', locale.value)
}

const settingsOpen = ref(false)

function openSettings() {
  settingsOpen.value = true
}

function closeSettings() {
  settingsOpen.value = false
}

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
      <!-- Page content -->
      <main class="flex-1 bg-base-200 min-h-screen pb-28 lg:pb-0">
        <RouterView />
      </main>

      <!-- Mobile floating pill nav -->
      <nav class="fixed bottom-6 left-1/2 -translate-x-1/2 z-30 lg:hidden flex items-center gap-1 px-3 py-2 rounded-full backdrop-blur-xl bg-base-100/75 border border-base-content/10 shadow-[0_8px_32px_rgba(0,0,0,0.25)]">
        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="{ name: item.name }"
          class="relative flex items-center justify-center w-11 h-11 rounded-full text-base-content/40 transition-all hover:text-base-content"
          active-class="text-primary bg-primary/10"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-[22px] h-[22px]">
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.svg" />
          </svg>
          <span
            v-if="item.name === 'notifications' && unreadCount > 0"
            class="absolute top-1.5 right-1.5 bg-primary text-primary-content text-[7px] font-bold rounded-full w-3 h-3 flex items-center justify-center leading-none"
          >{{ unreadCount }}</span>
        </RouterLink>

        <!-- Separator -->
        <div class="w-px h-5 bg-base-content/15 mx-1" />

        <!-- Settings button -->
        <button
          class="flex items-center justify-center w-11 h-11 rounded-full text-base-content/40 transition-all hover:text-base-content"
          :class="{ 'text-primary bg-primary/10': settingsOpen }"
          @click="openSettings"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-[22px] h-[22px]">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
          </svg>
        </button>
      </nav>
    </div>

    <!-- Sidebar (desktop only) -->
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

        <!-- Footer: desktop only -->
        <div class="hidden lg:block p-3 border-t border-base-200 space-y-2">
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

  <!-- Mobile settings bottom sheet -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div v-if="settingsOpen" class="lg:hidden fixed inset-0 z-50 flex flex-col justify-end">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40" @click="closeSettings" />

        <!-- Sheet -->
        <Transition
          enter-active-class="transition-transform duration-300 ease-out"
          leave-active-class="transition-transform duration-300 ease-in"
          enter-from-class="translate-y-full"
          leave-to-class="translate-y-full"
        >
          <div v-if="settingsOpen" class="relative bg-base-100 rounded-t-2xl pb-10 shadow-xl">
            <!-- Handle -->
            <div class="w-10 h-1 bg-base-300 rounded-full mx-auto mt-3 mb-2" />

            <div class="px-4 py-3">
              <h2 class="text-base font-semibold">{{ t('nav.settings') }}</h2>
            </div>

            <!-- Language row -->
            <button
              class="flex items-center justify-between w-full px-4 py-3.5 hover:bg-base-200 transition-colors text-left"
              @click="toggleLocale"
            >
              <span class="text-sm font-medium">{{ t('settings.language') }}</span>
              <span class="text-sm font-mono text-base-content/50">{{ locale.toUpperCase() }}</span>
            </button>

            <!-- Theme row -->
            <div class="flex items-center justify-between w-full px-4 py-3.5">
              <span class="text-sm font-medium">{{ t('settings.theme') }}</span>
              <select
                class="select select-sm select-bordered capitalize"
                :value="themeStore.theme"
                @change="themeStore.setTheme(($event.target as HTMLSelectElement).value as any)"
              >
                <option v-for="th in THEMES" :key="th" :value="th" class="capitalize">{{ th }}</option>
              </select>
            </div>

            <div class="mx-4 my-1 border-t border-base-200" />

            <!-- Logout row -->
            <button
              class="flex items-center gap-3 w-full px-4 py-3.5 hover:bg-base-200 transition-colors text-error"
              @click="logout"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
              </svg>
              <span class="text-sm font-medium">{{ t('nav.logout') }}</span>
            </button>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Toast container -->
    <div class="toast toast-end toast-bottom z-[9999] fixed">
      <BaseToast
        v-for="toast in ui.toasts"
        :key="toast.id"
        :message="toast.message"
        :type="toast.type"
      />
    </div>
  </Teleport>
</template>
