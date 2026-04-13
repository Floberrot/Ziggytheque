import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/gate',
      name: 'gate',
      component: () => import('@/pages/GatePage.vue'),
      meta: { public: true },
    },
    {
      path: '/',
      component: () => import('@/components/organisms/MainLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/dashboard' },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/pages/DashboardPage.vue'),
        },
        {
          path: 'collection',
          name: 'collection',
          component: () => import('@/pages/CollectionPage.vue'),
        },
        {
          path: 'collection/:id',
          name: 'collection-detail',
          component: () => import('@/pages/MangaDetailPage.vue'),
        },
        {
          path: 'wishlist',
          name: 'wishlist',
          component: () => import('@/pages/WishlistPage.vue'),
        },
        {
          path: 'add',
          name: 'add',
          component: () => import('@/pages/AddMangaPage.vue'),
        },
        {
          path: 'price-codes',
          name: 'price-codes',
          component: () => import('@/pages/PriceCodesPage.vue'),
        },
        {
          path: 'notifications',
          name: 'notifications',
          component: () => import('@/pages/NotificationsPage.vue'),
        },
      ],
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
})

export default router
