<template>
  <el-dialog
    title="Import applicants"
    :visible="visible"
    width="840px"
    append-to-body
    :close-on-click-modal="false"
    @update:visible="close"
  >
    <el-steps :active="step" simple finish-status="success">
      <el-step title="Source" icon="el-icon-share"></el-step>
      <el-step :title="sourceStepTitle" icon="el-icon-upload"></el-step>
      <el-step title="Match fields" icon="el-icon-s-operation"></el-step>
      <el-step title="Result" icon="el-icon-circle-check"></el-step>
    </el-steps>

    <!-- Step 0 — source -->
    <div class="eso-import__step" v-if="step === 0">
      <div class="eso-import__sources">
        <button class="eso-import__source" type="button" @click="chooseSource('csv')">
          <i class="el-icon-document"></i>
          <strong>CSV file</strong>
          <span>Upload a spreadsheet exported from anywhere.</span>
        </button>

        <button
          class="eso-import__source"
          type="button"
          :disabled="!hasFluentForm"
          @click="chooseSource('fluentform')"
        >
          <i class="el-icon-tickets"></i>
          <strong>Fluent Forms</strong>
          <span v-if="hasFluentForm">Import submissions from a form on this site.</span>
          <span v-else>Fluent Forms is not installed on this site.</span>
        </button>
      </div>
    </div>

    <!-- Step 1a — CSV file -->
    <div class="eso-import__step" v-else-if="step === 1 && source === 'csv'">
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
        v-if="error"
        class="eso-import__alert"
        type="error"
        :closable="false"
        :title="error"
      ></el-alert>

      <p class="eso-import__hint">
        The first row must be a header row. Name and email are required; rows
        whose email already exists are skipped.
      </p>
    </div>

    <!-- Step 1b — Fluent Forms form picker -->
    <div class="eso-import__step" v-else-if="step === 1">
      <div v-loading="loadingForms">
        <el-alert
          v-if="error"
          class="eso-import__alert"
          type="error"
          :closable="false"
          :title="error"
        ></el-alert>

        <p class="eso-import__hint" v-if="!forms.length && !loadingForms && !error">
          No Fluent Forms form has any submissions yet.
        </p>

        <ul class="eso-import__forms" v-else>
          <li v-for="form in forms" :key="form.id">
            <button
              class="eso-import__form"
              type="button"
              :class="{ 'is-selected': selectedForm === form.id }"
              @click="selectForm(form)"
            >
              <span class="eso-import__form-title">{{ form.title }}</span>
              <span class="eso-import__form-meta">
                #{{ form.id }} ·
                {{ form.submissions }}
                {{ form.submissions === 1 ? 'submission' : 'submissions' }}
              </span>
              <i class="el-icon-arrow-right"></i>
            </button>
          </li>
        </ul>
      </div>
    </div>

    <!-- Step 2 — mapping -->
    <div class="eso-import__step" v-else-if="step === 2">
      <div class="eso-import__summary">
        <strong>{{ sourceLabel }}</strong>
        <span>{{ total }} {{ total === 1 ? 'record' : 'records' }}</span>
        <span>{{ sourceColumns.length }} source columns</span>
      </div>

      <el-alert
        v-if="missingRequired.length"
        class="eso-import__alert"
        type="warning"
        :closable="false"
        :title="'Required field not matched: ' + missingRequired.join(', ')"
        description="Pick the matching source column below before importing."
      ></el-alert>

      <div class="eso-import__map">
        <div class="eso-import__map-row eso-import__map-row--head">
          <span>Applicant field</span>
          <span>Source column</span>
          <span>First value</span>
        </div>
        <div class="eso-import__map-row" v-for="field in fields" :key="field.key">
          <label>
            {{ field.label
            }}<em v-if="field.required" class="eso-import__required">*</em>
          </label>

          <el-select
            v-model="mapping[field.key]"
            size="small"
            filterable
            placeholder="Ignore"
          >
            <el-option label="— Ignore —" value=""></el-option>
            <el-option
              v-for="column in sourceColumns"
              :key="String(column.value)"
              :label="column.label"
              :value="column.value"
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
            {{ rowWord }} {{ issue.row }} — {{ issue.reason }}
          </li>
        </ul>
        <p class="eso-import__hint" v-if="truncatedIssues">
          Only the first 50 skipped rows are listed.
        </p>
      </div>
    </div>

    <div class="eso-import__progress" v-if="importing">
      <el-progress :percentage="progress" :stroke-width="6"></el-progress>
      <span>Importing… {{ processed }} of {{ total }}</span>
    </div>

    <span slot="footer">
      <template v-if="step === 0">
        <el-button size="small" @click="close">Cancel</el-button>
      </template>

      <template v-else-if="step === 1">
        <el-button size="small" @click="back">Back</el-button>
      </template>

      <template v-else-if="step === 2">
        <el-button size="small" :disabled="importing" @click="back">Back</el-button>
        <el-button
          size="small"
          type="primary"
          :loading="importing"
          :disabled="!canImport"
          @click="startImport"
          >Import {{ total }} {{ total === 1 ? 'applicant' : 'applicants' }}</el-button
        >
      </template>

      <template v-else>
        <el-button size="small" @click="reset">Import something else</el-button>
        <el-button size="small" type="primary" @click="close">Done</el-button>
      </template>
    </span>
  </el-dialog>
</template>

<script>
import {
  IMPORT_FIELDS,
  parseCsv,
  autoMapColumns,
  applyMapping,
  columnsFromHeaders
} from './Common/csv'

// CSV rows post from the browser, so batches stay small enough for
// post_max_size. Fluent Forms rows never leave the server, so its slices can
// be larger — only the mapping and an offset travel over the wire.
const CSV_BATCH = 200
const FLUENTFORM_BATCH = 100

const emptyResult = () => ({
  imported: 0,
  duplicates: 0,
  invalid: 0,
  failed: 0,
  issues: []
})

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
      source: '',
      fields: IMPORT_FIELDS,
      error: '',

      // CSV
      fileName: '',
      rows: [],

      // Fluent Forms
      forms: [],
      loadingForms: false,
      selectedForm: 0,
      selectedFormTitle: '',

      sourceColumns: [],
      mapping: {},
      total: 0,

      importing: false,
      processed: 0,
      result: emptyResult()
    }
  },
  computed: {
    hasFluentForm () {
      return !!window.eventSpeechOrganizerAdmin.has_fluentform
    },
    sourceStepTitle () {
      return this.source === 'fluentform' ? 'Choose form' : 'Choose file'
    },
    sourceLabel () {
      return this.source === 'fluentform' ? this.selectedFormTitle : this.fileName
    },
    rowWord () {
      return this.source === 'fluentform' ? 'Submission' : 'Row'
    },
    missingRequired () {
      return this.fields
        .filter(field => field.required && this.mapping[field.key] === '')
        .map(field => field.label)
    },
    canImport () {
      return this.total > 0 && !this.missingRequired.length
    },
    progress () {
      if (!this.total) {
        return 0
      }
      return Math.min(100, Math.round((this.processed / this.total) * 100))
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
      this.source = ''
      this.error = ''
      this.fileName = ''
      this.rows = []
      this.forms = []
      this.selectedForm = 0
      this.selectedFormTitle = ''
      this.sourceColumns = []
      this.mapping = {}
      this.total = 0
      this.processed = 0
      this.result = emptyResult()
    },
    back () {
      this.error = ''

      if (this.step === 2) {
        this.step = 1
        return
      }

      this.reset()
    },
    chooseSource (source) {
      this.source = source
      this.error = ''
      this.step = 1

      if (source === 'fluentform') {
        this.fetchForms()
      }
    },
    toMappingStep (columns, total) {
      this.sourceColumns = columns
      this.total = total
      this.mapping = autoMapColumns(columns)
      this.step = 2
    },

    // --- CSV -------------------------------------------------------------

    onFileChange (file) {
      this.error = ''

      const raw = file.raw || file
      const reader = new FileReader()

      reader.onload = event => {
        try {
          this.loadCsv(raw.name, event.target.result)
        } catch (err) {
          this.error = 'Could not read that file: ' + err.message
        }
      }
      reader.onerror = () => {
        this.error = 'Could not read that file.'
      }

      reader.readAsText(raw)
    },
    loadCsv (name, text) {
      const parsed = parseCsv(text)

      if (parsed.length < 2) {
        this.error = 'That file needs a header row and at least one data row.'
        return
      }

      this.fileName = name
      this.rows = parsed.slice(1)

      const headers = parsed[0].map(header => String(header).trim())

      this.toMappingStep(columnsFromHeaders(headers, this.rows[0]), this.rows.length)
    },

    // --- Fluent Forms ------------------------------------------------------

    fetchForms () {
      this.loadingForms = true
      this.error = ''

      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'fluentform_forms',
        nonce: window.eventSpeechOrganizerAdmin.nonce
      })
        .then(response => {
          if (!response || response.status === false) {
            this.error = (response && response.message) || 'Could not load forms.'
            return
          }
          this.forms = response.forms || []
        })
        .fail(xhr => {
          this.error = this.errorFrom(xhr, 'Could not load forms.')
        })
        .always(() => {
          this.loadingForms = false
        })
    },
    selectForm (form) {
      this.selectedForm = form.id
      this.selectedFormTitle = form.title
      this.loadingForms = true
      this.error = ''

      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'fluentform_columns',
        nonce: window.eventSpeechOrganizerAdmin.nonce,
        form_id: form.id
      })
        .then(response => {
          if (!response || response.status === false) {
            this.error = (response && response.message) || 'Could not read that form.'
            return
          }
          this.toMappingStep(response.columns || [], response.total || 0)
        })
        .fail(xhr => {
          this.error = this.errorFrom(xhr, 'Could not read that form.')
        })
        .always(() => {
          this.loadingForms = false
        })
    },

    // --- Shared ------------------------------------------------------------

    previewFor (key) {
      const source = this.mapping[key]

      if (source === '' || source === undefined) {
        return '—'
      }

      const column = this.sourceColumns.find(item => item.value === source)

      return column && column.sample ? column.sample : '—'
    },
    startImport () {
      this.importing = true
      this.processed = 0
      this.result = emptyResult()

      if (this.source === 'fluentform') {
        this.importFluentFormSlice(0)
        return
      }

      const records = applyMapping(this.rows, this.mapping)
      const batches = []

      for (let i = 0; i < records.length; i += CSV_BATCH) {
        batches.push(records.slice(i, i + CSV_BATCH))
      }

      this.sendCsvBatches(batches, 0)
    },
    sendCsvBatches (batches, index) {
      if (index >= batches.length) {
        this.finishImport()
        return
      }

      const batch = batches[index]
      const offset = index * CSV_BATCH

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

          this.collect(response, offset)
          this.processed = Math.min(this.processed + batch.length, this.total)
          this.sendCsvBatches(batches, index + 1)
        })
        .fail(xhr => {
          this.failImport(this.errorFrom(xhr, 'Import request failed.'))
        })
    },
    importFluentFormSlice (offset) {
      if (offset >= this.total) {
        this.finishImport()
        return
      }

      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'fluentform_import',
        nonce: window.eventSpeechOrganizerAdmin.nonce,
        form_id: this.selectedForm,
        mapping: JSON.stringify(this.mapping),
        offset: offset,
        limit: FLUENTFORM_BATCH
      })
        .then(response => {
          if (!response || response.status === false) {
            this.failImport((response && response.message) || 'Import failed.')
            return
          }

          this.collect(response, 0)

          const processed = response.processed || 0

          // A short slice means we ran off the end of the submissions.
          if (!processed) {
            this.finishImport()
            return
          }

          this.processed = Math.min(this.processed + processed, this.total)
          this.importFluentFormSlice(offset + processed)
        })
        .fail(xhr => {
          this.failImport(this.errorFrom(xhr, 'Import request failed.'))
        })
    },
    collect (response, rowOffset) {
      this.result.imported += response.imported || 0
      this.result.duplicates += response.duplicates || 0
      this.result.invalid += response.invalid || 0
      this.result.failed += response.failed || 0

      if (response.issues && response.issues.length) {
        response.issues.forEach(issue => {
          if (this.result.issues.length < 50) {
            this.result.issues.push({
              row: issue.row + rowOffset,
              reason: issue.reason
            })
          }
        })
      }
    },
    finishImport () {
      this.importing = false
      this.step = 3
      this.$emit('imported', this.result)
    },
    failImport (message) {
      this.importing = false
      this.$message.error(message)

      // Anything already inserted is real, so surface the partial result.
      if (this.result.imported) {
        this.step = 3
        this.$emit('imported', this.result)
      }
    },
    errorFrom (xhr, fallback) {
      if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
        return xhr.responseJSON.message
      }
      return fallback
    }
  }
}
</script>
