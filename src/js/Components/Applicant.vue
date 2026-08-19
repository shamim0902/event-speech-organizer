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

    <div class="eso-card" v-if="applicant">
      <div class="eso-card__header">
        <img
          class="eso-avatar"
          :src="gravatar(applicant.email, '96')"
          :alt="applicant.name"
        />
        <div class="eso-card__header-main">
          <div class="eso-card__title">{{ applicant.name }}</div>
          <div class="eso-card__meta">
            #{{ applicant.id }} · Applied {{ applicant.date || '—' }}
          </div>
        </div>
        <status-badge :status="applicant.status" />
      </div>

      <div class="eso-card__body">
        <h3 v-if="applicant.topic">{{ applicant.topic }}</h3>

        <ul class="eso-meta-list eso-card__contact">
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
            <a target="_blank" rel="noopener" :href="wpProfile(applicant.username)">{{
              applicant.username
            }}</a>
          </li>
        </ul>

        <dl class="eso-detail-list" style="margin-top: 16px">
          <template v-if="applicant.comment">
            <dt>Bio</dt>
            <dd>{{ applicant.comment }}</dd>
          </template>
          <template v-if="applicant.description">
            <dt>Description</dt>
            <dd>{{ applicant.description }}</dd>
          </template>
          <template v-if="applicant.type">
            <dt>Type</dt>
            <dd>{{ applicant.type }}</dd>
          </template>
          <template v-if="applicant.cospeakers">
            <dt>Co-speakers</dt>
            <dd>{{ applicant.cospeakers }}</dd>
          </template>
          <template v-if="applicant.audience">
            <dt>Audience</dt>
            <dd>{{ applicant.audience }}</dd>
          </template>
          <template v-if="applicant.experience">
            <dt>Experience</dt>
            <dd>{{ applicant.experience }}</dd>
          </template>
        </dl>
      </div>

      <div class="eso-card__footer">
        <el-button-group>
          <el-button
            plain
            type="success"
            size="mini"
            :disabled="applicant.status === 'approved'"
            @click="updateStatus('approved')"
          >
            Approve
          </el-button>
          <el-button
            plain
            type="info"
            size="mini"
            :disabled="applicant.status === 'waiting'"
            @click="updateStatus('waiting')"
          >
            Waitlist
          </el-button>
          <el-button
            plain
            type="danger"
            size="mini"
            :disabled="applicant.status === 'rejected'"
            @click="updateStatus('rejected')"
          >
            Reject
          </el-button>
        </el-button-group>

        <button class="eso-link-btn" type="button" @click="editModal = true">
          <i class="el-icon-edit"></i> Edit
        </button>
      </div>
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
      editModal: false
    }
  },
  computed: {
    eventId () {
      return this.$route.params.id
    },
    applicantId () {
      return this.$route.params.applicantId
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
    updateStatus (status) {
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
