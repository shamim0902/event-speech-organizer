<template>
  <el-dialog
    :title="dialogTitle"
    :visible="visible"
    width="760px"
    append-to-body
    @update:visible="$emit('update:visible', $event)"
  >
    <el-form :model="form" label-width="130px" label-position="top">
      <div class="eso-form-section">
        <p class="eso-form-section__title">Applicant</p>
        <el-row :gutter="16">
          <el-col :xs="24" :sm="12">
            <el-form-item label="Name">
              <el-input v-model="form.name" placeholder="Full name"></el-input>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="Application date">
              <el-input
                v-model="form.date"
                placeholder="e.g. 2024-05-14 10:30"
              ></el-input>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="Email">
              <el-input v-model="form.email" placeholder="name@example.com"></el-input>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="Phone">
              <el-input v-model="form.phone" placeholder="Phone number"></el-input>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="WordPress.org username">
              <el-input
                v-model="form.username"
                placeholder="Username or profile URL"
              ></el-input>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="Social handles">
              <el-input
                v-model="form.social"
                placeholder="Profile URL"
              ></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="Bio">
              <el-input
                type="textarea"
                :rows="3"
                v-model="form.comment"
                placeholder="Short speaker bio"
              ></el-input>
            </el-form-item>
          </el-col>
        </el-row>
      </div>

      <div class="eso-form-section">
        <p class="eso-form-section__title">Talk</p>
        <el-row :gutter="16">
          <el-col :xs="24" :sm="12">
            <el-form-item label="Topic title">
              <el-input v-model="form.topic" placeholder="Talk title"></el-input>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="Talk type">
              <el-input
                v-model="form.type"
                placeholder="e.g. Keynote, Lightning"
              ></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="Description">
              <el-input
                type="textarea"
                :rows="3"
                v-model="form.description"
                placeholder="What is the talk about?"
              ></el-input>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="Co-speakers">
              <el-input
                v-model="form.cospeakers"
                placeholder="Names of co-speakers"
              ></el-input>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item label="Audience">
              <el-input
                v-model="form.audience"
                placeholder="Who is this for?"
              ></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="Experience">
              <el-input
                type="textarea"
                :rows="2"
                v-model="form.experience"
                placeholder="Previous speaking experience"
              ></el-input>
            </el-form-item>
          </el-col>
        </el-row>
      </div>
    </el-form>

    <span slot="footer" class="dialog-footer">
      <el-button size="small" @click="$emit('update:visible', false)">Cancel</el-button>
      <el-button size="small" type="primary" :loading="saving" @click="save"
        >Save applicant</el-button
      >
    </span>
  </el-dialog>
</template>

<script>
/**
 * The add/edit applicant form, shared by the applicant table (add) and the
 * single applicant page (edit). Pass an applicant with an id to edit; an
 * empty object (or nothing) to add.
 */
const emptyApplicant = () => ({
  date: '',
  name: '',
  email: '',
  phone: '',
  social: '',
  username: '',
  comment: '',
  topic: '',
  description: '',
  type: '',
  cospeakers: '',
  audience: '',
  experience: '',
  status: 'waiting'
})

export default {
  name: 'ApplicantFormDialog',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    eventId: {
      type: [String, Number],
      required: true
    },
    applicant: {
      type: Object,
      default: () => ({})
    }
  },
  data () {
    return {
      form: emptyApplicant(),
      saving: false
    }
  },
  computed: {
    dialogTitle () {
      return this.form.id ? 'Edit applicant' : 'Add applicant'
    }
  },
  watch: {
    // Re-seed the form each time the dialog opens, so stale edits from a
    // previously cancelled session never leak through.
    visible (isOpen) {
      if (isOpen) {
        this.form = Object.assign(emptyApplicant(), this.applicant)
      }
    }
  },
  methods: {
    save () {
      const isUpdate = !!this.form.id

      this.saving = true

      this.$post({
        action: 'event_speech_organizer_admin_ajax',
        route: isUpdate ? 'edit_applicant' : 'add_applicant',
        event_id: this.eventId,
        data: this.form
      })
        .then(() => {
          this.$message.success(isUpdate ? 'Applicant updated.' : 'Applicant added.')
          this.$emit('update:visible', false)
          this.$emit('saved')
        })
        .always(() => {
          this.saving = false
        })
    }
  }
}
</script>
