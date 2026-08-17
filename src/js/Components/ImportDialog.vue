<template>
  <el-dialog
    title="Import applicants from CSV"
    :visible="visible"
    width="820px"
    append-to-body
    :close-on-click-modal="false"
    @update:visible="close"
  >
    <el-steps :active="step" simple finish-status="success">
      <el-step title="Choose file" icon="el-icon-upload"></el-step>
      <el-step title="Match columns" icon="el-icon-s-operation"></el-step>
      <el-step title="Result" icon="el-icon-circle-check"></el-step>
    </el-steps>

    <!-- Step 1 — file -->
    <div class="eso-import__step" v-if="step === 0">
      <el-upload
        drag
        action=""
        accept=".csv,text/csv"
        :auto-upload="false"
        :show-file-list="false"
        :on-change="onFileChange"
      >
        <i class="el-icon-upload"></i>
        <div class="el-upload__text">
          Drop a CSV file here, or <em>click to browse</em>
        </div>
      </el-upload>

      <el-alert
        v-if="parseError"
        class="eso-import__alert"
        type="error"
        :closable="false"
        :title="parseError"
      ></el-alert>

      <p class="eso-import__hint">
        The first row must be a header row. Name and email are required; rows
        whose email already exists are skipped.
      </p>
    </div>

    <!-- Step 2 — mapping -->
    <div class="eso-import__step" v-else-if="step === 1">
      <div class="eso-import__summary">
        <strong>{{ fileName }}</strong>
        <span>{{ rows.length }} data {{ rows.length === 1 ? 'row' : 'rows' }}</span>
        <span>{{ headers.length }} columns</span>
      </div>

      <el-alert
        v-if="missingRequired.length"
        class="eso-import__alert"
        type="warning"
        :closable="false"
        :title="'Required field not matched: ' + missingRequired.join(', ')"
        description="Pick the matching column below before importing."
      ></el-alert>

      <div class="eso-import__map">
        <div class="eso-import__map-row eso-import__map-row--head">
          <span>Applicant field</span>
          <span>CSV column</span>
          <span>First value</span>
        </div>
        <div
          class="eso-import__map-row"
          v-for="field in fields"
          :key="field.key"
        >
          <label>
            {{ field.label
            }}<em v-if="field.required" class="eso-import__required">*</em>
          </label>

          <el-select
            v-model="mapping[field.key]"
            size="small"
            placeholder="Ignore"
          >
            <el-option label="— Ignore —" :value="-1"></el-option>
            <el-option
              v-for="(header, index) in headers"
              :key="index"
              :label="header || 'Column ' + (index + 1)"
              :value="index"
            ></el-option>
          </el-select>

          <span class="eso-import__preview">{{ previewFor(field.key) }}</span>
        </div>
      </div>
    </div>

    <!-- Step 3 — result -->
    <div class="eso-import__step" v-else>
      <div class="eso-stats">
        <div class="eso-stat">
          <div class="eso-stat__value">{{ result.imported }}</div>
          <div class="eso-stat__label">Imported</div>
        </div>
        <div class="eso-stat">
          <div class="eso-stat__value">{{ result.duplicates }}</div>
          <div class="eso-stat__label">Duplicate email</div>
        </div>
        <div class="eso-stat">
          <div class="eso-stat__value">{{ result.invalid }}</div>
          <div class="eso-stat__label">Invalid</div>
        </div>
        <div class="eso-stat" v-if="result.failed">
          <div class="eso-stat__value">{{ result.failed }}</div>
          <div class="eso-stat__label">Failed</div>
        </div>
      </div>

      <div class="eso-import__issues" v-if="result.issues.length">
        <p class="eso-section__title">Skipped rows</p>
        <ul>
          <li v-for="(issue, index) in result.issues" :key="index">
            Row {{ issue.row }} — {{ issue.reason }}
          </li>
        </ul>
        <p class="eso-import__hint" v-if="truncatedIssues">
          Only the first 50 skipped rows are listed.
        </p>
      </div>
    </div>

    <div class="eso-import__progress" v-if="importing">
      <el-progress :percentage="progress" :stroke-width="6"></el-progress>
      <span>Importing… {{ processed }} of {{ rows.length }}</span>
    </div>

    <span slot="footer">
      <template v-if="step === 0">
        <el-button size="small" @click="close">Cancel</el-button>
      </template>

      <template v-else-if="step === 1">
        <el-button size="small" :disabled="importing" @click="reset"
          >Choose another file</el-button
        >
        <el-button
          size="small"
          type="primary"
          :loading="importing"
          :disabled="!canImport"
          @click="startImport"
          >Import {{ rows.length }}
          {{ rows.length === 1 ? 'applicant' : 'applicants' }}</el-button
        >
      </template>

      <template v-else>
        <el-button size="small" @click="reset">Import another file</el-button>
        <el-button size="small" type="primary" @click="close">Done</el-button>
      </template>
    </span>
  </el-dialog>
</template>

<script>
import { IMPORT_FIELDS, parseCsv, autoMapColumns, applyMapping } from './Common/csv'

// Rows are posted in batches so a large file does not hit post_max_size or
// PHP's max_input_vars, and so the progress bar has something to report.
const BATCH_SIZE = 200

export default {
  name: 'ImportDialog',
  props: {
    visible: {
      type: Boolean,
      default: false
    }
  },
  data () {
    return {
      step: 0,
      fields: IMPORT_FIELDS,
      fileName: '',
      headers: [],
      rows: [],
      mapping: {},
      parseError: '',
      importing: false,
      processed: 0,
      result: {
        imported: 0,
        duplicates: 0,
        invalid: 0,
        failed: 0,
        issues: []
      }
    }
  },
  computed: {
    missingRequired () {
      return this.fields
        .filter(field => field.required && this.mapping[field.key] === -1)
        .map(field => field.label)
    },
    canImport () {
      return this.rows.length > 0 && !this.missingRequired.length
    },
    progress () {
      if (!this.rows.length) {
        return 0
      }
      return Math.round((this.processed / this.rows.length) * 100)
    },
    truncatedIssues () {
      const skipped = this.result.duplicates + this.result.invalid + this.result.failed
      return skipped > this.result.issues.length
    }
  },
  methods: {
    close () {
      if (this.importing) {
        return
      }
      this.$emit('close')
    },
    reset () {
      this.step = 0
      this.fileName = ''
      this.headers = []
      this.rows = []
      this.mapping = {}
      this.parseError = ''
      this.processed = 0
      this.result = { imported: 0, duplicates: 0, invalid: 0, failed: 0, issues: [] }
    },
    onFileChange (file) {
      this.parseError = ''

      const raw = file.raw || file
      const reader = new FileReader()

      reader.onload = event => {
        try {
          this.loadCsv(raw.name, event.target.result)
        } catch (error) {
          this.parseError = 'Could not read that file: ' + error.message
        }
      }
      reader.onerror = () => {
        this.parseError = 'Could not read that file.'
      }

      reader.readAsText(raw)
    },
    loadCsv (name, text) {
      const parsed = parseCsv(text)

      if (parsed.length < 2) {
        this.parseError = 'That file needs a header row and at least one data row.'
        return
      }

      this.fileName = name
      this.headers = parsed[0].map(header => String(header).trim())
      this.rows = parsed.slice(1)
      this.mapping = autoMapColumns(this.headers)
      this.step = 1
    },
    previewFor (key) {
      const index = this.mapping[key]

      if (index === -1 || !this.rows.length) {
        return '—'
      }

      const value = this.rows[0][index]
      if (value === undefined || String(value).trim() === '') {
        return '—'
      }

      const text = String(value).trim()
      return text.length > 40 ? text.slice(0, 40) + '…' : text
    },
    startImport () {
      const records = applyMapping(this.rows, this.mapping)

      const batches = []
      for (let i = 0; i < records.length; i += BATCH_SIZE) {
        batches.push(records.slice(i, i + BATCH_SIZE))
      }

      this.importing = true
      this.processed = 0
      this.result = { imported: 0, duplicates: 0, invalid: 0, failed: 0, issues: [] }

      this.sendBatches(batches, 0)
    },
    sendBatches (batches, index) {
      if (index >= batches.length) {
        this.importing = false
        this.step = 2
        this.$emit('imported', this.result)
        return
      }

      const batch = batches[index]
      // Offset so reported row numbers line up with the original file.
      const offset = index * BATCH_SIZE

      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'import_applicants',
        nonce: window.eventSpeechOrganizerAdmin.nonce,
        rows: JSON.stringify(batch)
      })
        .then(response => {
          if (!response || response.status === false) {
            this.failImport((response && response.message) || 'Import failed.')
            return
          }

          this.result.imported += response.imported || 0
          this.result.duplicates += response.duplicates || 0
          this.result.invalid += response.invalid || 0
          this.result.failed += response.failed || 0

          if (response.issues && response.issues.length) {
            response.issues.forEach(issue => {
              if (this.result.issues.length < 50) {
                this.result.issues.push({
                  row: issue.row + offset,
                  reason: issue.reason
                })
              }
            })
          }

          this.processed = Math.min(this.processed + batch.length, this.rows.length)
          this.sendBatches(batches, index + 1)
        })
        .fail(xhr => {
          const message =
            xhr && xhr.responseJSON && xhr.responseJSON.message
              ? xhr.responseJSON.message
              : 'Import request failed.'
          this.failImport(message)
        })
    },
    failImport (message) {
      this.importing = false
      this.$message.error(message)

      // Anything already inserted is real, so surface the partial result.
      if (this.result.imported) {
        this.step = 2
        this.$emit('imported', this.result)
      }
    }
  }
}
</script>
