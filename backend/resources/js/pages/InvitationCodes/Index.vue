<template>
    <div class="space-y-6">
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-900">邀请码列表</h2>
            <div class="flex space-x-3">
                <router-link to="/invitation-codes/redeem" class="btn btn-secondary">
                    使用邀请码
                </router-link>
                <router-link to="/invitation-codes/batch-generate" class="btn btn-secondary">
                    批量生成
                </router-link>
                <router-link to="/invitation-codes/create" class="btn btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    创建邀请码
                </router-link>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="搜索邀请码或描述..."
                        class="input"
                        @input="debouncedSearch"
                    />
                </div>
                <div class="flex items-center space-x-4">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input
                            v-model="activeOnly"
                            type="checkbox"
                            class="rounded border-gray-300 text-primary focus:ring-primary"
                            @change="store.setActiveOnly(activeOnly)"
                        />
                        <span class="text-sm text-gray-700">仅显示有效</span>
                    </label>
                </div>
            </div>
        </div>

        <div v-if="store.loading" class="flex justify-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>

        <div v-else class="card overflow-hidden">
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">邀请码</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">关联分组</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">描述</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">使用次数</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">过期时间</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="item in store.items" :key="item.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <code class="text-sm font-mono text-indigo-700 bg-indigo-50 px-2 py-1 rounded">{{ item.code }}</code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span v-if="item.customer_group" class="text-sm text-gray-900">{{ item.customer_group.name }}</span>
                                <span v-else class="text-sm text-gray-400">-</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500 max-w-xs truncate">{{ item.description || '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-600">{{ item.uses_display }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span v-if="item.expires_at" class="text-sm" :class="item.is_expired ? 'text-red-500' : 'text-gray-600'">
                                    {{ formatDate(item.expires_at) }}
                                </span>
                                <span v-else class="text-sm text-gray-400">永不过期</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button
                                    @click="toggleActive(item)"
                                    class="badge cursor-pointer transition-opacity hover:opacity-80"
                                    :class="getStatusBadge(item)"
                                >
                                    {{ getStatusText(item) }}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <router-link
                                    :to="`/invitation-codes/${item.id}`"
                                    class="text-blue-600 hover:text-blue-900"
                                >
                                    查看
                                </router-link>
                                <router-link
                                    :to="`/invitation-codes/${item.id}/edit`"
                                    class="text-indigo-600 hover:text-indigo-900"
                                >
                                    编辑
                                </router-link>
                                <button
                                    @click="confirmDelete(item)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    删除
                                </button>
                            </td>
                        </tr>
                        <tr v-if="store.items.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                                <p class="mt-2">暂无邀请码数据</p>
                                <router-link to="/invitation-codes/create" class="btn btn-primary mt-4">
                                    创建第一个邀请码
                                </router-link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="store.pagination.last_page > 1" class="bg-white px-4 py-3 flex flex-col sm:flex-row items-center justify-between border-t border-gray-200 sm:px-6 gap-4">
                <div class="flex-1 w-full flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-center sm:text-left">
                        <p class="text-sm text-gray-700">
                            共 <span class="font-medium">{{ store.pagination.total }}</span> 条记录，
                            第 <span class="font-medium">{{ store.pagination.current_page }}</span> / <span class="font-medium">{{ store.pagination.last_page }}</span> 页
                        </p>
                    </div>
                    <div class="w-full sm:w-auto">
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px w-full sm:w-auto justify-center">
                            <button
                                @click="store.setPage(store.pagination.current_page - 1)"
                                :disabled="store.pagination.current_page === 1"
                                class="relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed flex-1 sm:flex-none justify-center"
                            >
                                上一页
                            </button>
                            <button
                                v-for="page in visiblePages"
                                :key="page"
                                @click="store.setPage(page)"
                                class="relative inline-flex items-center px-4 py-2 border text-sm font-medium hidden sm:inline-flex"
                                :class="page === store.pagination.current_page ? 'z-10 bg-primary border-primary text-white' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                            >
                                {{ page }}
                            </button>
                            <button
                                @click="store.setPage(store.pagination.current_page + 1)"
                                :disabled="store.pagination.current_page === store.pagination.last_page"
                                class="relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed flex-1 sm:flex-none justify-center"
                            >
                                下一页
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">确认删除</h3>
                <p class="text-gray-500 mb-6">确定要删除邀请码 <strong>{{ itemToDelete?.code }}</strong> 吗？此操作不可恢复。</p>
                <div class="flex justify-end space-x-3">
                    <button @click="showDeleteModal = false" class="btn btn-secondary">
                        取消
                    </button>
                    <button @click="deleteItem" class="btn btn-danger">
                        确认删除
                    </button>
                </div>
            </div>
        </div>

        <div v-if="message" class="fixed bottom-4 right-4 z-50 text-white px-6 py-3 rounded-lg shadow-lg" :class="message.type === 'success' ? 'bg-emerald-500' : 'bg-red-500'">
            {{ message.text }}
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useInvitationCodesStore } from '../../stores/invitationCodes';

const store = useInvitationCodesStore();
const search = ref(store.search);
const activeOnly = ref(store.activeOnly);
const showDeleteModal = ref(false);
const itemToDelete = ref(null);
const message = ref(null);

let searchTimeout = null;

watch(() => store.search, (val) => {
    if (val !== search.value) {
        search.value = val;
    }
});

watch(() => store.activeOnly, (val) => {
    if (val !== activeOnly.value) {
        activeOnly.value = val;
    }
});

const visiblePages = computed(() => {
    const pages = [];
    const current = store.pagination.current_page;
    const last = store.pagination.last_page;
    const range = 2;
    for (let i = Math.max(1, current - range); i <= Math.min(last, current + range); i++) {
        pages.push(i);
    }
    return pages;
});

const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        store.setSearch(search.value);
    }, 300);
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('zh-CN');
};

const getStatusBadge = (item) => {
    if (!item.is_valid) {
        if (item.is_expired) return 'badge-danger';
        if (item.is_used_up) return 'badge-danger';
        if (!item.is_active) return 'bg-gray-200 text-gray-600';
    }
    return 'badge-success';
};

const getStatusText = (item) => {
    if (!item.is_valid) {
        if (item.is_expired) return '已过期';
        if (item.is_used_up) return '已用完';
        if (!item.is_active) return '已禁用';
    }
    return '有效';
};

const toggleActive = async (item) => {
    try {
        await store.toggleActive(item.id);
        showMessage('状态更新成功', 'success');
    } catch (error) {
        showMessage('状态更新失败', 'error');
    }
};

const confirmDelete = (item) => {
    itemToDelete.value = item;
    showDeleteModal.value = true;
};

const deleteItem = async () => {
    try {
        await store.delete(itemToDelete.value.id);
        showDeleteModal.value = false;
        itemToDelete.value = null;
        showMessage('删除成功', 'success');
    } catch (error) {
        showMessage('删除失败', 'error');
    }
};

const showMessage = (text, type) => {
    message.value = { text, type };
    setTimeout(() => {
        message.value = null;
    }, 3000);
};

onMounted(() => {
    store.fetchList();
});
</script>
