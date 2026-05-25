import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/LoginPage.vue'),
      meta: { public: true, title: 'Connexion' },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/pages/RegisterPage.vue'),
      meta: { public: true, title: 'Inscription' },
    },
    {
      path: '/verify-email',
      name: 'verify-email',
      component: () => import('@/pages/VerifyEmailPage.vue'),
      meta: { public: true, title: 'Vérification email' },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/pages/ForgotPasswordPage.vue'),
      meta: { public: true, title: 'Mot de passe oublié' },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/pages/ResetPasswordPage.vue'),
      meta: { public: true, title: 'Réinitialiser le mot de passe' },
    },
    {
      path: '/gate',
      name: 'gate',
      component: () => import('@/pages/GatePage.vue'),
      meta: { requiresAuth: true, requiresAdmin: true, title: 'Accès admin' },
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
        {
          path: 'journal',
          name: 'journal',
          component: () => import('@/pages/JournalPage.vue'),
          meta: { title: 'Journal', requiresAdminUnlocked: true },
        },
        {
          path: 'shelf',
          name: 'shelf',
          component: () => import('@/pages/ShelfPage.vue'),
          meta: { title: 'Bibliothèque 3D' },
        },
        {
          path: 'admin/users',
          name: 'admin-users',
          component: () => import('@/pages/AdminUsersPage.vue'),
          meta: { title: 'Utilisateurs', requiresAdmin: true, requiresAdminUnlocked: true },
        },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.isAuthenticated && auth.user === null) {
    await auth.loadUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.public && auth.isAuthenticated && to.name !== 'verify-email' && to.name !== 'reset-password') {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAdminUnlocked && !auth.isAdminUnlocked) {
    return { name: 'gate', query: { redirect: to.fullPath } }
  }

  const pageTitle = to.meta.title as string | undefined
  document.title = pageTitle ? `${pageTitle} — Ziggy` : 'Ziggytheque'
})

export default router
