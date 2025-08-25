<template>
  <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
    <div class="text-center mb-6">
      <h2 class="text-2xl font-bold text-gray-800">🔐 {{ $t('auth.login') }}</h2>
      <p class="text-gray-600 mt-2">EctoKorea {{ $t('auth.loginRequired') }}</p>
    </div>

    <form @submit.prevent="handleLogin" class="space-y-4">
      <!-- 이메일 입력 -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ $t('auth.email') }} *
        </label>
        <input
          v-model="form.email"
          type="email"
          required
          :disabled="authStore.loading"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
          placeholder="example@email.com"
        />
      </div>

      <!-- 비밀번호 입력 -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ $t('auth.password') }} *
        </label>
        <input
          v-model="form.password"
          type="password"
          required
          :disabled="authStore.loading"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
          placeholder="비밀번호를 입력하세요"
        />
      </div>

      <!-- 자동 로그인 체크박스 -->
      <div class="flex items-center">
        <input
          v-model="form.remember"
          type="checkbox"
          id="remember"
          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
        />
        <label for="remember" class="ml-2 block text-sm text-gray-700">
          7일간 로그인 상태 유지
        </label>
      </div>

      <!-- 로그인 버튼 -->
      <button
        type="submit"
        :disabled="authStore.loading"
        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        {{ authStore.loading ? '로그인 중...' : '로그인' }}
      </button>

      <!-- 에러 메시지 -->
      <div v-if="errorMessage" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
        <p class="text-red-700 text-sm">{{ errorMessage }}</p>
      </div>

      <!-- 성공 메시지 -->
      <div v-if="successMessage" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-md">
        <p class="text-green-700 text-sm">{{ successMessage }}</p>
      </div>
    </form>

    <!-- 테스트용 계정 정보 -->
    <div class="mt-6 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
      <p class="text-yellow-800 text-sm font-medium">테스트 계정</p>
      <p class="text-yellow-700 text-xs mt-1">
        이메일: test@example.com<br>
        비밀번호: password123
      </p>
    </div>

    <!-- 회원가입 링크 -->
    <div class="mt-4 text-center">
      <p class="text-sm text-gray-600">
        계정이 없으신가요? 
        <button 
          @click="$emit('switch-to-register')" 
          class="text-blue-600 hover:text-blue-800 font-medium"
        >
          회원가입
        </button>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useAuthStore } from '../stores/auth.js'

const authStore = useAuthStore()

// 폼 데이터
const form = reactive({
  email: '',
  password: '',
  remember: true
})

// 메시지 상태
const errorMessage = ref('')
const successMessage = ref('')

// 메시지 초기화
const clearMessages = () => {
  errorMessage.value = ''
  successMessage.value = ''
}

// 로그인 처리
const handleLogin = async () => {
  clearMessages()

  if (!form.email || !form.password) {
    errorMessage.value = '이메일과 비밀번호를 모두 입력해주세요.'
    return
  }

  try {
    const result = await authStore.login({
      email: form.email,
      password: form.password
    })

    if (result.success) {
      successMessage.value = `환영합니다, ${result.user.name}님!`
      
      // 로그인 성공 후 폼 초기화
      form.email = ''
      form.password = ''
      
      // 부모 컴포넌트에 로그인 성공 이벤트 전달
      emit('login-success', result.user)
      
      setTimeout(() => {
        successMessage.value = ''
      }, 3000)
    } else {
      errorMessage.value = result.message || '로그인에 실패했습니다.'
    }
  } catch (error) {
    console.error('로그인 처리 중 오류:', error)
    errorMessage.value = '로그인 처리 중 오류가 발생했습니다.'
  }
}

// 이벤트 정의
const emit = defineEmits(['login-success', 'switch-to-register'])
</script>

<style scoped>
/* 추가 스타일이 필요한 경우 여기에 작성 */
</style>