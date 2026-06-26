<template>
  <div>
    <BmiForm
      title="Add BMI Record"
      button-label="Save BMI"
      @save-bmi="save"
    />

    <div v-if="message" class="notice" :class="ok ? 'good' : 'danger'">
      {{ message }}
    </div>
  </div>
</template>

<script>
import BmiForm from '@/components/BmiForm.vue'
import { apiPost, formatApiMessage } from '@/services/api'

export default {
  name: 'AddBmiView',
  components: { BmiForm },
  data() {
    return {
      message: '',
      ok: false
    }
  },
  methods: {
    async save(payload) {
      const result = await apiPost('/persons', payload)
      this.ok = result.ok
      this.message = formatApiMessage(result)

      if (result.ok) {
        setTimeout(() => this.$router.push('/my-bmi'), 1500)
      }
    }
  }
}
</script>
