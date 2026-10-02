<script setup lang="ts">
import { computed, shallowRef, watch, type Component } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { useUiStore } from '@/stores/useUiStore'
import { useThemeStore } from '@/stores/useThemeStore'
import { useI18n } from 'vue-i18n'
import {
  Settings, LogOut, Globe, Sun, Moon, LayoutDashboard, Library, ShoppingCart, PlusCircle, Plus, Bell,
  ClipboardList, Users, ChevronRight, BellRing,
} from 'lucide-vue-next'
import BaseToast from '@/components/atoms/BaseToast.vue'
import AppLogo from '@/components/atoms/AppLogo.vue'

const auth = useAuthStore()
const ui = useUiStore()
const themeStore = useThemeStore()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

function logout() {
  auth.logout()
  router.push({ name: 'login' })
}

const settingsOpen = shallowRef(false)

watch(() => route.path, () => { settingsOpen.value = false })

function openSettings() {
  settingsOpen.value = true
}

function closeSettings() {
  settingsOpen.value = false
}

interface NavItem {
  name: string
  labelKey: string
  /** Label of the phone bottom bar, where room is short. */
  shortLabelKey?: string
  icon: Component
  /** Also active on the pages below it ("/collection/42" for the collection). */
  pathPrefix: string
  adminOnly?: true
}

const allNavItems: NavItem[] = [
  { name: 'dashboard',     labelKey: 'nav.dashboard',     shortLabelKey: 'nav.dashboardShort', icon: LayoutDashboard, pathPrefix: '/dashboard' },
  { name: 'collection',    labelKey: 'nav.collection',    icon: Library,         pathPrefix: '/collection' },
  { name: 'wishlist',      labelKey: 'nav.wishlist',      shortLabelKey: 'nav.wishlistShort', icon: ShoppingCart, pathPrefix: '/wishlist' },
  { name: 'add',           labelKey: 'nav.add',           icon: PlusCircle,      pathPrefix: '/add' },
  { name: 'notifications', labelKey: 'nav.notifications', icon: Bell,            pathPrefix: '/notifications' },
  { name: 'journal',       labelKey: 'nav.journal',       icon: ClipboardList,   pathPrefix: '/journal', adminOnly: true },
  { name: 'admin-users',   labelKey: 'nav.adminUsers',    icon: Users,           pathPrefix: '/admin', adminOnly: true },
]

const mainNavItems = computed(() => allNavItems.filter((item) => !item.adminOnly))
const adminNavItems = computed(() =>
  auth.isAdmin ? allNavItems.filter((item) => item.adminOnly) : [],
)

/** Phone bottom bar: the add button sits in the middle, between these pairs. */
const BOTTOM_LEFT = ['dashboard', 'collection']
const BOTTOM_RIGHT = ['wishlist']
const bottomLeftItems = computed(() => allNavItems.filter((item) => BOTTOM_LEFT.includes(item.name)))
const bottomRightItems = computed(() => allNavItems.filter((item) => BOTTOM_RIGHT.includes(item.name)))

function isActive(item: NavItem): boolean {
  return route.path.startsWith(item.pathPrefix)
}

const addActive = computed(() => route.path.startsWith('/add'))
const notificationsActive = computed(() => route.path.startsWith('/notifications'))
</script>

<template>
  <!-- Mobile top header — safe-top grows it under the iOS notch -->
  <header class="lg:hidden fixed top-0 inset-x-0 z-30 safe-top bg-base-100/80 backdrop-blur-md border-b border-base-200">
    <div class="flex items-center h-14 px-4 gap-3">
      <RouterLink :to="{ name: 'dashboard' }" class="flex-1 flex items-center">
        <AppLogo />
      </RouterLink>

      <RouterLink
        :to="{ name: 'notifications' }"
        class="flex items-center justify-center w-10 h-10 rounded-lg text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
        :class="{ 'text-primary bg-primary/10': notificationsActive }"
        :aria-label="t('nav.notifications')"
      >
        <Bell class="w-5 h-5" stroke-width="1.5" />
      </RouterLink>
    </div>
  </header>

  <div class="drawer lg:drawer-open min-h-screen">
    <input id="drawer" type="checkbox" class="drawer-toggle" />

    <div class="drawer-content flex flex-col">
      <main class="flex-1 bg-base-200 min-h-screen pt-mobile-header pb-mobile-nav lg:pt-0 lg:pb-0">
        <!-- Keyed by route + id: opening another series from a series page mounts a fresh
             page, so nothing keeps acting on the previous id. -->
        <RouterView v-slot="{ Component, route: viewRoute }">
          <component :is="Component" :key="`${String(viewRoute.name)}:${String(viewRoute.params.id ?? '')}`" />
        </RouterView>
      </main>
    </div>

    <!-- Sidebar (desktop only) -->
    <div class="drawer-side z-20">
      <label for="drawer" class="drawer-overlay" />
      <aside class="w-64 min-h-screen bg-base-100 flex flex-col">
        <div class="px-4 py-2 border-b border-base-200">
          <AppLogo :full="true" />
        </div>

        <nav class="flex-1 p-3 space-y-1">
          <RouterLink
            v-for="item in mainNavItems"
            :key="item.name"
            :to="{ name: item.name }"
            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors hover:bg-base-200"
            :class="{ 'bg-primary/10 text-primary': isActive(item) }"
          >
            <component :is="item.icon" class="w-5 h-5 shrink-0" stroke-width="1.5" />
            <span>{{ t(item.labelKey) }}</span>
          </RouterLink>

          <template v-if="adminNavItems.length > 0">
            <p class="mt-2 pt-3 px-3 border-t border-base-200 text-[10px] font-semibold uppercase tracking-wider text-base-content/40">
              {{ t('nav.admin') }}
            </p>
            <RouterLink
              v-for="item in adminNavItems"
              :key="item.name"
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
          <!-- Theme toggle : Ziggy Dark ⇄ Ziggy Light -->
          <button
            class="flex items-center gap-3 px-3 py-2 rounded-lg w-full text-sm font-medium text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
            :aria-label="themeStore.isDark ? t('settings.switchToLight') : t('settings.switchToDark')"
            @click="themeStore.toggle()"
          >
            <Moon v-if="themeStore.isDark" class="w-5 h-5 shrink-0" stroke-width="1.5" />
            <Sun v-else class="w-5 h-5 shrink-0" stroke-width="1.5" />
            <span class="flex-1 text-left">{{ t('settings.theme') }}</span>
            <span class="text-xs text-base-content/40">
              {{ themeStore.isDark ? t('settings.themeDark') : t('settings.themeLight') }}
            </span>
          </button>

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
    <!-- Mobile bottom navigation — the add button stands out in the middle -->
    <nav
      class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-base-100/90 backdrop-blur-md border-t border-base-200 safe-bottom"
      :aria-label="t('nav.mobileNav')"
    >
      <div class="grid grid-cols-5 items-end h-16 max-w-lg mx-auto px-1">
        <RouterLink
          v-for="item in bottomLeftItems"
          :key="item.name"
          :to="{ name: item.name }"
          class="bottom-nav-item"
          :class="{ 'bottom-nav-item--active': isActive(item) }"
        >
          <component :is="item.icon" class="w-6 h-6" stroke-width="1.6" />
          <span>{{ t(item.shortLabelKey ?? item.labelKey) }}</span>
        </RouterLink>

        <RouterLink
          :to="{ name: 'add' }"
          class="flex flex-col items-center gap-1 pb-1.5 text-[10px] font-semibold"
          :class="addActive ? 'text-primary' : 'text-base-content/70'"
          :aria-label="t('collection.add')"
        >
          <span
            class="-mt-7 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-content shadow-lg shadow-primary/40 ring-4 ring-base-100 transition-transform active:scale-95"
          >
            <Plus class="h-7 w-7" stroke-width="2.5" />
          </span>
          <span>{{ t('nav.add') }}</span>
        </RouterLink>

        <RouterLink
          v-for="item in bottomRightItems"
          :key="item.name"
          :to="{ name: item.name }"
          class="bottom-nav-item"
          :class="{ 'bottom-nav-item--active': isActive(item) }"
        >
          <component :is="item.icon" class="w-6 h-6" stroke-width="1.6" />
          <span>{{ t(item.shortLabelKey ?? item.labelKey) }}</span>
        </RouterLink>

        <button
          type="button"
          class="bottom-nav-item"
          :class="{ 'bottom-nav-item--active': settingsOpen }"
          @click="openSettings"
        >
          <Settings class="w-6 h-6" stroke-width="1.6" />
          <span>{{ t('nav.settings') }}</span>
        </button>
      </div>
    </nav>

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
          <div v-if="settingsOpen" class="relative bg-base-100 rounded-t-2xl pb-safe-sheet shadow-xl">
            <div class="w-10 h-1 bg-base-300 rounded-full mx-auto mt-3 mb-2" />

            <div class="px-4 py-3">
              <h2 class="text-base font-semibold">{{ t('nav.settings') }}</h2>
            </div>

            <!-- Pages that have no room in the bottom bar -->
            <RouterLink
              :to="{ name: 'notification-preferences' }"
              class="flex items-center gap-3 w-full px-4 py-3.5 hover:bg-base-200 transition-colors"
            >
              <BellRing class="w-5 h-5 shrink-0 text-base-content/60" stroke-width="1.5" />
              <span class="flex-1 text-sm font-medium">{{ t('nav.notificationPreferences') }}</span>
              <ChevronRight class="w-4 h-4 text-base-content/30" />
            </RouterLink>
            <RouterLink
              v-for="item in adminNavItems"
              :key="item.name"
              :to="{ name: item.name }"
              class="flex items-center gap-3 w-full px-4 py-3.5 hover:bg-base-200 transition-colors"
            >
              <component :is="item.icon" class="w-5 h-5 shrink-0 text-base-content/60" stroke-width="1.5" />
              <span class="flex-1 text-sm font-medium">{{ t(item.labelKey) }}</span>
              <ChevronRight class="w-4 h-4 text-base-content/30" />
            </RouterLink>

            <div class="mx-4 my-1 border-t border-base-200" />

            <!-- Language row (disabled — coming soon) -->
            <div class="flex items-center justify-between w-full px-4 py-3.5 opacity-40 cursor-not-allowed select-none">
              <span class="text-sm font-medium">{{ t('settings.language') }}</span>
              <span class="badge badge-sm badge-ghost text-[10px]">bientôt</span>
            </div>

            <!-- Theme row : Ziggy Dark ⇄ Ziggy Light -->
            <button
              class="flex items-center justify-between w-full px-4 py-3.5 hover:bg-base-200 transition-colors"
              :aria-label="themeStore.isDark ? t('settings.switchToLight') : t('settings.switchToDark')"
              @click="themeStore.toggle()"
            >
              <span class="text-sm font-medium">{{ t('settings.theme') }}</span>
              <span class="flex items-center gap-2 text-sm text-base-content/60">
                <Moon v-if="themeStore.isDark" class="w-4 h-4" stroke-width="1.5" />
                <Sun v-else class="w-4 h-4" stroke-width="1.5" />
                {{ themeStore.isDark ? t('settings.themeDark') : t('settings.themeLight') }}
              </span>
            </button>

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

    <!-- Toast container — kept above the phone bottom bar and the iOS home indicator -->
    <div class="toast toast-end toast-bottom z-[9999] fixed mb-mobile-nav lg:mb-safe">
      <BaseToast
        v-for="toast in ui.toasts"
        :key="toast.id"
        :toast="toast"
      />
    </div>
  </Teleport>
</template>

<style scoped>
.bottom-nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  gap: 0.2rem;
  height: 100%;
  padding-bottom: 0.4rem;
  font-size: 10px;
  font-weight: 500;
  color: color-mix(in oklab, var(--color-base-content) 55%, transparent);
  transition: color 150ms;
}

.bottom-nav-item span {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bottom-nav-item--active {
  color: var(--color-primary);
}
</style>
