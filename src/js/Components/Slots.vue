<template>
  <div>
    <div class="eso-columns">
      <div>
        <div class="eso-toolbar">
          <span class="eso-toolbar__count">{{ countLabel }}</span>
          <span class="eso-toolbar__spacer"></span>
          <el-button type="primary" icon="el-icon-plus" size="small" @click="create"
            >Add slot</el-button
          >
        </div>

        <div v-loading="loading">
          <empty-state
            v-if="!slots.length"
            icon="el-icon-time"
            title="No slots yet"
            hint="Add time slots to start building the event schedule."
          >
            <el-button type="primary" size="small" icon="el-icon-plus" @click="create"
              >Add slot</el-button
            >
          </empty-state>

          <div class="eso-timeline" v-else>
            <div class="eso-timeline__row" v-for="slot in slots" :key="slot.id">
              <div class="eso-timeline__time">
                <strong>{{ slot.from || '—' }}</strong>
                {{ slot.to || '—' }}
              </div>

              <div class="eso-card eso-timeline__card">
                <div class="eso-card__header">
                  <div class="eso-card__header-main">
                    <div class="eso-card__title">{{ slot.name || 'Untitled slot' }}</div>
                    <div class="eso-card__meta">{{ durationLabel(slot) }}</div>
                  </div>
                  <span
                    class="eso-badge"
                    :class="'eso-badge--' + slot.talk_type"
                    v-if="slot.talk_type"
                    >{{ typeLabel(slot.talk_type) }}</span
                  >
                </div>

                <div class="eso-card__body" v-if="slotSpeakers(slot).length">
                  <ul class="eso-chips">
                    <li
                      class="eso-chip"
                      v-for="(name, index) in slotSpeakers(slot)"
                      :key="index"
                    >
                      {{ name }}
                    </li>
                  </ul>
                </div>

                <div class="eso-card__footer">
                  <span class="eso-toolbar__count">
                    {{ slotSpeakers(slot).length }} speaker{{
                      slotSpeakers(slot).length === 1 ? '' : 's'
                    }}
                  </span>
                  <span class="eso-link-actions">
                    <button class="eso-link-btn" type="button" @click="edit(slot)">
                      <i class="el-icon-edit"></i> Edit
                    </button>
                    <button
                      class="eso-link-btn eso-link-btn--danger"
                      type="button"
                      @click="confirmRemove(slot)"
                    >
                      <i class="el-icon-delete"></i> Delete
                    </button>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div>
        <div class="eso-section">
          <p class="eso-section__title">Slots by talk type</p>
          <div class="eso-stats">
            <div class="eso-stat" v-for="type in talkTypes" :key="type.slug">
              <div class="eso-stat__value">{{ slotCounts[type.slug] || 0 }}</div>
              <div class="eso-stat__label">
                {{ type.name
                }}<template v-if="type.duration !== '-'"> · {{ type.duration }} min</template>
              </div>
            </div>
          </div>
        </div>

        <div class="eso-section">
          <p class="eso-section__title">Totals</p>
          <div class="eso-stats">
            <div class="eso-stat eso-stat--total">
              <div class="eso-stat__value">{{ slots.length }}</div>
              <div class="eso-stat__label">Total slots</div>
            </div>
            <div class="eso-stat eso-stat--total">
              <div class="eso-stat__value">{{ totalSpeakers }}</div>
              <div class="eso-stat__label">Assigned speakers</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <el-dialog
      :title="dialogTitle"
      :visible.sync="dialogVisible"
      width="640px"
      append-to-body
    >
      <el-form ref="form" :model="form" label-position="top">
        <el-form-item label="Talk title">
          <el-autocomplete
            class="eso-full-width"
            v-model="form.name"
            :fetch-suggestions="querySearchAsync"
            placeholder="Search existing topics or type a new one"
            @select="handleSelect"
          ></el-autocomplete>
        </el-form-item>

        <el-form-item label="Talk type">
          <el-select
            class="eso-full-width"
            v-model="form.talk_type"
            placeholder="Select a talk type"
          >
            <el-option
              v-for="type in talkTypes"
              :key="type.slug"
              :label="type.name"
              :value="type.slug"
            ></el-option>
          </el-select>
        </el-form-item>

        <el-row :gutter="16">
          <el-col :xs="24" :sm="12">
            <el-form-item label="Start time">
              <el-time-select
                class="eso-full-width"
                placeholder="Start time"
                v-model="form.from"
                :picker-options="{ start: '08:00', step: '00:05', end: '18:30' }"
              >
              </el-time-select>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="End time">
              <el-time-select
                class="eso-full-width"
                placeholder="End time"
                v-model="form.to"
                :picker-options="{
                  start: '08:00',
                  step: '00:05',
                  end: '18:30',
                  minTime: form.from
                }"
              >
              </el-time-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="Speakers">
          <el-select
            class="eso-full-width"
            v-model="form.speakers"
            multiple
            filterable
            allow-create
            default-first-option
            placeholder="Assign speakers to this slot"
          >
            <el-option
              v-for="item in speakers"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            >
            </el-option>
          </el-select>
        </el-form-item>
      </el-form>

      <span slot="footer" class="dialog-footer">
        <el-button size="small" @click="dialogVisible = false">Cancel</el-button>
        <el-button size="small" type="primary" @click="addNew">Save slot</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import EmptyState from './Common/EmptyState.vue'

export default {
  name: 'Slots',
  components: {
    EmptyState
  },
  data () {
    return {
      dialogVisible: false,
      loading: false,
      searchTimer: null,
      form_mock: {
        name: '',
        talk_type: '',
        from: '',
        to: '',
        speakers: []
      },
      form: {
        name: '',
        talk_type: '',
        from: '',
        to: '',
        speakers: []
      },
      talkTypes: [
        {
          name: 'Panel',
          description: 'Panel',
          slug: 'panel',
          duration: '45'
        },
        {
          name: 'Keynote',
          description: 'Keynote',
          slug: 'keynote',
          duration: '30'
        },
        {
          name: 'Semi Keynote',
          description: 'semi Keynote',
          slug: 'semi-keynote',
          duration: '20'
        },
        {
          name: 'Lightning Talk',
          description: 'Lightning Talk',
          slug: 'lightning',
          duration: '10'
        },
        {
          name: 'Break',
          description: 'Break Time',
          slug: 'break',
          duration: '-'
        }
      ],
      slots: [],
      speakers: []
    }
  },
  computed: {
    eventId () {
      return this.$route.params.id
    },
    dialogTitle () {
      return this.form.id ? 'Edit slot' : 'Add slot'
    },
    slotCounts () {
      return this.slots.reduce((counts, slot) => {
        counts[slot.talk_type] = (counts[slot.talk_type] || 0) + 1
        return counts
      }, {})
    },
    totalSpeakers () {
      return this.slots.reduce((count, slot) => {
        return count + (slot.speakers ? slot.speakers.length : 0)
      }, 0)
    },
    countLabel () {
      return this.slots.length + (this.slots.length === 1 ? ' slot' : ' slots')
    }
  },
  methods: {
    typeLabel (slug) {
      const type = this.talkTypes.find(item => item.slug === slug)
      return type ? type.name : slug
    },
    durationLabel (slot) {
      const type = this.talkTypes.find(item => item.slug === slot.talk_type)
      if (!type || type.duration === '-') {
        return slot.from && slot.to ? slot.from + ' – ' + slot.to : ''
      }
      return type.duration + ' min'
    },
    slotSpeakers (slot) {
      if (!slot.speakers) {
        return []
      }
      return slot.speakers.map(id => this.getName(id))
    },
    handleSelect (item) {
      this.form.name = item.value
    },
    querySearchAsync (queryString, cb) {
      if (this.searchTimer) {
        clearTimeout(this.searchTimer)
      }
      this.searchTimer = setTimeout(() => {
        this.searchTitle(queryString, cb)
      }, 400)
    },
    searchTitle (queryString, cb) {
      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'search_speakers',
        event_id: this.eventId,
        search_by: queryString
      }).then(response => {
        cb(response || [])
      })
    },
    create () {
      this.form = Object.assign({}, this.form_mock, { speakers: [] })
      this.dialogVisible = true
    },
    addNew () {
      const isUpdate = !!this.form.id

      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'save_slots',
        event_id: this.eventId,
        data: this.form
      }).then(() => {
        this.dialogVisible = false
        this.$message.success(isUpdate ? 'Slot updated.' : 'Slot added.')
        this.fetch()
      })
    },
    getName (id) {
      const speaker = this.speakers.find(speaker => speaker.id === id)
      return speaker ? speaker.name : id
    },
    confirmRemove (slot) {
      this.$confirm(
        'Delete "' + (slot.name || 'this slot') + '"? This cannot be undone.',
        'Delete slot',
        {
          confirmButtonText: 'Delete',
          cancelButtonText: 'Cancel',
          type: 'warning'
        }
      )
        .then(() => this.remove(slot))
        .catch(() => {})
    },
    remove (slot) {
      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: 'delete_slot',
        event_id: this.eventId,
        id: slot.id
      }).then(() => {
        this.$message.success('Slot deleted.')
        this.fetch()
      })
    },
    edit (slot) {
      // `speakers` comes back from json_decode() and is null for empty slots,
      // which el-select rejects in multiple mode.
      this.form = Object.assign({}, slot, { speakers: slot.speakers || [] })
      this.dialogVisible = true
    },
    fetchSpeakers () {
      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_data',
        event_id: this.eventId
      }).then(response => {
        this.speakers = (response && response.data) || []
      })
    },
    fetch () {
      this.loading = true
      this.$get({
        action: 'event_speech_organizer_admin_ajax',
        route: 'get_slots',
        event_id: this.eventId
      })
        .then(response => {
          this.slots = (response && response.data) || []
        })
        .always(() => {
          this.loading = false
        })
    }
  },
  watch: {
    eventId () {
      this.fetch()
      this.fetchSpeakers()
    }
  },
  mounted () {
    this.fetch()
    this.fetchSpeakers()
  }
}
</script>
