<script setup lang="ts">
  import BaseLoader from './BaseLoader.vue'

  /**
   * A button that can be busy with its own action: a submit, a save, a lookup…
   *
   * While `loading` it keeps its colours, size and label — no DaisyUI grey-out —, shows the
   * app loader in place of its `icon` slot (or before its label when it has none), refuses
   * clicks and form submissions (Enter key included) and tells assistive technologies it is busy.
   * It is not `disabled` meanwhile, so the focus stays on it.
   *
   * Styling stays with the caller: pass the usual `btn …` classes.
   */
  const props = withDefaults(
    defineProps<{
      loading?: boolean
      disabled?: boolean
      type?: 'button' | 'submit' | 'reset'
    }>(),
    {
      loading: false,
      disabled: false,
      type: 'button',
    },
  )

  const emit = defineEmits<{ click: [event: MouseEvent] }>()

  function onClick(event: MouseEvent): void {
    if (props.loading) {
      // Cancelling the click also cancels the form submission it would trigger.
      event.preventDefault()
      return
    }
    emit('click', event)
  }
</script>

<template>
  <button
    class="base-button"
    :type="type"
    :disabled="disabled && !loading"
    :aria-busy="loading || undefined"
    :aria-disabled="loading || undefined"
    @click="onClick"
  >
    <BaseLoader v-if="loading" class="base-button__loader" />
    <slot v-else name="icon" />
    <slot />
  </button>
</template>

<style scoped>
  .base-button[aria-busy='true'] {
    cursor: progress;
  }

  .base-button__loader {
    animation: base-button-loader-in 0.18s ease-out both;
  }

  @keyframes base-button-loader-in {
    from {
      opacity: 0;
      transform: scale(0.6);
    }
    to {
      opacity: 1;
      transform: scale(1);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .base-button__loader {
      animation: none;
    }
  }
</style>
