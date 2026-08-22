import { ref } from 'vue'

const STORAGE_KEY = 'yijing-dark-mode'

function detectInitialDarkMode(): boolean {
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored !== null) {
    return stored === 'true'
  }
  // No stored preference yet — default to dark rather than PrimeVue's usual light default.
  return true
}

export const isDarkMode = ref(detectInitialDarkMode())

export function setDarkMode(value: boolean): void {
  isDarkMode.value = value
  document.documentElement.classList.toggle('p-dark', value)
  localStorage.setItem(STORAGE_KEY, String(value))
}

// Applied immediately at module load (before the app mounts) so there's no flash of the light
// theme while Vue boots.
document.documentElement.classList.toggle('p-dark', isDarkMode.value)
