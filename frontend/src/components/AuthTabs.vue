<template>
  <div class="max-w-md mx-auto mt-12">
    <!-- 로그인 페이지 제목 -->
    <div class="text-center mb-8">
      <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
        EctoKorea
      </h2>
      <p class="text-gray-600 dark:text-gray-400">
        일본 아마존 상품 수익성 계산 및 관리 시스템
      </p>
    </div>
    
    <!-- 탭 헤더 -->
    <div class="flex bg-gray-100 rounded-lg p-1 mb-6">
      <button
        @click="currentTab = 'login'"
        :class="[
          'flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors',
          currentTab === 'login'
            ? 'bg-white text-blue-600 shadow-sm'
            : 'text-gray-600 hover:text-gray-800'
        ]"
      >
        🔐 로그인
      </button>
      <button
        @click="currentTab = 'register'"
        :class="[
          'flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors',
          currentTab === 'register'
            ? 'bg-white text-green-600 shadow-sm'
            : 'text-gray-600 hover:text-gray-800'
        ]"
      >
        👤 회원가입
      </button>
    </div>


    <!-- 탭 콘텐츠 -->
    <div class="tab-content">
      <!-- 로그인 탭 -->
      <div v-show="currentTab === 'login'">
        <LoginForm @login-success="handleLoginSuccess" />
      </div>

      <!-- 회원가입 탭 -->
      <div v-show="currentTab === 'register'">
        <RegisterForm @register-success="handleRegisterSuccess" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import LoginForm from './LoginForm.vue'
import RegisterForm from './RegisterForm.vue'

const currentTab = ref('login') // 기본은 로그인 탭

// 로그인 성공 시 처리
const handleLoginSuccess = (user) => {
  emit('auth-success', { type: 'login', user })
}

// 회원가입 성공 시 처리
const handleRegisterSuccess = (result) => {
  if (result.autoLogin) {
    // 자동 로그인 성공 시
    emit('auth-success', { type: 'register', user: result.user, result })
  } else {
    // 일반 회원가입 성공 시
    emit('auth-success', { type: 'register', result })
    
    // 회원가입 성공 후 로그인 탭으로 전환 (3초 후)
    setTimeout(() => {
      currentTab.value = 'login'
    }, 3000)
  }
}


// 이벤트 정의
const emit = defineEmits(['auth-success'])
</script>

<style scoped>
.tab-content {
  min-height: 400px;
}
</style>