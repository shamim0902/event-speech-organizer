<template>
  <div class="eso-nav">
    <div class="eso-tabs">
      <router-link
        class="eso-tabs__item"
        :class="{ 'is-active': isApplicantsSection }"
        :to="{ name: 'applicants', params: { id: eventId } }"
      >
        <i class="el-icon-user"></i>Applicants
      </router-link>
      <router-link
        class="eso-tabs__item"
        :class="{ 'is-active': $route.name === 'slots' }"
        :to="{ name: 'slots', params: { id: eventId } }"
      >
        <i class="el-icon-time"></i>Slots
      </router-link>
    </div>

    <div class="eso-tabs eso-tabs--sub" v-if="isApplicantsSection">
      <router-link
        v-for="filter in filters"
        :key="filter.route"
        class="eso-tabs__item"
        :class="{ 'is-active': $route.name === filter.route }"
        :to="{ name: filter.route, params: { id: eventId } }"
      >
        {{ filter.label }}
        <span class="eso-badge eso-badge--count">{{ countFor(filter) }}</span>
      </router-link>
    </div>
  </div>
</template>

<script>
const APPLICANT_ROUTES = ['applicants', 'selected', 'waiting', 'rejected']

export default {
  name: 'FilterNav',
  props: {
    counts: {
      type: Object,
      default: () => ({})
    }
  },
  data () {
    return {
      filters: [
        { label: 'All', route: 'applicants', key: 'total' },
        { label: 'Selected', route: 'selected', key: 'approved' },
        { label: 'Waiting', route: 'waiting', key: 'waiting' },
        { label: 'Rejected', route: 'rejected', key: 'rejected' }
      ]
    }
  },
  computed: {
    eventId () {
      return this.$route.params.id
    },
    isApplicantsSection () {
      return APPLICANT_ROUTES.indexOf(this.$route.name) > -1
    }
  },
  methods: {
    countFor (filter) {
      return this.counts[filter.key] || 0
    }
  }
}
</script>
