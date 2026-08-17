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
  name: 'Waiting',
  mixins: [applicantListMixin],
  computed: {
    statusFilter () {
      return ['waiting']
    }
  }
}
</script>
