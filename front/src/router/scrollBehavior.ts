import type { RouterScrollBehavior } from 'vue-router'

/**
 * Where the window scrolls on a navigation:
 * - a list page that remembers its place (`meta.rememberScroll`) restores it itself, once its
 *   list is on screen (useRememberedScroll) — the router must not move it first;
 * - back / forward: where the reader was on that page;
 * - the same page with another query (a tab of the series page): stay put;
 * - any other page opens at its top.
 */
export const scrollBehavior: RouterScrollBehavior = (to, from, savedPosition) => {
  if (to.meta.rememberScroll) return false
  if (savedPosition) return savedPosition
  if (to.path === from.path) return false
  return { top: 0 }
}
