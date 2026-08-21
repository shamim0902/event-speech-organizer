<template>
  <div v-loading="loading">
    <div class="eso-applicant__topbar">
      <div class="eso-page-header__breadcrumb">
        <i class="el-icon-back"></i>
        <router-link :to="{ name: section, params: { id: eventId } }">{{
          backLabel
        }}</router-link>
      </div>

      <div class="eso-applicant__nav" v-if="applicant">
        <span class="eso-applicant__position" v-if="siblings.length > 1">
          {{ position }} of {{ siblings.length }}
        </span>
        <button
          class="eso-icon-btn eso-icon-btn--label"
          type="button"
          :disabled="!previous"
          :title="
            previous ? 'Previous: ' + previous.name + ' (←)' : 'No previous applicant'
          "
          @click="go(previous)"
        >
          <i class="el-icon-arrow-left"></i> <span>Prev</span>
        </button>
        <button
          class="eso-icon-btn eso-icon-btn--label"
          type="button"
          :disabled="!next"
          :title="next ? 'Next: ' + next.name + ' (→)' : 'No next applicant'"
          @click="go(next)"
        >
          <span>Next</span> <i class="el-icon-arrow-right"></i>
        </button>
      </div>

      <el-dropdown
        v-if="applicant"
        trigger="click"
        placement="bottom-end"
        @command="onMenuCommand"
      >
        <button class="eso-icon-btn" type="button" title="More actions">
          <i class="el-icon-more"></i>
        </button>
        <el-dropdown-menu slot="dropdown">
          <el-dropdown-item command="edit" icon="el-icon-edit"
            >Edit applicant</el-dropdown-item
          >
          <el-dropdown-item
            command="delete"
            icon="el-icon-delete"
            class="eso-dropdown-item--danger"
            divided
            >Delete applicant</el-dropdown-item
          >
        </el-dropdown-menu>
      </el-dropdown>
    </div>

    <empty-state
      v-if="!loading && !applicant"
      icon="el-icon-user"
      title="Applicant not found"
      hint="It may have been deleted, or it belongs to a different event."
    >
      <el-button
        size="small"
        @click="$router.push({ name: section, params: { id: eventId } })"
        >Back to applicants</el-button
      >
    </empty-state>

    <div class="eso-applicant" v-if="applicant">
      <aside>
        <div class="eso-card">
          <div class="eso-applicant__profile">
            <img
              class="eso-avatar"
              :src="gravatar(applicant.email, '144')"
              :alt="applicant.name"
            />
            <div class="eso-applicant__name">{{ applicant.name }}</div>
            <div class="eso-card__meta">
              #{{ applicant.id }} · Applied {{ applicant.date || '—' }}
            </div>
            <div class="eso-status-stack eso-status-stack--row">
              <status-badge :status="applicant.status" />
              <span
                v-if="needsSlot"
                class="eso-badge eso-badge--needs-slot"
                title="Approved but not assigned to any slot yet"
                >Slot required</span
              >
            </div>
          </div>

          <div class="eso-card__body eso-applicant__contact">
            <p class="eso-section-label">Contact</p>
            <ul class="eso-meta-list">
              <li class="eso-meta-list__item" v-if="applicant.email">
                <i class="el-icon-message"></i>
                <a :href="'mailto:' + applicant.email">{{ applicant.email }}</a>
              </li>
              <li class="eso-meta-list__item" v-if="applicant.phone">
                <i class="el-icon-phone-outline"></i>
                <a :href="'tel:' + applicant.phone">{{ applicant.phone }}</a>
              </li>
              <li class="eso-meta-list__item" v-if="applicant.social">
                <i class="el-icon-share"></i>
                <a target="_blank" rel="noopener" :href="applicant.social">{{
                  applicant.social
                }}</a>
              </li>
              <li class="eso-meta-list__item" v-if="applicant.username">
                <i class="el-icon-link"></i>
                <a
                  target="_blank"
                  rel="noopener"
                  :href="wpProfile(applicant.username)"
                  >{{ applicant.username }}</a
                >
              </li>
              <li class="eso-meta-list__item" v-if="!hasContact">
                <span>No contact details</span>
              </li>
            </ul>
          </div>

          <div class="eso-applicant__actions">
            <p class="eso-section-label">Status</p>

            <div class="eso-status-choices">
              <button
                v-for="choice in statusChoices"
                :key="choice.status"
                type="button"
                class="eso-status-choice"
                :class="[
                  'eso-status-choice--' + choice.status,
                  { 'is-active': applicant.status === choice.status }
                ]"
                @click="updateStatus(choice.status)"
              >
                <i :class="choice.icon"></i>
                <span>{{ choice.label }}</span>
              </button>
            </div>

          </div>
        </div>
      </aside>

      <main class="eso-applicant__main">
        <div class="eso-card">
          <div class="eso-card__header">
            <div class="eso-card__header-main">
              <div class="eso-card__title">
                {{ applicant.topic || 'Untitled talk' }}
              </div>
            </div>
            <span class="eso-badge eso-badge--count" v-if="applicant.type">{{
              applicant.type
            }}</span>
          </div>

          <div class="eso-card__body">
            <div class="eso-section" v-if="applicant.description">
              <p class="eso-section-label">Description</p>
              <p class="eso-prose">{{ applicant.description }}</p>
            </div>

            <div class="eso-section" v-if="applicant.cospeakers">
              <p class="eso-section-label">Co-speakers</p>
              <p class="eso-prose">{{ applicant.cospeakers }}</p>
            </div>

            <div class="eso-section" v-if="applicant.audience">
              <p class="eso-section-label">Intended audience</p>
              <p class="eso-prose">{{ applicant.audience }}</p>
            </div>

            <div class="eso-section" v-if="applicant.experience">
              <p class="eso-section-label">Speaking experience</p>
              <p class="eso-prose">{{ applicant.experience }}</p>
            </div>

            <div class="eso-section" v-if="applicant.question">
              <p class="eso-section-label">Question</p>
              <p class="eso-prose">{{ applicant.question }}</p>
            </div>

            <p class="eso-prose" v-if="!hasTalkDetails">
              No talk details were provided.
            </p>
          </div>
        </div>

        <div class="eso-card" v-if="applicant.comment">
          <div class="eso-card__header">
            <div class="eso-card__header-main">
              <div class="eso-card__title">Speaker bio</div>
            </div>
          </div>
          <div class="eso-card__body">
            <p class="eso-prose">{{ applicant.comment }}</p>
          </div>
        </div>
      </main>
    </div>

    <confirm-delete-dialog
      v-if="applicant"
      :visible.sync="deleteVisible"
      title="Delete applicant"
      :message="'Delete \'' + applicant.name + '\'?'"
      hint="Their application details will be removed from this event. This cannot be undone."
      :require-typing="false"
      :loading="deleting"
      @confirm="remove"
    />

    <applicant-form-dialog
      v-if="applicant"
      :visible.sync="editModal"
      :event-id="eventId"
      :applicant="applicant"
      @saved="fetch"
    />
  </div>
</template>

<script>
import EmptyState from './Common/EmptyState.vue'
import ConfirmDeleteDialog from './Common/ConfirmDeleteDialog.vue'
import StatusBadge from './Common/StatusBadge.vue'
import ApplicantFormDialog from './ApplicantFormDialog.vue'
import { gravatarUrl, wpProfileUrl, loadAssignedSpeakerIds } from './Common/applicantHelpers'
import { filterAndSort, statusesForSection } from './Common/applicantList'
import listState from './Common/listState'

export default {
  name: 'Applicant',
  components: {
    EmptyState,
    ConfirmDeleteDialog,
    StatusBadge,
    ApplicantFormDialog
  },
  data () {
    return {
      applicant: null,
      applicants: [],
      listState,
      loading: false,
      editModal: false,
      deleteVisible: false,
      deleting: false,
      assignedSpeakerIds: [],
      statusChoices: [
        { status: 'approved', label: 'Approve', icon: 'el-icon-circle-check' },
        { status: 'waiting', label: 'Waitlist', icon: 'el-icon-time' },
        { status: 'rejected', label: 'Reject', icon: 'el-icon-circle-close' }
      ]
    }
  },
  computed: {
    eventId () {
      return this.$route.params.id
    },
    applicantId () {
      return this.$route.params.applicantId
    },
    /**
     * The list route the user arrived from — prev/next walks that same list,
     * in the same order, so the arrows match what the table showed.
     */
    section () {
      const from = this.$route.query.from
      return from && from !== 'applicant' ? from : 'applicants'
    },
    backLabel () {
      const labels = {
        selected: 'Approved applicants',
        waiting: 'Waitlisted applicants',
        rejected: 'Rejected applicants'
      }
      return labels[this.section] || 'All applicants'
    },
    siblings () {
      return filterAndSort(
        this.applicants,
        this.listState,
        statusesForSection(this.section)
      )
    },
    currentIndex () {
      return this.siblings.findIndex(
        item => String(item.id) === String(this.applicantId)
      )
    },
    position () {
      return this.currentIndex + 1
    },
    previous () {
      return this.currentIndex > 0 ? this.siblings[this.currentIndex - 1] : null
    },
    next () {
      return this.currentIndex > -1 && this.currentIndex < this.siblings.length - 1
        ? this.siblings[this.currentIndex + 1]
        : null
    },
    needsSlot () {
      return (
        this.applicant &&
        this.applicant.status === 'approved' &&
        this.assignedSpeakerIds.indexOf(String(this.applicant.id)) === -1
      )
    },
    hasContact () {
      const applicant = this.applicant || {}
      return !!(applicant.email || applicant.phone || applicant.social || applicant.username)
    },
    hasTalkDetails () {
      const applicant = this.applicant || {}
      return !!(
        applicant.description ||
        applicant.cospeakers ||
        applicant.audience ||
        applicant.experience ||
        applicant.question
      )
    }
  },
  methods: {
    gravatar: gravatarUrl,
    wpProfile: wpProfileUrl,
    fetchSlots () {
      loadAssignedSpeakerIds(this.$get, this.eventId).then(ids => {
        this.assignedSpeakerIds = ids
      })
    },
    fetch () {
      this.loading = true
      this.fetchSlots()

      // There is no single-applicant ajax route; the event's list is small
      // enough to fetch and pick from, and it stays scoped to this event.
      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_data',
        event_id: this.eventId
      })
        .then(response => {
          this.applicants = (response && response.data) || []
          this.applicant =
            this.applicants.find(
              item => String(item.id) === String(this.applicantId)
            ) || null
        })
        .always(() => {
          this.loading = false
        })
    },
    /**
     * Left/right arrows (or j/k) page between applicants. Ignored while the
     * user is typing, holding a modifier, or working inside a dialog — the
     * edit form and the delete confirm both need their own key handling.
     */
    onKeydown (event) {
      if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) {
        return
      }

      const target = event.target

      if (
        target &&
        (target.isContentEditable ||
          /^(input|textarea|select)$/i.test(target.tagName || '') ||
          (target.closest && target.closest('.el-dialog, .el-select-dropdown')))
      ) {
        return
      }

      if (this.editModal || this.deleteVisible) {
        return
      }

      let applicant = null

      if (event.key === 'ArrowLeft' || event.key === 'k') {
        applicant = this.previous
      } else if (event.key === 'ArrowRight' || event.key === 'j') {
        applicant = this.next
      } else {
        return
      }

      event.preventDefault()
      this.go(applicant)
    },
    go (applicant) {
      if (!applicant) {
        return
      }

      this.$router.push({
        name: 'applicant',
        params: { id: this.eventId, applicantId: applicant.id },
        query: { from: this.section }
      })
    },
    onMenuCommand (command) {
      if (command === 'edit') {
        this.editModal = true
      } else if (command === 'delete') {
        this.deleteVisible = true
      }
    },
    remove () {
      this.deleting = true

      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'delete_applicant',
        nonce: window.eventSpeechOrganizerAdmin.nonce,
        event_id: this.eventId,
        id: this.applicant.id
      })
        .then(response => {
          if (!response || !response.status) {
            this.$message.error(
              (response && response.message) || 'Could not delete the applicant.'
            )
            return
          }

          this.$message.success('Applicant deleted.')
          window.eventSpeechOrganizerBus.$emit('applicants-updated')
          this.$router.replace({ name: 'applicants', params: { id: this.eventId } })
        })
        .fail(xhr => {
          const message =
            xhr && xhr.responseJSON && xhr.responseJSON.message
              ? xhr.responseJSON.message
              : 'Could not delete the applicant.'
          this.$message.error(message)
        })
        .always(() => {
          this.deleting = false
        })
    },
    updateStatus (status) {
      if (this.applicant.status === status) {
        return
      }

      this.$set(this.applicant, 'status', status)

      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'update_status',
        event_id: this.eventId,
        options: {
          id: this.applicant.id,
          status: status
        }
      }).then(() => {
        this.$message.success(this.applicant.name + ' marked as ' + status + '.')
        window.eventSpeechOrganizerBus.$emit('applicants-updated')
      })
    }
  },
  watch: {
    // vue-router reuses this component when only the params change.
    applicantId () {
      // Paging with the arrows already has the list in hand — swap locally so
      // it feels instant, and only hit the server when we don't.
      const known = this.applicants.find(
        item => String(item.id) === String(this.applicantId)
      )

      if (known) {
        this.applicant = known
        this.fetchSlots()
        return
      }

      this.fetch()
    }
  },
  mounted () {
    this.fetch()
    document.addEventListener('keydown', this.onKeydown)
  },
  beforeDestroy () {
    document.removeEventListener('keydown', this.onKeydown)
  }
}
</script>
