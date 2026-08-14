import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import HexagramEditorPage from './HexagramEditorPage.vue'
import { computeHexagramFromLines } from '../../entities/hexagram/api'
import type { Hexagram } from '../../entities/hexagram/model'

vi.mock('../../entities/hexagram/api', () => ({
  computeHexagramFromLines: vi.fn(),
}))

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

const qian: Hexagram = {
  kingWenNumber: 1,
  chineseName: '乾',
  pinyin: 'Qián',
  lines: Array.from({ length: 6 }, (_, i) => ({ position: i + 1, polarity: 'yang' as const })),
  upperTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
  lowerTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
  judgment: null,
  image: null,
  lineStatements: null,
  relationships: {
    nuclear: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    reversed: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    complement: { kingWenNumber: 2, chineseName: '坤', pinyin: 'Kūn' },
  },
}

const tai: Hexagram = {
  kingWenNumber: 11,
  chineseName: '泰',
  pinyin: 'Tài',
  lines: [
    { position: 1, polarity: 'yang' },
    { position: 2, polarity: 'yang' },
    { position: 3, polarity: 'yang' },
    { position: 4, polarity: 'yin' },
    { position: 5, polarity: 'yin' },
    { position: 6, polarity: 'yin' },
  ],
  upperTrigram: { id: 'Kun', name: 'Kun', chineseName: '坤', pinyin: 'Kūn', symbol: '☷' },
  lowerTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
  judgment: null,
  image: null,
  lineStatements: null,
  relationships: {
    nuclear: { kingWenNumber: 54, chineseName: '歸妹', pinyin: 'Guī Mèi' },
    reversed: { kingWenNumber: 12, chineseName: '否', pinyin: 'Pǐ' },
    complement: { kingWenNumber: 12, chineseName: '否', pinyin: 'Pǐ' },
  },
}

describe('HexagramEditorPage', () => {
  let wrapper: VueWrapper | undefined

  afterEach(() => {
    wrapper?.unmount()
  })

  it('computes and shows hexagram 1 (all yang) by default', async () => {
    vi.mocked(computeHexagramFromLines).mockResolvedValue(qian)

    wrapper = mount(HexagramEditorPage, { global: { stubs } })
    await flushPromises()

    expect(computeHexagramFromLines).toHaveBeenCalledWith([
      'yang',
      'yang',
      'yang',
      'yang',
      'yang',
      'yang',
    ])
    expect(wrapper.text()).toContain('1. 乾')
  })

  it('re-computes and updates the preview when a line toggle changes', async () => {
    vi.mocked(computeHexagramFromLines).mockResolvedValueOnce(qian)
    wrapper = mount(HexagramEditorPage, { global: { stubs } })
    await flushPromises()

    vi.mocked(computeHexagramFromLines).mockResolvedValue(tai)
    const position4YinRadio = wrapper.find('[data-position="4"] input[value="yin"]')
    const position5YinRadio = wrapper.find('[data-position="5"] input[value="yin"]')
    const position6YinRadio = wrapper.find('[data-position="6"] input[value="yin"]')
    await position4YinRadio.setValue(true)
    await position5YinRadio.setValue(true)
    await position6YinRadio.setValue(true)
    await flushPromises()

    expect(computeHexagramFromLines).toHaveBeenLastCalledWith([
      'yang',
      'yang',
      'yang',
      'yin',
      'yin',
      'yin',
    ])
    expect(wrapper.text()).toContain('11. 泰')
    expect(wrapper.text()).toContain('54') // nuclear
  })

  it('shows an error message when the computation fails', async () => {
    vi.mocked(computeHexagramFromLines).mockRejectedValue(new Error('bad request'))

    wrapper = mount(HexagramEditorPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('bad request')
  })
})
