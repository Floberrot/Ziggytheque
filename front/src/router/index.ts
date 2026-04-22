import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    requiresAuth?: boolean
    layout?: string
  }
}

const routes: RouteRecordRaw[] = [
  {
    path: '/gate',
    name: 'gate',
    component: () => import('@/pages/GatePage.vue'),
    meta: { layout: 'auth', title: 'Accès' },
  },
  {
    path: '/',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { requiresAuth: true, layout: 'app' },
    children: [
      { path: '', redirect: '/dashboard' },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('@/pages/DashboardPage.vue'),
        meta: { title: 'Tableau de bord' },
      },
      {
        path: 'collection',
        name: 'collection',
        component: () => import('@/pages/CollectionPage.vue'),
        meta: { title: 'Collection' },
      },
      {
        path: 'collection/:id',
        name: 'collection-detail',
        component: () => import('@/pages/MangaDetailPage.vue'),
        meta: { title: 'Série' },
      },
      {
        path: 'wishlist',
        name: 'wishlist',
        component: () => import('@/pages/WishlistPage.vue'),
        meta: { title: 'Liste de souhaits' },
      },
      {
        path: 'add',
        name: 'add',
        component: () => import('@/pages/AddMangaPage.vue'),
        meta: { title: 'Ajouter une série' },
      },
      {
        path: 'notifications',
        name: 'notifications',
        component: () => import('@/pages/NotificationsPage.vue'),
        meta: { title: 'Notifications' },
      },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'gate' }
  }
  if (to.name === 'gate' && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  const pageTitle = to.meta.title as string | undefined
  document.title = pageTitle ? `${pageTitle} — Ziggy` : 'Ziggytheque'
})

export default router
