import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BarChart from './BarChart.vue'

describe('BarChart', () => {
  it('renders one table row per item with a row header and the value', () => {
    const wrapper = mount(BarChart, {
      props: { items: [{ label: 'A', value: 3 }, { label: 'B', value: 9 }] },
    })

    expect(wrapper.element.tagName).toBe('TABLE')
    const headers = wrapper.findAll('th[scope="row"]').map((th) => th.text())
    expect(headers).toEqual(['A', 'B'])
    expect(wrapper.text()).toContain('3')
    expect(wrapper.text()).toContain('9')
  })

  it('scales bar widths to the largest value', () => {
    const wrapper = mount(BarChart, {
      props: { items: [{ label: 'A', value: 5 }, { label: 'B', value: 10 }] },
    })

    const fills = wrapper.findAll('.bar-chart-fill')
    expect(fills[0]!.attributes('style')).toContain('width: 50%')
    expect(fills[1]!.attributes('style')).toContain('width: 100%')
  })

  it('renders zero-width bars when every value is zero', () => {
    const wrapper = mount(BarChart, {
      props: { items: [{ label: 'A', value: 0 }, { label: 'B', value: 0 }] },
    })

    for (const fill of wrapper.findAll('.bar-chart-fill')) {
      expect(fill.attributes('style')).toContain('width: 0%')
    }
  })
})
