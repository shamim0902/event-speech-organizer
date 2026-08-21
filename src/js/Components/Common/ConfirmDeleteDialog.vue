<template>
  <el-dialog
    :title="title"
    :visible="visible"
    width="440px"
    append-to-body
    custom-class="eso-confirm"
    @update:visible="$emit('update:visible', $event)"
    @open="typed = ''"
  >
    <div class="eso-confirm__body">
      <div class="eso-confirm__icon"><i class="el-icon-warning-outline"></i></div>
      <div class="eso-confirm__text">
        <p class="eso-confirm__message">{{ message }}</p>
        <p class="eso-confirm__hint" v-if="hint">{{ hint }}</p>
      </div>
    </div>

    <label class="eso-confirm__label" v-if="requireTyping">
      Type <code>{{ word }}</code> to confirm
    </label>
    <el-input
      v-if="requireTyping"
      ref="input"
      v-model="typed"
      :placeholder="word"
      autocomplete="off"
      spellcheck="false"
      @keyup.enter.native="submit"
    ></el-input>

    <span slot="footer">
      <el-button size="small" @click="$emit('update:visible', false)">Cancel</el-button>
      <el-button
        size="small"
        type="danger"
        :disabled="!matches"
        :loading="loading"
        @click="submit"
        >{{ confirmText }}</el-button
      >
    </span>
  </el-dialog>
</template>

<script>
/**
 * Destructive confirmation that requires the user to type a word before the
 * action button unlocks — for deletes that take related data down with them.
 */
export default {
  name: 'ConfirmDeleteDialog',
  props: {
    visible: { type: Boolean, default: false },
    title: { type: String, default: 'Delete' },
    message: { type: String, required: true },
    hint: { type: String, default: '' },
    word: { type: String, default: 'delete' },
    requireTyping: { type: Boolean, default: true },
    confirmText: { type: String, default: 'Delete' },
    loading: { type: Boolean, default: false }
  },
  data () {
    return { typed: '' }
  },
  computed: {
    matches () {
      if (!this.requireTyping) {
        return true
      }
      return this.typed.trim().toLowerCase() === this.word.toLowerCase()
    }
  },
  methods: {
    submit () {
      if (this.matches && !this.loading) {
        this.$emit('confirm')
      }
    }
  },
  watch: {
    visible (open) {
      if (open && this.requireTyping) {
        this.$nextTick(() => {
          this.$refs.input && this.$refs.input.focus()
        })
      }
    }
  }
}
</script>
