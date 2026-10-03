import { createRouter, createWebHistory } from 'vue-router'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/useAuthStore'
import { scrollBehavior } from './scrollBehavior'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior,
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/LoginPage.vue'),
      meta: { public: true, titleKey: 'pageTitle.login' },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/pages/RegisterPage.vue'),
      meta: { public: true, titleKey: 'pageTitle.register' },
    },
    {
      path: '/verify-email',
      name: 'verify-email',
      component: () => import('@/pages/VerifyEmailPage.vue'),
      meta: { public: true, titleKey: 'pageTitle.verifyEmail' },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/pages/ForgotPasswordPage.vue'),
      meta: { public: true, titleKey: 'pageTitle.forgotPassword' },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/pages/ResetPasswordPage.vue'),
      meta: { public: true, titleKey: 'pageTitle.resetPassword' },
    },
    {
      path: '/gate',
      name: 'gate',
      component: () => import('@/pages/GatePage.vue'),
      meta: { requiresAuth: true, requiresAdmin: true, titleKey: 'pageTitle.gate' },
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
          meta: { titleKey: 'pageTitle.dashboard' },
        },
        {
          path: 'collection',
          name: 'collection',
          component: () => import('@/pages/CollectionPage.vue'),
          // Search, filters and scroll offset are kept when the reader opens a series and comes back.
          meta: { titleKey: 'pageTitle.collection', rememberScroll: true },
        },
        {
          path: 'collection/:id',
          name: 'collection-detail',
          component: () => import('@/pages/MangaDetailPage.vue'),
          meta: { titleKey: 'pageTitle.series' },
        },
        {
          path: 'wishlist',
          name: 'wishlist',
          component: () => import('@/pages/WishlistPage.vue'),
          meta: { titleKey: 'pageTitle.wishlist', rememberScroll: true },
        },
        {
          path: 'add',
          name: 'add',
          component: () => import('@/pages/AddMangaPage.vue'),
          meta: { titleKey: 'pageTitle.add' },
        },
        {
          path: 'notifications',
          name: 'notifications',
          component: () => import('@/pages/NotificationsPage.vue'),
          meta: { titleKey: 'pageTitle.notifications' },
        },
        {
          path: 'notification-preferences',
          name: 'notification-preferences',
          component: () => import('@/pages/NotificationPreferencesPage.vue'),
          meta: { titleKey: 'pageTitle.notificationPreferences' },
        },
        {
          path: 'journal',
          name: 'journal',
          component: () => import('@/pages/JournalPage.vue'),
          meta: { titleKey: 'pageTitle.journal', requiresAdminUnlocked: true },
        },
        {
          path: 'admin/users',
          name: 'admin-users',
          component: () => import('@/pages/AdminUsersPage.vue'),
          meta: { titleKey: 'pageTitle.adminUsers', requiresAdmin: true, requiresAdminUnlocked: true },
        },
      ],
    },
    {
      path: '/scan/:token',
      name: 'scan',
      component: () => import('@/pages/ScanPage.vue'),
      meta: { public: true, titleKey: 'pageTitle.scan' },
    },
    {
      path: '/share/:token',
      name: 'share',
      component: () => import('@/pages/SharePage.vue'),
      meta: { public: true, titleKey: 'pageTitle.share' },
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

  if (
    to.meta.public &&
    auth.isAuthenticated &&
    to.name !== 'verify-email' &&
    to.name !== 'reset-password' &&
    to.name !== 'scan' &&
    to.name !== 'share'
  ) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAdminUnlocked && !auth.isAdminUnlocked) {
    return { name: 'gate', query: { redirect: to.fullPath } }
  }

  // Titles are i18n keys, translated in the language picked in the settings.
  const titleKey = to.meta.titleKey as string | undefined
  document.title = titleKey ? `${i18n.global.t(titleKey)} — Ziggy` : 'Ziggytheque'
})

export default router
