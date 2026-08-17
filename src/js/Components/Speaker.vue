<template>
  <div>
    <div class="eso-toolbar">
      <el-input
        class="eso-toolbar__search"
        v-model="search"
        size="small"
        clearable
        prefix-icon="el-icon-search"
        placeholder="Search name, email or topic"
      ></el-input>

      <el-select
        class="eso-toolbar__sort"
        v-model="sortBy"
        size="small"
        placeholder="Sort by"
      >
        <el-option label="Newest first" value="newest"></el-option>
        <el-option label="Name (A–Z)" value="name"></el-option>
        <el-option label="Status" value="status"></el-option>
      </el-select>

      <span class="eso-toolbar__count">{{ countLabel }}</span>

      <span class="eso-toolbar__spacer"></span>

      <el-button
        type="default"
        icon="el-icon-upload2"
        size="small"
        @click="importModal = true"
        >Import CSV</el-button
      >

      <el-button
        type="default"
        icon="el-icon-download"
        size="small"
        :disabled="!visibleSpeakers.length"
        @click="downloadCSV"
        >Download CSV</el-button
      >

      <el-button type="primary" icon="el-icon-plus" size="small" @click="add"
        >Add applicant</el-button
      >
    </div>

    <div v-loading="loading">
      <empty-state
        v-if="!visibleSpeakers.length"
        icon="el-icon-user"
        :title="emptyTitle"
        :hint="emptyHint"
      >
        <el-button
          v-if="!search"
          type="primary"
          size="small"
          icon="el-icon-plus"
          @click="add"
          >Add applicant</el-button
        >
        <el-button v-else size="small" @click="search = ''"
          >Clear search</el-button
        >
      </empty-state>

      <ul class="eso-card-grid" v-else>
        <li v-for="speaker in visibleSpeakers" :key="speaker.id">
          <div class="eso-card">
            <div class="eso-card__header">
              <img
                class="eso-avatar"
                :src="get_gravatar_image_url(speaker.email, '96')"
                :alt="speaker.name"
              />
              <div class="eso-card__header-main">
                <div class="eso-card__title">{{ speaker.name }}</div>
                <div class="eso-card__meta">
                  #{{ speaker.id }} · Applied {{ speaker.date || '—' }}
                </div>
              </div>
              <status-badge :status="speaker.status" />
            </div>

            <div class="eso-card__body">
              <h3 v-if="speaker.topic">{{ speaker.topic }}</h3>

              <ul class="eso-meta-list eso-card__contact">
                <li class="eso-meta-list__item" v-if="speaker.email">
                  <i class="el-icon-message"></i>
                  <a :href="'mailto:' + speaker.email">{{ speaker.email }}</a>
                </li>
                <li class="eso-meta-list__item" v-if="speaker.phone">
                  <i class="el-icon-phone-outline"></i>
                  <a :href="'tel:' + speaker.phone">{{ speaker.phone }}</a>
                </li>
                <li class="eso-meta-list__item" v-if="speaker.social">
                  <i class="el-icon-share"></i>
                  <a target="_blank" rel="noopener" :href="speaker.social">{{
                    speaker.social
                  }}</a>
                </li>
                <li class="eso-meta-list__item" v-if="speaker.username">
                  <i class="el-icon-link"></i>
                  <a target="_blank" rel="noopener" :href="getWpProfile(speaker)"
                    >{{ speaker.username }}</a
                  >
                </li>
              </ul>

              <el-collapse class="eso-collapse">
                <el-collapse-item title="Bio" name="bio" v-if="speaker.comment">
                  <p>{{ speaker.comment }}</p>
                </el-collapse-item>

                <el-collapse-item title="Talk details" name="talk">
                  <p v-if="speaker.description">{{ speaker.description }}</p>
                  <dl class="eso-detail-list">
                    <template v-if="speaker.type">
                      <dt>Type</dt>
                      <dd>{{ speaker.type }}</dd>
                    </template>
                    <template v-if="speaker.cospeakers">
                      <dt>Co-speakers</dt>
                      <dd>{{ speaker.cospeakers }}</dd>
                    </template>
                    <template v-if="speaker.audience">
                      <dt>Audience</dt>
                      <dd>{{ speaker.audience }}</dd>
                    </template>
                    <template v-if="speaker.experience">
                      <dt>Experience</dt>
                      <dd>{{ speaker.experience }}</dd>
                    </template>
                  </dl>
                </el-collapse-item>
              </el-collapse>
            </div>

            <div class="eso-card__footer">
              <el-button-group>
                <el-button
                  plain
                  type="success"
                  size="mini"
                  :disabled="speaker.status === 'approved'"
                  @click="updateStatus(speaker, 'approved')"
                >
                  Approve
                </el-button>
                <el-button
                  plain
                  type="info"
                  size="mini"
                  :disabled="speaker.status === 'waiting'"
                  @click="updateStatus(speaker, 'waiting')"
                >
                  Waitlist
                </el-button>
                <el-button
                  plain
                  type="danger"
                  size="mini"
                  :disabled="speaker.status === 'rejected'"
                  @click="updateStatus(speaker, 'rejected')"
                >
                  Reject
                </el-button>
              </el-button-group>

              <button
                class="eso-link-btn"
                type="button"
                @click="editApplicant(speaker)"
              >
                <i class="el-icon-edit"></i> Edit
              </button>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <import-dialog
      :visible="importModal"
      :event-id="eventId"
      @close="importModal = false"
      @imported="onImported"
    />

    <!-- add / edit applicant -->
    <el-dialog
      :title="dialogTitle"
      :visible.sync="speakerModal"
      width="760px"
      append-to-body
    >
      <el-form :model="speakerNew" label-width="130px" label-position="top">
        <div class="eso-form-section">
          <p class="eso-form-section__title">Applicant</p>
          <el-row :gutter="16">
            <el-col :xs="24" :sm="12">
              <el-form-item label="Name">
                <el-input v-model="speakerNew.name" placeholder="Full name"></el-input>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Application date">
                <el-input
                  v-model="speakerNew.date"
                  placeholder="e.g. 2024-05-14 10:30"
                ></el-input>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Email">
                <el-input v-model="speakerNew.email" placeholder="name@example.com"></el-input>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Phone">
                <el-input v-model="speakerNew.phone" placeholder="Phone number"></el-input>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="WordPress.org username">
                <el-input
                  v-model="speakerNew.username"
                  placeholder="Username or profile URL"
                ></el-input>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Social handles">
                <el-input
                  v-model="speakerNew.social"
                  placeholder="Profile URL"
                ></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="24">
              <el-form-item label="Bio">
                <el-input
                  type="textarea"
                  :rows="3"
                  v-model="speakerNew.comment"
                  placeholder="Short speaker bio"
                ></el-input>
              </el-form-item>
            </el-col>
          </el-row>
        </div>

        <div class="eso-form-section">
          <p class="eso-form-section__title">Talk</p>
          <el-row :gutter="16">
            <el-col :xs="24" :sm="12">
              <el-form-item label="Topic title">
                <el-input v-model="speakerNew.topic" placeholder="Talk title"></el-input>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Talk type">
                <el-input
                  v-model="speakerNew.type"
                  placeholder="e.g. Keynote, Lightning"
                ></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="24">
              <el-form-item label="Description">
                <el-input
                  type="textarea"
                  :rows="3"
                  v-model="speakerNew.description"
                  placeholder="What is the talk about?"
                ></el-input>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Co-speakers">
                <el-input
                  v-model="speakerNew.cospeakers"
                  placeholder="Names of co-speakers"
                ></el-input>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Audience">
                <el-input
                  v-model="speakerNew.audience"
                  placeholder="Who is this for?"
                ></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="24">
              <el-form-item label="Experience">
                <el-input
                  type="textarea"
                  :rows="2"
                  v-model="speakerNew.experience"
                  placeholder="Previous speaking experience"
                ></el-input>
              </el-form-item>
            </el-col>
          </el-row>
        </div>
      </el-form>

      <span slot="footer" class="dialog-footer">
        <el-button size="small" @click="speakerModal = false">Cancel</el-button>
        <el-button size="small" type="primary" @click="addOrUpdate"
          >Save applicant</el-button
        >
      </span>
    </el-dialog>
  </div>
</template>

<script>
var md5 = require('md5')
import EmptyState from './Common/EmptyState.vue'
import StatusBadge from './Common/StatusBadge.vue'
import ImportDialog from './ImportDialog.vue'
import { IMPORT_FIELDS, toCsv } from './Common/csv'

const STATUS_ORDER = { approved: 0, waiting: 1, rejected: 2 }

export default {
  name: 'Speaker',
  components: {
    EmptyState,
    StatusBadge,
    ImportDialog
  },
  data () {
    return {
      speakerModal: false,
      importModal: false,
      search: '',
      sortBy: 'newest',
      speakerFormMock: {
        date: '',
        name: '',
        email: '',
        phone: '',
        social: '',
        username: '',
        comment: '',
        topic: '',
        description: '',
        type: '',
        cospeakers: '',
        audience: '',
        experience: '',
        status: 'waiting'
      },
      speakerNew: {}
    }
  },
  props: {
    eventSpeechOrganizer: {
      type: Array,
      required: true
    },
    loading: {
      type: Boolean,
      default: false
    }
  },
  computed: {
    eventId () {
      return this.$route.params.id
    },
    dialogTitle () {
      return this.speakerNew.id ? 'Edit applicant' : 'Add applicant'
    },
    visibleSpeakers () {
      const query = this.search.trim().toLowerCase()

      let list = this.eventSpeechOrganizer.filter(speaker => {
        if (!query) {
          return true
        }
        return ['name', 'email', 'topic'].some(field => {
          return (speaker[field] || '').toLowerCase().indexOf(query) > -1
        })
      })

      list = list.slice()

      if (this.sortBy === 'name') {
        list.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
      } else if (this.sortBy === 'status') {
        list.sort((a, b) => {
          const rankA = STATUS_ORDER[a.status] !== undefined ? STATUS_ORDER[a.status] : 3
          const rankB = STATUS_ORDER[b.status] !== undefined ? STATUS_ORDER[b.status] : 3
          return rankA - rankB
        })
      } else {
        list.sort((a, b) => Number(b.id) - Number(a.id))
      }

      return list
    },
    countLabel () {
      const total = this.eventSpeechOrganizer.length
      const shown = this.visibleSpeakers.length

      if (shown === total) {
        return total + (total === 1 ? ' applicant' : ' applicants')
      }
      return shown + ' of ' + total + ' applicants'
    },
    emptyTitle () {
      return this.search ? 'No matching applicants' : 'No applicants in this list'
    },
    emptyHint () {
      return this.search
        ? 'Try a different name, email address or topic.'
        : 'Applicants you add or import will appear here.'
    }
  },
  methods: {
    editApplicant (speaker) {
      this.speakerModal = true
      this.speakerNew = Object.assign({}, this.speakerFormMock, speaker)
    },
    add () {
      this.speakerModal = true
      this.speakerNew = Object.assign({}, this.speakerFormMock)
    },
    refresh () {
      this.$emit('fetch')
      window.eventSpeechOrganizerBus.$emit('applicants-updated')
    },
    addOrUpdate () {
      const isUpdate = !!this.speakerNew.id

      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: isUpdate ? 'edit_applicant' : 'add_applicant',
        event_id: this.eventId,
        data: this.speakerNew
      }).then(() => {
        this.speakerModal = false
        this.$message.success(isUpdate ? 'Applicant updated.' : 'Applicant added.')
        this.refresh()
      })
    },
    onImported (result) {
      if (result.imported) {
        this.$message.success(
          result.imported + ' applicant' + (result.imported === 1 ? '' : 's') + ' imported.'
        )
        this.refresh()
      }
    },
    downloadCSV () {
      // Exports every importable column so an export can be re-imported.
      const csvContent = toCsv(IMPORT_FIELDS, this.visibleSpeakers)

      this.downloadStringAsFile(csvContent, this.$route.name + '.csv')
    },
    downloadStringAsFile (text, filename) {
      const blob = new Blob([text], { type: 'text/csv' })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.setAttribute('href', url)
      link.setAttribute('download', filename)
      link.style.display = 'none'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
    },
    updateStatus (speaker, status) {
      this.$set(speaker, 'status', status)
      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'update_status',
        event_id: this.eventId,
        options: {
          id: speaker.id,
          status: status
        }
      }).then(() => {
        this.$message.success(speaker.name + ' marked as ' + status + '.')
        this.refresh()
      })
    },
    getWpProfile (speaker) {
      const username = speaker.username || ''

      if (username.indexOf('@') > -1) {
        return 'https://profiles.wordpress.org/' + username.split('@')[1]
      } else if (username.indexOf('https://') > -1) {
        return username
      }
      return 'https://profiles.wordpress.org/' + username
    },
    get_gravatar_image_url (
      email,
      size,
      default_image,
      allowed_rating,
      force_default
    ) {
      email = typeof email !== 'undefined' && email ? email : 'john.doe@example.com'
      size = size >= 1 && size <= 2048 ? size : 80
      default_image =
        typeof default_image !== 'undefined' ? default_image : 'mm'
      allowed_rating =
        typeof allowed_rating !== 'undefined' ? allowed_rating : 'g'
      force_default = force_default === true ? 'y' : 'n'
      return (
        'https://secure.gravatar.com/avatar/' +
        md5(email.toLowerCase().trim()) +
        '?size=' +
        size +
        '&default=' +
        encodeURIComponent(default_image) +
        '&rating=' +
        allowed_rating +
        (force_default === 'y' ? '&forcedefault=' + force_default : '')
      )
    }
  }
}
</script>
