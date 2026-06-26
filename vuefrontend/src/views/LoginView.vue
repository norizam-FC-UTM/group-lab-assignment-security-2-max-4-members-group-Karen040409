<template>
  <div class="card">
    <h2>Login</h2>

    <form @submit.prevent="login">
      <div class="form-row">
        <label>Email</label>
        <input v-model="email" type="email" placeholder="email@example.com" />
      </div>
      <div class="form-row">
        <label>Password</label>
        <input v-model="password" type="password" placeholder="Password" />
      </div>
      <button class="btn" type="submit">Login</button>
    </form>

    <div v-if="message" class="notice" :class="ok ? 'good' : 'danger'">{{ message }}</div>
  </div>
</template>

<script>
import { apiPost, formatApiMessage } from '@/services/api'
import { setSession } from '@/utils/auth'

export default {
  name: 'LoginView',
  data() {
    return {
      email: '',
      password: '',
      message: '',
      ok: false
    }
  },
  methods: {
    async login() {
      const result = await apiPost('/login', {
        email: this.email,
        password: this.password
      })

      this.ok = result.ok

      if (result.ok && result.data.token) {
        const user = result.data.user || {
          id: result.data.user_id,
          name: result.data.name || this.email,
          email: this.email,
          role: result.data.role || 'user'
        }

        setSession(result.data.token, user)
        this.message = 'Login successful.'
        this.$router.push('/dashboard')
      } else {
        this.message = formatApiMessage(result, 'Login failed')
      }
    }
  }
}
</script>
