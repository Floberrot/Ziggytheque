export interface EditionInfo {
  name: string
  logo: string
}

function favicon(domain: string): string {
  return `https://www.google.com/s2/favicons?domain=${domain}&sz=64`
}

export const FRENCH_EDITIONS: EditionInfo[] = [
  { name: 'Pika Édition',      logo: favicon('pika-editions.fr') },
  { name: 'Glénat',            logo: favicon('glenat.com') },
  { name: 'Kana',              logo: favicon('kana.fr') },
  { name: 'Ki-oon',            logo: favicon('ki-oon.com') },
  { name: 'Kazé Manga',        logo: favicon('kaze-manga.fr') },
  { name: 'Kurokawa',          logo: favicon('kurokawa.fr') },
  { name: 'Delcourt / Tonkam', logo: favicon('delcourt.fr') },
  { name: 'Akata',             logo: favicon('akata.fr') },
  { name: 'Nobi Nobi!',        logo: favicon('nobi-nobi.fr') },
  { name: 'Doki-Doki',         logo: favicon('doki-doki.net') },
  { name: 'Soleil Manga',      logo: favicon('soleilprod.com') },
  { name: 'Michel Lafon',      logo: favicon('michel-lafon.fr') },
  { name: "J'ai Lu",           logo: favicon('jailu.com') },
  { name: 'Panini Manga',      logo: favicon('panini.fr') },
  { name: 'Bamboo Édition',    logo: favicon('bamboo.fr') },
  { name: 'Kami',              logo: favicon('kamilivres.fr') },
  { name: 'Vega-Dupuis',       logo: favicon('vega-dupuis.com') },
  { name: 'Crunchyroll',       logo: favicon('crunchyroll.com') },
]
