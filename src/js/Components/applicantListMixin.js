import Speaker from './Speaker.vue'

/**
 * Shared behaviour for the four applicant list routes (All / Selected /
 * Waiting / Rejected). Each route component only declares which statuses it
 * wants via `statusFilter`; everything else lives here.
 */
export default {
  components: {
    Speaker
  },
  data () {
    return {
      eventSpeechOrganizer: [],
      loading: false
    }
  },
  methods: {
    fetch () {
      const request = {
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_data'
      }

      if (this.statusFilter && this.statusFilter.length) {
        request.options = { status: this.statusFilter }
      }

      this.loading = true

      this.$get(request)
        .then(response => {
          this.eventSpeechOrganizer = (response && response.data) || []
        })
        .always(() => {
          this.loading = false
        })
    }
  },
  mounted () {
    this.fetch()
  }
}
