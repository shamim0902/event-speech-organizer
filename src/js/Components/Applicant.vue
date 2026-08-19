<template>
  <div v-loading="loading">
    <div class="eso-page-header__breadcrumb" style="margin-bottom: 12px">
      <i class="el-icon-back"></i>
      <router-link :to="{ name: 'applicants', params: { id: eventId } }"
        >All applicants</router-link
      >
    </div>

    <empty-state
      v-if="!loading && !applicant"
      icon="el-icon-user"
      title="Applicant not found"
      hint="It may have been deleted, or it belongs to a different event."
    >
      <el-button
        size="small"
        @click="$router.push({ name: 'applicants', params: { id: eventId } })"
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
            <status-badge :status="applicant.status" />
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

            <div class="eso-applicant__manage">
              <el-button
                size="small"
                icon="el-icon-edit"
                @click="editModal = true"
                >Edit</el-button
              >
              <el-button
                size="small"
                type="danger"
                plain
                icon="el-icon-delete"
                @click="confirmRemove"
                >Delete</el-button
              >
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
import StatusBadge from './Common/StatusBadge.vue'
import ApplicantFormDialog from './ApplicantFormDialog.vue'
import { gravatarUrl, wpProfileUrl } from './Common/applicantHelpers'

export default {
  name: 'Applicant',
  components: {
    EmptyState,
    StatusBadge,
    ApplicantFormDialog
  },
  data () {
    return {
      applicant: null,
      loading: false,
      editModal: false,
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
    fetch () {
      this.loading = true

      // There is no single-applicant ajax route; the event's list is small
      // enough to fetch and pick from, and it stays scoped to this event.
      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_data',
        event_id: this.eventId
      })
        .then(response => {
          const applicants = (response && response.data) || []
          this.applicant =
            applicants.find(item => String(item.id) === String(this.applicantId)) || null
        })
        .always(() => {
          this.loading = false
        })
    },
    confirmRemove () {
      this.$confirm(
        'Delete "' + this.applicant.name + '"? This cannot be undone.',
        'Delete applicant',
        {
          confirmButtonText: 'Delete',
          cancelButtonText: 'Cancel',
          type: 'warning'
        }
      )
        .then(() => this.remove())
        .catch(() => {})
    },
    remove () {
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
      this.fetch()
    }
  },
  mounted () {
    this.fetch()
  }
}
</script>
