<template>
  <div class="card">
    <h3>{{ person.name }} <span class="small">#{{ person.id }}</span></h3>
    <p><strong>Age:</strong> {{ person.age }}</p>
    <p><strong>Height:</strong> {{ person.height }} m</p>
    <p><strong>Weight:</strong> {{ person.weight }} kg</p>
    <p><strong>BMI:</strong> {{ person.bmi }}</p>
    <p><strong>Category:</strong> {{ person.category }}</p>
    <p v-if="person.notes"><strong>Notes:</strong> {{ person.notes }}</p>

    <div class="actions">
      <button class="btn danger" @click="$emit('delete', person)">Delete</button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BmiCard',
  props: {
    person: { type: Object, required: true }
  }
}
</script>
