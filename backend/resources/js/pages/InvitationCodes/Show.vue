<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <router-link to="/invitation-codes" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </router-link>
                <h2 class="text-2xl font-bold text-gray-900">邀请码详情</h2>
            </div>
            <div class="flex space-x-3">
                <router-link :to="`/invitation-codes/${id}/edit`" class="btn btn-primary">
                    编辑
                </router-link>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>

        <div v-else-if="item" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="card p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">基本信息</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">邀请码</dt>
                            <dd class="mt-1">
                                <code class="text-lg font-mono text-indigo-700 bg-indigo-50 px-3 py-2 rounded">{{ item.code }}</code>
                                <button @click="copyCode" class="ml-3 text-indigo-500 hover:text-indigo-700 text-sm">
                                    复制
                                </button>
                            </dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">关联分组</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span v-if="item.customer_group">{{ item.customer_group.name }} ({{ item.customer_group.code }})</span>
                                <span v-else class="text-gray-400">-</span>
                            </dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">描述</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ item.description || '-' }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">使用次数</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ item.uses_display }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">状态</dt>
                            <dd class="mt-1">
                                <span class="badge" :class="item.is_valid ? 'badge-success' : 'badge-danger'">
                                    {{ item.is_valid ? '有效' : '无效' }}
                                </span>
                            </dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">过期时间</dt>
                            <dd class="mt-1 text-sm" :class="item.is_expired ? 'text-red-500' : 'text-gray-900'">
                                {{ item.expires_at ? formatDate(item.expires_at) : '永不过期' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">创建时间</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(item.created_at) }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">更新时间</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(item.updated_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="item.usages && item.usages.length > 0" class="card p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">使用记录</h3>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">用户</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">使用时间</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="usage in item.usages" :key="usage.id">
                                <td class="px-4 py-2 text-sm text-gray-900">
                                    <span v-if="usage.user">{{ usage.user.name }} ({{ usage.user.email }})</span>
                                    <span v-else class="text-gray-400">未知用户</span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ formatDate(usage.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">快捷操作</h3>
                    <div class="space-y-3">
                        <button @click="toggleActive" class="w-full btn btn-secondary">
                            {{ item.is_active ? '禁用邀请码' : '启用邀请码' }}
                        </button>
                        <button @click="confirmDelete" class="w-full btn btn-danger">
                            删除邀请码
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">确认删除</h3>
                <p class="text-gray-500 mb-6">确定要删除邀请码 <strong>{{ item?.code }}</strong> 吗？此操作不可恢复。</p>
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
import { useRoute, useRouter } from 'vue-router';
import { useInvitationCodesStore } from '../../stores/invitationCodes';

const route = useRoute();
const router = useRouter();
const store = useInvitationCodesStore();

const loading = ref(true);
const showDeleteModal = ref(false);
const message = ref(null);

const id = computed(() => route.params.id);
const item = computed(() => store.currentItem);

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('zh-CN');
};

const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(item.value.code);
        showMessage('已复制到剪贴板', 'success');
    } catch {
        showMessage('复制失败', 'error');
    }
};

const toggleActive = async () => {
    try {
        await store.toggleActive(id.value);
        showMessage('状态更新成功', 'success');
    } catch (error) {
        showMessage('状态更新失败', 'error');
    }
};

const confirmDelete = () => {
    showDeleteModal.value = true;
};

const deleteItem = async () => {
    try {
        await store.delete(id.value);
        showDeleteModal.value = false;
        showMessage('删除成功', 'success');
        setTimeout(() => {
            router.push('/invitation-codes');
        }, 1000);
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

const loadData = async () => {
    loading.value = true;
    try {
        await store.fetchDetail(id.value);
    } catch (error) {
        router.push('/invitation-codes');
    } finally {
        loading.value = false;
    }
};

watch(() => route.params.id, (newId) => {
    if (newId) {
        loadData();
    }
});

onMounted(() => {
    loadData();
});
</script>
