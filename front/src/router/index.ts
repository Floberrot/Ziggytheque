import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

declare module 'vue-router' {
  interface RouteMeta {
    layout?: 'auth' | 'app'
    title?: string
    requiresAuth?: boolean
    public?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/gate',
      name: 'gate',
      component: () => import('@/features/gate/pages/GatePage.vue'),
      meta: { layout: 'auth', public: true, title: 'Accès' },
    },
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/features/dashboard/pages/DashboardPage.vue'),
      meta: { layout: 'app', requiresAuth: true, title: 'Tableau de bord' },
    },
    {
      path: '/collection',
      name: 'collection',
      component: () => import('@/features/collection/pages/CollectionPage.vue'),
      meta: { layout: 'app', requiresAuth: true, title: 'Collection' },
    },
    {
      path: '/collection/:id',
      name: 'collection-detail',
      component: () => import('@/features/collection/pages/MangaDetailPage.vue'),
      meta: { layout: 'app', requiresAuth: true, title: 'Série' },
    },
    {
      path: '/wishlist',
      name: 'wishlist',
      component: () => import('@/features/wishlist/pages/WishlistPage.vue'),
      meta: { layout: 'app', requiresAuth: true, title: 'Liste de souhaits' },
    },
    {
      path: '/add',
      name: 'add',
      component: () => import('@/features/add/pages/AddMangaPage.vue'),
      meta: { layout: 'app', requiresAuth: true, title: 'Ajouter une série' },
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: () => import('@/features/notifications/pages/NotificationsPage.vue'),
      meta: { layout: 'app', requiresAuth: true, title: 'Notifications' },
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
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
