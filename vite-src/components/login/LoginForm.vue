<template>
  <div class="login-card">
    <img
      :src="logoUrl"
      alt="Caraga Regional Hospital Logo"
      class="logo"
    />

    <h2 class="login-title">Staff Login</h2>
    <p class="login-subtitle">Sign in to access the system</p>

    <form @submit.prevent="onSubmit">
      <div class="field">
        <label for="username">Username</label>
        <input
          id="username"
          v-model="username"
          type="text"
          placeholder="Enter your username"
          required
          autocomplete="username"
        />
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="password-input">
          <input
            id="password"
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Enter your password"
            required
            autocomplete="current-password"
          />
          <button
            type="button"
            class="password-toggle"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            :aria-pressed="showPassword"
            aria-controls="password"
            @click="showPassword = !showPassword"
          >
            <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="m3 3 18 18" />
              <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
              <path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3.1 3.9M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.5 10.5 0 0 0 3-.4" />
            </svg>
          </button>
        </div>
      </div>

      <p v-if="error" class="error">{{ error }}</p>

      <button type="submit" class="btn-signin" :disabled="loading">
        {{ loading ? 'Signing in…' : 'Sign In' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import logoUrl from '../../assets/caraga-regional-hospital20220211.webp'

const emit = defineEmits(['submit'])

const username = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

async function onSubmit() {
  error.value = ''
  loading.value = true

  try {
    await new Promise((resolve) => setTimeout(resolve, 600))
    emit('submit', { username: username.value, password: password.value })
  } catch {
    error.value = 'Invalid username or password.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-card {
  background: #ffffff;
  border-radius: 18px;
  padding: 2.5rem 2.25rem;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.22);
  text-align: center;
  box-sizing: border-box;
}

.logo {
  width: 95px;
  height: 95px;
  object-fit: contain;
  margin: 0 auto 0.85rem;
  display: block;
}

.login-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0;
}

.login-subtitle {
  color: #666;
  font-size: 0.88rem;
  margin: 0.35rem 0 1.5rem;
}

.field {
  text-align: left;
  margin-bottom: 1.1rem;
}

.field label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 0.4rem;
  color: #333;
}

.field input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.95rem;
  outline: none;
  background: #fff;
  color: #1e293b;
  box-sizing: border-box;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.password-input {
  position: relative;
}

.field .password-input input {
  padding-right: 3rem;
}

.password-toggle {
  position: absolute;
  top: 50%;
  right: 0.45rem;
  display: grid;
  width: 2rem;
  height: 2rem;
  place-items: center;
  transform: translateY(-50%);
  border: 0;
  border-radius: 6px;
  padding: 0;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  transition: color 0.16s ease, background 0.16s ease;
}

.password-toggle:hover {
  color: #1b5e20;
  background: #f0fdf4;
}

.password-toggle svg {
  width: 19px;
  height: 19px;
}

.password-toggle:focus-visible {
  outline: 2px solid #1b5e20;
  outline-offset: 2px;
}

.field input:focus {
  border-color: #1b5e20;
  box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.12);
}

.btn-signin {
  width: 100%;
  margin-top: 0.5rem;
  padding: 0.85rem;
  background: #1b5e20;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
}

.btn-signin:hover:not(:disabled) {
  background: #144a18;
}

.btn-signin:active:not(:disabled) {
  transform: scale(0.98);
}

.btn-signin:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.error {
  color: #c0392b;
  font-size: 0.85rem;
  margin-bottom: 0.5rem;
}
</style>
