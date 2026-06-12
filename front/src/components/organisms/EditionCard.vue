<script setup lang="ts">
import { computed, ref } from 'vue'
import type { ExternalEdition } from '@/api/manga'
import BaseCountryFlag from '@/components/atoms/BaseCountryFlag.vue'

const props = defineProps<{ edition: ExternalEdition }>()
const emit = defineEmits<{ import: [edition: ExternalEdition] }>()

// Some catalogue cover URLs resolve to a blank/placeholder image; fall back to the
// "?" tile instead of showing a broken-image icon.
const imageFailed = ref(false)

const FORMAT_LABELS: Record<string, string> = {
  broche: 'Broché',
  relie: 'Relié',
  coffret: 'Coffret',
  deluxe: 'Deluxe',
  omnibus: 'Omnibus',
  unknown: '',
}

// Where the record comes from — always shown so the user can judge reliability.
const SOURCE_LABELS: Record<string, string> = {
  bnf: 'BnF',
  dnb: 'DNB',
  ndl: 'NDL',
  open_library: 'Open Library',
  google_books: 'Google',
}

// Lead with the edition line ("Perfect Edition", "Coffret"…) when known, otherwise
// the publisher. The publisher then drops to the muted subline (see template).
const title = computed(
  () => props.edition.editionLine ?? props.edition.publisher ?? props.edition.workTitle,
)
</script>

<template>
  <div
    class="flex gap-3 p-3 rounded-xl border border-base-300/70 bg-base-100 hover:border-primary/30 transition-colors"
  >
    <!-- Cover thumbnail -->
    <div
      class="shrink-0 w-10 aspect-[2/3] rounded-lg overflow-hidden bg-base-200 flex items-center justify-center"
    >
      <img
        v-if="edition.coverUrl && !imageFailed"
        :src="edition.coverUrl"
        :alt="edition.editionLabel"
        class="w-full h-full object-cover"
        @error="imageFailed = true"
      />
      <span v-else class="text-base-content/20 text-xs font-bold">?</span>
    </div>

    <!-- Info -->
    <div class="flex-1 min-w-0">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold leading-tight truncate">
            {{ title }}
          </p>
          <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
            <BaseCountryFlag :country="edition.country" size="sm" />
            <span
              v-if="edition.editionLine"
              class="badge badge-xs badge-primary"
            >
              {{ edition.editionLine }}
            </span>
            <span
              v-if="FORMAT_LABELS[edition.format]"
              class="badge badge-xs badge-outline"
            >
              {{ FORMAT_LABELS[edition.format] }}
            </span>
            <span
              v-if="edition.volumeCount"
              class="text-[11px] text-base-content/50"
            >
              {{ edition.volumeCount }} tome{{ edition.volumeCount > 1 ? 's' : '' }}
            </span>
            <span class="badge badge-xs badge-ghost text-base-content/40">
              {{ SOURCE_LABELS[edition.source] ?? edition.source }}
            </span>
          </div>
          <p
            v-if="edition.editionLine && edition.publisher"
            class="text-[11px] text-base-content/50 mt-0.5 truncate"
          >
            {{ edition.publisher }}
          </p>
          <p
            v-if="edition.isbnSample"
            class="text-[10px] text-base-content/30 font-mono mt-0.5"
          >
            ISBN {{ edition.isbnSample }}
          </p>
        </div>
        <button
          class="btn btn-primary btn-xs shrink-0"
          @click="emit('import', props.edition)"
        >
          Importer
        </button>
      </div>
    </div>
  </div>
</template>
