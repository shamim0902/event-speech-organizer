<template>
  <div>
    <page-header
      title="Events"
      subtitle="Choose an event to review its speaker applications and schedule."
    />

    <empty-state
      v-if="!events.length"
      icon="el-icon-date"
      title="No events yet"
      hint="Once events are available they will show up here."
    />

    <ul class="eso-card-grid" v-else>
      <li v-for="event in events" :key="event.id">
        <router-link :to="{ name: 'applicants', params: { id: event.id } }">
          <div class="eso-card eso-card--link">
            <div class="eso-card__header">
              <div class="eso-card__header-main">
                <div class="eso-card__title">{{ event.title }}</div>
                <div class="eso-card__meta">Event #{{ event.id }}</div>
              </div>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </router-link>
      </li>
    </ul>

    <router-view></router-view>
  </div>
</template>

<script>
import PageHeader from './Common/PageHeader.vue'
import EmptyState from './Common/EmptyState.vue'

export default {
  name: 'Events',
  components: {
    PageHeader,
    EmptyState
  },
  data () {
    return {
      events: [
        {
          id: 1,
          title: 'Event 1'
        },
        {
          id: 2,
          title: 'Event 2'
        }
      ]
    }
  }
}
</script>
