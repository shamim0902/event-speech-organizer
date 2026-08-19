<template>
  <el-dialog
    title="Webhook field mapper"
    :visible="visible"
    width="720px"
    append-to-body
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-loading="loading">
      <p style="margin-top: 0">
        Incoming webhook fields are matched to applicant columns automatically.
        Map a field manually when the automatic match picks the wrong column or
        misses the field entirely; choose <em>Ignore</em> to drop it.
      </p>

      <p class="eso-card__meta">
        <template v-if="receivedAt">
          Last payload received {{ receivedAt }} — its fields are listed below.
        </template>
        <template v-else>
          No payload received yet. Send a test submission to the webhook URL and
          reopen this dialog to map the real field names.
        </template>
      </p>

      <el-input
        v-if="url"
        :value="url"
        readonly
        size="small"
        style="margin-bottom: 16px"
        @focus="$event.target.select()"
      >
        <el-button slot="append" icon="el-icon-document-copy" @click="copyUrl"
          >Copy</el-button
        >
      </el-input>

      <el-table v-if="rows.length" class="eso-table" :data="rows">
        <el-table-column label="Incoming field" min-width="240">
          <template slot-scope="scope">
            <el-input
              v-if="scope.row.custom"
              v-model="scope.row.key"
              size="small"
              placeholder="Exact field key, e.g. g1155-email"
            ></el-input>
            <template v-else>
              <div class="eso-table__name">{{ scope.row.key }}</div>
              <div class="eso-table__meta" v-if="scope.row.sample">
                {{ scope.row.sample }}
              </div>
            </template>
          </template>
        </el-table-column>

        <el-table-column label="Maps to" width="260">
          <template slot-scope="scope">
            <el-select
              v-model="scope.row.column"
              size="small"
              class="eso-full-width"
            >
              <el-option value="" :label="autoLabel(scope.row)"></el-option>
              <el-option value="ignore" label="Ignore this field"></el-option>
              <el-option
                v-for="(label, column) in columns"
                :key="column"
                :value="column"
                :label="label"
              ></el-option>
            </el-select>
          </template>
        </el-table-column>

        <el-table-column width="46">
          <template slot-scope="scope">
            <button
              v-if="scope.row.custom"
              class="eso-link-btn eso-link-btn--danger"
              type="button"
              @click="removeRow(scope.row)"
            >
              <i class="el-icon-close"></i>
            </button>
          </template>
        </el-table-column>
      </el-table>

      <div style="margin-top: 12px">
        <el-button size="small" icon="el-icon-plus" @click="addRow"
          >Add field</el-button
        >
      </div>
    </div>

    <span slot="footer">
      <el-button size="small" @click="$emit('update:visible', false)"
        >Cancel</el-button
      >
      <el-button size="small" type="primary" :loading="saving" @click="save"
        >Save mapping</el-button
      >
    </span>
  </el-dialog>
</template>

<script>
/**
 * Per-event mapping of incoming webhook field keys onto applicant columns.
 * Rows come from the union of the event's last captured payload and the
 * stored mapping; "Add field" covers keys that have not been received yet.
 */
export default {
  name: 'WebhookMapperDialog',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    eventId: {
      type: [String, Number],
      required: true
    }
  },
  data () {
    return {
      loading: false,
      saving: false,
      rows: [],
      columns: {},
      receivedAt: '',
      url: ''
    }
  },
  watch: {
    visible (isOpen) {
      if (isOpen) {
        this.fetch()
      }
    }
  },
  methods: {
    fetch () {
      this.loading = true

      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_webhook_mapping',
        nonce: window.eventSpeechOrganizerAdmin.nonce,
        event_id: this.eventId
      })
        .then(response => {
          if (!response || !response.status) {
            this.$message.error('Could not load the field mapping.')
            return
          }

          this.columns = response.columns || {}
          this.url = response.url || ''

          const payload = response.last_payload || {}
          const fields = payload.fields || {}
          const mapping = response.mapping || {}
          const auto = response.auto || {}

          this.receivedAt = payload.received_at || ''

          const rows = Object.keys(fields).map(key => ({
            key: key,
            sample: fields[key],
            inPayload: true,
            auto: auto[key] || null,
            column: mapping[key] || '',
            custom: false
          }))

          // Mapped keys the last payload did not carry are rendered like
          // hand-added rows — editable key and removable — since the payload
          // cannot vouch for them.
          Object.keys(mapping).forEach(key => {
            if (fields[key] === undefined) {
              rows.push({
                key: key,
                sample: '',
                inPayload: false,
                auto: null,
                column: mapping[key],
                custom: true
              })
            }
          })

          this.rows = rows
        })
        .fail(() => {
          this.$message.error('Could not load the field mapping.')
        })
        .always(() => {
          this.loading = false
        })
    },
    autoLabel (row) {
      if (row.custom) {
        return 'Auto'
      }
      return row.auto && this.columns[row.auto]
        ? 'Auto — ' + this.columns[row.auto]
        : 'Auto — no match'
    },
    addRow () {
      this.rows.push({
        key: '',
        sample: '',
        inPayload: false,
        auto: null,
        column: '',
        custom: true
      })
    },
    removeRow (row) {
      this.rows = this.rows.filter(item => item !== row)
    },
    save () {
      const mapping = {}

      this.rows.forEach(row => {
        const key = (row.key || '').trim()

        if (key && row.column) {
          mapping[key] = row.column
        }
      })

      this.saving = true

      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'save_webhook_mapping',
        nonce: window.eventSpeechOrganizerAdmin.nonce,
        event_id: this.eventId,
        mapping: JSON.stringify(mapping)
      })
        .then(response => {
          if (!response || !response.status) {
            this.$message.error(
              (response && response.message) || 'Could not save the mapping.'
            )
            return
          }

          this.$message.success('Field mapping saved.')
          this.$emit('update:visible', false)
        })
        .fail(() => {
          this.$message.error('Could not save the mapping.')
        })
        .always(() => {
          this.saving = false
        })
    },
    copyUrl () {
      const copied = () => this.$message.success('Webhook URL copied.')

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(this.url).then(copied)
        return
      }

      // http:// admin screens have no navigator.clipboard.
      const input = document.createElement('textarea')
      input.value = this.url
      document.body.appendChild(input)
      input.select()
      document.execCommand('copy')
      document.body.removeChild(input)
      copied()
    }
  }
}
</script>
