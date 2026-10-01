<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Check, Info, Pencil, X } from 'lucide-vue-next'
import type { CatalogueEdition } from '@/api/catalogue'
import type { CatalogueSelection } from '@/types'
import BaseModal from '@/components/atoms/BaseModal.vue'
import BaseCover from '@/components/atoms/BaseCover.vue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import EditionBadge from '@/components/molecules/EditionBadge.vue'

/**
 * Pick the tomes you own in one series. The whole series is added either way; the
 * tomes left unselected stay "untracked" (not owned, not wished).
 */
const props = defineProps<{
  open: boolean
  edition: CatalogueEdition | null
  requestedVolume: number | null
  loadingVolumes: boolean
  adding: boolean
}>()

const emit = defineEmits<{
  close: []
  add: [selection: CatalogueSelection]
}>()

const { t } = useI18n()

const selected = ref<Set<number>>(new Set())
// Once the user picks or unpicks a tome, the selection is theirs — never reset it.
const touched = ref(false)
const showCorrection = ref(false)
const workTitle = ref('')
const publisher = ref('')
const specialEdition = ref('')

const ownedNumbers = computed(() => new Set(props.edition?.collection?.ownedNumbers ?? []))
const tomeNumbers = computed(() =>
  Array.from({ length: props.edition?.volumeCount ?? 0 }, (_, index) => index + 1),
)
const coverByNumber = computed(() => {
  const covers = new Map<number, string>()
  for (const volume of props.edition?.volumes ?? []) {
    if (volume.coverUrl) covers.set(volume.number, volume.coverUrl)
  }
  return covers
})
// Covers that turned out broken or a placeholder: the tile goes back to its plain number.
const missingCovers = ref(new Set<number>())
function hasCover(number: number): boolean {
  return coverByNumber.value.has(number) && !missingCovers.value.has(number)
}
const selectableNumbers = computed(() => tomeNumbers.value.filter((number) => !ownedNumbers.value.has(number)))
const isInCollection = computed(() => props.edition?.collection != null)

function canPreselect(number: number | null): number is number {
  return number !== null && number > 0 && number <= (props.edition?.volumeCount ?? 0) && !ownedNumbers.value.has(number)
}

// A new series (or a correction) resets the form; the requested tome is preselected.
watch(
  () => [props.edition?.workTitle, props.edition?.publisher, props.edition?.specialEdition, props.open],
  () => {
    const initial = new Set<number>()
    const requested = props.requestedVolume
    // "mob psycho 100" asks no tome 100: only preselect a tome the series has.
    if (canPreselect(requested)) {
      initial.add(requested)
    }
    selected.value = initial
    missingCovers.value = new Set()
    touched.value = false
    showCorrection.value = false
    workTitle.value = props.edition?.workTitle ?? ''
    publisher.value = props.edition?.publisher ?? ''
    specialEdition.value = props.edition?.specialEdition ?? ''
  },
  { immediate: true },
)

// The search saw only some tomes; when the complete list arrives, the requested
// tome may now exist ("berserk 38" while the search stopped at 30).
watch(
  () => props.edition?.volumeCount,
  () => {
    const requested = props.requestedVolume
    if (!touched.value && canPreselect(requested) && !selected.value.has(requested)) {
      selected.value = new Set([...selected.value, requested])
    }
  },
)

function toggle(number: number): void {
  if (ownedNumbers.value.has(number)) return
  touched.value = true
  const next = new Set(selected.value)
  if (next.has(number)) {
    next.delete(number)
  } else {
    next.add(number)
  }
  selected.value = next
}

function selectAll(): void {
  touched.value = true
  selected.value = new Set(selectableNumbers.value)
}

function selectNone(): void {
  touched.value = true
  selected.value = new Set()
}

function submit(numbers: number[]): void {
  emit('add', {
    ownedNumbers: numbers,
    workTitle: workTitle.value.trim() || (props.edition?.workTitle ?? ''),
    publisher: publisher.value.trim() || null,
    specialEdition: specialEdition.value.trim() || null,
  })
}
</script>

<template>
  <BaseModal :open="open" max-width-class="sm:max-w-2xl" @close="emit('close')">
    <template v-if="edition">
      <!-- Header -->
      <div class="flex items-start gap-3 p-4 border-b border-base-200">
        <div class="flex-1 min-w-0 space-y-1">
          <h2 class="text-lg font-bold leading-tight">{{ edition.workTitle }}</h2>
          <EditionBadge :publisher="edition.publisher" :special-edition="edition.specialEdition" />
          <p class="text-xs text-base-content/50">
            <span v-if="edition.author">{{ edition.author }} · </span>
            {{ t('catalogue.volumeCount', { count: edition.volumeCount }, edition.volumeCount) }}
          </p>
        </div>
        <button class="btn btn-ghost btn-sm btn-circle" :aria-label="t('common.close')" @click="emit('close')">
          <X class="h-5 w-5" />
        </button>
      </div>

      <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <!-- What will happen -->
        <p class="flex gap-2 text-xs text-base-content/60 bg-base-200/60 rounded-xl p-3">
          <Info class="h-4 w-4 shrink-0 text-primary" />
          <span>{{ isInCollection ? t('catalogue.sheetHintExisting') : t('catalogue.sheetHintNew', { count: edition.volumeCount }) }}</span>
        </p>

        <!-- Tome picker -->
        <div class="flex items-center justify-between gap-2">
          <h3 class="text-sm font-semibold">{{ t('catalogue.pickTomes') }}</h3>
          <div class="flex items-center gap-1">
            <BaseLoader v-if="loadingVolumes" size="xs" class="text-primary" />
            <button class="btn btn-ghost btn-xs" :disabled="selectableNumbers.length === 0" @click="selectAll">
              {{ t('catalogue.selectAll') }}
            </button>
            <button class="btn btn-ghost btn-xs" :disabled="selected.size === 0" @click="selectNone">
              {{ t('catalogue.selectNone') }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-5 sm:grid-cols-8 gap-2">
          <button
            v-for="number in tomeNumbers"
            :key="number"
            type="button"
            class="relative aspect-[2/3] rounded-lg overflow-hidden border-2 transition-all text-xs font-bold"
            :class="[
              ownedNumbers.has(number)
                ? 'border-success/60 cursor-default'
                : selected.has(number)
                  ? 'border-primary ring-2 ring-primary/30 scale-[1.03]'
                  : 'border-base-300 hover:border-primary/50',
            ]"
            :aria-pressed="selected.has(number) || ownedNumbers.has(number)"
            :disabled="ownedNumbers.has(number)"
            @click="toggle(number)"
          >
            <BaseCover
              v-if="hasCover(number)"
              :src="coverByNumber.get(number)"
              :alt="t('catalogue.tome', { number })"
              class="absolute inset-0 w-full h-full"
              @missing="missingCovers.add(number)"
            />
            <span
              class="absolute inset-0 flex items-center justify-center"
              :class="hasCover(number) ? 'bg-black/35 text-white' : 'bg-base-200 text-base-content/60'"
            >
              {{ number }}
            </span>
            <span
              v-if="ownedNumbers.has(number) || selected.has(number)"
              class="absolute top-1 right-1 h-4 w-4 rounded-full flex items-center justify-center"
              :class="ownedNumbers.has(number) ? 'bg-success text-success-content' : 'bg-primary text-primary-content'"
            >
              <Check class="h-3 w-3" stroke-width="3" />
            </span>
          </button>
        </div>
        <p class="text-[11px] text-base-content/40">{{ t('catalogue.pickTomesHint') }}</p>

        <!-- Correction: catalogues are not always right about the edition -->
        <div class="border-t border-base-200 pt-3">
          <button class="btn btn-ghost btn-xs gap-1.5 text-base-content/60" @click="showCorrection = !showCorrection">
            <Pencil class="h-3.5 w-3.5" />
            {{ t('catalogue.correct') }}
          </button>
          <div v-if="showCorrection" class="grid gap-2 mt-2 sm:grid-cols-3">
            <label class="flex flex-col gap-1">
              <span class="text-xs font-semibold text-base-content/60">{{ t('catalogue.workTitle') }}</span>
              <input v-model="workTitle" type="text" class="input input-bordered input-sm" maxlength="255" />
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-xs font-semibold text-base-content/60">{{ t('catalogue.publisher') }}</span>
              <input v-model="publisher" type="text" class="input input-bordered input-sm" maxlength="100" />
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-xs font-semibold text-base-content/60">{{ t('catalogue.specialEdition') }}</span>
              <input
                v-model="specialEdition"
                type="text"
                class="input input-bordered input-sm"
                maxlength="150"
                :placeholder="t('catalogue.standardEdition')"
              />
            </label>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="p-4 border-t border-base-200 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2">
        <button
          v-if="!isInCollection"
          class="btn btn-ghost btn-sm"
          :disabled="adding"
          @click="submit([])"
        >
          {{ t('catalogue.followWithoutTomes') }}
        </button>
        <button
          class="btn btn-primary"
          :disabled="adding || selected.size === 0"
          @click="submit([...selected].sort((left, right) => left - right))"
        >
          <BaseLoader v-if="adding" size="xs" />
          {{ t('catalogue.addTomes', { count: selected.size }, selected.size) }}
        </button>
      </div>
    </template>
  </BaseModal>
</template>
