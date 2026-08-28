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

  it('renders only the site name, no nav links, on a public route', () => {
    route.meta = { public: true }

    const wrapper = mount(App, { global: { stubs } })

    expect(wrapper.text()).toContain('Yijing')
    expect(wrapper.text()).not.toContain('Hexagrams')
    expect(wrapper.text()).not.toContain('History')
    expect(wrapper.text()).not.toContain('Statistics')
    // The only anchor left on a public route is the always-present skip link (SPEC-039); there
    // must be no navigational links into the rest of the private history (SPEC-029).
    const navLinks = wrapper.findAll('a').filter((a) => a.attributes('href') !== '#main')
    expect(navLinks).toHaveLength(0)
  })

  it('renders the skip link as the first focusable element on every route', () => {
    route.meta = { public: true }

    const wrapper = mount(App, { global: { stubs } })

    const first = wrapper.find('a')
    expect(first.attributes('href')).toBe('#main')
    expect(first.classes()).toContain('skip-link')
  })

  it('exposes the primary navigation as a named landmark', () => {
    route.meta = {}

    const wrapper = mount(App, { global: { stubs } })

    const nav = wrapper.find('nav')
    expect(nav.exists()).toBe(true)
    expect(nav.attributes('aria-label')).toBeTruthy()
  })

  it('renders a hamburger menu button on a normal route, collapsed by default', async () => {
    route.meta = {}

    const wrapper = mount(App, { global: { stubs } })

    const hamburger = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Menu')
    expect(hamburger).toBeTruthy()
    expect(hamburger!.attributes('aria-expanded')).toBe('false')

    await hamburger!.trigger('click')
    expect(hamburger!.attributes('aria-expanded')).toBe('true')
  })

  it('renders no hamburger menu button on a public route', () => {
    route.meta = { public: true }

    const wrapper = mount(App, { global: { stubs } })

    expect(wrapper.findAll('button').some((b) => b.attributes('aria-label') === 'Menu')).toBe(false)
  })
})
