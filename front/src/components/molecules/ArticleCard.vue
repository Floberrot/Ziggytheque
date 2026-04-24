<script setup lang="ts">
import type { Article } from '@/types'

defineProps<{ article: Article }>()
</script>

<template>
  <a
    :href="article.url"
    target="_blank"
    rel="noopener noreferrer"
    class="card card-side bg-base-200 shadow hover:shadow-lg transition-shadow duration-200 overflow-hidden group"
  >
    <!-- Preview image -->
    <figure class="w-28 sm:w-36 shrink-0 bg-base-300">
      <img
        v-if="article.imageUrl"
        :src="article.imageUrl"
        :alt="article.title"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
        loading="lazy"
      />
      <div v-else class="w-full h-full flex items-center justify-center text-base-content/20">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h4" />
        </svg>
      </div>
    </figure>

    <div class="card-body py-4 px-5 gap-1.5">
      <!-- Manga badge + source -->
      <div class="flex items-center gap-2 flex-wrap">
        <div class="flex items-center gap-1.5">
          <img
            v-if="article.collectionEntry.manga.coverUrl"
            :src="article.collectionEntry.manga.coverUrl"
            :alt="article.collectionEntry.manga.title"
            class="w-5 h-7 object-cover rounded-sm shrink-0"
          />
          <span class="text-xs font-semibold text-primary truncate max-w-32">
            {{ article.collectionEntry.manga.title }}
          </span>
        </div>
        <span class="badge badge-ghost badge-xs text-base-content/50">{{ article.sourceName }}</span>
      </div>

      <!-- Title -->
      <h3 class="text-sm font-bold leading-snug line-clamp-2 text-base-content group-hover:text-primary transition-colors">
        {{ article.title }}
      </h3>

      <!-- Snippet -->
      <p v-if="article.snippet" class="text-[11px] text-base-content/55 leading-relaxed line-clamp-2 italic mt-0.5">
        {{ article.snippet }}
      </p>

      <!-- Author + date -->
      <div class="flex items-center gap-3 text-[11px] text-base-content/40 mt-1">
        <span v-if="article.author">par {{ article.author }}</span>
        <span v-if="article.publishedAt">
          {{ new Date(article.publishedAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }) }}
        </span>
      </div>
    </div>
  </a>
</template>
