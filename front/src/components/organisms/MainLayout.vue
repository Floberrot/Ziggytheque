<script setup lang="ts">
import { shallowRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { useUiStore } from '@/stores/useUiStore'
import { useThemeStore, THEMES } from '@/stores/useThemeStore'
import { useI18n } from 'vue-i18n'
import { Menu, Settings, LogOut, Globe, Palette, LayoutDashboard, Library, ShoppingCart, PlusCircle, Bell } from 'lucide-vue-next'
import BaseToast from '@/components/atoms/BaseToast.vue'

const auth = useAuthStore()
const ui = useUiStore()
const themeStore = useThemeStore()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

function logout() {
  auth.logout()
  router.push({ name: 'gate' })
}

const mobileNavOpen = shallowRef(false)
const settingsOpen = shallowRef(false)

watch(() => route.path, () => { mobileNavOpen.value = false })

function openSettings() {
  settingsOpen.value = true
  mobileNavOpen.value = false
}

function closeSettings() {
  settingsOpen.value = false
}

interface NavItem {
  name: string
  labelKey: string
  icon: unknown
  comingSoon?: true
}

const navItems: NavItem[] = [
  { name: 'dashboard',     labelKey: 'nav.dashboard',     icon: LayoutDashboard },
  { name: 'collection',    labelKey: 'nav.collection',    icon: Library },
  { name: 'wishlist',      labelKey: 'nav.wishlist',      icon: ShoppingCart },
  { name: 'add',           labelKey: 'nav.add',           icon: PlusCircle },
  { name: 'notifications', labelKey: 'nav.notifications', icon: Bell, comingSoon: true },
]
</script>

<template>
  <!-- Mobile top header -->
  <header class="lg:hidden fixed top-0 inset-x-0 z-30 h-14 bg-base-100/80 backdrop-blur-md border-b border-base-200 flex items-center px-4 gap-3">
    <button
      class="flex items-center justify-center w-10 h-10 rounded-lg text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
      :class="{ 'text-primary bg-primary/10': mobileNavOpen }"
      @click="mobileNavOpen = true"
    >
      <Menu class="w-5 h-5" stroke-width="1.5" />
    </button>

    <span class="font-bold tracking-tight flex-1">Ziggytheque</span>

    <button
      class="flex items-center justify-center w-10 h-10 rounded-lg text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
      :class="{ 'text-primary bg-primary/10': settingsOpen }"
      @click="openSettings"
    >
      <Settings class="w-5 h-5" stroke-width="1.5" />
    </button>
  </header>

  <div class="drawer lg:drawer-open min-h-screen">
    <input id="drawer" type="checkbox" class="drawer-toggle" />

    <div class="drawer-content flex flex-col">
      <main class="flex-1 bg-base-200 min-h-screen pt-14 lg:pt-0">
        <RouterView />
      </main>
    </div>

    <!-- Sidebar (desktop only) -->
    <div class="drawer-side z-20">
      <label for="drawer" class="drawer-overlay" />
      <aside class="w-64 min-h-screen bg-base-100 flex flex-col">
        <div class="p-4 border-b border-base-200">
          <h1 class="text-xl font-bold tracking-tight">Ziggytheque</h1>
        </div>

        <nav class="flex-1 p-3 space-y-1">
          <template v-for="item in navItems" :key="item.name">
            <div
              v-if="item.comingSoon"
              class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-base-content/30 cursor-not-allowed select-none"
            >
              <component :is="item.icon" class="w-5 h-5 shrink-0" stroke-width="1.5" />
              <span class="flex-1">{{ t(item.labelKey) }}</span>
              <span class="badge badge-sm badge-ghost text-base-content/30 border-base-content/15 text-[10px]">bientôt</span>
            </div>
            <RouterLink
              v-else
              :to="{ name: item.name }"
              class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors hover:bg-base-200"
              active-class="bg-primary/10 text-primary"
            >
              <component :is="item.icon" class="w-5 h-5 shrink-0" stroke-width="1.5" />
              <span>{{ t(item.labelKey) }}</span>
            </RouterLink>
          </template>
        </nav>

        <!-- Footer: desktop only -->
        <div class="hidden lg:flex flex-col p-3 border-t border-base-200 gap-0.5">
          <!-- Theme picker -->
          <div class="dropdown dropdown-top">
            <button
              tabindex="0"
              class="flex items-center gap-3 px-3 py-2 rounded-lg w-full text-sm font-medium text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
            >
              <Palette class="w-5 h-5 shrink-0" stroke-width="1.5" />
              <span class="flex-1 text-left">{{ t('settings.theme') }}</span>
              <span class="text-xs capitalize text-base-content/40">{{ themeStore.theme }}</span>
            </button>
            <ul
              tabindex="0"
              class="dropdown-content bg-base-200 rounded-box shadow-lg z-50 w-48 max-h-72 overflow-y-auto p-1 flex flex-col gap-0.5 mb-1"
            >
              <li v-for="th in THEMES" :key="th">
                <button
                  class="btn btn-ghost btn-xs w-full justify-start font-normal capitalize"
                  :class="{ 'btn-active text-primary': themeStore.theme === th }"
                  @click="themeStore.setTheme(th)"
                >
                  {{ th }}
                </button>
              </li>
            </ul>
          </div>

          <!-- Language toggle (disabled — coming soon) -->
          <div class="flex items-center gap-3 px-3 py-2 rounded-lg w-full text-sm font-medium text-base-content/30 cursor-not-allowed select-none">
            <Globe class="w-5 h-5 shrink-0" stroke-width="1.5" />
            <span class="flex-1 text-left">{{ t('settings.language') }}</span>
            <span class="badge badge-sm badge-ghost text-base-content/30 border-base-content/15 text-[10px]">bientôt</span>
          </div>

          <!-- Logout -->
          <button
            class="flex items-center gap-3 px-3 py-2 rounded-lg w-full text-sm font-medium text-error/70 hover:bg-error/10 hover:text-error transition-colors"
            @click="logout"
          >
            <LogOut class="w-5 h-5 shrink-0" stroke-width="1.5" />
            <span>{{ t('nav.logout') }}</span>
          </button>
        </div>
      </aside>
    </div>
  </div>

  <Teleport to="body">
    <!-- Mobile nav overlay -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div v-if="mobileNavOpen" class="lg:hidden fixed inset-0 z-40 flex">
        <div class="absolute inset-0 bg-black/40" @click="mobileNavOpen = false" />

        <Transition
          enter-active-class="transition-transform duration-300 ease-out"
          leave-active-class="transition-transform duration-250 ease-in"
          enter-from-class="-translate-x-full"
          leave-to-class="-translate-x-full"
        >
          <nav v-if="mobileNavOpen" class="relative flex flex-col w-20 min-h-screen bg-base-100 shadow-2xl">
            <!-- Nav items -->
            <div class="flex-1 flex flex-col items-center pt-4 gap-1">
              <template v-for="item in navItems" :key="item.name">
                <div
                  v-if="item.comingSoon"
                  class="relative flex items-center justify-center w-14 h-14 rounded-xl text-base-content/20 cursor-not-allowed select-none"
                >
                  <component :is="item.icon" class="w-6 h-6" stroke-width="1.5" />
                </div>
                <RouterLink
                  v-else
                  :to="{ name: item.name }"
                  class="flex items-center justify-center w-14 h-14 rounded-xl text-base-content/50 transition-colors hover:bg-base-200 hover:text-base-content"
                  active-class="bg-primary/10 text-primary"
                >
                  <component :is="item.icon" class="w-6 h-6" stroke-width="1.5" />
                </RouterLink>
              </template>
            </div>

            <!-- Bottom actions -->
            <div class="flex flex-col items-center pb-8 gap-1 border-t border-base-200 pt-2">
              <button
                class="flex items-center justify-center w-14 h-14 rounded-xl text-base-content/50 hover:bg-base-200 hover:text-base-content transition-colors"
                @click="openSettings"
              >
                <Settings class="w-6 h-6" stroke-width="1.5" />
              </button>
              <button
                class="flex items-center justify-center w-14 h-14 rounded-xl text-error/60 hover:bg-error/10 hover:text-error transition-colors"
                @click="logout"
              >
                <LogOut class="w-6 h-6" stroke-width="1.5" />
              </button>
            </div>
          </nav>
        </Transition>
      </div>
    </Transition>

    <!-- Mobile settings bottom sheet -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div v-if="settingsOpen" class="lg:hidden fixed inset-0 z-50 flex flex-col justify-end">
        <div class="absolute inset-0 bg-black/40" @click="closeSettings" />

        <Transition
          enter-active-class="transition-transform duration-300 ease-out"
          leave-active-class="transition-transform duration-300 ease-in"
          enter-from-class="translate-y-full"
          leave-to-class="translate-y-full"
        >
          <div v-if="settingsOpen" class="relative bg-base-100 rounded-t-2xl pb-10 shadow-xl">
            <div class="w-10 h-1 bg-base-300 rounded-full mx-auto mt-3 mb-2" />

            <div class="px-4 py-3">
              <h2 class="text-base font-semibold">{{ t('nav.settings') }}</h2>
            </div>

            <!-- Language row (disabled — coming soon) -->
            <div class="flex items-center justify-between w-full px-4 py-3.5 opacity-40 cursor-not-allowed select-none">
              <span class="text-sm font-medium">{{ t('settings.language') }}</span>
              <span class="badge badge-sm badge-ghost text-[10px]">bientôt</span>
            </div>

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

            <button
              class="flex items-center gap-3 w-full px-4 py-3.5 hover:bg-base-200 transition-colors text-error"
              @click="logout"
            >
              <LogOut class="w-5 h-5 shrink-0" stroke-width="1.5" />
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
