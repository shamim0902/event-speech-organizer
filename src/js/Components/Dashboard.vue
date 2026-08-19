<template>
  <div>
    <page-header
      :title="eventTitle"
      :subtitle="subtitle"
      :back-to="{ name: 'events', query: { all: '1' } }"
      back-label="All events"
    >
      <template slot="actions" v-if="!isSingleApplicant">
        <el-dropdown trigger="click" @command="onMenuCommand">
          <el-button size="small" icon="el-icon-more" circle></el-button>
          <el-dropdown-menu slot="dropdown">
            <template v-if="isApplicantList">
              <el-dropdown-item command="add" icon="el-icon-plus"
                >Add applicant</el-dropdown-item
              >
              <el-dropdown-item command="import" icon="el-icon-upload2"
                >Import CSV</el-dropdown-item
              >
              <el-dropdown-item command="download" icon="el-icon-download"
                >Download CSV</el-dropdown-item
              >
            </template>
            <el-dropdown-item
              command="mapper"
              icon="el-icon-set-up"
              :divided="isApplicantList"
              >Webhook field mapper</el-dropdown-item
            >
          </el-dropdown-menu>
        </el-dropdown>
      </template>
    </page-header>

    <filter-nav :counts="counts" />

    <router-view></router-view>

    <webhook-mapper-dialog :visible.sync="mapperVisible" :event-id="eventId" />
  </div>
</template>

<script>
import FilterNav from './FilterNav.vue'
import PageHeader from './Common/PageHeader.vue'
import WebhookMapperDialog from './WebhookMapperDialog.vue'
import listState from './Common/listState'

export default {
  name: 'Dashboard',
  components: {
    FilterNav,
    PageHeader,
    WebhookMapperDialog
  },
  data () {
    return {
      event: null,
      mapperVisible: false,
      counts: {
        total: 0,
        approved: 0,
        waiting: 0,
        rejected: 0
      }
    }
  },
  computed: {
    eventId () {
      return this.$route.params.id
    },
    isApplicantList () {
      return ['applicants', 'selected', 'waiting', 'rejected'].indexOf(this.$route.name) > -1
    },
    // The single applicant page has its own actions; the event-level menu
    // would only distract there.
    isSingleApplicant () {
      return this.$route.name === 'applicant'
    },
    eventTitle () {
      return this.event ? this.event.title : 'Loading…'
    },
    subtitle () {
      if (!this.event) {
        return ''
      }

      const parts = []
      if (this.event.event_date) {
        parts.push(this.event.event_date)
      }
      if (this.event.location) {
        parts.push(this.event.location)
      }

      return parts.length
        ? parts.join(' · ')
        : 'Review speaker applications and build the schedule.'
    }
  },
  methods: {
    onMenuCommand (command) {
      if (command === 'mapper') {
        this.mapperVisible = true
        return
      }

      // add / import / download — handled by the applicant list component.
      window.eventSpeechOrganizerBus.$emit('applicant-list-action', command)
    },
    fetchEvent () {
      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_events'
      }).then(response => {
        const events = (response && response.data) || []
        this.event = events.find(item => String(item.id) === String(this.eventId)) || null
      })
    },
    fetchCounts () {
      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_data',
        event_id: this.eventId
      }).then(response => {
        const applicants = (response && response.data) || []
        const counts = {
          total: applicants.length,
          approved: 0,
          waiting: 0,
          rejected: 0
        }

        applicants.forEach(applicant => {
          const status = (applicant.status || '').toLowerCase()
          if (counts[status] !== undefined && status !== 'total') {
            counts[status]++
          }
        })

        this.counts = counts
      })
    },
    refresh () {
      // A search typed in one event must not silently filter another.
      listState.search = ''

      this.fetchEvent()
      this.fetchCounts()
    }
  },
  watch: {
    // Switching events without leaving the dashboard must re-scope everything.
    eventId () {
      this.refresh()
    }
  },
  mounted () {
    this.refresh()
    window.eventSpeechOrganizerBus.$on('applicants-updated', this.fetchCounts)
  },
  beforeDestroy () {
    window.eventSpeechOrganizerBus.$off('applicants-updated', this.fetchCounts)
  }
}
</script>
