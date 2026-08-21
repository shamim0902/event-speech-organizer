<template>
  <el-dialog
    title="Share schedule"
    :visible="visible"
    width="560px"
    append-to-body
    @update:visible="$emit('update:visible', $event)"
    @open="fetch"
  >
    <div v-loading="loading">
      <p class="eso-field-hint">
        The schedule page lists every slot with its speakers. Open it and choose
        <strong>Print → Save as PDF</strong> for a file you can attach anywhere.
      </p>

      <div class="eso-share__row">
        <el-button
          type="primary"
          size="small"
          icon="el-icon-view"
          @click="openPage"
          >Open schedule page</el-button
        >
        <el-button size="small" icon="el-icon-printer" @click="print"
          >Print / PDF</el-button
        >
      </div>

      <div class="eso-share__toggle">
        <div>
          <p class="eso-section-label">Public link</p>
          <p class="eso-field-hint">
            {{
              share.is_public
                ? 'Anyone with the link can view this schedule — no login needed.'
                : 'Sharing is off. Only logged-in organisers can open the page.'
            }}
          </p>
        </div>
        <el-switch
          :value="share.is_public"
          :disabled="saving"
          @change="setPublic"
        ></el-switch>
      </div>

      <template v-if="share.is_public">
        <el-input :value="share.url" readonly size="small">
          <el-button slot="append" icon="el-icon-document-copy" @click="copy"
            >Copy</el-button
          >
        </el-input>

        <p class="eso-field-hint eso-share__revoke">
          Sent the link to the wrong list?
          <button class="eso-link-btn" type="button" @click="regenerate">
            Generate a new link
          </button>
          — the old one stops working.
        </p>
      </template>
    </div>

    <span slot="footer">
      <el-button size="small" @click="$emit('update:visible', false)">Close</el-button>
    </span>
  </el-dialog>
</template>

<script>
/**
 * Share/print controls for an event's public schedule page.
 */
export default {
  name: 'ScheduleShareDialog',
  props: {
    visible: { type: Boolean, default: false },
    eventId: { type: [String, Number], required: true }
  },
  data () {
    return {
      loading: false,
      saving: false,
      share: { url: '', is_public: false }
    }
  },
  methods: {
    fetch () {
      this.loading = true

      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_schedule_share',
        event_id: this.eventId
      })
        .then(response => {
          this.share = (response && response.data) || this.share
        })
        .always(() => {
          this.loading = false
        })
    },
    update (payload, message) {
      this.saving = true

      this.$post(
        Object.assign(
          {
            action: 'event_speech_organizer_admin_ajax',
            route: 'update_schedule_share',
            nonce: window.eventSpeechOrganizerAdmin.nonce,
            event_id: this.eventId
          },
          payload
        )
      )
        .then(response => {
          if (!response || !response.status) {
            this.$message.error((response && response.message) || 'Could not update sharing.')
            return
          }

          this.share = response.data
          this.$message.success(message)
        })
        .always(() => {
          this.saving = false
        })
    },
    setPublic (isPublic) {
      this.update(
        { is_public: isPublic ? 1 : 0 },
        isPublic ? 'Schedule is now shareable.' : 'Public sharing switched off.'
      )
    },
    regenerate () {
      this.$confirm(
        'The current link will stop working immediately. Continue?',
        'Generate a new link',
        { confirmButtonText: 'Generate', cancelButtonText: 'Cancel', type: 'warning' }
      )
        .then(() => this.update({ regenerate: 1 }, 'New link generated.'))
        .catch(() => {})
    },
    openPage () {
      window.open(this.share.url, '_blank', 'noopener')
    },
    print () {
      // Organisers can always open the page, so printing works even with
      // sharing switched off.
      const tab = window.open(this.share.url, '_blank', 'noopener')

      if (tab) {
        this.$message.info('Use your browser\'s Print dialog, then "Save as PDF".')
      }
    },
    copy () {
      const input = document.createElement('input')
      input.value = this.share.url
      document.body.appendChild(input)
      input.select()

      try {
        document.execCommand('copy')
        this.$message.success('Link copied.')
      } catch (error) {
        this.$message.info('Press Ctrl/Cmd + C to copy the link.')
      }

      document.body.removeChild(input)
    }
  }
}
</script>
