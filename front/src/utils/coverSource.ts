// Brand names of the cover sources: shown as is in every language.
const SOURCE_LABELS: Record<string, string> = {
  bnf: 'BnF',
  open_library: 'Open Library',
  google_books: 'Google Books',
  hardcover: 'Hardcover',
  mangadex: 'MangaDex',
}

/** Where a cover suggestion comes from, as the user knows the source. */
export function coverSourceLabel(source: string): string {
  return SOURCE_LABELS[source] ?? source
}
