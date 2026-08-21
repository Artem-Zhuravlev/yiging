import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import App from './App.vue'

const route = reactive<{ meta: { public?: boolean } }>({ meta: {} })

vi.mock('vue-router', () => ({
  useRoute: () => route,
}))

const stubs = {
  RouterLink: { template: '<a><slot /></a>' },
  RouterView: { template: '<div />' },
}

describe('App', () => {
  it('renders the full nav on a normal (non-public) route', () => {
    route.meta = {}

    const wrapper = mount(App, { global: { stubs } })

    expect(wrapper.text()).toContain('Hexagrams')
    expect(wrapper.text()).toContain('History')
    expect(wrapper.text()).toContain('Statistics')
  })

  it('renders only the site name, no links, on a public route', () => {
    route.meta = { public: true }

    const wrapper = mount(App, { global: { stubs } })

    expect(wrapper.text()).toContain('Yijing')
    expect(wrapper.text()).not.toContain('Hexagrams')
    expect(wrapper.text()).not.toContain('History')
    expect(wrapper.text()).not.toContain('Statistics')
    expect(wrapper.findAll('a')).toHaveLength(0)
  })
})
