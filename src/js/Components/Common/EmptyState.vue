<template>
  <div class="eso-empty">
    <i class="eso-empty__icon" :class="icon"></i>
    <p class="eso-empty__title">{{ title }}</p>
    <p class="eso-empty__hint" v-if="hint">{{ hint }}</p>
    <div class="eso-empty__action" v-if="$slots.default">
      <slot></slot>
    </div>
  </div>
</template>

<script>
export default {
  name: 'EmptyState',
  props: {
    title: {
      type: String,
      required: true
    },
    hint: {
      type: String,
      default: ''
    },
    icon: {
      type: String,
      default: 'el-icon-document'
    }
  }
}
</script>
