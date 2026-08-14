import { createRouter, createWebHistory } from 'vue-router'

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
  ],
})

export default router
