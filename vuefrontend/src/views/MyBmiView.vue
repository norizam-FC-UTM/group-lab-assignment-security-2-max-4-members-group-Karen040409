<template>
  <div>
    <div class="card">
      <h2>My BMI Records</h2>
      <button class="btn" @click="loadMyRecords">Refresh</button>
    </div>

    <div v-if="message" class="notice" :class="ok ? 'good' : 'danger'">{{ message }}</div>

    <BmiList :persons="persons" @delete="deleteRecord" />
  </div>
</template>

<script>
import BmiList from '@/components/BmiList.vue'
import { apiGet, apiDelete, formatApiMessage } from '@/services/api'

export default {
  name: 'MyBmiView',
  components: { BmiList },
  data() {
    return {
      persons: [],
      message: '',
      ok: false
    }
  },
  mounted() {
    this.loadMyRecords()
  },
  methods: {
    async loadMyRecords() {
      const result = await apiGet('/persons')
      this.ok = result.ok
      this.message = formatApiMessage(result)
      this.persons = Array.isArray(result.data) ? result.data : (result.data.persons || [])
    },
    async deleteRecord(person) {
      if (!confirm('Delete record #' + person.id + '?')) return
      const result = await apiDelete('/persons/' + person.id)
      this.ok = result.ok
      this.message = formatApiMessage(result)
      this.loadMyRecords()
    }
  }
}
</script>
