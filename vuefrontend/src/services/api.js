// API helper for Vue CLI. Sends JWT Bearer token on protected requests.

import { getToken, logout } from '@/utils/auth'

const API_BASE = process.env.VUE_APP_API_BASE_URL || 'http://localhost:8080/api'

const PUBLIC_PATHS = ['/login', '/register']

async function parseResponse(response, path = '') {
  let data = null
  try {
    data = await response.json()
  } catch (e) {
    data = { error: 'Invalid JSON response' }
  }

  if (response.status === 401 && !PUBLIC_PATHS.includes(path)) {
    logout()
    if (window.location.pathname !== '/login') {
      window.location.href = '/login'
    }
  }

  return {
    ok: response.ok,
    status: response.status,
    data
  }
}

function authHeaders(includeJson = true) {
  const headers = {}
  if (includeJson) headers['Content-Type'] = 'application/json'

  const token = getToken()
  if (token) headers['Authorization'] = 'Bearer ' + token
  return headers
}

export async function apiGet(path) {
  const response = await fetch(API_BASE + path, {
    headers: authHeaders(false)
  })
  return parseResponse(response, path)
}

export async function apiPost(path, body) {
  const response = await fetch(API_BASE + path, {
    method: 'POST',
    headers: authHeaders(true),
    body: JSON.stringify(body)
  })
  return parseResponse(response, path)
}

export async function apiPut(path, body) {
  const response = await fetch(API_BASE + path, {
    method: 'PUT',
    headers: authHeaders(true),
    body: JSON.stringify(body)
  })
  return parseResponse(response, path)
}

export async function apiDelete(path) {
  const response = await fetch(API_BASE + path, {
    method: 'DELETE',
    headers: authHeaders(false)
  })
  return parseResponse(response, path)
}

export function formatApiMessage(result, fallback = 'Request failed') {
  const data = result?.data || {}

  if (data.message) {
    return data.message
  }

  if (data.error) {
    return data.error
  }

  if (data.errors && typeof data.errors === 'object') {
    return Object.values(data.errors).join(' ')
  }

  if (!result?.ok) {
    const statusMessages = {
      400: 'Invalid request',
      401: 'Unauthorized',
      403: 'Access denied',
      404: 'Not found',
      500: 'Unable to process request'
    }
    return statusMessages[result.status] || fallback
  }

  return ''
}

export { API_BASE }
