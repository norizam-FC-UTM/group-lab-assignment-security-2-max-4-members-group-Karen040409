// JWT session helpers. Token is stored in localStorage (used consistently in this project).

export function getToken() {
  return localStorage.getItem('jwt')
}

export function getStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('user') || 'null')
  } catch (e) {
    return null
  }
}

export function isLoggedIn() {
  return !!getToken()
}

export function getRole() {
  const user = getStoredUser()
  return user?.role || null
}

export function setSession(token, user) {
  localStorage.setItem('jwt', token)
  localStorage.setItem('user', JSON.stringify(user))
}

export function logout() {
  localStorage.removeItem('jwt')
  localStorage.removeItem('user')
}
