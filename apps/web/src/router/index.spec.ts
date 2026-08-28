import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import router from './index'

// SPEC-039, REQ-A11Y-003: the router's afterEach hook moves keyboard focus to the destination
// page's <main id="main"> on every client-side navigation — but not on the first one, so a cold
// load leaves focus at the document start where the skip link is.
describe('router focus management', () => {
  let main: HTMLElement

  beforeEach(() => {
    document.body.innerHTML = ''
    main = document.createElement('main')
    main.id = 'main'
    main.tabIndex = -1
    document.body.appendChild(main)
    vi.spyOn(main, 'focus')
  })

  it('skips the initial navigation but focuses #main on every one after', async () => {
    await router.push('/')
    await router.isReady()
    await flushPromises()

    expect(main.focus).not.toHaveBeenCalled()

    await router.push('/journal')
    await flushPromises()

    expect(main.focus).toHaveBeenCalledTimes(1)
  })
})
