import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import HexagramLines from './HexagramLines.vue'
import type { HexagramLine } from '../model'

const allLines: HexagramLine[] = [
  { position: 1, polarity: 'yang' },
  { position: 2, polarity: 'yin' },
  { position: 3, polarity: 'yang' },
  { position: 4, polarity: 'yin' },
  { position: 5, polarity: 'yang' },
  { position: 6, polarity: 'yin' },
]

describe('HexagramLines', () => {
  it('renders lines top to bottom (position 6 first, matching visual stacking)', () => {
    const wrapper = mount(HexagramLines, { props: { lines: allLines } })

    const rendered = wrapper.findAll('[data-position]')
    expect(rendered.map((el) => el.attributes('data-position'))).toEqual([
      '6',
      '5',
      '4',
      '3',
      '2',
      '1',
    ])
  })

  it('does not mutate the lines prop', () => {
    const lines = [...allLines]
    mount(HexagramLines, { props: { lines } })

    expect(lines).toEqual(allLines)
  })

  it('renders one bar for a yang line and two bars for a yin line', () => {
    const wrapper = mount(HexagramLines, {
      props: {
        lines: [
          { position: 1, polarity: 'yang' },
          { position: 2, polarity: 'yin' },
        ],
      },
    })

    expect(wrapper.find('[data-position="1"]').findAll('span')).toHaveLength(1)
    expect(wrapper.find('[data-position="2"]').findAll('span')).toHaveLength(2)
  })

  it('marks changing lines and leaves non-changing lines unmarked', () => {
    const wrapper = mount(HexagramLines, {
      props: {
        lines: [
          { position: 1, polarity: 'yang', changing: true },
          { position: 2, polarity: 'yin' },
        ],
      },
    })

    expect(wrapper.find('[data-position="1"]').attributes('data-changing')).toBe('true')
    expect(wrapper.find('[data-position="2"]').attributes('data-changing')).toBeUndefined()
  })
})
