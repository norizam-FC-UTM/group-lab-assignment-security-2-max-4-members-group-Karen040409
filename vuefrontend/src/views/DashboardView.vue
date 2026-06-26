<template>
  <div>
    <div class="card">
      <h2>Dashboard</h2>
      <p>Welcome, {{ user?.name || 'User' }}.</p>
      <p><UserRoleBadge :role="user?.role" /></p>
    </div>

    <div class="grid">
      <router-link class="card link-card" to="/my-bmi">
        <h3>My BMI Records</h3>
        <p>View and manage your BMI records.</p>
      </router-link>
      <router-link class="card link-card" to="/add-bmi">
        <h3>Add BMI Record</h3>
        <p>Create a new BMI entry.</p>
      </router-link>
      <router-link
        v-if="user?.role === 'staff' || user?.role === 'admin'"
        class="card link-card"
        to="/staff/bmi-records"
      >
        <h3>Staff Monitor</h3>
        <p>View all BMI records.</p>
      </router-link>
      <router-link
        v-if="user?.role === 'admin'"
        class="card link-card"
        to="/admin/users"
      >
        <h3>Admin Users</h3>
        <p>Manage user roles.</p>
      </router-link>
    </div>
  </div>
</template>

<script>
import UserRoleBadge from '@/components/UserRoleBadge.vue'
import { getStoredUser } from '@/utils/auth'

export default {
  name: 'DashboardView',
  components: { UserRoleBadge },
  computed: {
    user() {
      return getStoredUser()
    }
  }
}
</script>
