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

          <div class="eso-agenda" v-else>
            <div
              class="eso-agenda__row"
              :class="{ 'eso-agenda__row--break': slot.talk_type === 'break' }"
              v-for="slot in sortedSlots"
              :key="slot.id"
            >
              <div class="eso-agenda__time">
                <strong>{{ slot.from || '—' }}</strong>
                <span>{{ slot.to || '' }}</span>
              </div>

              <div class="eso-agenda__rail">
                <span
                  class="eso-dot"
                  :class="'eso-dot--' + (slot.talk_type || 'none')"
                ></span>
              </div>

              <div class="eso-agenda__card">
                <div class="eso-agenda__main">
                  <div class="eso-agenda__title-row">
                    <span class="eso-agenda__title">{{
                      slot.name || 'Untitled slot'
                    }}</span>
                    <span
                      class="eso-badge"
                      :class="'eso-badge--' + slot.talk_type"
                      v-if="slot.talk_type"
                      >{{ typeLabel(slot.talk_type) }}</span
                    >
                    <span class="eso-agenda__duration">{{ durationLabel(slot) }}</span>
                  </div>

                  <ul
                    class="eso-chips eso-agenda__speakers"
                    v-if="slotSpeakerObjects(slot).length"
                  >
                    <li
                      class="eso-chip eso-chip--speaker"
                      v-for="(speaker, index) in slotSpeakerObjects(slot)"
                      :key="index"
                    >
                      <img
                        v-if="speaker.email"
                        class="eso-chip__avatar"
                        :src="gravatar(speaker.email, '32')"
                        :alt="speaker.name"
                      />
                      {{ speaker.name }}
                    </li>
                  </ul>
                  <span class="eso-agenda__empty" v-else-if="slot.talk_type !== 'break'"
                    >No speakers assigned</span
                  >
                </div>

                <div class="eso-agenda__actions">
                  <button
                    class="eso-icon-btn"
                    type="button"
                    title="Edit slot"
                    @click="edit(slot)"
                  >
                    <i class="el-icon-edit"></i>
                  </button>
                  <button
                    class="eso-icon-btn eso-icon-btn--danger"
                    type="button"
                    title="Delete slot"
                    @click="confirmRemove(slot)"
                  >
                    <i class="el-icon-delete"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div>
        <div class="eso-section">
          <p class="eso-section__title">Slots by talk type</p>
          <div class="eso-card">
            <ul class="eso-type-list">
              <li class="eso-type-list__item" v-for="type in talkTypes" :key="type.slug">
                <span class="eso-dot" :class="'eso-dot--' + type.slug"></span>
                <span class="eso-type-list__name">
                  {{ type.name
                  }}<em v-if="type.duration !== '-'"> · {{ type.duration }} min</em>
                </span>
                <span class="eso-badge eso-badge--count">{{
                  slotCounts[type.slug] || 0
                }}</span>
              </li>
            </ul>
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
        <div class="eso-form-section">
          <p class="eso-form-section__title">Speakers</p>
          <el-form-item>
            <el-select
              class="eso-full-width"
              v-model="form.speakers"
              multiple
              filterable
              allow-create
              default-first-option
              placeholder="Pick speakers — approved applicants come first"
              @change="onSpeakersChange"
            >
              <el-option
                v-for="item in speakerOptions"
                :key="item.id"
                :label="item.name"
                :value="item.id"
              >
                <div class="eso-speaker-option">
                  <img
                    class="eso-chip__avatar"
                    :src="gravatar(item.email, '32')"
                    :alt="item.name"
                  />
                  <span class="eso-speaker-option__name">{{ item.name }}</span>
                  <span class="eso-speaker-option__topic" v-if="item.topic">{{
                    item.topic
                  }}</span>
                </div>
              </el-option>
            </el-select>
            <div class="eso-field-hint">
              Picking a speaker fills in their proposed topic and talk type below —
              both stay editable.
            </div>
          </el-form-item>
        </div>

        <div class="eso-form-section">
          <p class="eso-form-section__title">Talk</p>
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
              @change="maybeFillEnd"
            >
              <el-option
                v-for="type in talkTypes"
                :key="type.slug"
                :label="
                  type.duration === '-' ? type.name : type.name + ' · ' + type.duration + ' min'
                "
                :value="type.slug"
              ></el-option>
            </el-select>
          </el-form-item>
        </div>

        <div class="eso-form-section">
          <p class="eso-form-section__title">Schedule</p>
          <el-row :gutter="16">
            <el-col :xs="24" :sm="12">
              <el-form-item label="Start time">
                <el-time-select
                  class="eso-full-width"
                  placeholder="Start time"
                  v-model="form.from"
                  :picker-options="{ start: '08:00', step: '00:05', end: '18:30' }"
                  @change="maybeFillEnd"
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
          <div class="eso-field-hint">
            The end time follows the talk type's duration once a start time is set —
            adjust it freely.
          </div>
        </div>
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
import { gravatarUrl } from './Common/applicantHelpers'

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
      // Last values this dialog filled in automatically. A field is only
      // ever auto-overwritten while it still holds its auto value — the
      // moment the user edits it, their text wins.
      autoFilled: {
        name: '',
        talk_type: '',
        to: ''
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
    },
    /**
     * Approved applicants first — they are the ones being scheduled — then
     * everyone else, each group alphabetical.
     */
    speakerOptions () {
      return this.speakers.slice().sort((a, b) => {
        const approvedA = a.status === 'approved' ? 0 : 1
        const approvedB = b.status === 'approved' ? 0 : 1

        if (approvedA !== approvedB) {
          return approvedA - approvedB
        }
        return (a.name || '').localeCompare(b.name || '')
      })
    },
    /**
     * Agenda order: by start time (zero-padded HH:MM sorts as text), untimed
     * slots last, ties by creation order.
     */
    sortedSlots () {
      return this.slots.slice().sort((a, b) => {
        const fromA = a.from || '99:99'
        const fromB = b.from || '99:99'

        if (fromA !== fromB) {
          return fromA < fromB ? -1 : 1
        }
        return Number(a.id) - Number(b.id)
      })
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
    gravatar: gravatarUrl,
    /**
     * Slot speakers as {name, email}. Ids resolve against the applicant
     * list; free-typed names pass through with no email (and no avatar).
     */
    slotSpeakerObjects (slot) {
      if (!slot.speakers) {
        return []
      }

      return slot.speakers.map(id => {
        const speaker = this.speakers.find(item => String(item.id) === String(id))

        return speaker
          ? { name: speaker.name, email: speaker.email }
          : { name: id, email: '' }
      })
    },
    handleSelect (item) {
      this.form.name = item.value
    },
    /**
     * Seed title and type from the first selected speaker's application.
     * Only fields that are empty or still hold a previous auto value are
     * touched, so manual edits always survive.
     */
    onSpeakersChange (selected) {
      const primary =
        selected && selected.length
          ? this.speakers.find(item => String(item.id) === String(selected[0]))
          : null

      if (!primary) {
        return
      }

      if (primary.topic && (!this.form.name || this.form.name === this.autoFilled.name)) {
        this.form.name = primary.topic
        this.autoFilled.name = primary.topic
      }

      // Keyword guess from the application's session type; when it matches
      // nothing (e.g. "Long Talk"), default to the first talk type so the
      // field never stays empty after picking a speaker.
      let slug = this.guessTypeSlug(primary.type)

      if (!slug && this.talkTypes.length) {
        slug = this.talkTypes[0].slug
      }

      if (slug && (!this.form.talk_type || this.form.talk_type === this.autoFilled.talk_type)) {
        this.form.talk_type = slug
        this.autoFilled.talk_type = slug
        this.maybeFillEnd()
      }
    },
    /**
     * Applicants describe their session in free text ("Long Talk",
     * "lightning session") — map it onto a slot talk type by keyword.
     */
    guessTypeSlug (text) {
      text = (text || '').toLowerCase()

      if (!text) {
        return ''
      }

      if (text.indexOf('lightning') > -1) {
        return 'lightning'
      }
      if (text.indexOf('panel') > -1) {
        return 'panel'
      }
      if (text.indexOf('semi') > -1) {
        return 'semi-keynote'
      }
      if (text.indexOf('keynote') > -1) {
        return 'keynote'
      }

      const match = this.talkTypes.find(
        type => text.indexOf(type.name.toLowerCase()) > -1
      )

      return match ? match.slug : ''
    },
    /**
     * End time follows start time + the talk type's duration, but never
     * overwrites an end time the user picked themselves.
     */
    maybeFillEnd () {
      const type = this.talkTypes.find(item => item.slug === this.form.talk_type)

      if (!type || type.duration === '-' || !this.form.from) {
        return
      }

      if (this.form.to && this.form.to !== this.autoFilled.to) {
        return
      }

      const end = this.addMinutes(this.form.from, parseInt(type.duration, 10))

      this.form.to = end
      this.autoFilled.to = end
    },
    addMinutes (time, minutes) {
      const parts = time.split(':')
      const total = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10) + minutes

      const hours = Math.floor(total / 60) % 24
      const mins = total % 60

      return (
        (hours < 10 ? '0' : '') + hours + ':' + (mins < 10 ? '0' : '') + mins
      )
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
      this.autoFilled = { name: '', talk_type: '', to: '' }
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
      // Everything on a stored slot counts as chosen by the user.
      this.autoFilled = { name: '', talk_type: '', to: '' }
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
