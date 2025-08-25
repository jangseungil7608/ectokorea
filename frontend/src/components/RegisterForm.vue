<template>
  <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
    <div class="text-center mb-6">
      <h2 class="text-2xl font-bold text-gray-800">👤 회원가입</h2>
      <p class="text-gray-600 mt-2">EctoKorea 새 계정을 만드세요</p>
    </div>

    <form @submit.prevent="handleRegister" class="space-y-4">
      <!-- 이름 입력 -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          이름 *
        </label>
        <input
          v-model="form.name"
          type="text"
          required
          :disabled="authStore.loading"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
          placeholder="홍길동"
          minlength="2"
          maxlength="100"
        />
      </div>

      <!-- 이메일 입력 -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          이메일 주소 *
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
          비밀번호 *
        </label>
        <input
          v-model="form.password"
          type="password"
          required
          :disabled="authStore.loading"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
          placeholder="최소 6자리 이상"
          minlength="6"
        />
        <p class="text-xs text-gray-500 mt-1">최소 6자 이상의 비밀번호를 입력해주세요</p>
      </div>

      <!-- 비밀번호 확인 -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          비밀번호 확인 *
        </label>
        <input
          v-model="form.password_confirmation"
          type="password"
          required
          :disabled="authStore.loading"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
          placeholder="비밀번호를 다시 입력하세요"
          minlength="6"
        />
      </div>

      <!-- 비밀번호 일치 확인 -->
      <div v-if="form.password && form.password_confirmation" class="text-sm">
        <div v-if="passwordsMatch" class="text-green-600 flex items-center">
          <span class="mr-1">✓</span> 비밀번호가 일치합니다
        </div>
        <div v-else class="text-red-600 flex items-center">
          <span class="mr-1">✗</span> 비밀번호가 일치하지 않습니다
        </div>
      </div>

      <!-- 이용약관 동의 -->
      <div class="flex items-start space-x-2">
        <input
          v-model="form.agreeTerms"
          type="checkbox"
          id="agreeTerms"
          required
          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-0.5"
        />
        <label for="agreeTerms" class="text-sm text-gray-700">
          <span class="text-red-500">*</span>
          <span class="text-blue-600 underline cursor-pointer hover:text-blue-800">이용약관</span>
          및 
          <span class="text-blue-600 underline cursor-pointer hover:text-blue-800">개인정보처리방침</span>
          에 동의합니다
        </label>
      </div>

      <!-- 회원가입 버튼 -->
      <button
        type="submit"
        :disabled="authStore.loading || !passwordsMatch || !form.agreeTerms"
        class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        {{ authStore.loading ? '가입 처리 중...' : '회원가입' }}
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

    <!-- 로그인 링크 -->
    <div class="mt-6 text-center">
      <p class="text-sm text-gray-600">
        이미 계정이 있으신가요? 
        <button 
          @click="$emit('switch-to-login')" 
          class="text-blue-600 hover:text-blue-800 font-medium"
        >
          로그인
        </button>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useAuthStore } from '../stores/auth.js'

const authStore = useAuthStore()

// 폼 데이터
const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  agreeTerms: false
})

// 메시지 상태
const errorMessage = ref('')
const successMessage = ref('')

// 비밀번호 일치 확인
const passwordsMatch = computed(() => {
  return form.password && form.password_confirmation && 
         form.password === form.password_confirmation
})

// 메시지 초기화
const clearMessages = () => {
  errorMessage.value = ''
  successMessage.value = ''
}

// 폼 검증
const validateForm = () => {
  if (!form.name || form.name.length < 2) {
    errorMessage.value = '이름은 최소 2자 이상 입력해주세요.'
    return false
  }

  if (!form.email) {
    errorMessage.value = '이메일 주소를 입력해주세요.'
    return false
  }

  if (!form.password || form.password.length < 6) {
    errorMessage.value = '비밀번호는 최소 6자 이상 입력해주세요.'
    return false
  }

  if (!passwordsMatch.value) {
    errorMessage.value = '비밀번호가 일치하지 않습니다.'
    return false
  }

  if (!form.agreeTerms) {
    errorMessage.value = '이용약관에 동의해주세요.'
    return false
  }

  return true
}

// 회원가입 처리
const handleRegister = async () => {
  clearMessages()

  if (!validateForm()) {
    return
  }

  try {
    const result = await authStore.registerAndLogin({
      name: form.name,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation
    })

    if (result.success) {
      if (result.autoLogin) {
        // 자동 로그인 성공
        successMessage.value = `회원가입 완료! ${result.user.name}님, 환영합니다!`
        
        // 폼 초기화
        form.name = ''
        form.email = ''
        form.password = ''
        form.password_confirmation = ''
        form.agreeTerms = false
        
        // 부모 컴포넌트에 회원가입 및 로그인 성공 이벤트 전달
        emit('register-success', { ...result, autoLogin: true })
        
        // 2초 후 메인 화면으로 (자동 로그인으로 인해)
        setTimeout(() => {
          successMessage.value = ''
        }, 2000)
      } else {
        // 회원가입은 성공했지만 자동 로그인 실패
        successMessage.value = result.message + ' 로그인 페이지에서 로그인해주세요.'
        
        // 폼 초기화
        form.name = ''
        form.email = ''
        form.password = ''
        form.password_confirmation = ''
        form.agreeTerms = false
        
        // 부모 컴포넌트에 회원가입 성공 이벤트 전달
        emit('register-success', result)
        
        // 3초 후 로그인 페이지로 전환
        setTimeout(() => {
          successMessage.value = ''
          emit('switch-to-login')
        }, 3000)
      }
    } else {
      errorMessage.value = result.message || '회원가입에 실패했습니다.'
    }
  } catch (error) {
    console.error('회원가입 처리 중 오류:', error)
    errorMessage.value = '회원가입 처리 중 오류가 발생했습니다.'
  }
}

// 이벤트 정의
const emit = defineEmits(['register-success', 'switch-to-login'])
</script>

<style scoped>
/* 추가 스타일이 필요한 경우 여기에 작성 */
</style>