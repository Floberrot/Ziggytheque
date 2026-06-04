<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { X, Copy, Check, Mail, MessageSquare, Share2, Link2, Loader2 } from 'lucide-vue-next'
import { useUiStore } from '@/stores/useUiStore'
import type { Stats } from '@/types'

const props = defineProps<{ open: boolean; url: string | null; loading: boolean; stats: Stats | undefined }>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const ui = useUiStore()

const copied = ref(false)
const canNativeShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function'

watch(
  () => props.open,
  (open) => {
    if (!open) copied.value = false
  },
)

const readingProgress = computed(() => {
  if (!props.stats?.totalOwned) return 0
  return Math.round((props.stats.totalRead / props.stats.totalOwned) * 100)
})

const shareText = computed(() => t('share.inviteText'))

const mailtoHref = computed(() => {
  const subject = encodeURIComponent(t('share.mailSubject'))
  const body = encodeURIComponent(`${shareText.value}\n\n${props.url ?? ''}`)
  return `mailto:?subject=${subject}&body=${body}`
})

const smsHref = computed(() => {
  const body = encodeURIComponent(`${shareText.value} ${props.url ?? ''}`)
  return `sms:?&body=${body}`
})

async function copyLink(): Promise<void> {
  if (!props.url) return
  try {
    await navigator.clipboard.writeText(props.url)
    copied.value = true
    ui.addToast(t('share.copied'), 'success')
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    ui.addToast(t('share.copyError'), 'error')
  }
}

async function nativeShare(): Promise<void> {
  if (!props.url) return
  try {
    await navigator.share({ title: t('share.mailSubject'), text: shareText.value, url: props.url })
  } catch {
    /* user dismissed the native sheet — no-op */
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="share">
      <div
        v-if="open"
        class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-0 sm:p-4"
        role="dialog"
        aria-modal="true"
      >
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="emit('close')" />

        <div class="relative z-10 w-full sm:max-w-md bg-base-100 rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-base-200">
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 rounded-xl bg-primary/15 text-primary flex items-center justify-center">
                <Share2 class="h-5 w-5" />
              </div>
              <div>
                <h2 class="font-bold text-lg leading-tight">{{ t('share.title') }}</h2>
                <p class="text-xs text-base-content/50">{{ t('share.subtitle') }}</p>
              </div>
            </div>
            <button class="btn btn-ghost btn-sm btn-circle" :aria-label="t('common.close')" @click="emit('close')">
              <X class="h-4 w-4" />
            </button>
          </div>

          <div class="p-5 space-y-5">
            <!-- Preview card -->
            <div class="rounded-2xl bg-gradient-to-br from-primary/10 via-base-200/40 to-secondary/10 border border-base-200 p-4">
              <p class="text-[10px] font-semibold uppercase tracking-widest text-base-content/40 mb-3">
                {{ t('share.previewLabel') }}
              </p>
              <div v-if="stats" class="grid grid-cols-3 gap-3 text-center">
                <div>
                  <p class="text-2xl font-bold leading-none">{{ stats.totalMangas }}</p>
                  <p class="text-[10px] uppercase tracking-wide text-base-content/45 mt-1">{{ t('dashboard.totalMangas') }}</p>
                </div>
                <div>
                  <p class="text-2xl font-bold leading-none text-success">{{ stats.totalOwned }}</p>
                  <p class="text-[10px] uppercase tracking-wide text-base-content/45 mt-1">{{ t('dashboard.ownedVolumes') }}</p>
                </div>
                <div>
                  <p class="text-2xl font-bold leading-none text-primary">{{ readingProgress }}%</p>
                  <p class="text-[10px] uppercase tracking-wide text-base-content/45 mt-1">{{ t('dashboard.readingProgress') }}</p>
                </div>
              </div>
            </div>

            <!-- Link -->
            <div v-if="loading" class="flex items-center justify-center gap-2 py-4 text-base-content/50">
              <Loader2 class="h-4 w-4 animate-spin" />
              <span class="text-sm">{{ t('share.generating') }}</span>
            </div>

            <template v-else-if="url">
              <div class="flex items-center gap-2 rounded-xl border border-base-300 bg-base-200/50 p-1.5 pl-3">
                <Link2 class="h-4 w-4 text-base-content/40 shrink-0" />
                <input
                  :value="url"
                  readonly
                  class="flex-1 bg-transparent text-sm text-base-content/70 outline-none truncate"
                  @focus="($event.target as HTMLInputElement).select()"
                />
                <button class="btn btn-sm btn-primary gap-1.5" @click="copyLink">
                  <Check v-if="copied" class="h-4 w-4" />
                  <Copy v-else class="h-4 w-4" />
                  {{ copied ? t('share.copied') : t('share.copy') }}
                </button>
              </div>

              <!-- Share targets -->
              <div class="grid grid-cols-3 gap-2.5">
                <a :href="mailtoHref" class="btn btn-ghost flex-col h-auto py-3 gap-1.5 border border-base-200">
                  <Mail class="h-5 w-5 text-primary" />
                  <span class="text-xs">{{ t('share.email') }}</span>
                </a>
                <a :href="smsHref" class="btn btn-ghost flex-col h-auto py-3 gap-1.5 border border-base-200">
                  <MessageSquare class="h-5 w-5 text-success" />
                  <span class="text-xs">{{ t('share.sms') }}</span>
                </a>
                <button
                  v-if="canNativeShare"
                  class="btn btn-ghost flex-col h-auto py-3 gap-1.5 border border-base-200"
                  @click="nativeShare"
                >
                  <Share2 class="h-5 w-5 text-secondary" />
                  <span class="text-xs">{{ t('share.more') }}</span>
                </button>
                <button v-else class="btn btn-ghost flex-col h-auto py-3 gap-1.5 border border-base-200" @click="copyLink">
                  <Copy class="h-5 w-5 text-secondary" />
                  <span class="text-xs">{{ t('share.copy') }}</span>
                </button>
              </div>

              <p class="text-xs text-base-content/40 text-center leading-relaxed">{{ t('share.privacyNote') }}</p>
            </template>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.share-enter-active,
.share-leave-active {
  transition: opacity 0.2s ease;
}
.share-enter-active .relative,
.share-leave-active .relative {
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.share-enter-from,
.share-leave-to {
  opacity: 0;
}
.share-enter-from .relative {
  transform: translateY(40px) scale(0.97);
}
</style>
