<template>
  <form class="card" @submit.prevent="submitForm">
    <h2>{{ title }}</h2>

    <div class="grid">
      <div class="form-row">
        <label>Name</label>
        <input v-model="form.name" type="text" placeholder="Your name" />
      </div>

      <div class="form-row">
        <label>Age</label>
        <input v-model.number="form.age" type="number" placeholder="21" />
      </div>

      <div class="form-row">
        <label>Height (meters)</label>
        <input v-model="form.height" type="text" placeholder="1.70" />
      </div>

      <div class="form-row">
        <label>Weight (kg)</label>
        <input v-model="form.weight" type="text" placeholder="65" />
      </div>
    </div>

    <div class="form-row">
      <label>Notes</label>
      <textarea v-model="form.notes" placeholder="Optional notes"></textarea>
    </div>

    <button class="btn" type="submit">{{ buttonLabel }}</button>
  </form>
</template>

<script>
export default {
  name: 'BmiForm',
  emits: ['save-bmi'],
  props: {
    title: { type: String, default: 'BMI Form' },
    buttonLabel: { type: String, default: 'Submit' },
    initialValue: {
      type: Object,
      default: () => ({
        name: '',
        age: 21,
        height: '1.70',
        weight: '65',
        notes: ''
      })
    }
  },
  data() {
    return {
      form: {
        name: this.initialValue.name || '',
        age: this.initialValue.age !== undefined && this.initialValue.age !== ''
          ? Number(this.initialValue.age)
          : 21,
        height: this.initialValue.height !== undefined && this.initialValue.height !== ''
          ? String(this.initialValue.height)
          : '1.70',
        weight: this.initialValue.weight !== undefined && this.initialValue.weight !== ''
          ? String(this.initialValue.weight)
          : '65',
        notes: this.initialValue.notes || ''
      }
    }
  },
  methods: {
    submitForm() {
      this.$emit('save-bmi', {
        name: this.form.name,
        age: this.form.age,
        height: this.form.height,
        weight: this.form.weight,
        notes: this.form.notes
      })
    }
  }
}
</script>
