import { createRouter, createWebHistory, START_LOCATION } from 'vue-router'
import { focusMain } from '../shared/lib/focusMain'

declare module 'vue-router' {
  interface RouteMeta {
    /** True for routes that must never render the main nav (SPEC-029) — a public share page
     * must not offer a navigational path into the rest of the user's private history. */
    public?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('../pages/home/HomePage.vue'),
    },
    {
      path: '/hexagrams',
      name: 'hexagrams',
      component: () => import('../pages/hexagrams/HexagramListPage.vue'),
    },
    {
      path: '/hexagrams/editor',
      name: 'hexagram-editor',
      component: () => import('../pages/hexagrams/HexagramEditorPage.vue'),
    },
    {
      path: '/hexagrams/compare',
      name: 'hexagram-compare',
      component: () => import('../pages/hexagrams/HexagramComparePage.vue'),
    },
    {
      path: '/hexagrams/:number',
      name: 'hexagram-detail',
      component: () => import('../pages/hexagrams/HexagramDetailPage.vue'),
    },
    {
      path: '/consultations/new',
      name: 'consultation-new',
      component: () => import('../pages/consultations/NewConsultationPage.vue'),
    },
    {
      path: '/consultations',
      name: 'consultations',
      component: () => import('../pages/consultations/ConsultationHistoryPage.vue'),
    },
    {
      path: '/consultations/:id',
      name: 'consultation-detail',
      component: () => import('../pages/consultations/ConsultationPage.vue'),
    },
    {
      path: '/share/consultations/:id',
      name: 'consultation-share',
      meta: { public: true },
      component: () => import('../pages/consultations/SharedConsultationPage.vue'),
    },
    {
      path: '/statistics',
      name: 'statistics',
      component: () => import('../pages/statistics/StatisticsPage.vue'),
    },
    {
      path: '/journal',
      name: 'journal',
      component: () => import('../pages/journal/JournalPage.vue'),
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('../pages/settings/InterpretationSettingsPage.vue'),
    },
  ],
})

// Move focus to the destination page's <main> on every client-side navigation, so keyboard and
// screen-reader users land in the new content instead of keeping focus on the link they clicked
// (SPEC-039, REQ-A11Y-003). Skipped on the very first navigation (`from === START_LOCATION`) so a
// cold page load leaves focus at the document start, where the skip link is.
router.afterEach((_to, from) => {
  if (from === START_LOCATION) {
    return
  }
  void focusMain()
})

export default router
