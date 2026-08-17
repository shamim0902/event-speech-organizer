<template>
  <div>
    <page-header
      :title="eventTitle"
      :subtitle="subtitle"
      :back-to="{ name: 'events' }"
      back-label="All events"
    />

    <filter-nav :counts="counts" />

    <router-view></router-view>
  </div>
</template>

<script>
import FilterNav from './FilterNav.vue'
import PageHeader from './Common/PageHeader.vue'

export default {
  name: 'Dashboard',
  components: {
    FilterNav,
    PageHeader
  },
  data () {
    return {
      event: null,
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
