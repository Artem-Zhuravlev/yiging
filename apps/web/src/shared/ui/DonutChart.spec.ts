import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DonutChart from './DonutChart.vue'

describe('DonutChart', () => {
  it('exposes each segment and its rounded percentage in the aria-label', () => {
    const wrapper = mount(DonutChart, {
      props: { segments: [{ label: 'Yin', value: 6 }, { label: 'Yang', value: 12 }] },
    })

    const svg = wrapper.find('svg[role="img"]')
    expect(svg.attributes('aria-label')).toBe('33% Yin, 67% Yang')
  })

  it('handles an all-one-segment history as 100% / 0%', () => {
    const wrapper = mount(DonutChart, {
      props: { segments: [{ label: 'Yin', value: 0 }, { label: 'Yang', value: 30 }] },
    })

    expect(wrapper.find('svg[role="img"]').attributes('aria-label')).toBe('0% Yin, 100% Yang')
  })

  it('renders the caption when given one', () => {
    const wrapper = mount(DonutChart, {
      props: { segments: [{ label: 'Yin', value: 1 }, { label: 'Yang', value: 1 }], caption: '1 / 1 (50% / 50%)' },
    })

    expect(wrapper.text()).toContain('1 / 1 (50% / 50%)')
  })
})
