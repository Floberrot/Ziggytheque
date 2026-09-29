<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Book, CheckCircle2, ChevronRight } from 'lucide-vue-next'
import type { CatalogueEdition } from '@/api/catalogue'
import EditionBadge from '@/components/molecules/EditionBadge.vue'
import { coverUrl } from '@/utils/coverUrl'

const props = defineProps<{ edition: CatalogueEdition }>()
const emit = defineEmits<{ select: [edition: CatalogueEdition] }>()
const { t } = useI18n()

// Some catalogue covers resolve to a blank placeholder: fall back to the book icon.
const imageFailed = ref(false)
const cover = computed(() => (imageFailed.value ? null : coverUrl(props.edition.coverUrl)))
const ownedCount = computed(() => props.edition.collection?.ownedNumbers.length ?? 0)
</script>

<template>
  <button
    type="button"
    class="group w-full flex items-center gap-3 p-2.5 rounded-2xl border border-base-300/70 bg-base-100 text-left transition-all hover:border-primary/50 hover:shadow-md active:scale-[0.99]"
    @click="emit('select', edition)"
  >
    <div class="w-14 shrink-0 aspect-[2/3] rounded-lg overflow-hidden bg-base-200 ring-1 ring-base-300/60">
      <img
        v-if="cover"
        :src="cover"
        :alt="edition.workTitle"
        class="w-full h-full object-cover"
        loading="lazy"
        @error="imageFailed = true"
      />
      <div v-else class="w-full h-full flex items-center justify-center text-base-content/20">
        <Book class="h-6 w-6" stroke-width="1.5" />
      </div>
    </div>

    <div class="flex-1 min-w-0 space-y-1">
      <p class="font-semibold leading-tight line-clamp-2">{{ edition.workTitle }}</p>
      <EditionBadge :publisher="edition.publisher" :special-edition="edition.specialEdition" />
      <p class="text-xs text-base-content/50 truncate">
        <span v-if="edition.author">{{ edition.author }} · </span>
        {{ t('catalogue.volumeCount', { count: edition.volumeCount }, edition.volumeCount) }}
      </p>
      <p
        v-if="edition.collection"
        class="inline-flex items-center gap-1 text-[11px] font-medium text-success"
      >
        <CheckCircle2 class="h-3.5 w-3.5" />
        {{ t('catalogue.inCollection', { owned: ownedCount, total: edition.volumeCount }) }}
      </p>
    </div>

    <ChevronRight class="h-5 w-5 shrink-0 text-base-content/30 group-hover:text-primary transition-colors" />
  </button>
</template>
