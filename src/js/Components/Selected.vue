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
  name: 'Selected',
  mixins: [applicantListMixin],
  computed: {
    statusFilter () {
      return ['approved']
    }
  }
}
</script>
