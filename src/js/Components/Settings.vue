<template>
  <div>
    <page-header
      title="Settings"
      subtitle="Connect incoming webhooks and manage plugin settings."
    />

    <div class="eso-card" v-loading="loading">
      <div class="eso-card__header">
        <div class="eso-card__header-main">
          <div class="eso-card__title">Incoming webhook</div>
          <div class="eso-card__meta">
            POST speaker application forms to these URLs and they are added as
            waiting applicants. Each URL is scoped to one event — paste it as
            the webhook / notification URL on the site running the form.
          </div>
        </div>
      </div>

      <div class="eso-card__body">
        <empty-state
          v-if="!loading && !events.length"
          icon="el-icon-date"
          title="No events yet"
          hint="Create an event first — each event gets its own webhook URL."
        >
          <el-button
            type="primary"
            size="small"
            @click="$router.push({ name: 'events', query: { all: '1' } })"
            >Go to events</el-button
          >
        </empty-state>

        <el-form v-else label-position="top">
          <el-form-item v-for="event in events" :key="event.id" :label="event.title">
            <el-input :value="event.url" readonly @focus="$event.target.select()">
              <el-button
                slot="append"
                icon="el-icon-document-copy"
                @click="copy(event.url)"
                >Copy</el-button
              >
            </el-input>
          </el-form-item>
        </el-form>
      </div>

      <div class="eso-card__footer">
        <span class="eso-card__meta">
          The URLs contain a secret token — anyone who has one can submit
          applicants. If a URL leaks, regenerate the token; every previously
          shared URL stops working immediately.
        </span>
        <el-button
          size="small"
          type="danger"
          plain
          :loading="regenerating"
          @click="confirmRegenerate"
          >Regenerate token</el-button
        >
      </div>
    </div>
  </div>
</template>

<script>
import PageHeader from './Common/PageHeader.vue'
import EmptyState from './Common/EmptyState.vue'

export default {
  name: 'Settings',
  components: {
    PageHeader,
    EmptyState
  },
  data () {
    return {
      events: [],
      loading: false,
      regenerating: false
    }
  },
  methods: {
    fetch () {
      this.loading = true

      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_webhook_settings',
        nonce: window.eventSpeechOrganizerAdmin.nonce
      })
        .then(response => {
          this.events = (response && response.events) || []
        })
        .fail(() => {
          this.$message.error('Could not load the webhook settings.')
        })
        .always(() => {
          this.loading = false
        })
    },
    confirmRegenerate () {
      this.$confirm(
        'Regenerate the webhook token? Every URL already pasted into a form ' +
          'will stop working and must be replaced with the new one.',
        'Regenerate token',
        {
          confirmButtonText: 'Regenerate',
          cancelButtonText: 'Cancel',
          type: 'warning'
        }
      )
        .then(() => this.regenerate())
        .catch(() => {})
    },
    regenerate () {
      this.regenerating = true

      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'regenerate_webhook_token',
        nonce: window.eventSpeechOrganizerAdmin.nonce
      })
        .then(response => {
          this.events = (response && response.events) || []
          this.$message.success('Webhook token regenerated. Update the URLs everywhere they are used.')
        })
        .fail(() => {
          this.$message.error('Could not regenerate the token.')
        })
        .always(() => {
          this.regenerating = false
        })
    },
    copy (url) {
      const copied = () => this.$message.success('Webhook URL copied.')

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(copied)
        return
      }

      // http:// admin screens have no navigator.clipboard.
      const input = document.createElement('textarea')
      input.value = url
      document.body.appendChild(input)
      input.select()
      document.execCommand('copy')
      document.body.removeChild(input)
      copied()
    }
  },
  mounted () {
    this.fetch()
  }
}
</script>
