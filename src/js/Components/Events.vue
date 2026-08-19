<template>
  <div>
    <page-header
      title="Events"
      subtitle="Choose an event to review its speaker applications and schedule."
    >
      <template slot="actions">
        <el-button type="primary" icon="el-icon-plus" size="small" @click="create"
          >Add event</el-button
        >
      </template>
    </page-header>

    <div v-loading="loading" v-if="!redirecting">
      <empty-state
        v-if="!events.length"
        icon="el-icon-date"
        title="No events yet"
        hint="Create an event to start collecting speaker applications."
      >
        <el-button type="primary" size="small" icon="el-icon-plus" @click="create"
          >Add event</el-button
        >
      </empty-state>

      <ul class="eso-card-grid" v-else>
        <li v-for="event in events" :key="event.id">
          <div class="eso-card">
            <router-link :to="{ name: 'applicants', params: { id: event.id } }">
              <div class="eso-card__header">
                <div class="eso-card__header-main">
                  <div class="eso-card__title">{{ event.title }}</div>
                  <div class="eso-card__meta">
                    {{ event.event_date || 'No date set'
                    }}<template v-if="event.location"> · {{ event.location }}</template>
                  </div>
                </div>
                <span
                  class="eso-badge"
                  :class="event.status === 'archived' ? 'eso-badge--break' : 'eso-badge--approved'"
                  >{{ event.status }}</span
                >
              </div>
            </router-link>

            <div class="eso-card__body">
              <div class="eso-stats">
                <div class="eso-stat">
                  <div class="eso-stat__value">{{ event.applicants }}</div>
                  <div class="eso-stat__label">Applicants</div>
                </div>
                <div class="eso-stat">
                  <div class="eso-stat__value">{{ event.approved }}</div>
                  <div class="eso-stat__label">Approved</div>
                </div>
                <div class="eso-stat">
                  <div class="eso-stat__value">{{ event.slots }}</div>
                  <div class="eso-stat__label">Slots</div>
                </div>
              </div>
            </div>

            <div class="eso-card__footer">
              <router-link
                class="eso-link-btn"
                :to="{ name: 'applicants', params: { id: event.id } }"
                >Open <i class="el-icon-arrow-right"></i
              ></router-link>

              <span class="eso-link-actions">
                <router-link
                  class="eso-link-btn"
                  :to="{ name: 'slots', params: { id: event.id } }"
                >
                  <i class="el-icon-time"></i> Slots
                </router-link>
                <button class="eso-link-btn" type="button" @click="edit(event)">
                  <i class="el-icon-edit"></i> Edit
                </button>
                <button
                  class="eso-link-btn eso-link-btn--danger"
                  type="button"
                  @click="confirmRemove(event)"
                >
                  <i class="el-icon-delete"></i> Delete
                </button>
              </span>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <el-dialog
      :title="dialogTitle"
      :visible.sync="dialogVisible"
      width="560px"
      append-to-body
    >
      <el-form :model="form" label-position="top">
        <el-form-item label="Event title">
          <el-input
            v-model="form.title"
            placeholder="e.g. WordCamp Dhaka 2026"
          ></el-input>
        </el-form-item>

        <el-row :gutter="16">
          <el-col :xs="24" :sm="12">
            <el-form-item label="Date">
              <el-input
                v-model="form.event_date"
                placeholder="e.g. 2026-11-14"
              ></el-input>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="Location">
              <el-input v-model="form.location" placeholder="City or venue"></el-input>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="Description">
          <el-input
            type="textarea"
            :rows="3"
            v-model="form.description"
            placeholder="What is this event about?"
          ></el-input>
        </el-form-item>

        <el-form-item label="Status">
          <el-select v-model="form.status" class="eso-full-width">
            <el-option label="Active" value="active"></el-option>
            <el-option label="Archived" value="archived"></el-option>
          </el-select>
        </el-form-item>
      </el-form>

      <span slot="footer">
        <el-button size="small" @click="dialogVisible = false">Cancel</el-button>
        <el-button size="small" type="primary" :loading="saving" @click="save"
          >Save event</el-button
        >
      </span>
    </el-dialog>
  </div>
</template>

<script>
import PageHeader from './Common/PageHeader.vue'
import EmptyState from './Common/EmptyState.vue'

const emptyForm = () => ({
  title: '',
  description: '',
  location: '',
  event_date: '',
  status: 'active'
})

export default {
  name: 'Events',
  components: {
    PageHeader,
    EmptyState
  },
  data () {
    return {
      events: [],
      loading: false,
      redirecting: false,
      saving: false,
      dialogVisible: false,
      form: emptyForm()
    }
  },
  computed: {
    dialogTitle () {
      return this.form.id ? 'Edit event' : 'Add event'
    }
  },
  methods: {
    fetch () {
      this.loading = true

      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_events'
      })
        .then(response => {
          const events = (response && response.data) || []

          if (this.shouldAutoOpen(events)) {
            this.redirecting = true
            this.$router
              .replace({ name: 'applicants', params: { id: events[0].id } })
              .catch(() => {})
            return
          }

          this.events = events
        })
        .always(() => {
          this.loading = false
        })
    },
    /**
     * With a single event this list is just an extra click, so go straight in.
     *
     * `?all=1` is the escape hatch used by the dashboard's "All events" link:
     * without it a one-event install could never reach this screen to add a
     * second event.
     */
    shouldAutoOpen (events) {
      return events.length === 1 && !this.$route.query.all
    },
    create () {
      this.form = emptyForm()
      this.dialogVisible = true
    },
    edit (event) {
      this.form = Object.assign(emptyForm(), event)
      this.dialogVisible = true
    },
    save () {
      if (!this.form.title.trim()) {
        this.$message.error('An event title is required.')
        return
      }

      this.saving = true

      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'save_event',
        nonce: window.eventSpeechOrganizerAdmin.nonce,
        data: this.form
      })
        .then(response => {
          if (!response || response.status === false) {
            this.$message.error((response && response.message) || 'Could not save the event.')
            return
          }

          this.dialogVisible = false
          this.$message.success(this.form.id ? 'Event updated.' : 'Event created.')
          this.fetch()
        })
        .fail(xhr => {
          const message =
            xhr && xhr.responseJSON && xhr.responseJSON.message
              ? xhr.responseJSON.message
              : 'Could not save the event.'
          this.$message.error(message)
        })
        .always(() => {
          this.saving = false
        })
    },
    confirmRemove (event) {
      const hasContent = event.applicants > 0 || event.slots > 0

      const message = hasContent
        ? 'Delete "' + event.title + '"? Its ' + event.applicants +
          ' applicant(s) and ' + event.slots + ' slot(s) will be deleted too.'
        : 'Delete "' + event.title + '"? This cannot be undone.'

      this.$confirm(message, 'Delete event', {
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        type: 'warning'
      })
        .then(() => this.remove(event))
        .catch(() => {})
    },
    remove (event) {
      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'delete_event',
        nonce: window.eventSpeechOrganizerAdmin.nonce,
        id: event.id,
        with_content: true
      }).then(() => {
        this.$message.success('Event deleted.')
        this.fetch()
      })
    }
  },
  watch: {
    // Only the query changes between /#/ and /#/?all=1, so vue-router reuses
    // this component and mounted() will not fire again.
    '$route.query.all' () {
      this.redirecting = false
      this.fetch()
    }
  },
  mounted () {
    this.fetch()
  }
}
</script>
