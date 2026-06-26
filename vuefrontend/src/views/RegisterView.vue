<template>
  <div class="card">
    <h2>Register</h2>

    <form @submit.prevent="register">
      <div class="grid">
        <div class="form-row">
          <label>Name</label>
          <input v-model="form.name" placeholder="Your name" />
        </div>
        <div class="form-row">
          <label>Email</label>
          <input v-model="form.email" type="email" placeholder="email@example.com" />
        </div>
      </div>
      <div class="form-row">
        <label>Password</label>
        <input v-model="form.password" type="password" placeholder="Password" />
      </div>
      <button class="btn" type="submit">Register</button>
    </form>

    <div v-if="message" class="notice" :class="ok ? 'good' : 'danger'">{{ message }}</div>
  </div>
</template>

<script>
import { apiPost, formatApiMessage } from '@/services/api'

export default {
  name: 'RegisterView',
  data() {
    return {
      form: {
        name: '',
        email: '',
        password: ''
      },
      message: '',
      ok: false
    }
  },
  methods: {
    async register() {
      const result = await apiPost('/register', this.form)
      this.ok = result.ok
      this.message = formatApiMessage(result)

      if (result.ok) {
        setTimeout(() => this.$router.push('/login'), 1500)
      }
    }
  }
}
</script>
