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
      path: '/hexagrams/:number',
      name: 'hexagram-detail',
      component: () => import('../pages/hexagrams/HexagramDetailPage.vue'),
    },
  ],
})

export default router
