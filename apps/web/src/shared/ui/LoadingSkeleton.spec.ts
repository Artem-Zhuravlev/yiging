import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import LoadingSkeleton from './LoadingSkeleton.vue'

describe('LoadingSkeleton', () => {
  it('renders a title bar plus `lines` bars, all decorative', () => {
    const wrapper = mount(LoadingSkeleton, { props: { lines: 5 } })

    // one title Skeleton + 5 line Skeletons
    expect(wrapper.findAll('.p-skeleton')).toHaveLength(6)
    expect(wrapper.find('.loading-skeleton').attributes('aria-hidden')).toBe('true')
  })

  it('defaults to 4 lines', () => {
    const wrapper = mount(LoadingSkeleton)
    expect(wrapper.findAll('.p-skeleton')).toHaveLength(5)
  })

  it('exposes "Loading…" to assistive tech via an sr-only span', () => {
    const wrapper = mount(LoadingSkeleton)
    const srOnly = wrapper.find('.sr-only')
    expect(srOnly.exists()).toBe(true)
    expect(srOnly.text()).toBe('Loading…')
  })
})
