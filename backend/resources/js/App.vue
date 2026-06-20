<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center space-x-4">
                        <h1 class="text-xl font-bold text-gray-900">
                            内容审核标注平台
                        </h1>
                        <span class="text-sm text-gray-500">客户分组管理</span>
                    </div>
                    <nav class="flex space-x-4">
                        <router-link
                            to="/customer-groups"
                            class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors"
                            :class="{ 'bg-gray-100 text-gray-900': $route.name === 'CustomerGroups' }"
                        >
                            分组列表
                        </router-link>
                        <router-link
                            to="/customer-groups/create"
                            class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors"
                            :class="{ 'bg-gray-100 text-gray-900': $route.name === 'CustomerGroupCreate' }"
                        >
                            新建分组
                        </router-link>
                    </nav>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <router-view v-slot="{ Component }">
                <transition name="fade" mode="out-in">
                    <component :is="Component" />
                </transition>
            </router-view>
        </main>

        <footer class="bg-white border-t border-gray-200 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <p class="text-center text-sm text-gray-500">
                    &copy; {{ new Date().getFullYear() }} 内容审核标注平台 - 客户分组管理系统
                </p>
            </div>
        </footer>
    </div>
</template>

<script setup>
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
