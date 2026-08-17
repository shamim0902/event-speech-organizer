<template>
  <div class="eso-page-header">
    <div class="eso-page-header__text">
      <div class="eso-page-header__breadcrumb" v-if="backTo">
        <i class="el-icon-back"></i>
        <router-link :to="backTo">{{ backLabel }}</router-link>
      </div>
      <div class="eso-page-header__title">
        <h1>{{ title }}</h1>
        <slot name="title-suffix"></slot>
      </div>
      <p class="eso-page-header__subtitle" v-if="subtitle">{{ subtitle }}</p>
    </div>

    <div class="eso-page-header__actions" v-if="$slots.actions">
      <slot name="actions"></slot>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PageHeader',
  props: {
    title: {
      type: String,
      required: true
    },
    subtitle: {
      type: String,
      default: ''
    },
    backTo: {
      type: [String, Object],
      default: null
    },
    backLabel: {
      type: String,
      default: 'Back'
    }
  }
}
</script>
