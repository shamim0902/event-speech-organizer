<template>
  <span class="eso-badge" :class="'eso-badge--' + normalized">
    <i :class="icon" v-if="icon"></i>{{ label }}
  </span>
</template>

<script>
const ICONS = {
  approved: 'el-icon-check',
  waiting: 'el-icon-time',
  rejected: 'el-icon-close'
}

export default {
  name: 'StatusBadge',
  props: {
    status: {
      type: String,
      default: ''
    }
  },
  computed: {
    normalized () {
      return (this.status || '').toLowerCase().trim()
    },
    label () {
      return this.normalized || 'no status'
    },
    icon () {
      return ICONS[this.normalized] || ''
    }
  }
}
</script>
