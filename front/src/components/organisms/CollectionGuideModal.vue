<script setup lang="ts">
import { ref } from 'vue'
import {
  X, Package, BookOpen, Star, Megaphone, CircleDashed,
  BookMarked, Layers, CheckCircle2, Gift, Wallet, PieChart, TrendingUp,
  Search, QrCode, Camera, Link2, Sparkles, Lightbulb, Info,
} from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()

type Tab = 'statuses' | 'dashboard' | 'covers'
const tab = ref<Tab>('statuses')

// ── Statuses legend ──
const STATUSES = [
  { key: 'owned',     icon: Package,       chip: 'bg-success/15 text-success' },
  { key: 'read',      icon: BookOpen,      chip: 'bg-info/15 text-info' },
  { key: 'wished',    icon: Star,          chip: 'bg-warning/15 text-warning' },
  { key: 'announced', icon: Megaphone,     chip: 'bg-secondary/15 text-secondary' },
  { key: 'untracked', icon: CircleDashed,  chip: 'bg-base-content/10 text-base-content/40' },
] as const

const RULES = ['rule1', 'rule2', 'rule3', 'rule4'] as const

// ── Dashboard metrics ──
const METRICS = [
  { key: 'series',        icon: BookMarked,  chip: 'bg-primary/15 text-primary' },
  { key: 'owned',         icon: Layers,      chip: 'bg-success/15 text-success' },
  { key: 'read',          icon: CheckCircle2, chip: 'bg-info/15 text-info' },
  { key: 'progress',      icon: TrendingUp,  chip: 'bg-primary/15 text-primary' },
  { key: 'wishlist',      icon: Gift,        chip: 'bg-warning/15 text-warning' },
  { key: 'ownedValue',    icon: Wallet,      chip: 'bg-success/15 text-success' },
  { key: 'wishlistValue', icon: Wallet,      chip: 'bg-warning/15 text-warning' },
  { key: 'totalValue',    icon: Wallet,      chip: 'bg-base-content/10 text-base-content/70' },
  { key: 'genre',         icon: PieChart,    chip: 'bg-secondary/15 text-secondary' },
] as const

// ── Cover methods ──
const COVER_METHODS = [
  { key: 'search', icon: Search,   chip: 'bg-primary/15 text-primary',     steps: 3 },
  { key: 'isbn',   icon: QrCode,   chip: 'bg-secondary/15 text-secondary', steps: 3 },
  { key: 'scan',   icon: Camera,   chip: 'bg-info/15 text-info',           steps: 3 },
  { key: 'url',    icon: Link2,    chip: 'bg-base-content/10 text-base-content/60', steps: 0 },
  { key: 'auto',   icon: Sparkles, chip: 'bg-success/15 text-success',     steps: 0 },
] as const

const COVER_SOURCES = ['mangadex', 'googlebooks', 'bnf', 'openlibrary', 'hardcover'] as const
</script>

<template>
  <Teleport to="body">
    <Transition name="guide">
      <div
        v-if="open"
        class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-0 sm:p-4"
        role="dialog"
        aria-modal="true"
      >
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="emit('close')" />

        <div class="relative z-10 w-full sm:max-w-2xl bg-base-100 rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col h-[88dvh] sm:h-[640px]">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-base-200 bg-gradient-to-br from-primary/8 to-transparent">
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 rounded-xl bg-primary/15 text-primary flex items-center justify-center">
                <Info class="h-5 w-5" />
              </div>
              <div>
                <h2 class="font-bold text-lg leading-tight">{{ t('guide.title') }}</h2>
                <p class="text-xs text-base-content/50">{{ t('guide.subtitle') }}</p>
              </div>
            </div>
            <button class="btn btn-ghost btn-sm btn-circle" :aria-label="t('common.close')" @click="emit('close')">
              <X class="h-4 w-4" />
            </button>
          </div>

          <!-- Tabs -->
          <div class="px-3 sm:px-5 pt-3 shrink-0">
            <div class="inline-flex p-1 bg-base-200 rounded-xl gap-1 w-full sm:w-auto">
              <button
                class="btn btn-sm border-0 flex-1 sm:flex-none gap-1.5"
                :class="tab === 'statuses' ? 'btn-primary' : 'btn-ghost'"
                @click="tab = 'statuses'"
              >
                <BookMarked class="h-4 w-4" />
                <span>{{ t('guide.tabStatuses') }}</span>
              </button>
              <button
                class="btn btn-sm border-0 flex-1 sm:flex-none gap-1.5"
                :class="tab === 'dashboard' ? 'btn-primary' : 'btn-ghost'"
                @click="tab = 'dashboard'"
              >
                <PieChart class="h-4 w-4" />
                <span>{{ t('guide.tabDashboard') }}</span>
              </button>
              <button
                class="btn btn-sm border-0 flex-1 sm:flex-none gap-1.5"
                :class="tab === 'covers' ? 'btn-primary' : 'btn-ghost'"
                @click="tab = 'covers'"
              >
                <Search class="h-4 w-4" />
                <span>{{ t('guide.tabCovers') }}</span>
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto px-5 py-4 min-h-0">
            <!-- ── Statuses ── -->
            <div v-if="tab === 'statuses'" class="space-y-4">
              <p class="text-sm text-base-content/60 leading-relaxed">{{ t('guide.statusesIntro') }}</p>

              <ul class="space-y-2.5">
                <li
                  v-for="s in STATUSES"
                  :key="s.key"
                  class="flex items-start gap-3 rounded-xl border border-base-200 bg-base-100 p-3"
                >
                  <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :class="s.chip">
                    <component :is="s.icon" class="h-5 w-5" />
                  </div>
                  <div class="min-w-0">
                    <p class="font-semibold text-sm leading-tight">{{ t(`guide.status.${s.key}.name`) }}</p>
                    <p class="text-xs text-base-content/55 mt-0.5 leading-snug">{{ t(`guide.status.${s.key}.desc`) }}</p>
                  </div>
                </li>
              </ul>

              <div class="rounded-xl bg-primary/5 border border-primary/15 p-3.5">
                <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-primary/80 mb-2">
                  <Lightbulb class="h-3.5 w-3.5" />
                  {{ t('guide.rulesTitle') }}
                </p>
                <ul class="space-y-1.5">
                  <li
                    v-for="rule in RULES"
                    :key="rule"
                    class="flex items-start gap-2 text-xs text-base-content/65 leading-snug"
                  >
                    <span class="mt-1 h-1.5 w-1.5 rounded-full bg-primary/60 shrink-0" />
                    {{ t(`guide.${rule}`) }}
                  </li>
                </ul>
              </div>
            </div>

            <!-- ── Dashboard ── -->
            <div v-else-if="tab === 'dashboard'" class="space-y-4">
              <p class="text-sm text-base-content/60 leading-relaxed">{{ t('guide.dashboardIntro') }}</p>

              <ul class="space-y-2.5">
                <li
                  v-for="m in METRICS"
                  :key="m.key"
                  class="flex items-start gap-3 rounded-xl border border-base-200 bg-base-100 p-3"
                >
                  <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :class="m.chip">
                    <component :is="m.icon" class="h-5 w-5" />
                  </div>
                  <div class="min-w-0">
                    <p class="font-semibold text-sm leading-tight">{{ t(`guide.metric.${m.key}.name`) }}</p>
                    <p class="text-xs text-base-content/55 mt-0.5 leading-snug">{{ t(`guide.metric.${m.key}.formula`) }}</p>
                  </div>
                </li>
              </ul>

              <div class="rounded-xl bg-secondary/5 border border-secondary/15 p-3.5">
                <p class="flex items-start gap-2 text-xs text-base-content/65 leading-snug">
                  <Wallet class="h-4 w-4 text-secondary shrink-0 mt-px" />
                  {{ t('guide.priceNote') }}
                </p>
              </div>
            </div>

            <!-- ── Covers ── -->
            <div v-else class="space-y-4">
              <p class="text-sm text-base-content/60 leading-relaxed">{{ t('guide.coversIntro') }}</p>

              <div
                v-for="method in COVER_METHODS"
                :key="method.key"
                class="rounded-xl border border-base-200 bg-base-100 p-3.5"
              >
                <div class="flex items-start gap-3">
                  <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :class="method.chip">
                    <component :is="method.icon" class="h-5 w-5" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="font-semibold text-sm leading-tight">{{ t(`guide.cover.${method.key}.name`) }}</p>
                    <p class="text-xs text-base-content/55 mt-0.5 leading-snug">{{ t(`guide.cover.${method.key}.desc`) }}</p>
                    <ol v-if="method.steps > 0" class="mt-2.5 space-y-1.5">
                      <li
                        v-for="step in method.steps"
                        :key="step"
                        class="flex items-start gap-2 text-xs text-base-content/65 leading-snug"
                      >
                        <span class="flex items-center justify-center h-4 w-4 rounded-full bg-base-200 text-[10px] font-bold text-base-content/60 shrink-0 mt-px">
                          {{ step }}
                        </span>
                        {{ t(`guide.cover.${method.key}.step${step}`) }}
                      </li>
                    </ol>
                  </div>
                </div>
              </div>

              <div class="rounded-xl bg-base-200/50 border border-base-200 p-3.5">
                <p class="text-xs font-bold uppercase tracking-wide text-base-content/50 mb-2">{{ t('guide.coverSourcesTitle') }}</p>
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="src in COVER_SOURCES"
                    :key="src"
                    class="badge badge-sm badge-ghost font-medium"
                  >
                    {{ t(`guide.coverSource.${src}`) }}
                  </span>
                </div>
                <p class="text-xs text-base-content/50 mt-2.5 leading-snug">{{ t('guide.coverSourcesNote') }}</p>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="shrink-0 px-5 py-3 border-t border-base-200">
            <button class="btn btn-primary btn-sm w-full sm:w-auto sm:float-right" @click="emit('close')">
              {{ t('guide.gotIt') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.guide-enter-active,
.guide-leave-active {
  transition: opacity 0.2s ease;
}
.guide-enter-active .relative,
.guide-leave-active .relative {
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.guide-enter-from,
.guide-leave-to {
  opacity: 0;
}
.guide-enter-from .relative {
  transform: translateY(40px) scale(0.97);
}
</style>
