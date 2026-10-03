<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Pencil, Sparkles } from 'lucide-vue-next'
import type { MangaUpdatePayload } from '@/api/manga'
import type { Manga } from '@/types'
import { FRENCH_EDITIONS } from '@/data/editions'
import BaseEditionSelector from '@/components/atoms/BaseEditionSelector.vue'
import BaseHeartRating from '@/components/atoms/BaseHeartRating.vue'

/**
 * Who a series is: its title, publisher and special edition — each editable in place —,
 * its language and genre, the user's rating and the author. The page owns the three
 * `editing…` flags: a successful save closes every editor at once.
 */
const props = defineProps<{
  manga: Manga
  rating: number | null
}>()

const editingTitle = defineModel<boolean>('editingTitle', { required: true })
const editingEdition = defineModel<boolean>('editingEdition', { required: true })
const editingSpecialEdition = defineModel<boolean>('editingSpecialEdition', { required: true })

const emit = defineEmits<{
  save: [payload: MangaUpdatePayload]
  rate: [rating: number]
}>()

const { t } = useI18n()

const titleDraft = ref('')
const editionDraft = ref<string | null>(null)
const specialEditionDraft = ref('')

const editionLogo = computed(
  () => FRENCH_EDITIONS.find((edition) => edition.name === props.manga.edition)?.logo ?? null,
)

function startEditTitle(): void {
  titleDraft.value = props.manga.title
  editingTitle.value = true
}

function startEditEdition(): void {
  editionDraft.value = props.manga.edition
  editingEdition.value = true
}

function startEditSpecialEdition(): void {
  specialEditionDraft.value = props.manga.specialEdition ?? ''
  editingSpecialEdition.value = true
}

function saveTitle(): void {
  emit('save', { title: titleDraft.value })
}

function saveEdition(): void {
  emit('save', { edition: editionDraft.value ?? '' })
}

function saveSpecialEdition(): void {
  emit('save', { specialEdition: specialEditionDraft.value.trim() })
}
</script>

<template>
  <div>
    <!-- Inline title edit -->
    <div v-if="editingTitle" class="flex items-center gap-2">
      <input
        v-model="titleDraft"
        class="input input-bordered input-sm text-2xl md:text-3xl font-extrabold leading-tight w-full"
        :aria-label="t('manga.title')"
        autofocus
        @keydown.enter="saveTitle"
        @keydown.escape="editingTitle = false"
      />
      <button class="btn btn-primary btn-sm" :aria-label="t('common.save')" @click="saveTitle">✓</button>
      <button class="btn btn-ghost btn-sm" :aria-label="t('common.cancel')" @click="editingTitle = false">✕</button>
    </div>
    <div v-else class="group/title flex items-center gap-2">
      <h1 class="text-2xl md:text-3xl font-extrabold leading-tight">{{ manga.title }}</h1>
      <div class="tooltip tooltip-right" :data-tip="t('manga.renameTooltip')">
        <button
          class="btn btn-ghost btn-xs opacity-0 group-hover/title:opacity-60 focus-visible:opacity-60 transition-opacity"
          :aria-label="t('manga.renameTooltip')"
          @click="startEditTitle"
        >
          <Pencil class="h-4 w-4" />
        </button>
      </div>
    </div>

    <!-- Inline edition edit -->
    <div class="flex flex-wrap gap-1.5 mt-2">
      <div v-if="editingEdition" class="flex items-center gap-1.5">
        <BaseEditionSelector
          :model-value="editionDraft"
          input-class="input input-bordered input-xs font-medium w-40"
          :autofocus="true"
          @update:model-value="editionDraft = $event"
          @confirm="saveEdition"
          @cancel="editingEdition = false"
        />
        <button class="btn btn-primary btn-xs" :aria-label="t('common.save')" @click="saveEdition">✓</button>
        <button class="btn btn-ghost btn-xs" :aria-label="t('common.cancel')" @click="editingEdition = false">✕</button>
      </div>
      <div v-else class="group/edition flex items-center gap-1">
        <div class="tooltip tooltip-bottom" :data-tip="t('manga.editPublisherTooltip')">
          <span
            class="badge cursor-pointer gap-1.5"
            :class="manga.edition ? 'badge-primary' : 'badge-ghost'"
            @click="startEditEdition"
          >
            <img
              v-if="editionLogo"
              :src="editionLogo"
              :alt="manga.edition!"
              class="w-3.5 h-3.5 rounded-sm object-contain"
            />
            {{ manga.edition ?? t('manga.unknownPublisher') }}
          </span>
        </div>
        <button
          class="btn btn-ghost btn-xs opacity-0 group-hover/edition:opacity-60 focus-visible:opacity-60 transition-opacity p-0 min-h-0 h-auto"
          :aria-label="t('manga.editPublisher')"
          @click="startEditEdition"
        >
          <Pencil class="h-3 w-3" />
        </button>
      </div>

      <!-- Special edition: free text, as the catalogue (or the user) names it -->
      <div v-if="editingSpecialEdition" class="flex items-center gap-1.5">
        <input
          v-model="specialEditionDraft"
          class="input input-bordered input-xs font-medium w-44"
          maxlength="150"
          :placeholder="t('catalogue.standardEdition')"
          :aria-label="t('manga.specialEdition')"
          autofocus
          @keydown.enter="saveSpecialEdition"
          @keydown.escape="editingSpecialEdition = false"
        />
        <button class="btn btn-primary btn-xs" :aria-label="t('common.save')" @click="saveSpecialEdition">✓</button>
        <button class="btn btn-ghost btn-xs" :aria-label="t('common.cancel')" @click="editingSpecialEdition = false">✕</button>
      </div>
      <button
        v-else-if="manga.specialEdition"
        class="badge badge-warning gap-1 cursor-pointer"
        :title="t('manga.specialEdition')"
        @click="startEditSpecialEdition"
      >
        <Sparkles class="h-3 w-3" />
        {{ manga.specialEdition }}
      </button>
      <button
        v-else
        class="badge badge-ghost cursor-pointer text-base-content/50"
        @click="startEditSpecialEdition"
      >
        {{ t('manga.addSpecialEdition') }}
      </button>
      <span class="badge badge-outline">{{ manga.language.toUpperCase() }}</span>
      <span v-if="manga.genre" class="badge badge-outline capitalize">{{ manga.genre }}</span>

      <!-- Rating, right after the genre -->
      <div class="tooltip tooltip-top ml-1" :data-tip="rating !== null ? t('rating.editTooltip') : t('rating.rateTooltip')">
        <BaseHeartRating
          :model-value="rating"
          @update:model-value="emit('rate', $event)"
        />
      </div>
    </div>
    <p v-if="manga.author" class="text-sm text-base-content/60 mt-1.5 font-medium">{{ manga.author }}</p>
  </div>
</template>
