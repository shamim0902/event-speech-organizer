<template>
  <div>
    <div v-loading="loading">
      <empty-state
        v-if="!visibleSpeakers.length"
        icon="el-icon-user"
        :title="emptyTitle"
        :hint="emptyHint"
      >
        <el-button
          v-if="!listState.search"
          type="primary"
          size="small"
          icon="el-icon-plus"
          @click="add"
          >Add applicant</el-button
        >
        <el-button v-else size="small" @click="listState.search = ''"
          >Clear search</el-button
        >
      </empty-state>

      <el-table
        v-else
        class="eso-table eso-table--clickable"
        :data="visibleSpeakers"
        row-key="id"
        @row-click="openApplicant"
      >
        <el-table-column type="expand">
          <template slot-scope="scope">
            <div class="eso-table__expand">
              <ul class="eso-meta-list">
                <li class="eso-meta-list__item" v-if="scope.row.phone">
                  <i class="el-icon-phone-outline"></i>
                  <a :href="'tel:' + scope.row.phone">{{ scope.row.phone }}</a>
                </li>
                <li class="eso-meta-list__item" v-if="scope.row.social">
                  <i class="el-icon-share"></i>
                  <a target="_blank" rel="noopener" :href="scope.row.social">{{
                    scope.row.social
                  }}</a>
                </li>
                <li class="eso-meta-list__item" v-if="scope.row.username">
                  <i class="el-icon-link"></i>
                  <a
                    target="_blank"
                    rel="noopener"
                    :href="wpProfile(scope.row.username)"
                    >{{ scope.row.username }}</a
                  >
                </li>
              </ul>

              <dl class="eso-detail-list">
                <template v-if="scope.row.comment">
                  <dt>Bio</dt>
                  <dd>{{ scope.row.comment }}</dd>
                </template>
                <template v-if="scope.row.description">
                  <dt>Description</dt>
                  <dd>{{ scope.row.description }}</dd>
                </template>
                <template v-if="scope.row.cospeakers">
                  <dt>Co-speakers</dt>
                  <dd>{{ scope.row.cospeakers }}</dd>
                </template>
                <template v-if="scope.row.audience">
                  <dt>Audience</dt>
                  <dd>{{ scope.row.audience }}</dd>
                </template>
                <template v-if="scope.row.experience">
                  <dt>Experience</dt>
                  <dd>{{ scope.row.experience }}</dd>
                </template>
              </dl>

              <router-link
                class="eso-link-btn"
                :to="{ name: 'applicant', params: { id: eventId, applicantId: scope.row.id } }"
              >
                Open applicant page <i class="el-icon-arrow-right"></i>
              </router-link>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="Applicant" min-width="220">
          <template slot-scope="scope">
            <div class="eso-table__applicant">
              <img
                class="eso-avatar"
                :src="get_gravatar_image_url(scope.row.email, '96')"
                :alt="scope.row.name"
              />
              <div>
                <div class="eso-table__name">{{ scope.row.name }}</div>
                <a class="eso-table__email" :href="'mailto:' + scope.row.email">{{
                  scope.row.email
                }}</a>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="Topic" min-width="220">
          <template slot-scope="scope">
            <div class="eso-table__name" v-if="scope.row.topic">
              {{ scope.row.topic }}
            </div>
            <span v-else>—</span>
            <div class="eso-table__meta" v-if="scope.row.type">
              {{ scope.row.type }}
            </div>
          </template>
        </el-table-column>

        <el-table-column label="Applied" width="160">
          <template slot-scope="scope">
            <div class="eso-table__meta">
              #{{ scope.row.id }} · {{ scope.row.date || '—' }}
            </div>
          </template>
        </el-table-column>

        <el-table-column label="Status" width="120">
          <template slot-scope="scope">
            <div class="eso-status-stack">
              <status-badge :status="scope.row.status" />
              <span
                v-if="needsSlot(scope.row)"
                class="eso-badge eso-badge--needs-slot"
                title="Approved but not assigned to any slot yet"
                >Slot required</span
              >
            </div>
          </template>
        </el-table-column>

        <el-table-column label="Actions" width="280" align="right">
          <template slot-scope="scope">
            <el-button-group>
              <el-button
                plain
                type="success"
                size="mini"
                :disabled="scope.row.status === 'approved'"
                @click="updateStatus(scope.row, 'approved')"
              >
                Approve
              </el-button>
              <el-button
                plain
                type="info"
                size="mini"
                :disabled="scope.row.status === 'waiting'"
                @click="updateStatus(scope.row, 'waiting')"
              >
                Waitlist
              </el-button>
              <el-button
                plain
                type="danger"
                size="mini"
                :disabled="scope.row.status === 'rejected'"
                @click="updateStatus(scope.row, 'rejected')"
              >
                Reject
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <import-dialog
      :visible="importModal"
      :event-id="eventId"
      @close="importModal = false"
      @imported="onImported"
    />

    <!-- add applicant -->
    <applicant-form-dialog
      :visible.sync="speakerModal"
      :event-id="eventId"
      @saved="refresh"
    />
  </div>
</template>

<script>
import EmptyState from './Common/EmptyState.vue'
import StatusBadge from './Common/StatusBadge.vue'
import ImportDialog from './ImportDialog.vue'
import ApplicantFormDialog from './ApplicantFormDialog.vue'
import { IMPORT_FIELDS, toCsv } from './Common/csv'
import { gravatarUrl, wpProfileUrl, loadAssignedSpeakerIds } from './Common/applicantHelpers'
import listState from './Common/listState'

const STATUS_ORDER = { approved: 0, waiting: 1, rejected: 2 }

export default {
  name: 'Speaker',
  components: {
    EmptyState,
    StatusBadge,
    ImportDialog,
    ApplicantFormDialog
  },
  data () {
    return {
      speakerModal: false,
      importModal: false,
      listState,
      assignedSpeakerIds: []
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
    visibleSpeakers () {
      const query = this.listState.search.trim().toLowerCase()

      let list = this.eventSpeechOrganizer.filter(speaker => {
        if (!query) {
          return true
        }
        return ['name', 'email', 'topic'].some(field => {
          return (speaker[field] || '').toLowerCase().indexOf(query) > -1
        })
      })

      list = list.slice()

      if (this.listState.sortBy === 'name') {
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
    emptyTitle () {
      return this.listState.search ? 'No matching applicants' : 'No applicants in this list'
    },
    emptyHint () {
      return this.listState.search
        ? 'Try a different name, email address or topic.'
        : 'Applicants you add or import will appear here.'
    }
  },
  methods: {
    add () {
      this.speakerModal = true
    },
    fetchSlots () {
      loadAssignedSpeakerIds(this.$get, this.eventId).then(ids => {
        this.assignedSpeakerIds = ids
      })
    },
    needsSlot (speaker) {
      return (
        speaker.status === 'approved' &&
        this.assignedSpeakerIds.indexOf(String(speaker.id)) === -1
      )
    },
    /**
     * Import / Download / Add moved into the event page's 3-dot menu, which
     * lives in Dashboard — its choices arrive over the event bus.
     */
    onListAction (action) {
      if (action === 'add') {
        this.add()
      } else if (action === 'import') {
        this.importModal = true
      } else if (action === 'download') {
        if (!this.visibleSpeakers.length) {
          this.$message.info('No applicants to export in this list.')
          return
        }
        this.downloadCSV()
      }
    },
    /**
     * Whole-row click opens the applicant's page. Links and buttons inside
     * the row (mailto, status actions) keep their own behaviour, and the
     * expand column only toggles the detail row.
     */
    openApplicant (row, column, event) {
      if (column && column.type === 'expand') {
        return
      }

      if (
        event &&
        event.target &&
        event.target.closest('a, button, .el-table__expand-icon')
      ) {
        return
      }

      this.$router.push({
        name: 'applicant',
        params: { id: this.eventId, applicantId: row.id }
      })
    },
    refresh () {
      this.$emit('fetch')
      window.eventSpeechOrganizerBus.$emit('applicants-updated')
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
    get_gravatar_image_url: gravatarUrl,
    wpProfile: wpProfileUrl
  },
  watch: {
    // vue-router reuses the component when only the event id changes.
    eventId () {
      this.fetchSlots()
    }
  },
  mounted () {
    this.fetchSlots()
    window.eventSpeechOrganizerBus.$on('applicant-list-action', this.onListAction)
  },
  beforeDestroy () {
    window.eventSpeechOrganizerBus.$off('applicant-list-action', this.onListAction)
  }
}
</script>
