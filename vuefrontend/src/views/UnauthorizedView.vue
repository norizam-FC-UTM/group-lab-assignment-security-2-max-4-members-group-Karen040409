<template>
  <div class="card">
    <h2>Access Denied</h2>
    <p>You do not have permission to view this page.</p>
    <router-link class="btn" to="/dashboard">Back to Dashboard</router-link>
  </div>
</template>

<script>
export default { name: 'UnauthorizedView' }
</script>
