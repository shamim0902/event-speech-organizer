<template>
  <div>
    <page-header
      :title="eventTitle"
      subtitle="Review speaker applications and build the schedule."
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
      counts: {
        total: 0,
        approved: 0,
        waiting: 0,
        rejected: 0
      }
    }
  },
  computed: {
    eventTitle () {
      return 'Event ' + this.$route.params.id
    }
  },
  methods: {
    fetchCounts () {
      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_data'
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
    }
  },
  mounted () {
    this.fetchCounts()
    window.eventSpeechOrganizerBus.$on('applicants-updated', this.fetchCounts)
  },
  beforeDestroy () {
    window.eventSpeechOrganizerBus.$off('applicants-updated', this.fetchCounts)
  }
}
</script>
