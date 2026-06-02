import { ref, watch } from 'vue'
import type { CoverProvider } from '@/api/manga'

export interface CoverProviderOption {
  /** Sent to the backend as the `provider` query param. */
  key: CoverProvider
  /** Label shown in the tooltip and the picker (brand names stay untranslated). */
  label: string
}

/**
 * Cover sources selectable for the title search. Only providers that resolve a
 * cover from a title + volume are offered: MangaDex and Google Books, plus the
 * default `composite` cascade ("Auto"). The ISBN-only sources (BnF, OpenLibrary)
 * return nothing for a title search, so they are intentionally excluded here.
 */
export const COVER_PROVIDERS: CoverProviderOption[] = [
  { key: 'composite', label: 'Auto' },
  { key: 'mangadex', label: 'MangaDex' },
  { key: 'googlebooks', label: 'Google Books' },
]

const DEFAULT_PROVIDER: CoverProvider = 'composite'
const STORAGE_KEY = 'ziggytheque.coverProvider'

function loadProvider(): CoverProvider {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (COVER_PROVIDERS.some((option) => option.key === stored)) {
      return stored as CoverProvider
    }
  } catch {
    // localStorage may be unavailable (private mode) — fall back to the default.
  }
  return DEFAULT_PROVIDER
}

export function useCoverProvider() {
  const provider = ref<CoverProvider>(loadProvider())

  watch(provider, (next) => {
    try {
      localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Ignore persistence failures — the choice still applies for this session.
    }
  })

  return { provider, providers: COVER_PROVIDERS }
}
