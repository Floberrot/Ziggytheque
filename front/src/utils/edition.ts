/** "Glénat · Prestige" — how a series' edition reads everywhere in the app. */
export function editionLabel(
  publisher: string | null | undefined,
  specialEdition: string | null | undefined,
): string {
  return [publisher, specialEdition]
    .map((part) => part?.trim() ?? '')
    .filter((part) => part !== '')
    .join(' · ')
}
