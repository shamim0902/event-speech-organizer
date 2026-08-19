<template>
  <!-- Status filters for the applicant lists on the left; the switch between
       an event's applicants and its slots lives on the right. -->
  <div class="eso-nav" v-if="isApplicantsSection || isSlots">
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

    <span class="eso-nav__spacer"></span>

    <template v-if="isApplicantsSection">
      <el-input
        class="eso-nav__search"
        v-model="listState.search"
        size="small"
        clearable
        prefix-icon="el-icon-search"
        placeholder="Search applicants"
      ></el-input>

      <el-select
        class="eso-nav__sort"
        v-model="listState.sortBy"
        size="small"
      >
        <el-option label="Newest first" value="newest"></el-option>
        <el-option label="Name (A–Z)" value="name"></el-option>
        <el-option label="Status" value="status"></el-option>
      </el-select>
    </template>

    <div class="eso-tabs">
      <router-link
        v-if="isSlots"
        class="eso-tabs__item"
        :to="{ name: 'applicants', params: { id: eventId } }"
      >
        <i class="el-icon-user"></i>Applicants
      </router-link>
      <router-link
        v-else
        class="eso-tabs__item"
        :to="{ name: 'slots', params: { id: eventId } }"
      >
        <i class="el-icon-time"></i>Slots
      </router-link>
    </div>
  </div>
</template>

<script>
import listState from './Common/listState'

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
      listState,
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
    },
    isSlots () {
      return this.$route.name === 'slots'
    }
  },
  methods: {
    countFor (filter) {
      return this.counts[filter.key] || 0
    }
  }
}
</script>
