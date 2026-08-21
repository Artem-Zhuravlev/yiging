export type InterpretationLens = 'general' | 'psychological' | 'practical' | 'symbolic'

export const INTERPRETATION_LENSES: InterpretationLens[] = [
  'general',
  'psychological',
  'practical',
  'symbolic',
]

export interface Interpretation {
  summary: string
  coreTheme: string
  situation: string
  changingLineMeaning: string | null
  transition: string | null
  practicalReflection: string
  uncertainties: string[]
  sourceReferences: string[]
  lens: InterpretationLens
}
