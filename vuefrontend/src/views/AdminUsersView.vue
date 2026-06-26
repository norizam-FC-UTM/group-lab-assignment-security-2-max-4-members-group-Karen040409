<template>
  <div>
    <div class="card">
      <h2>Admin Users</h2>
      <button class="btn" @click="loadUsers">Refresh</button>
    </div>

    <div v-if="message" class="notice" :class="ok ? 'good' : 'danger'">{{ message }}</div>

    <div class="card table-wrap" v-if="users.length">
      <table>
        <thead>
          <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Update Role</th></tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id">
            <td>{{ u.id }}</td>
            <td>{{ u.name }}</td>
            <td>{{ u.email }}</td>
            <td><UserRoleBadge :role="u.role" /></td>
            <td>
              <select v-model="u.newRole">
                <option value="user">user</option>
                <option value="staff">staff</option>
                <option value="admin">admin</option>
              </select>
              <button class="btn secondary" @click="changeRole(u)">Save</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { apiGet, apiPut, formatApiMessage } from '@/services/api'
import UserRoleBadge from '@/components/UserRoleBadge.vue'

export default {
  name: 'AdminUsersView',
  components: { UserRoleBadge },
  data() {
    return {
      users: [],
      message: '',
      ok: false
    }
  },
  mounted() {
    this.loadUsers()
  },
  methods: {
    async loadUsers() {
      const result = await apiGet('/admin/users')
      this.ok = result.ok
      this.message = formatApiMessage(result)
      const list = Array.isArray(result.data) ? result.data : (result.data.users || [])
      this.users = list.map(u => ({ ...u, newRole: u.role }))
    },
    async changeRole(user) {
      const result = await apiPut('/admin/users/' + user.id + '/role', { role: user.newRole })
      this.ok = result.ok
      this.message = formatApiMessage(result)
      this.loadUsers()
    }
  }
}
</script>
