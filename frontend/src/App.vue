<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from './stores/auth.js'
import { useThemeStore } from './stores/theme.js'
import { useLanguageStore } from './stores/language.js'
import ProductForm from './components/ProductForm.vue'
import ProductList from './components/ProductList.vue'
import ProfitCalculator from './components/ProfitCalculator.vue'
import ProductCollector from './components/ProductCollector.vue'
import CollectedProductList from './components/CollectedProductList.vue'
import CollectionJobMonitor from './components/CollectionJobMonitor.vue'
import AuthTabs from './components/AuthTabs.vue'
import UserMenu from './components/UserMenu.vue'
import ThemeToggle from './components/ThemeToggle.vue'
import LanguageToggle from './components/LanguageToggle.vue'

const authStore = useAuthStore()
const themeStore = useThemeStore()
const languageStore = useLanguageStore()
const productList = ref([])
const currentTab = ref('calculator')

// 앱 초기화
onMounted(async () => {
  await authStore.initializeAuth()
  themeStore.initTheme() // 테마 초기화
  languageStore.initLanguage() // 언어 초기화
  
  // 인증 관련 이벤트 리스너
  window.addEventListener('auth:logout', () => {
    console.log('인증 만료로 인한 자동 로그아웃')
    currentTab.value = 'calculator'
  })
})

const refreshList = () => {
  productList.value.fetchProducts()
}

// 인증 성공 시 처리 (로그인/회원가입)
const handleAuthSuccess = ({ type, user, result }) => {
  if (type === 'login') {
    console.log('로그인 성공:', user)
  } else if (type === 'register') {
    console.log('회원가입 성공:', result)
  }
}

// 로그아웃 성공 시 처리  
const handleLogoutSuccess = () => {
  console.log('로그아웃 성공')
  currentTab.value = 'calculator'
}

// 상품 수집 관련 이벤트 핸들러
const collectedProductList = ref()

const handleProductCollected = (product) => {
  console.log('상품 수집 완료:', product)
  // 수집 상품 목록 새로고침
  if (collectedProductList.value) {
    collectedProductList.value.refreshList()
  }
}

const handleBulkJobCreated = (job) => {
  console.log('대량 수집 작업 생성:', job)
  // 필요시 작업 목록 페이지로 이동하거나 알림 표시
}

const handleViewDetails = (product) => {
  console.log('상품 상세 보기:', product)
  // 상품 상세 모달 또는 페이지 표시 로직
}

const handleViewJobDetails = (job) => {
  console.log('작업 상세 보기:', job)
  // 작업 상세 모달 또는 페이지 표시 로직
}
</script>

<template>
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900 transition-colors">
    <!-- 헤더 -->
    <header class="bg-white dark:bg-gray-800 shadow-sm border-b dark:border-gray-700">
      <div class="max-w-7xl mx-auto px-4 py-4">
        <div class="flex justify-between items-center">
          <!-- 로그인하지 않은 경우 -->
          <div v-if="!authStore.isLoggedIn" class="flex items-center justify-between w-full">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              EctoKorea
            </h1>
            <div class="flex items-center space-x-4">
              <!-- 언어 토글 -->
              <LanguageToggle />
              <!-- 테마 토글 -->
              <ThemeToggle />
            </div>
          </div>
          
          <!-- 로그인한 경우 -->
          <template v-else>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ $t('header.title') }}
            </h1>
            
            <!-- 헤더 우측 메뉴 -->
            <div class="flex items-center space-x-4">
              <!-- 언어 토글 -->
              <LanguageToggle />
              
              <!-- 테마 토글 -->
              <ThemeToggle />
              
              <!-- 사용자 메뉴 -->
              <UserMenu 
                @logout-success="handleLogoutSuccess"
              />
            </div>
          </template>
        </div>
      </div>
    </header>

    <!-- 탭 네비게이션 (로그인 시에만 표시) -->
    <nav v-if="authStore.isLoggedIn" class="bg-white dark:bg-gray-800 shadow-sm border-b dark:border-gray-700">
      <div class="max-w-7xl mx-auto px-4">
        <div class="flex space-x-8">
          <button
            @click="currentTab = 'calculator'"
            :class="[
              'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
              currentTab === 'calculator'
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
            ]"
          >
            {{ $t('nav.calculator') }}
          </button>
          <button
            @click="currentTab = 'collector'"
            :class="[
              'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
              currentTab === 'collector'
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
            ]"
          >
            🔍 상품 수집
          </button>
          <button
            @click="currentTab = 'collected'"
            :class="[
              'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
              currentTab === 'collected'
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
            ]"
          >
            📦 수집 상품
          </button>
          <button
            @click="currentTab = 'jobs'"
            :class="[
              'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
              currentTab === 'jobs'
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
            ]"
          >
            🔄 수집 작업
          </button>
          <button
            @click="currentTab = 'products'"
            :class="[
              'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
              currentTab === 'products'
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
            ]"
          >
            {{ $t('nav.products') }}
          </button>
        </div>
      </div>
    </nav>

    <!-- 메인 콘텐츠 -->
    <main class="max-w-7xl mx-auto py-6 px-4">
      <!-- 로그인이 필요한 경우 -->
      <div v-if="!authStore.isLoggedIn">
        <AuthTabs @auth-success="handleAuthSuccess" />
      </div>

      <!-- 로그인 후 메인 콘텐츠 -->
      <div v-else>
        <!-- 이익 계산기 탭 -->
        <div v-show="currentTab === 'calculator'">
          <ProfitCalculator />
        </div>

        <!-- 상품 수집 탭 -->
        <div v-show="currentTab === 'collector'">
          <ProductCollector 
            @collected="handleProductCollected"
            @bulk-job-created="handleBulkJobCreated"
          />
        </div>

        <!-- 수집 상품 목록 탭 -->
        <div v-show="currentTab === 'collected'">
          <CollectedProductList 
            ref="collectedProductList"
            @view-details="handleViewDetails"
          />
        </div>

        <!-- 수집 작업 모니터 탭 -->
        <div v-show="currentTab === 'jobs'">
          <CollectionJobMonitor 
            @view-job-details="handleViewJobDetails"
          />
        </div>

        <!-- 상품 관리 탭 -->
        <div v-show="currentTab === 'products'" class="space-y-6">
          <ProductForm @product-registered="refreshList" />
          <ProductList ref="productList" />
        </div>
      </div>
    </main>

    <!-- 푸터 -->
    <footer class="bg-white dark:bg-gray-800 border-t dark:border-gray-700 mt-12">
      <div class="max-w-7xl mx-auto py-6 px-4 text-center text-gray-500 dark:text-gray-400 text-sm">
        <p>{{ $t('header.subtitle') }}</p>
      </div>
    </footer>
  </div>
</template>

<style>
body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
</style>
