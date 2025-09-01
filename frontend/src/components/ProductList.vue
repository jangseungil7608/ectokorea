<template>
  <div class="p-4">
    <h2 class="text-xl font-bold mb-4">{{ $t('products.productList') }}</h2>

    <ul v-if="products.data && products.data.length">
      <li
        v-for="item in products.data"
        :key="item.id"
        class="flex justify-between items-center mb-2"
      >
        <span>{{ item.name }} - ￥{{ item.price }}</span>
        <div>
          <button @click="editProduct(item)" class="text-blue-500 mr-2">{{ $t('common.edit') }}</button>
          <button @click="confirmDelete(item)" class="text-red-500 hover:underline">{{ $t('common.delete') }}</button>
        </div>
      </li>
    </ul>

    <!-- ページネーション -->
    <div v-if="products.last_page" class="mt-4 flex space-x-2">
    <button
        v-for="page in products.last_page"
        :key="page"
        :class="['px-3 py-1 rounded', page === currentPage ? 'bg-blue-600 text-white' : 'bg-gray-200']"
        @click="fetchProducts(page)"
    >
        {{ page }}
    </button>
    </div>

    <!-- 編集フォーム -->
    <div v-if="editingProduct" class="mt-6 border-t pt-4">
      <h3 class="text-lg font-semibold mb-2">{{ $t('common.edit') }}: {{ editingProduct.name }}</h3>
      <input v-model="editingProduct.name" type="text" class="border px-2 py-1 mr-2" />
      <input v-model.number="editingProduct.price" type="number" class="border px-2 py-1 mr-2" />
      <button @click="updateProduct" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">{{ $t('common.save') }}</button>
      <button @click="cancelEdit" class="ml-2 text-gray-600">{{ $t('common.cancel') }}</button>
    </div>

    <!-- 削除確認モーダル -->
    <div v-if="deleteTarget" class="mt-6 p-4 bg-red-50 border border-red-300 rounded">
      <p class="text-red-700 font-semibold mb-2">本当に削除しますか？</p>
      <p>商品名: <strong>{{ deleteTarget.name }}</strong></p>
      <p>価格: <strong>￥{{ deleteTarget.price }}</strong></p>
      <div class="mt-4">
        <button @click="deleteProduct" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">削除する</button>
        <button @click="cancelDelete" class="ml-3 text-gray-600">キャンセル</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/utils/api'

const products = ref([])
const currentPage = ref(1)
const editingProduct = ref(null)
const deleteTarget = ref(null)

const fetchProducts = async (page = 1) => {
  try {
    const res = await api.get(`/products?page=${page}`)
    products.value = res.data
    currentPage.value = page
  } catch (error) {
    console.error(error)
  }
}

const editProduct = (item) => {
  editingProduct.value = { ...item }
}

const updateProduct = async () => {
  await api.put(`/products/${editingProduct.value.id}`, editingProduct.value)
  await fetchProducts(currentPage.value)
  editingProduct.value = null
}

const confirmDelete = (item) => {
  deleteTarget.value = item
}

const deleteProduct = async () => {
  try {
    await api.delete(`/products/${deleteTarget.value.id}`)
    deleteTarget.value = null
    await fetchProducts()
  } catch (error) {
    console.error('削除失敗:', error)
  }
}

const cancelEdit = () => {
  editingProduct.value = null
}

const cancelDelete = () => {
  deleteTarget.value = null
}

// 外部から呼べるように expose
defineExpose({ fetchProducts })


onMounted(() => fetchProducts())
</script>
