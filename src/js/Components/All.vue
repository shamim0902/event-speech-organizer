<template>
  <speaker
    @fetch="fetch"
    :loading="loading"
    :eventSpeechOrganizer="eventSpeechOrganizer"
  ></speaker>
</template>

<script>
import applicantListMixin from './applicantListMixin'

export default {
  name: 'All',
  mixins: [applicantListMixin],
  computed: {
    statusFilter () {
      return null
    }
  }
}
</script>
