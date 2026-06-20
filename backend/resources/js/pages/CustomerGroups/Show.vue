<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <router-link to="/customer-groups" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </router-link>
                <h2 class="text-2xl font-bold text-gray-900">客户分组详情</h2>
            </div>
            <div class="flex space-x-3">
                <router-link :to="`/customer-groups/${id}/edit`" class="btn btn-primary">
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
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">分组名称</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ item.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">分组代码</dt>
                            <dd class="mt-1">
                                <code class="text-sm text-gray-600 bg-gray-100 px-2 py-1 rounded">{{ item.code }}</code>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">排序</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ item.sort_order }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">状态</dt>
                            <dd class="mt-1">
                                <span class="badge" :class="item.is_active ? 'badge-success' : 'badge-danger'">
                                    {{ item.is_active ? '启用' : '禁用' }}
                                </span>
                            </dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">描述</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ item.description || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">创建时间</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(item.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">更新时间</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(item.updated_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">配置参数</h3>
                    </div>
                    <div v-if="item.settings && Object.keys(item.settings).length > 0" class="bg-gray-50 rounded-lg p-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">参数名</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">参数值</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(value, key) in item.settings" :key="key">
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ key }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ value }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-500">暂无配置参数</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">关联用户</h3>
                    <div v-if="item.models && item.models.length > 0" class="space-y-2">
                        <div
                            v-for="user in item.models"
                            :key="user.id"
                            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white text-sm font-medium">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ user.name }}</p>
                                    <p class="text-xs text-gray-500">{{ user.email }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">暂无关联用户</p>
                </div>

                <div class="card p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">快捷操作</h3>
                    <div class="space-y-3">
                        <button @click="toggleActive" class="w-full btn btn-secondary">
                            {{ item.is_active ? '禁用分组' : '启用分组' }}
                        </button>
                        <button @click="confirmDelete" class="w-full btn btn-danger">
                            删除分组
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">确认删除</h3>
                <p class="text-gray-500 mb-6">确定要删除客户分组 <strong>{{ item?.name }}</strong> 吗？此操作不可恢复。</p>
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

        <div v-if="message" class="fixed bottom-4 right-4 z-50" :class="message.type === 'success' ? 'bg-emerald-500' : 'bg-red-500'" class="text-white px-6 py-3 rounded-lg shadow-lg">
            {{ message.text }}
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCustomerGroupsStore } from '../../stores/customerGroups';

const route = useRoute();
const router = useRouter();
const store = useCustomerGroupsStore();

const loading = ref(true);
const showDeleteModal = ref(false);
const message = ref(null);

const id = computed(() => route.params.id);
const item = computed(() => store.currentItem);

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('zh-CN');
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
            router.push('/customer-groups');
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

onMounted(async () => {
    try {
        await store.fetchDetail(id.value);
    } catch (error) {
        router.push('/customer-groups');
    } finally {
        loading.value = false;
    }
});
</script>
