import { apiGet, apiPatch } from '../../shared/api/http'
import type { InterpretationProfile, InterpretationProfilePatch } from './model'

export function fetchInterpretationProfile(): Promise<InterpretationProfile> {
  return apiGet<InterpretationProfile>('/interpretation-profile')
}

export function updateInterpretationProfile(
  patch: InterpretationProfilePatch,
): Promise<InterpretationProfile> {
  return apiPatch<InterpretationProfile>('/interpretation-profile', patch)
}
