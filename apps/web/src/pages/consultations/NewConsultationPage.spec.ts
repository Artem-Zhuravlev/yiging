import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { reactive } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import NewConsultationPage from './NewConsultationPage.vue'
import { createConsultation, fetchConsultation } from '../../entities/consultation/api'
import { ApiError } from '../../shared/api/http'
import type { Consultation, NewConsultationRequest } from '../../entities/consultation/model'

const push = vi.fn()
const route = reactive<{ query: Record<string, string> }>({ query: {} })

vi.mock('../../entities/consultation/api', () => ({
  createConsultation: vi.fn(),
  fetchConsultation: vi.fn(),
}))

// CastingReveal fetches the primary hexagram's lines on mount; keep it pending so the reveal
// stays on screen (and never reaches its own navigation) during the one test that asserts it.
vi.mock('../../entities/hexagram/api', () => ({
  fetchHexagram: vi.fn().mockReturnValue(new Promise(() => {})),
}))

vi.mock('vue-router', () => ({
  useRoute: () => route,
  useRouter: () => ({ push }),
}))

const sample: Consultation = {
  id: 'new-id',
  question: 'Test?',
  method: 'three_coins',
  primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
  changingLinePositions: [],
  resultingHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
  createdAt: '2026-08-14T10:00:00+00:00',
  notes: [],
  tags: [],
  context: null,
  whatHappenedBefore: null,
  whatUserWantsToUnderstand: null,
  backgroundInformation: null,
  initialInterpretation: null,
  outcome: null,
  followUpTo: null,
  followUps: [],
  favorite: false,
}

describe('NewConsultationPage', () => {
  beforeEach(() => {
    push.mockClear()
    vi.mocked(createConsultation).mockClear()
    vi.mocked(fetchConsultation).mockReset()
    route.query = {}
    // Default these tests to the no-animation path so a successful cast navigates synchronously
    // (SPEC-042); the reveal itself is covered by CastingReveal.spec.ts and the one test below.
    localStorage.setItem('yijing-casting-reveal', 'off')
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('submits a three_coins request and navigates to the new consultation', async () => {
    vi.mocked(createConsultation).mockResolvedValue(sample)

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('#question').setValue('Test?')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createConsultation).toHaveBeenCalledWith({ question: 'Test?', method: 'three_coins' })
    expect(push).toHaveBeenCalledWith('/consultations/new-id')
  })

  it('submits a manual request with exactly 6 lines when manual is selected', async () => {
    vi.mocked(createConsultation).mockResolvedValue(sample)

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('#question').setValue('Test?')
    await wrapper.find('input[type="radio"][value="manual"]').setValue()
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createConsultation).toHaveBeenCalledTimes(1)
    const request = vi.mocked(createConsultation).mock.calls[0]![0] as NewConsultationRequest
    expect(request.method).toBe('manual')
    if (request.method === 'manual') {
      expect(request.lines).toHaveLength(6)
      expect(request.lines.every((line) => line.polarity === 'yang' && !line.changing)).toBe(true)
    }
  })

  it('reflects a changed line into the submitted manual payload', async () => {
    vi.mocked(createConsultation).mockResolvedValue(sample)

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('#question').setValue('Test?')
    await wrapper.find('input[type="radio"][value="manual"]').setValue()
    await wrapper.find('[data-position="1"] input[type="radio"][value="yin"]').setValue()
    await wrapper.find('[data-position="1"] input[type="checkbox"]').setValue(true)
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    const request = vi.mocked(createConsultation).mock.calls[0]![0] as NewConsultationRequest
    if (request.method === 'manual') {
      expect(request.lines[0]).toEqual({ polarity: 'yin', changing: true })
    }
  })

  it('includes filled-in context fields in the request and omits blank ones', async () => {
    vi.mocked(createConsultation).mockResolvedValue(sample)

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('#question').setValue('Test?')
    await wrapper.find('#context').setValue('Some context.')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createConsultation).toHaveBeenCalledWith({
      question: 'Test?',
      method: 'three_coins',
      context: 'Some context.',
    })
  })

  it('shows the target question and submits the link when ?followUpTo= is present', async () => {
    route.query = { followUpTo: 'original-id' }
    vi.mocked(fetchConsultation).mockResolvedValue({
      ...sample,
      id: 'original-id',
      question: 'Original?',
      repeats: { primaryHexagram: [], resultingHexagram: [], changingLines: [] },
      readingGuidance: { changingLineCount: 0, rule: 'no-changing-lines', refs: [], specialText: null },
    })
    vi.mocked(createConsultation).mockResolvedValue(sample)

    const wrapper = mount(NewConsultationPage)
    await flushPromises()

    expect(fetchConsultation).toHaveBeenCalledWith('original-id')
    expect(wrapper.text()).toContain('Follow-up to: Original?')

    await wrapper.find('#question').setValue('Follow-up question?')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createConsultation).toHaveBeenCalledWith({
      question: 'Follow-up question?',
      method: 'three_coins',
      followUpToConsultationId: 'original-id',
    })
  })

  it('has no follow-up banner and omits the link when there is no ?followUpTo=', async () => {
    vi.mocked(createConsultation).mockResolvedValue(sample)

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('#question').setValue('Test?')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).not.toContain('Follow-up to:')
    expect(createConsultation).toHaveBeenCalledWith({ question: 'Test?', method: 'three_coins' })
  })

  it('groups each manual line in a fieldset labelled with its line number', async () => {
    const wrapper = mount(NewConsultationPage)
    await wrapper.find('input[type="radio"][value="manual"]').setValue()

    const groups = wrapper.findAll('fieldset[data-position]')
    expect(groups).toHaveLength(6)
    for (const group of groups) {
      const position = group.attributes('data-position')
      expect(group.find('legend').text()).toBe(`Line ${position}`)
    }
  })

  it('marks the submit button aria-busy while a cast is in flight', async () => {
    vi.mocked(createConsultation).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(NewConsultationPage)
    const button = wrapper.find('button[type="submit"]')
    expect(button.attributes('aria-busy')).toBe('false')

    await wrapper.find('#question').setValue('Test?')
    await wrapper.find('form').trigger('submit.prevent')

    expect(button.attributes('aria-busy')).toBe('true')
  })

  it('shows the casting reveal instead of navigating when the animation is enabled', async () => {
    localStorage.setItem('yijing-casting-reveal', 'on')
    vi.mocked(createConsultation).mockResolvedValue(sample)

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('#question').setValue('Test?')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(push).not.toHaveBeenCalled()
    expect(wrapper.findComponent({ name: 'CastingReveal' }).exists()).toBe(true)
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('shows the API error message inline on a 422 without navigating', async () => {
    vi.mocked(createConsultation).mockRejectedValue(new ApiError(422, 'Question is required.'))

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('Question is required.')
    expect(push).not.toHaveBeenCalled()
  })
})
